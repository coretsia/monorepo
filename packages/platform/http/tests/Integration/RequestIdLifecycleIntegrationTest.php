<?php

declare(strict_types=1);

/*
 * Coretsia Framework (Monorepo)
 *
 * Project: Coretsia Framework (Monorepo)
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdLifecycleIntegrationTest extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAB';
    private const string UPSTREAM_ID = 'Trusted-42._A';
    private const string SUBSTITUTED_ID = 'substituted-identity';
    private const string INVALID_ID = 'invalid identity';
    private const string CORRELATION_HEADER = 'X-Correlation-Id';
    private const array CORRELATIONS = ['01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW'];
    private const array UOW_IDS = ['01ARZ3NDEKTSV4RRFFQ69G5FAX', '01ARZ3NDEKTSV4RRFFQ69G5FAY'];
    private const array APPLICATION_IDS = ['application-owned', 'application-owned-other'];

    /** @param list<string> $upstream */
    #[DataProvider('lifecycleCases')]
    public function testRequestIdentityLifecycleAndSequentialReuse(
        string $scenario,
        bool $enabled,
        bool $acceptFromUpstream,
        bool $correlationEnabled,
        array $upstream,
        ?string $firstId,
    ): void {
        $defaults = require __DIR__ . '/../../config/http.php';
        $config = $defaults['request_id'];
        self::assertTrue(
            $config === [
                'enabled' => false,
                'header_name' => 'X-Request-Id',
                'accept_from_upstream' => false,
                'max_length' => 128,
            ],
            'request-id-defaults-mismatch',
        );
        if ($enabled) {
            $config = [
                'enabled' => true,
                'header_name' => 'X-Edge-Request-Id',
                'accept_from_upstream' => $acceptFromUpstream,
                'max_length' => 26,
            ];
        }
        $headerName = $config['header_name'];
        $context = new ContextStore();
        $invocation = 0;
        $events = [];
        $afterObservations = [];
        $resetObservations = [];
        $selected = null;
        $hookIdentity = null;
        $outcome = '';
        $activeScenario = '';
        $response = null;
        $failure = new \RuntimeException('synthetic-primary-failure');
        $problemFailure = BadJsonException::invalid();
        $logger = new NoopLogger();
        $meter = new NoopMeter();
        $tracer = new NoopTracer();

        $generator = $this->createMock(IdGeneratorInterface::class);
        $generationCount = ($enabled ? 1 : 0) + (int) ($firstId === self::FIRST_ID || $scenario === 'generator-failure');
        $generator->expects(self::exactly($generationCount))->method('generate')->willReturnCallback(
            static function () use ($context, &$invocation, $scenario, $failure, &$events): string {
                self::assertTrue(
                    $context->get(ContextKeys::UOW_ID) === self::UOW_IDS[$invocation],
                    'request-id-generation-before-uow-context',
                );
                self::assertTrue(
                    $context->get(ContextKeys::CORRELATION_ID) === self::CORRELATIONS[$invocation],
                    'request-id-generation-before-correlation-context',
                );
                self::assertTrue(
                    $context->get(ContextKeys::UOW_TYPE) === 'http',
                    'request-id-generation-before-uow-type',
                );

                $events[] = 'generate';
                if ($invocation === 0 && $scenario === 'generator-failure') {
                    throw $failure;
                }

                return $invocation === 0 ? self::FIRST_ID : self::SECOND_ID;
            },
        );
        $requestIds = new RequestIdPolicy(
            $context,
            $generator,
            $config['enabled'],
            $headerName,
            $config['accept_from_upstream'],
            $config['max_length'],
        );

        $after = $this->createMock(AfterUowHookInterface::class);
        $after->expects(self::exactly(2))->method('afterUow')->willReturnCallback(
            static function (array $contextPayload, array $resultPayload) use (
                $context,
                &$invocation,
                &$hookIdentity,
                &$outcome,
                &$activeScenario,
                &$events,
                &$afterObservations,
            ): void {
                $events[] = 'after';
                // Record only predicates, not request identifiers or context snapshots.
                $afterObservations[] = [
                    self::matchesIdentity($context, $hookIdentity),
                    $context->get(ContextKeys::CORRELATION_ID) === self::CORRELATIONS[$invocation],
                    $context->get(ContextKeys::UOW_ID) === self::UOW_IDS[$invocation],
                    $context->get(ContextKeys::UOW_TYPE) === 'http',
                    self::matchesHttpContext($context, $activeScenario !== 'generator-failure'),
                    self::canonicalPayloads($contextPayload, $resultPayload, $invocation, $outcome),
                ];
            },
        );
        $resetProbe = $this->createMock(ResetInterface::class);
        $resetProbe->expects(self::exactly(2))->method('reset')->willReturnCallback(
            static function () use ($context, &$hookIdentity, &$invocation, &$events, &$resetObservations): void {
                // This probe precedes the canonical ContextStore reset by tag priority.
                $resetObservations[] = self::matchesIdentity($context, $hookIdentity)
                    && $context->get(ContextKeys::UOW_ID) === self::UOW_IDS[$invocation];
                $events[] = 'reset';
            },
        );
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): mixed => match ($id) {
                ContextStore::class, ContextAccessorInterface::class => $context,
                'App\\RequestIdAfterHook' => $after,
                'App\\RequestIdResetProbe' => $resetProbe,
                default => throw new \LogicException('unexpected-test-service'),
            },
        );
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => \in_array($id, [
                ContextStore::class,
                ContextAccessorInterface::class,
                'App\\RequestIdAfterHook',
                'App\\RequestIdResetProbe',
            ], true),
        );
        $hookTags = new TagRegistry();
        $hookTags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'App\\RequestIdAfterHook');
        $tags = new TagRegistry();
        $reset = new ResetOrchestrator($container, $tags);
        $tags->add(ReservedTags::KERNEL_STATEFUL, ContextStore::class);
        $tags->add(ReservedTags::KERNEL_STATEFUL, 'App\\RequestIdResetProbe');
        $tags->add($reset->effectiveResetTag(), 'App\\RequestIdResetProbe', 100);
        $tags->add($reset->effectiveResetTag(), ContextStore::class);
        $ids = $this->createMock(IdGeneratorInterface::class);
        $ids->expects(self::exactly(2))->method('generate')->willReturnCallback(
            static function () use ($context, &$invocation): string {
                self::assertFalse(
                    $context->has(ContextKeys::REQUEST_ID),
                    'request-id-prepared-before-uow-begin',
                );

                return self::UOW_IDS[$invocation];
            },
        );
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturnCallback(
            static function () use (&$invocation): string {
                return self::CORRELATIONS[$invocation];
            },
        );
        $runtime = new KernelRuntime(
            contextStore: $context,
            resetOrchestrator: $reset,
            stopwatch: new Stopwatch(),
            uowIds: $ids,
            correlationIdProvider: $correlation,
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker($container, $hookTags),
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );

        $first = $this->createStub(MiddlewareInterface::class);
        $first->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                $context,
                $requestIds,
                &$selected,
                &$events,
            ): ResponseInterface {
                $events[] = 'before-enrichment';
                self::assertTrue(self::matchesIdentity($context, $selected), 'request-id-before-pipeline-mismatch');
                self::assertTrue($requestIds->current() === $selected, 'request-id-before-pipeline-current-mismatch');
                self::assertFalse($context->has(ContextKeys::PATH), 'premature-or-stale-http-context');

                return $next->handle($request);
            },
        );
        $post = $this->createStub(MiddlewareInterface::class);
        $post->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                &$activeScenario,
                &$response,
                &$events,
            ): ResponseInterface {
                $events[] = 'post';
                if ($activeScenario === 'post-short-circuit') {
                    return $response;
                }

                $completed = $next->handle($request);
                $events[] = 'post-return';

                return $completed;
            },
        );
        $requestPhase = $this->createStub(MiddlewareInterface::class);
        $requestPhase->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                &$activeScenario,
                &$response,
                &$events,
            ): ResponseInterface {
                $events[] = 'request-phase';

                return $activeScenario === 'request-short-circuit' ? $response : $next->handle($request);
            },
        );
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturnCallback(
            static function () use (
                $context,
                &$selected,
                &$activeScenario,
                &$response,
                &$events,
                $failure,
                $problemFailure,
            ): ResponseInterface {
                $events[] = 'terminal';
                self::assertTrue(self::matchesIdentity($context, $selected), 'terminal-identity-mismatch');
                self::assertTrue($context->has(ContextKeys::PATH), 'missing-http-enrichment');

                switch ($activeScenario) {
                    case 'remove':
                        $context->remove(ContextKeys::REQUEST_ID);
                        break;
                    case 'invalid-replacement':
                        $context->set(ContextKeys::REQUEST_ID, self::INVALID_ID);
                        break;
                    case 'substitution':
                        $context->set(ContextKeys::REQUEST_ID, self::SUBSTITUTED_ID);
                        break;
                    case 'pipeline-failure':
                        throw $failure;
                    case 'problem-details':
                        throw $problemFailure;
                }

                return $response;
            },
        );
        $middleware = [$first, new RequestContextMiddleware($context, true, false), $post, $requestPhase];
        if (\in_array($scenario, ['problem-details', 'generator-failure'], true)) {
            $expectProblemResponse = $scenario === 'problem-details';

            $errors = $this->createMock(ErrorHandlerInterface::class);
            $errors->expects($expectProblemResponse ? self::once() : self::never())
                ->method('handle')->willReturnCallback(
                    static function (\Throwable $actual) use ($problemFailure): ErrorDescriptor {
                        self::assertTrue($actual === $problemFailure, 'problem-details-primary-mismatch');
                        $descriptor = new HttpExceptionMapper()->map($actual);
                        self::assertInstanceOf(ErrorDescriptor::class, $descriptor);

                        return $descriptor;
                    },
                );
            $responses = $this->createMock(ResponseFactoryInterface::class);
            $responses->expects($expectProblemResponse ? self::once() : self::never())
                ->method('createResponse')->with(400)->willReturnCallback(
                    static function () use (&$response): ResponseInterface {
                        return $response;
                    },
                );
            $streams = $this->createMock(StreamFactoryInterface::class);
            $streams->expects($expectProblemResponse ? self::once() : self::never())
                ->method('createStream')->willReturn(
                    $this->createStub(StreamInterface::class),
                );
            \array_unshift(
                $middleware,
                new HttpErrorHandlingMiddleware(
                    $errors,
                    new ErrorDescriptorToProblemDetailsAdapter(),
                    new ProblemDetailsJsonRenderer(),
                    $responses,
                    $streams,
                ),
            );
        }
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline($middleware, $terminal),
            new HttpResponseFinalizer(
                new CorrelationIdProvider($context),
                $correlationEnabled,
                self::CORRELATION_HEADER,
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
            $requestIds,
        );

        for ($invocation = 0; $invocation < 2; ++$invocation) {
            self::assertResetContext($context);
            $events = [];
            $activeScenario = $invocation === 0 ? $scenario : 'normal';
            $selected = $invocation === 0 ? $firstId : ($enabled ? self::SECOND_ID : null);
            $hookIdentity = match ($activeScenario) {
                'remove' => null,
                'invalid-replacement' => self::INVALID_ID,
                'substitution' => self::SUBSTITUTED_ID,
                default => $selected,
            };
            $status = match ($activeScenario) {
                'request-short-circuit' => 403,
                'post-short-circuit' => 503,
                'problem-details' => 400,
                default => 200,
            };
            $fatal = \in_array($activeScenario, [
                'remove',
                'invalid-replacement',
                'substitution',
                'pipeline-failure',
                'generator-failure',
                'request-header-failure',
                'correlation-header-failure',
            ], true);
            $outcome = $fatal ? 'fatal_error' : ($status >= 400 ? 'handled_error' : 'success');
            $expectedMutations = self::headerMutations($activeScenario, $enabled, $correlationEnabled);
            $operations = $expectedMutations;
            if ($activeScenario === 'problem-details') {
                \array_splice($operations, 2, 0, ['problem-body']);
            }

            $headersByStage = [[$headerName => self::APPLICATION_IDS]];
            $mutations = [];
            $stages = [];

            for ($index = 0; $index <= \count($operations); ++$index) {
                $stages[] = $this->createMock(ResponseInterface::class);
            }

            $response = $stages[0];
            $expectedResponse = $stages[\count($operations)];

            $recordHeader = static function (string $name, mixed $value) use (
                $context,
                $requestIds,
                $selected,
                $headerName,
                $activeScenario,
                $invocation,
                $failure,
                &$mutations,
                &$events,
            ): void {
                if ($name === self::CORRELATION_HEADER) {
                    $mutations[] = 'correlation-header';
                    $events[] = 'correlation-header';
                    self::assertTrue($value === self::CORRELATIONS[$invocation], 'correlation-header-mismatch');
                    if ($activeScenario === 'correlation-header-failure') {
                        throw $failure;
                    }
                } elseif ($name === $headerName) {
                    $mutations[] = 'request-id-header';
                    $events[] = 'request-id-header';
                    self::assertTrue($selected !== null && $value === $selected, 'selected-header-mismatch');
                    self::assertTrue(self::matchesIdentity($context, $selected), 'finalization-context-mismatch');
                    self::assertTrue($requestIds->current() === $selected, 'finalization-current-mismatch');
                    if ($activeScenario === 'request-header-failure') {
                        throw $failure;
                    }
                } elseif ($name === 'Content-Type') {
                    $mutations[] = 'problem-content-type';
                    $events[] = 'problem-content-type';
                    self::assertTrue($value === 'application/problem+json', 'problem-content-type-mismatch');
                } elseif ($name === 'Cache-Control') {
                    $mutations[] = 'problem-cache-control';
                    $events[] = 'problem-cache-control';
                    self::assertTrue($value === 'no-store', 'problem-cache-control-mismatch');
                } else {
                    self::fail('unexpected-response-header-mutation');
                }
            };

            foreach ($stages as $index => $stage) {
                $operation = $operations[$index] ?? null;

                $stage->expects(
                    !$fatal && $stage === $expectedResponse ? self::exactly(2) : self::never(),
                )->method('getStatusCode')->willReturn($status);

                $stage->method('getHeader')->willReturnCallback(
                    static function (string $name) use (&$headersByStage, $index): array {
                        return $headersByStage[$index][$name] ?? [];
                    },
                );

                $stage->expects(self::never())->method('withAddedHeader');
                $stage->expects(self::never())->method('withoutHeader');
                $stage->expects(self::never())->method('withStatus');

                $stage->expects(
                    $operation !== null && $operation !== 'problem-body' ? self::once() : self::never(),
                )->method('withHeader')->willReturnCallback(
                    static function (string $name, mixed $value) use (
                        $recordHeader,
                        $stages,
                        $index,
                        &$headersByStage,
                    ): ResponseInterface {
                        $recordHeader($name, $value);

                        $headersByStage[$index + 1] = $headersByStage[$index];
                        $headersByStage[$index + 1][$name] = [$value];

                        return $stages[$index + 1];
                    },
                );

                $stage->expects(
                    $operation === 'problem-body' ? self::once() : self::never(),
                )->method('withBody')->willReturnCallback(
                    static function () use ($stages, $index, &$headersByStage): ResponseInterface {
                        $headersByStage[$index + 1] = $headersByStage[$index];

                        return $stages[$index + 1];
                    },
                );
            }

            $request = $this->request(
                $headerName,
                $invocation === 0 ? $upstream : [],
                $enabled && $acceptFromUpstream,
                $activeScenario !== 'generator-failure',
            );
            $returned = null;
            $primary = null;
            try {
                $returned = $kernel->handle($request);
            } catch (\Throwable $actual) {
                $primary = $actual;
            }

            if ($fatal) {
                self::assertTrue($returned === null, 'fatal-request-returned-response');
                if (\in_array($activeScenario, ['remove', 'invalid-replacement', 'substitution'], true)) {
                    self::assertTrue(
                        $primary instanceof \RuntimeException && $primary->getMessage() === 'http-request-id-context-invalid',
                        'tampered-identity-failure-mismatch',
                    );
                } else {
                    self::assertTrue($primary === $failure, 'primary-throwable-not-preserved');
                }
                self::assertTrue(
                    $response->getHeader($headerName) === self::APPLICATION_IDS,
                    'fatal-request-id-header-mutated',
                );
            } else {
                self::assertTrue(
                    $primary === null && $returned === $expectedResponse,
                    'completed-response-mismatch',
                );
                self::assertTrue($returned->getStatusCode() === $status, 'completed-status-mismatch');
                self::assertTrue(
                    $returned->getHeader($headerName) === ($enabled ? [$selected] : self::APPLICATION_IDS),
                    'completed-request-id-header-mismatch',
                );
                self::assertTrue(
                    $returned->getHeader(
                        self::CORRELATION_HEADER,
                    ) === ($correlationEnabled ? [self::CORRELATIONS[$invocation]] : []),
                    'completed-correlation-header-mismatch',
                );
            }

            self::assertTrue(
                $response->getHeader($headerName) === self::APPLICATION_IDS,
                'initial-response-request-id-header-mutated',
            );
            self::assertTrue(
                $response->getHeader(self::CORRELATION_HEADER) === [],
                'initial-response-correlation-header-mutated',
            );

            self::assertSame($expectedMutations, $mutations, 'identity-header-mutation-order-mismatch');
            self::assertSame(
                self::lifecycleEvents(
                    $activeScenario,
                    $selected === self::FIRST_ID || $selected === self::SECOND_ID,
                    $expectedMutations,
                ),
                $events,
                'request-lifecycle-order-mismatch',
            );
            self::assertTrue(
                ($afterObservations[$invocation] ?? null) === [true, true, true, true, true, true],
                'after-uow-context-or-payload-mismatch',
            );
            self::assertTrue(
                ($resetObservations[$invocation] ?? false) === true,
                'context-cleared-before-canonical-reset',
            );
            self::assertResetContext($context);
        }
        self::assertCount(2, $afterObservations, 'after-uow-cardinality-mismatch');
        self::assertCount(2, $resetObservations, 'reset-cardinality-mismatch');
    }

    /** @return iterable<string, array{string, bool, bool, bool, list<string>, ?string}> */
    public static function lifecycleCases(): iterable
    {
        yield 'default-disabled' => ['normal', false, false, true, [self::UPSTREAM_ID], null];
        yield 'both-disabled' => ['normal', false, false, false, [self::UPSTREAM_ID], null];
        yield 'upstream-disabled' => ['normal', true, false, true, [self::UPSTREAM_ID], self::FIRST_ID];
        yield 'trusted-upstream' => ['normal', true, true, true, [self::UPSTREAM_ID], self::UPSTREAM_ID];
        yield 'request-id-only' => ['normal', true, true, false, [self::UPSTREAM_ID], self::UPSTREAM_ID];
        yield 'missing-upstream' => ['normal', true, true, true, [], self::FIRST_ID];
        yield 'malformed-upstream' => ['normal', true, true, true, ['invalid,value'], self::FIRST_ID];
        yield 'multiple-upstream' => [
            'normal',
            true,
            true,
            true,
            [self::UPSTREAM_ID, self::UPSTREAM_ID],
            self::FIRST_ID,
        ];
        yield 'oversized-upstream' => ['normal', true, true, true, [\str_repeat('a', 27)], self::FIRST_ID];
        yield 'request-short-circuit' => [
            'request-short-circuit',
            true,
            true,
            true,
            [self::UPSTREAM_ID],
            self::UPSTREAM_ID,
        ];
        yield 'post-short-circuit' => ['post-short-circuit', true, false, true, [], self::FIRST_ID];
        yield 'problem-details' => ['problem-details', true, true, true, [self::UPSTREAM_ID], self::UPSTREAM_ID];
        yield 'removed-identity' => ['remove', true, true, true, [self::UPSTREAM_ID], self::UPSTREAM_ID];
        yield 'invalid-replacement' => ['invalid-replacement', true, false, true, [], self::FIRST_ID];
        yield 'valid-substitution' => ['substitution', true, true, true, [self::UPSTREAM_ID], self::UPSTREAM_ID];
        yield 'uncaught-pipeline' => ['pipeline-failure', true, false, true, [], self::FIRST_ID];
        yield 'generator-failure' => ['generator-failure', true, false, true, [], null];
        yield 'request-header-failure' => ['request-header-failure', true, false, true, [], self::FIRST_ID];
        yield 'correlation-header-failure' => ['correlation-header-failure', true, false, true, [], self::FIRST_ID];
    }

    /** @param list<string> $upstream */
    private function request(
        string $headerName,
        array $upstream,
        bool $readIdentity,
        bool $enrichContext,
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getScheme')->willReturn('https');
        $uri->method('getHost')->willReturn('example.test');
        $uri->method('getPort')->willReturn(null);
        $uri->method('getPath')->willReturn('/request-id');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.0.2.1']);
        $request->expects(self::never())->method('getHeaderLine');
        $request->expects(self::never())->method('getHeaders');
        $request->expects(self::never())->method('withHeader');
        $expectedReads = ($readIdentity ? 1 : 0) + ($enrichContext ? 2 : 0);
        $request->expects(self::exactly($expectedReads))->method('getHeader')->willReturnCallback(
            static function (string $name) use ($headerName, $upstream, $readIdentity, $enrichContext): array {
                return match ($name) {
                    $headerName => $readIdentity ? $upstream : throw new \LogicException(
                        'unexpected-request-id-header-read',
                    ),
                    'Host' => $enrichContext ? ['example.test'] : throw new \LogicException(
                        'unexpected-context-header-read',
                    ),
                    'User-Agent' => $enrichContext ? ['synthetic-agent'] : throw new \LogicException(
                        'unexpected-context-header-read',
                    ),
                    default => throw new \LogicException('unexpected-request-header-read'),
                };
            },
        );

        return $request;
    }

    /** @return list<string> */
    private static function headerMutations(string $scenario, bool $enabled, bool $correlationEnabled): array
    {
        if (\in_array($scenario, ['generator-failure', 'pipeline-failure'], true)) {
            return [];
        }

        $mutations = $scenario === 'problem-details' ? ['problem-content-type', 'problem-cache-control'] : [];
        if ($correlationEnabled) {
            $mutations[] = 'correlation-header';
        }
        if ($enabled && !\in_array(
            $scenario,
            ['remove', 'invalid-replacement', 'substitution', 'correlation-header-failure'],
            true,
        )) {
            $mutations[] = 'request-id-header';
        }

        return $mutations;
    }

    /**
     * @param list<string> $mutations
     * @return list<string>
     */
    private static function lifecycleEvents(string $scenario, bool $generated, array $mutations): array
    {
        if ($scenario === 'generator-failure') {
            return ['generate', 'after', 'reset'];
        }

        $events = $generated ? ['generate'] : [];
        $events[] = 'before-enrichment';
        $events[] = 'post';
        if ($scenario !== 'post-short-circuit') {
            $events[] = 'request-phase';
            if ($scenario !== 'request-short-circuit') {
                $events[] = 'terminal';
            }
            if (!\in_array($scenario, ['pipeline-failure', 'problem-details'], true)) {
                $events[] = 'post-return';
            }
        }

        return [...$events, ...$mutations, 'after', 'reset'];
    }

    private static function matchesIdentity(ContextAccessorInterface $context, ?string $expected): bool
    {
        return $expected === null
            ? !$context->has(ContextKeys::REQUEST_ID)
            : $context->has(ContextKeys::REQUEST_ID) && $context->get(ContextKeys::REQUEST_ID) === $expected;
    }

    private static function matchesHttpContext(ContextAccessorInterface $context, bool $enriched): bool
    {
        foreach (
            [
                ContextKeys::CLIENT_IP => '192.0.2.1',
                ContextKeys::SCHEME => 'https',
                ContextKeys::HOST => 'example.test',
                ContextKeys::PATH => '/request-id',
                ContextKeys::USER_AGENT => 'synthetic-agent',
            ] as $key => $value
        ) {
            if ($enriched ? (!$context->has($key) || $context->get($key) !== $value) : $context->has($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $result
     */
    private static function canonicalPayloads(array $context, array $result, int $invocation, string $outcome): bool
    {
        $duration = $result['durationMs'] ?? null;
        if (!\is_int($duration) || $duration < 0) {
            return false;
        }
        $expectedResult = [
            'correlationId' => self::CORRELATIONS[$invocation],
            'durationMs' => $duration,
        ];
        if ($outcome === 'fatal_error') {
            $expectedResult['error'] = new ErrorDescriptor(
                code: 'coretsia.kernel.runtime.error',
                message: 'kernel-runtime-error',
            )->toArray();
        }
        $expectedResult['extensions'] = [];
        $expectedResult['outcome'] = $outcome;
        $expectedResult['type'] = 'http';
        $expectedResult['uowId'] = self::UOW_IDS[$invocation];

        // Exact shapes also prove request identity is not exported in either payload.
        return $context === [
                'attributes' => [],
                'correlationId' => self::CORRELATIONS[$invocation],
                'type' => 'http',
                'uowId' => self::UOW_IDS[$invocation],
            ] && $result === $expectedResult;
    }

    private static function assertResetContext(ContextAccessorInterface $context): void
    {
        foreach (ContextKeys::all() as $key) {
            self::assertFalse($context->has($key), 'request-local-context-not-reset:' . $key);
        }
    }
}
