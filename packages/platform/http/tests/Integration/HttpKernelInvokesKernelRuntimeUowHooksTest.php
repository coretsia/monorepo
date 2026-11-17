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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
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
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernelInvokesKernelRuntimeUowHooksTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('completedResponses')]
    public function testSuccessfulBeginHasOnePipelineAndOneAfterWithCanonicalOutcome(int $status, string $outcome): void
    {
        $events = [];
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')
            ->willReturnCallback(static function () use ($handle, &$events): UnitOfWorkHandle {
                $events[] = 'begin';

                return $handle;
            });
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), $outcome, null)
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'after-reset';

                return [];
            });
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $initial = $this->createMock(ResponseInterface::class);
        $final = $this->createStub(ResponseInterface::class);
        $initial->expects(self::once())->method('withHeader')
            ->willReturnCallback(
                static function (string $name, mixed $value) use (&$events, $final): ResponseInterface {
                    self::assertTrue($name === 'X-Correlation-Id', 'correlation-header-name-mismatch');
                    self::assertTrue($value === self::CORRELATION, 'correlation-header-value-mismatch');

                    $events[] = 'finalize';

                    return $final;
                },
            );
        $final->method('getStatusCode')->willReturn($status);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)
            ->willReturnCallback(static function () use (&$events, $initial): ResponseInterface {
                $events[] = 'pipeline';

                return $initial;
            });
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('http.request_total', 1, [
            'method' => 'GET',
            'status' => $status,
            'outcome' => $outcome,
        ])->willReturnCallback(static function () use (&$events): void {
            $events[] = 'metric';
        });
        $meter->expects(self::once())->method('observe')->with('http.request_duration_ms');

        $kernel = self::kernel($runtime, new Pipeline([], $terminal), $meter);
        self::assertSame($final, $kernel->handle($request));
        self::assertSame(['begin', 'pipeline', 'finalize', 'metric', 'after-reset'], $events);
    }

    /** @return iterable<string, array{int, string}> */
    public static function completedResponses(): iterable
    {
        yield 'success' => [200, 'success'];
        yield 'short-circuit' => [403, 'handled_error'];
        yield 'bad-json' => [400, 'handled_error'];
        yield 'payload-too-large' => [413, 'handled_error'];
        yield 'emergency' => [500, 'handled_error'];
    }

    public function testRejectedBeginDoesNotStartTimerOrRunPipelineOrCompleteAnotherUow(): void
    {
        $failure = new \RuntimeException('uow-begin-rejected');
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willThrowException($failure);
        $runtime->expects(self::never())->method('afterUnitOfWork');
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::never())->method('increment');
        $meter->expects(self::never())->method('observe');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        try {
            self::kernel($runtime, new Pipeline([], $terminal), $meter)
                ->handle($this->createStub(ServerRequestInterface::class));
            self::fail('Rejected begin must escape without entering the HTTP request lifecycle.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testKernelStartsStopwatchOnlyAfterBeginAndStopsOnlyWithSuccessfulToken(): void
    {
        $file = new \ReflectionClass(HttpKernel::class)->getFileName();
        self::assertTrue(\is_string($file));
        $source = \file_get_contents($file);
        self::assertTrue(\is_string($source));
        $begin = \strpos($source, '$this->runtime->beginUnitOfWork(\'http\')');
        $start = \strpos($source, '$this->stopwatch->start()');
        $stop = \strpos($source, '$this->stopwatch->stop($startedAt)');
        self::assertTrue(\is_int($begin) && \is_int($start) && \is_int($stop));
        self::assertTrue($begin < $start && $start < $stop);
        self::assertTrue(\str_contains($source, 'if ($startedAt !== null)'));
        self::assertSame(1, \substr_count($source, '$this->stopwatch->start()'));
        self::assertSame(1, \substr_count($source, '$this->stopwatch->stop($startedAt)'));
    }

    #[DataProvider('pipelineFailureCases')]
    public function testPipelineFailureIsPrimaryEvenWhenKernelAfterAndOptionalRecordingAlsoFail(
        \Throwable $failure,
    ): void {
        $handle = self::handle();
        $events = [];
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'fatal_error', self::identicalTo($failure))
            ->willReturnCallback(static function () use (&$events): never {
                $events[] = 'after-reset';
                throw new \RuntimeException('secondary-after-failure');
            });
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturnCallback(
            static function () use (&$events, $failure): never {
                $events[] = 'pipeline';
                throw $failure;
            },
        );
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->with('http.request_total', 1, ['method' => 'POST', 'status' => 'unknown', 'outcome' => 'fatal_error'])
            ->willReturnCallback(static function () use (&$events): never {
                $events[] = 'metric';
                throw new \RuntimeException('optional-metric-failure');
            });
        $meter->expects(self::once())->method('observe')->with('http.request_duration_ms');

        try {
            self::kernel($runtime, new Pipeline([], $terminal), $meter)->handle($request);
            self::fail('Uncaught downstream Throwable must not be swallowed.');
        } catch (\Throwable $actual) {
            self::assertSame($failure, $actual);
        }
        self::assertSame(['pipeline', 'metric', 'after-reset'], $events);
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function pipelineFailureCases(): iterable
    {
        yield 'runtime-exception' => [new \RuntimeException('original-pipeline-throwable')];
        yield 'error' => [new \Error('original-pipeline-error')];
    }

    public function testFailedFinalizationNeverReturnsIntermediateResponseAndCompletesOnce(): void
    {
        $failure = new \RuntimeException('finalizer-header-failed');
        $handle = self::handle();
        $events = [];
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'fatal_error', self::identicalTo($failure))
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'after-reset';

                return [];
            });
        $intermediate = $this->createMock(ResponseInterface::class);
        $intermediate->expects(self::once())->method('withHeader')
            ->willReturnCallback(static function () use ($failure): never {
                throw $failure;
            });
        $intermediate->expects(self::never())->method('getStatusCode');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($intermediate);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->with('http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error'])
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'metric';
            });
        $meter->expects(self::once())->method('observe')->with('http.request_duration_ms');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        try {
            self::kernel($runtime, new Pipeline([], $terminal), $meter)->handle($request);
            self::fail('Intermediate response must not escape failed finalization.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
        self::assertSame(['metric', 'after-reset'], $events);
    }

    public function testKernelCompletionFailureIsPrimaryWhenHttpResponseCompletedSuccessfully(): void
    {
        $failure = new \RuntimeException('kernel-after-failed');
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'success', null)->willThrowException($failure);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('http.request_total');
        $meter->expects(self::once())->method('observe')->with('http.request_duration_ms');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        try {
            self::kernel($runtime, new Pipeline([], $terminal), $meter)->handle($request);
            self::fail('Kernel completion failure must propagate when no earlier HTTP Throwable exists.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testSequentialRequestsUseRequestLocalTimingAndCompleteIndependently(): void
    {
        $first = self::handle();
        $second = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::exactly(2))->method('beginUnitOfWork')->with('http')
            ->willReturnOnConsecutiveCalls($first, $second);
        $completed = [];
        $runtime->expects(self::exactly(2))->method('afterUnitOfWork')
            ->willReturnCallback(
                static function (UnitOfWorkHandle $handle, string $outcome, ?\Throwable $error) use (
                    &$completed,
                ): array {
                    $completed[] = [$handle, $outcome, $error];

                    return [];
                },
            );
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::exactly(2))->method('increment')->with('http.request_total');
        $durations = [];
        $meter->expects(self::exactly(2))->method('observe')->willReturnCallback(
            static function (string $name, int $duration, array $labels) use (&$durations): void {
                self::assertSame('http.request_duration_ms', $name);
                self::assertTrue($duration >= 0);
                self::assertSame(['method', 'status', 'outcome'], \array_keys($labels));
                $durations[] = [$duration, $labels];
            },
        );
        $one = $this->createStub(ResponseInterface::class);
        $two = $this->createStub(ResponseInterface::class);
        $one->method('getStatusCode')->willReturn(200);
        $two->method('getStatusCode')->willReturn(404);
        $one->method('withHeader')->willReturn($one);
        $two->method('withHeader')->willReturn($two);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')->willReturnOnConsecutiveCalls($one, $two);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $kernel = self::kernel($runtime, new Pipeline([], $terminal), $meter);

        self::assertSame($one, $kernel->handle($request));
        self::assertSame($two, $kernel->handle($request));
        self::assertCount(2, $durations);
        self::assertSame(200, $durations[0][1]['status']);
        self::assertSame(404, $durations[1][1]['status']);
        self::assertSame(['attributes', 'correlationId', 'type', 'uowId'], \array_keys($first->context()));
        self::assertSame([[$first, 'success', null], [$second, 'handled_error', null]], $completed);
    }

    public function testRealKernelRuntimeOwnsIdentityAndResetExactlyOncePerRequest(): void
    {
        $context = new ContextStore();
        $probe = new class($context) implements ResetInterface {
            public int $calls = 0;

            public function __construct(private ContextStore $context)
            {
            }

            public function reset(): void
            {
                ++$this->calls;
                TestCase::assertTrue($this->context->has(ContextKeys::CORRELATION_ID));
                TestCase::assertTrue($this->context->has(ContextKeys::UOW_ID));
            }
        };
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): mixed => match ($id) {
                ContextStore::class => $context,
                'App\\ResetProbe' => $probe,
                default => throw new \LogicException('unexpected-test-service'),
            },
        );
        $tags = new TagRegistry();
        $tags->add(ReservedTags::KERNEL_RESET, 'App\\ResetProbe', 100);
        $tags->add(ReservedTags::KERNEL_RESET, ContextStore::class);
        $ids = $this->createStub(IdGeneratorInterface::class);
        $ids->method('generate')->willReturn(self::CORRELATION);
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::CORRELATION);
        $runtime = new KernelRuntime(
            contextStore: $context,
            resetOrchestrator: new ResetOrchestrator($container, $tags),
            stopwatch: new Stopwatch(),
            uowIds: $ids,
            correlationIdProvider: $correlation,
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker($container, new TagRegistry()),
            logger: new NoopLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))->method('withHeader')
            ->willReturnCallback(
                static function (string $name, mixed $value) use ($response): ResponseInterface {
                    self::assertTrue($name === 'X-Correlation-Id', 'correlation-header-name-mismatch');
                    self::assertTrue($value === self::CORRELATION, 'correlation-header-value-mismatch');

                    return $response;
                },
            );
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')->willReturn($response);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::exactly(2))->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                $context,
            ): ResponseInterface {
                self::assertTrue(
                    $context->get(ContextKeys::CORRELATION_ID) === self::CORRELATION,
                    'kernel-correlation-id-mismatch',
                );
                self::assertTrue(
                    $context->get(ContextKeys::UOW_ID) === self::CORRELATION,
                    'kernel-uow-id-mismatch',
                );
                self::assertTrue(
                    $context->get(ContextKeys::UOW_TYPE) === 'http',
                    'kernel-uow-type-mismatch',
                );
                $context->set(ContextKeys::PATH, '/request-local');

                return $next->handle($request);
            },
        );
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $requestIds = new RequestIdPolicy(
            $context,
            new UlidGenerator(),
            false,
            'X-Request-Id',
            false,
            128,
        );
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([$middleware], $terminal),
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );

        self::assertSame($response, $kernel->handle($request));
        foreach (ContextKeys::all() as $key) {
            self::assertFalse($context->has($key), 'context-key-not-reset:' . $key);
        }
        self::assertSame(1, $probe->calls);
        self::assertSame($response, $kernel->handle($request));
        foreach (ContextKeys::all() as $key) {
            self::assertFalse($context->has($key), 'context-key-not-reset:' . $key);
        }
        self::assertSame(2, $probe->calls);
    }

    #[DataProvider('longRunningRequestIdConfigurations')]
    public function testLongRunningSequentialRequestsPreserveIsolationAcrossOutcomes(
        bool $requestIdEnabled,
    ): void {
        $context = new ContextStore();

        $requestIndex = 0;
        $requestId = static fn (int $index): string => \sprintf('01ARZ3NDEKTSV4RT%010d', $index);
        $probe = new class() implements ResetInterface {
            public int $calls = 0;
            public ?int $requestIndex = null;

            public function reset(): void
            {
                $this->requestIndex = null;
                ++$this->calls;
            }
        };

        $after = new class(
            static fn (): ?int => $probe->requestIndex,
            static function () use ($context, $requestIdEnabled, $requestId, &$requestIndex): bool {
                if (!$requestIdEnabled) {
                    return !$context->has(ContextKeys::REQUEST_ID);
                }

                return $context->has(ContextKeys::REQUEST_ID)
                    && $context->get(ContextKeys::REQUEST_ID) === $requestId($requestIndex);
            },
        ) implements AfterUowHookInterface {
            public int $calls = 0;
            public ?string $outcome = null;
            public ?int $observedRequestIndex = null;
            public bool $observedRequestIdMatches = false;

            public function __construct(
                private \Closure $requestIndex,
                private \Closure $requestIdentityMatches,
            ) {
            }

            public function afterUow(array $context, array $result): void
            {
                ++$this->calls;
                $this->outcome = \is_string($result['outcome'] ?? null) ? $result['outcome'] : null;
                $this->observedRequestIndex = ($this->requestIndex)();
                $this->observedRequestIdMatches = ($this->requestIdentityMatches)();
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): mixed => match ($id) {
                ContextStore::class => $context,
                'App\\LongRunningResetProbe' => $probe,
                'App\\LongRunningAfterHook' => $after,
                default => throw new \LogicException('unexpected-test-service'),
            },
        );
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => $id === 'App\\LongRunningAfterHook',
        );
        $hookTags = new TagRegistry();
        $hookTags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'App\\LongRunningAfterHook');
        $tags = new TagRegistry();
        $reset = new ResetOrchestrator($container, $tags);
        $resetTag = $reset->effectiveResetTag();
        $tags->add(ReservedTags::KERNEL_STATEFUL, ContextStore::class);
        $tags->add(ReservedTags::KERNEL_STATEFUL, 'App\\LongRunningResetProbe');
        $tags->add($resetTag, 'App\\LongRunningResetProbe', 100);
        $tags->add($resetTag, ContextStore::class);

        $uowId = static fn (int $index): string => \sprintf('01ARZ3NDEKTSV4RS%010d', $index);
        $correlationId = static fn (int $index): string => \sprintf('01ARZ3NDEKTSV4RR%010d', $index);
        $ids = $this->createStub(IdGeneratorInterface::class);
        $ids->method('generate')->willReturnCallback(
            static function () use (&$requestIndex, $uowId): string {
                return $uowId($requestIndex);
            },
        );
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturnCallback(
            static function () use (&$requestIndex, $correlationId): string {
                return $correlationId($requestIndex);
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
            logger: new NoopLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );

        $requestIdsGenerator = $this->createMock(IdGeneratorInterface::class);
        $requestIdsGenerator->expects($requestIdEnabled ? self::exactly(1000) : self::never())
            ->method('generate')
            ->willReturnCallback(
                static function () use (&$requestIndex, $requestId): string {
                    return $requestId($requestIndex);
                },
            );

        $requestIds = new RequestIdPolicy(
            $context,
            $requestIdsGenerator,
            $requestIdEnabled,
            'X-Request-Id',
            false,
            128,
        );

        $optionalKeys = [
            ContextKeys::CLIENT_IP,
            ContextKeys::SCHEME,
            ContextKeys::HOST,
            ContextKeys::PATH,
            ContextKeys::USER_AGENT,
        ];
        $beforeCalls = 0;
        $before = $this->createStub(MiddlewareInterface::class);
        $before->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                $context,
                $optionalKeys,
                $requestIdEnabled,
                $requestId,
                &$requestIndex,
                &$beforeCalls,
            ): ResponseInterface {
                foreach ($optionalKeys as $key) {
                    self::assertFalse($context->has($key), 'previous-request-key-present:' . $key);
                }

                if ($requestIdEnabled) {
                    self::assertTrue(
                        $context->has(ContextKeys::REQUEST_ID),
                        'request-id-missing-before-context-enrichment',
                    );
                    self::assertTrue(
                        $context->get(ContextKeys::REQUEST_ID) === $requestId($requestIndex),
                        'request-id-mismatch-before-context-enrichment',
                    );

                    if ($requestIndex > 0) {
                        self::assertTrue(
                            $context->get(ContextKeys::REQUEST_ID) !== $requestId($requestIndex - 1),
                            'previous-request-id-present-before-context-enrichment',
                        );
                    }
                } else {
                    self::assertFalse(
                        $context->has(ContextKeys::REQUEST_ID),
                        'disabled-request-id-present-before-context-enrichment',
                    );
                }

                ++$beforeCalls;

                return $next->handle($request);
            },
        );

        $activeRequest = null;
        $activeResponse = null;
        $terminalCalls = 0;
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $request) use (
                $context,
                $probe,
                $uowId,
                $correlationId,
                $requestIdEnabled,
                $requestId,
                &$requestIndex,
                &$activeRequest,
                &$activeResponse,
                &$terminalCalls,
            ): ResponseInterface {
                self::assertTrue($request === $activeRequest, 'http-request-identity-mismatch');
                self::assertNull($probe->requestIndex, 'previous-resettable-state-present');
                self::assertTrue($context->get(ContextKeys::UOW_ID) === $uowId($requestIndex), 'uow-id-mismatch');
                self::assertTrue(
                    $context->get(ContextKeys::CORRELATION_ID) === $correlationId($requestIndex),
                    'correlation-id-mismatch',
                );
                self::assertTrue($context->get(ContextKeys::UOW_TYPE) === 'http', 'uow-type-mismatch');
                if ($requestIdEnabled) {
                    self::assertTrue(
                        $context->get(ContextKeys::REQUEST_ID) === $requestId($requestIndex),
                        'request-id-changed-during-context-enrichment',
                    );
                } else {
                    self::assertFalse(
                        $context->has(ContextKeys::REQUEST_ID),
                        'disabled-request-id-present-in-terminal',
                    );
                }
                foreach (
                    [
                        ContextKeys::CLIENT_IP => '192.0.2.8',
                        ContextKeys::SCHEME => $requestIndex % 2 === 0 ? 'http' : 'https',
                        ContextKeys::HOST => 'example.test',
                        ContextKeys::PATH => '/long-running/' . $requestIndex,
                        ContextKeys::USER_AGENT => 'CoretsiaTest/' . $requestIndex,
                    ] as $key => $expected
                ) {
                    self::assertTrue($context->has($key), 'current-request-key-missing:' . $key);
                    self::assertTrue($context->get($key) === $expected, 'current-request-value-mismatch:' . $key);
                }
                $probe->requestIndex = $requestIndex;
                ++$terminalCalls;
                if ($requestIndex % 4 === 2) {
                    throw new \RuntimeException('synthetic-pipeline-failure');
                }

                if (!$activeResponse instanceof ResponseInterface) {
                    throw new \LogicException('active-http-response-missing');
                }

                return $activeResponse;
            },
        );
        $pipeline = new Pipeline([
            $before,
            new RequestContextMiddleware($context, true, false),
        ], $terminal);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer(
                new CorrelationIdProvider($context),
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );

        $finalizations = 0;
        $expectedFinalizations = 0;
        $requestIdFinalizations = 0;
        $expectedRequestIdFinalizations = 0;
        $scenarioCounts = [0, 0, 0, 0];
        $previousRequest = null;
        for ($requestIndex = 0; $requestIndex < 1000; ++$requestIndex) {
            $scenario = $requestIndex % 4;
            ++$scenarioCounts[$scenario];
            $uri = $this->createStub(UriInterface::class);
            $uri->method('getScheme')->willReturn($requestIndex % 2 === 0 ? 'http' : 'https');
            $uri->method('getHost')->willReturn('example.test');
            $uri->method('getPath')->willReturn('/long-running/' . $requestIndex);
            $request = $this->createStub(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn('GET');
            $request->method('getUri')->willReturn($uri);
            $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.0.2.8']);
            $request->method('getHeader')->willReturnCallback(
                static fn (string $name): array => match ($name) {
                    'Host' => ['example.test'],
                    'User-Agent' => ['CoretsiaTest/' . $requestIndex],
                    default => [],
                },
            );
            self::assertTrue($request !== $previousRequest, 'http-request-not-recreated');
            $activeRequest = $request;
            $previousRequest = $request;

            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($scenario === 1 ? 503 : 200);
            $response->expects(self::never())->method('withAddedHeader');
            $response->expects(self::never())->method('withoutHeader');

            $emittedCorrelationId = null;
            $emittedRequestId = null;

            $expectedHeaderMutations = $scenario === 2 ? 0 : 1;
            if ($requestIdEnabled && ($scenario === 0 || $scenario === 1)) {
                ++$expectedHeaderMutations;
            }

            $response->expects(self::exactly($expectedHeaderMutations))
                ->method('withHeader')
                ->willReturnCallback(
                    static function (string $name, mixed $value) use (
                        $response,
                        $correlationId,
                        $requestId,
                        $requestIdEnabled,
                        $scenario,
                        &$requestIndex,
                        &$finalizations,
                        &$requestIdFinalizations,
                        &$emittedCorrelationId,
                        &$emittedRequestId,
                    ): ResponseInterface {
                        if ($name === 'X-Correlation-Id') {
                            self::assertTrue(
                                $emittedCorrelationId === null,
                                'duplicate-correlation-header-mutation',
                            );
                            self::assertTrue(
                                $emittedRequestId === null,
                                'request-id-finalized-before-correlation',
                            );
                            self::assertTrue(
                                $value === $correlationId($requestIndex),
                                'correlation-header-value-mismatch',
                            );

                            if ($requestIndex > 0) {
                                self::assertTrue(
                                    $value !== $correlationId($requestIndex - 1),
                                    'previous-request-correlation-reused',
                                );
                            }

                            ++$finalizations;

                            if ($scenario === 3) {
                                throw new \RuntimeException('synthetic-finalization-failure');
                            }

                            $emittedCorrelationId = $value;

                            return $response;
                        }

                        self::assertTrue($name === 'X-Request-Id', 'unexpected-identity-header-name');
                        self::assertTrue($requestIdEnabled, 'disabled-request-id-header-mutation');
                        self::assertTrue(
                            $scenario === 0 || $scenario === 1,
                            'request-id-finalization-after-http-failure',
                        );
                        self::assertTrue(
                            $emittedCorrelationId === $correlationId($requestIndex),
                            'request-id-finalized-before-successful-correlation',
                        );
                        self::assertTrue(
                            $emittedRequestId === null,
                            'duplicate-request-id-header-mutation',
                        );
                        self::assertTrue(
                            $value === $requestId($requestIndex),
                            'request-id-header-value-mismatch',
                        );

                        if ($requestIndex > 0) {
                            self::assertTrue(
                                $value !== $requestId($requestIndex - 1),
                                'previous-request-id-header-reused',
                            );
                        }

                        ++$requestIdFinalizations;
                        $emittedRequestId = $value;

                        return $response;
                    },
                );

            $response->method('getHeaderLine')->willReturnCallback(
                static function (string $name) use (
                    &$emittedCorrelationId,
                    &$emittedRequestId,
                ): string {
                    return match ($name) {
                        'X-Correlation-Id' => $emittedCorrelationId ?? '',
                        'X-Request-Id' => $emittedRequestId ?? '',
                        default => '',
                    };
                },
            );

            $activeResponse = $response;

            $caught = null;
            $returned = null;
            try {
                $returned = $kernel->handle($request);
            } catch (\RuntimeException $exception) {
                $caught = $exception;
            }
            if ($scenario === 0 || $scenario === 1) {
                self::assertTrue($caught === null, 'unexpected-http-failure:' . $requestIndex);
                self::assertTrue($returned === $response, 'http-response-identity-mismatch');
                self::assertTrue(
                    $response->getStatusCode() === ($scenario === 0 ? 200 : 503),
                    'http-response-status-mismatch',
                );
                self::assertTrue(
                    $response->getHeaderLine('X-Correlation-Id') === $correlationId($requestIndex),
                    'finalized-correlation-header-mismatch',
                );
                self::assertTrue(
                    $response->getHeaderLine('X-Request-Id')
                    === ($requestIdEnabled ? $requestId($requestIndex) : ''),
                    'finalized-request-id-header-mismatch',
                );

                if ($requestIdEnabled) {
                    ++$expectedRequestIdFinalizations;
                }
            } else {
                $reason = $scenario === 2 ? 'synthetic-pipeline-failure' : 'synthetic-finalization-failure';
                self::assertTrue(
                    $caught !== null && $caught::class === \RuntimeException::class && $caught->getMessage() === $reason,
                    'unexpected-http-throwable:' . $requestIndex,
                );
                self::assertTrue($returned === null, 'failed-http-returned-response');
                self::assertTrue(
                    $emittedRequestId === null,
                    'request-id-header-emitted-after-http-failure',
                );
            }
            if ($scenario !== 2) {
                ++$expectedFinalizations;
            }
            self::assertSame($requestIndex + 1, $beforeCalls, 'http-before-calls-mismatch');
            self::assertSame($requestIndex + 1, $terminalCalls, 'http-terminal-calls-mismatch');
            self::assertSame($expectedFinalizations, $finalizations, 'http-finalizations-mismatch');
            self::assertSame(
                $expectedRequestIdFinalizations,
                $requestIdFinalizations,
                'http-request-id-finalizations-mismatch',
            );
            self::assertSame($requestIndex + 1, $after->calls, 'http-after-hook-count-mismatch');
            self::assertTrue(
                $after->outcome === match ($scenario) {
                    0 => 'success',
                    1 => 'handled_error',
                    default => 'fatal_error',
                },
                'http-outcome-mismatch:' . $requestIndex,
            );
            self::assertTrue(
                $after->observedRequestIndex === $requestIndex,
                'http-after-before-reset-mismatch:' . $requestIndex,
            );
            self::assertTrue(
                $after->observedRequestIdMatches,
                'http-after-request-id-before-reset-mismatch:' . $requestIndex,
            );
            self::assertSame($requestIndex + 1, $probe->calls, 'http-reset-count-mismatch');
            self::assertNull($probe->requestIndex, 'http-resettable-state-not-cleared');
            self::assertFalse(
                $context->has(ContextKeys::REQUEST_ID),
                'http-request-id-not-cleared-after-reset',
            );
            foreach (ContextKeys::all() as $key) {
                self::assertFalse($context->has($key), 'http-context-not-cleared:' . $key);
            }
        }

        self::assertSame(1000, $beforeCalls);
        self::assertSame(1000, $terminalCalls);
        self::assertSame(1000, $after->calls);
        self::assertSame(1000, $probe->calls);
        self::assertSame(750, $finalizations);
        self::assertSame($requestIdEnabled ? 500 : 0, $requestIdFinalizations);
        self::assertSame([250, 250, 250, 250], $scenarioCounts);
    }

    /** @return iterable<string, array{bool}> */
    public static function longRunningRequestIdConfigurations(): iterable
    {
        yield 'request-id-disabled' => [false];
        yield 'request-id-enabled' => [true];
    }

    private static function handle(): UnitOfWorkHandle
    {
        return new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => self::CORRELATION,
        ]);
    }

    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        ?MeterPortInterface $meter = null,
    ): HttpKernel {
        $correlation = new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            }
        };

        $requestIds = new RequestIdPolicy(
            new ContextStore(),
            new UlidGenerator(),
            false,
            'X-Request-Id',
            false,
            128,
        );

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder($meter ?? new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );
    }
}
