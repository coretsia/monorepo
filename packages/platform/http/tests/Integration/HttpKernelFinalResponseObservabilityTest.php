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

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\TraceContextMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class HttpKernelFinalResponseObservabilityTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string REQUEST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const string UPSTREAM_REQUEST_ID = 'trusted-upstream-request-42';
    private const string REJECTED_REQUEST_ID = 'rejected-request-id,invalid';
    private const string REQUEST_HEADER = 'X-Request-Id';

    #[DataProvider('requestIdEnablement')]
    public function testProviderWiresFourCompletionServicesOutsideAllHttpMiddlewareSlots(
        bool $requestIdEnabled,
    ): void {
        $http = require __DIR__ . '/../../config/http.php';
        $http['request_id']['enabled'] = $requestIdEnabled;

        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(['http' => $http]));
        $operations = $definitions->build()->toDescriptorStream();

        $ref = static fn (string $id): array => ContainerValueReference::service($id)->toArray();
        $expected = [
            RequestIdPolicy::class => [
                $ref(ContextStore::class),
                $ref(UlidGenerator::class),
                $requestIdEnabled,
                self::REQUEST_HEADER,
                false,
                128,
            ],
            HttpResponseFinalizer::class => [
                $ref(CorrelationIdProviderInterface::class),
                true,
                'X-Correlation-Id',
                $ref(RequestIdPolicy::class),
            ],
            HttpRequestMetricsRecorder::class => [$ref(MeterPortInterface::class), true],
            HttpAccessLogWriter::class => [$ref(LoggerInterface::class), true],
            HttpRequestSpanRecorder::class => [$ref(TracerPortInterface::class), true],
            HttpKernel::class => [
                $ref(KernelRuntimeInterface::class),
                $ref(Stopwatch::class),
                $ref(Pipeline::class),
                $ref(HttpResponseFinalizer::class),
                $ref(HttpRequestMetricsRecorder::class),
                $ref(HttpAccessLogWriter::class),
                $ref(HttpRequestSpanRecorder::class),
                $ref(RequestIdPolicy::class),
            ],
        ];

        $actual = [];
        $completionServices = [];
        $middlewareTags = [];

        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                self::assertNotContains($operation['serviceId'], \array_keys($expected));

                if (\str_starts_with($operation['tag'], 'http.middleware.')) {
                    $middlewareTags[] = $operation;
                }
            }

            if (($operation['kind'] ?? null) !== 'service.class') {
                continue;
            }

            $id = $operation['id'];
            if (isset($expected[$id])) {
                $actual[$id] = $operation['arguments'];
                self::assertSame($id, $operation['class']);
                self::assertTrue($operation['shared']);
            }

            if (
                $id === HttpResponseFinalizer::class
                || \str_starts_with($id, 'Coretsia\\Platform\\Http\\Observability\\')
            ) {
                $completionServices[] = $id;
            }
        }

        self::assertSame($expected, $actual);
        self::assertCount(6, $middlewareTags);
        self::assertSame([
            HttpResponseFinalizer::class,
            HttpRequestMetricsRecorder::class,
            HttpAccessLogWriter::class,
            HttpRequestSpanRecorder::class,
        ], $completionServices);

        $http['request_id']['enabled'] = !$requestIdEnabled;
        $opposite = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($opposite, new ContainerDefinitionContext(['http' => $http]));

        foreach ($operations as &$operation) {
            if (
                ($operation['kind'] ?? null) === 'service.class'
                && $operation['id'] === RequestIdPolicy::class
            ) {
                $operation['arguments'][2] = !$requestIdEnabled;
            }
        }
        unset($operation);

        self::assertSame($operations, $opposite->build()->toDescriptorStream());
    }

    /** @return iterable<string, array{bool}> */
    public static function requestIdEnablement(): iterable
    {
        yield 'request-id-disabled' => [false];
        yield 'request-id-enabled' => [true];
    }

    #[DataProvider('completedStatusCases')]
    public function testCompletedResponsesObserveOnlyFinalizedStatusWithOneSafeRecord(
        int $status,
        string $method,
        string $expectedMethod,
        bool $requestIdEnabled,
        bool $acceptValidUpstream,
    ): void {
        $events = [];
        $outcome = $status < 400 ? 'success' : 'handled_error';
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), $outcome, null)
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'after';

                return [];
            });
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getMethod')->willReturn($method);
        $upstream = $acceptValidUpstream ? self::UPSTREAM_REQUEST_ID : self::REJECTED_REQUEST_ID;
        $selectedRequestId = $acceptValidUpstream ? self::UPSTREAM_REQUEST_ID : self::REQUEST_ID;

        $request->expects($requestIdEnabled ? self::once() : self::never())
            ->method('getHeader')
            ->willReturnCallback(static function (string $name) use ($upstream): array {
                self::assertTrue($name === self::REQUEST_HEADER, 'unexpected-request-header-read');

                return [$upstream];
            });

        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($requestIdEnabled && !$acceptValidUpstream ? self::once() : self::never())
            ->method('generate')
            ->willReturn(self::REQUEST_ID);

        $requestIds = self::requestIds($requestIdEnabled, $generator, true);
        $intermediate = $this->createMock(ResponseInterface::class);
        $correlated = $this->createMock(ResponseInterface::class);
        $final = $requestIdEnabled ? $this->createMock(ResponseInterface::class) : $correlated;

        $intermediate->expects(self::once())->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use (
                &$events,
                $correlated,
            ): ResponseInterface {
                self::assertTrue($name === 'X-Correlation-Id', 'correlation-header-name-mismatch');
                self::assertTrue($value === self::CORRELATION, 'correlation-header-value-mismatch');
                $events[] = 'finalize';

                return $correlated;
            });

        $intermediate->expects(self::never())->method('getStatusCode');

        if ($requestIdEnabled) {
            $correlated->expects(self::never())->method('getStatusCode');
            $correlated->expects(self::once())->method('withHeader')
                ->willReturnCallback(static function (string $name, mixed $value) use (
                    &$events,
                    $final,
                    $selectedRequestId,
                ): ResponseInterface {
                    self::assertTrue($name === self::REQUEST_HEADER, 'request-id-header-name-mismatch');
                    self::assertTrue($value === $selectedRequestId, 'request-id-header-value-mismatch');
                    $events[] = 'finalize-request-id';

                    return $final;
                });
        } else {
            $correlated->expects(self::never())->method('withHeader');
        }

        $final->expects(self::once())->method('getStatusCode')->willReturn($status);
        if ($requestIdEnabled) {
            $final->expects(self::never())->method('withHeader');
        }
        $terminal = $this->createMock(RequestHandlerInterface::class);
        if ($status === 403) {
            $terminal->expects(self::never())->method('handle');
            $shortCircuit = $this->createMock(MiddlewareInterface::class);
            $shortCircuit->expects(self::once())->method('process')
                ->willReturnCallback(static function (
                    ServerRequestInterface $incoming,
                    RequestHandlerInterface $next,
                ) use ($request, &$events, $intermediate): ResponseInterface {
                    self::assertTrue($incoming === $request, 'http-request-identity-mismatch');
                    self::assertInstanceOf(RequestHandlerInterface::class, $next);
                    $events[] = 'pipeline';

                    return $intermediate;
                });
            $pipeline = new Pipeline([$shortCircuit], $terminal);
        } else {
            $terminal->expects(self::once())->method('handle')
                ->willReturnCallback(
                    static function (ServerRequestInterface $incoming) use (
                        $request,
                        &$events,
                        $intermediate,
                    ): ResponseInterface {
                        self::assertTrue($incoming === $request, 'http-request-identity-mismatch');
                        $events[] = 'pipeline';

                        return $intermediate;
                    },
                );
            $pipeline = new Pipeline([], $terminal);
        }
        $labels = ['method' => $expectedMethod, 'status' => $status, 'outcome' => $outcome];
        $measuredDuration = null;
        $counters = [];
        $observations = [];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->willReturnCallback(static function (string $name, int $delta, array $values) use (
                &$events,
                &$counters,
            ): void {
                $counters[] = [$name, $delta, $values];
                $events[] = 'counter';
            });
        $meter->expects(self::once())->method('observe')
            ->willReturnCallback(static function (string $name, int $duration, array $values) use (
                &$events,
                &$measuredDuration,
                &$observations,
            ): void {
                $measuredDuration = $duration;
                $observations[] = [$name, $duration, $values];
                $events[] = 'duration';
            });
        $loggedRecords = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$events, &$loggedRecords): void {
                $events[] = 'log';
                $loggedRecords[] = [$event, $context];
            });
        $span = $this->createMock(SpanInterface::class);
        $attributes = [];
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(static function (string $key, mixed $value) use (
                &$events,
                &$attributes,
            ): void {
                $attributes[] = [$key, $value];
                $events[] = 'span:' . $key;
            });
        $span->expects(self::once())->method('end')->willReturnCallback(static function () use (&$events): void {
            $events[] = 'span:end';
        });
        $span->expects(self::never())->method('recordException');
        $startedSpans = [];
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->willReturnCallback(static function (string $name, array $values) use (
                &$events,
                &$startedSpans,
                $span,
            ): SpanInterface {
                $startedSpans[] = [$name, $values];
                $events[] = 'span:start';

                return $span;
            });

        $kernel = self::kernel($runtime, $pipeline, $meter, $logger, $tracer, requestIds: $requestIds);
        self::assertSame($final, $kernel->handle($request));

        self::assertTrue(
            $counters === [['http.request_total', 1, $labels]],
            'http-counter-record-mismatch',
        );
        self::assertTrue(
            \is_int($measuredDuration) && $measuredDuration >= 0,
            'http-duration-invalid',
        );
        self::assertTrue(
            $observations === [['http.request_duration_ms', $measuredDuration, $labels]],
            'http-duration-record-mismatch',
        );
        self::assertTrue(
            $startedSpans === [['http.request', ['method' => $expectedMethod]]],
            'http-span-start-mismatch',
        );
        self::assertTrue(
            $attributes === [
                ['method', $expectedMethod],
                ['status', $status],
                ['outcome', $outcome],
            ],
            'http-span-attributes-mismatch',
        );
        self::assertTrue(
            $loggedRecords === [
                [
                    'http.request.completed',
                    [
                        'method' => $expectedMethod,
                        'status' => $status,
                        'outcome' => $outcome,
                        'duration_ms' => $measuredDuration,
                        'route_template' => 'unknown',
                        'correlation_id' => self::CORRELATION,
                    ],
                ],
            ],
            'http-access-log-record-mismatch',
        );

        $expectedEvents = [
            'span:start',
            'pipeline',
            'finalize',
        ];

        if ($requestIdEnabled) {
            $expectedEvents[] = 'finalize-request-id';
        }

        self::assertTrue(
            $events === [
                ...$expectedEvents,
                'counter',
                'duration',
                'log',
                'span:method',
                'span:status',
                'span:outcome',
                'span:end',
                'after',
            ],
            'http-completion-order-mismatch',
        );

        self::assertTrue(
            $requestIds->current() === ($requestIdEnabled ? $selectedRequestId : null),
            'http-request-id-completion-mismatch',
        );
    }

    /** @return iterable<string, array{int, string, string, bool, bool}> */
    public static function completedStatusCases(): iterable
    {
        $cases = [
            'normal-200' => [200, 'GET', 'GET'],
            'short-circuit-403' => [403, 'HEAD', 'HEAD'],
            'mapped-problem-400' => [400, 'POST', 'POST'],
            'mapped-problem-413' => [413, 'PATCH', 'PATCH'],
            'emergency-problem-500' => [500, 'PUT', 'PUT'],
            'untrusted-method-429' => [429, 'GET /private?token=secret', 'OTHER'],
            'fallback-503' => [503, 'webdav', 'OTHER'],
        ];

        foreach ($cases as $name => $case) {
            yield $name . '-request-id-disabled' => [...$case, false, false];
            yield $name . '-request-id-generated' => [...$case, true, false];
            yield $name . '-request-id-accepted' => [...$case, true, true];
        }
    }

    #[DataProvider('realProblemDetailsCases')]
    public function testRealProblemDetailsBoundaryFeedsFinalizedStatusToHttpObservability(
        string $failureKind,
        int $expectedStatus,
        bool $requestIdEnabled,
    ): void {
        $failure = match ($failureKind) {
            'bad-json' => BadJsonException::invalid(),
            'body-too-large' => BodyTooLargeException::tooLarge(),
            'emergency' => new \RuntimeException('private-primary-error'),
        };
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'handled_error', null)->willReturn([]);
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $handling = $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure));
        if ($failureKind === 'emergency') {
            $handling->willThrowException(new \RuntimeException('mapper-not-available'));
        } else {
            $descriptor = new HttpExceptionMapper()->map($failure);
            self::assertNotSame(null, $descriptor);
            $handling->willReturn($descriptor);
        }
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $headers = [];
        $response->expects(self::exactly($requestIdEnabled ? 4 : 3))->method('withHeader')
            ->willReturnCallback(
                static function (string $name, string $value) use (&$headers, $response): ResponseInterface {
                    $headers[$name] = $value;

                    return $response;
                },
            );
        $response->expects(self::once())->method('withBody')->willReturn($response);
        $responseFactories = $this->createMock(ResponseFactoryInterface::class);
        $responseFactories->expects(self::once())->method('createResponse')
            ->with($expectedStatus)->willReturn($response);
        $stream = $this->createStub(StreamInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $publicBodies = [];
        $streams->expects(self::once())->method('createStream')
            ->willReturnCallback(static function (string $body) use (&$publicBodies, $stream): StreamInterface {
                $publicBodies[] = $body;

                return $stream;
            });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willThrowException($failure);
        $boundary = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responseFactories,
            $streams,
        );
        $labels = ['method' => 'GET', 'status' => $expectedStatus, 'outcome' => 'handled_error'];
        $counters = [];
        $observations = [];
        $records = [];
        $attributes = [];
        $startedSpans = [];

        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->willReturnCallback(
                static function (string $name, int $delta, array $values) use (&$counters): void {
                    $counters[] = [$name, $delta, $values];
                },
            );
        $meter->expects(self::once())->method('observe')
            ->willReturnCallback(
                static function (string $name, int $duration, array $values) use (&$observations): void {
                    $observations[] = [$name, $duration, $values];
                },
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->willReturnCallback(
                static function (string $event, array $context) use (&$records): void {
                    $records[] = [$event, $context];
                },
            );

        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(
                static function (string $key, mixed $value) use (&$attributes): void {
                    $attributes[] = [$key, $value];
                },
            );
        $span->expects(self::once())->method('end');
        $span->expects(self::never())->method('recordException');

        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->willReturnCallback(
                static function (string $name, array $values) use (&$startedSpans, $span): SpanInterface {
                    $startedSpans[] = [$name, $values];

                    return $span;
                },
            );
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        self::assertSame(
            $response,
            self::kernel(
                $runtime,
                new Pipeline([$boundary], $terminal),
                $meter,
                $logger,
                $tracer,
                requestIds: self::requestIds($requestIdEnabled),
            )->handle($request),
        );
        $expectedHeaders = [
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Correlation-Id' => self::CORRELATION,
        ];

        if ($requestIdEnabled) {
            $expectedHeaders[self::REQUEST_HEADER] = self::REQUEST_ID;
        }

        self::assertTrue($headers === $expectedHeaders, 'problem-details-response-headers-mismatch');
        self::assertTrue(
            $counters === [['http.request_total', 1, $labels]],
            'problem-details-counter-mismatch',
        );

        self::assertCount(1, $observations);
        $duration = $observations[0][1];

        self::assertTrue(
            \is_int($duration) && $duration >= 0,
            'problem-details-duration-invalid',
        );
        self::assertTrue(
            $observations === [['http.request_duration_ms', $duration, $labels]],
            'problem-details-duration-mismatch',
        );
        self::assertTrue(
            $records === [
                [
                    'http.request.completed',
                    [
                        'method' => 'GET',
                        'status' => $expectedStatus,
                        'outcome' => 'handled_error',
                        'duration_ms' => $duration,
                        'route_template' => 'unknown',
                        'correlation_id' => self::CORRELATION,
                    ],
                ],
            ],
            'problem-details-access-log-mismatch',
        );
        self::assertTrue(
            $startedSpans === [['http.request', ['method' => 'GET']]],
            'problem-details-span-start-mismatch',
        );
        self::assertTrue(
            $attributes === [
                ['method', 'GET'],
                ['status', $expectedStatus],
                ['outcome', 'handled_error'],
            ],
            'problem-details-span-attributes-mismatch',
        );
        self::assertCount(1, $publicBodies);
        $public = \json_decode($publicBodies[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expectedStatus, $public['status']);
        self::assertSame(['status', 'title', 'type'], \array_keys($public));
        self::assertFalse(\str_contains($publicBodies[0], 'private-primary-error'));
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function realProblemDetailsCases(): iterable
    {
        foreach ([false, true] as $enabled) {
            $suffix = $enabled ? '-request-id-enabled' : '-request-id-disabled';

            yield 'mapped-json-400' . $suffix => ['bad-json', 400, $enabled];
            yield 'mapped-payload-413' . $suffix => ['body-too-large', 413, $enabled];
            yield 'emergency-500' . $suffix => ['emergency', 500, $enabled];
        }
    }

    #[DataProvider('disabledFeatures')]
    public function testFeatureFlagSuppressesOnlyItsOwnCompletionChannel(
        string $disabled,
        bool $requestIdEnabled,
    ): void {
        $flags = [
            'correlation' => true,
            'metrics' => true,
            'access_log' => true,
            'tracing' => true,
            'request_id' => $requestIdEnabled,
        ];
        $flags[$disabled] = false;

        $http = require __DIR__ . '/../../config/http.php';
        $http[$disabled]['enabled'] = false;
        $http['request_id']['enabled'] = $flags['request_id'];
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(['http' => $http]));
        $expectedFlags = [
            HttpResponseFinalizer::class => $flags['correlation'],
            HttpRequestMetricsRecorder::class => $flags['metrics'],
            HttpAccessLogWriter::class => $flags['access_log'],
            HttpRequestSpanRecorder::class => $flags['tracing'],
            TraceContextMiddleware::class => $flags['tracing'],
            RequestIdPolicy::class => $flags['request_id'],
        ];
        $effectiveFlags = [];
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'service.class'
                && \array_key_exists($operation['id'], $expectedFlags)
            ) {
                $index = $operation['id'] === RequestIdPolicy::class ? 2 : 1;
                $effectiveFlags[$operation['id']] = $operation['arguments'][$index] ?? null;
            }
        }
        \ksort($expectedFlags);
        \ksort($effectiveFlags);
        self::assertSame($expectedFlags, $effectiveFlags);

        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with($handle, 'success', null)->willReturn([]);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $response = $this->identityResponse(200, $flags['correlation'], $flags['request_id']);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects($flags['metrics'] ? self::once() : self::never())->method('increment');
        $meter->expects($flags['metrics'] ? self::once() : self::never())->method('observe');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($flags['access_log'] ? self::once() : self::never())->method('info');
        $span = $this->createMock(SpanInterface::class);
        $span->expects($flags['tracing'] ? self::once() : self::never())->method('end');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects($flags['tracing'] ? self::once() : self::never())->method('startSpan')->willReturn($span);
        $kernel = self::kernel($runtime, new Pipeline([], $terminal), $meter, $logger, $tracer, $flags);
        self::assertSame($response, $kernel->handle($request));

        if ($disabled === 'tracing') {
            $registrations = [];
            foreach ($definitions->build()->toDescriptorStream() as $operation) {
                if (($operation['kind'] ?? null) === 'tag'
                    && ($operation['tag'] ?? null) === ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE
                    && ($operation['serviceId'] ?? null) === TraceContextMiddleware::class
                ) {
                    $registrations[] = $operation;
                }
            }

            self::assertCount(1, $registrations);
            $registration = $registrations[0];
            self::assertSame(930, $registration['priority']);
            self::assertSame(['toggle' => 'http.tracing.enabled'], $registration['meta']);

            $tags = new TagRegistry();
            $tags->add(
                $registration['tag'],
                $registration['serviceId'],
                $registration['priority'],
                $registration['meta'],
            );
            self::assertNotContains(TraceContextMiddleware::class, new HttpMiddlewareStackBuilder($tags)->build($http));

            $enabled = $http;
            $enabled['tracing']['enabled'] = true;
            self::assertSame([TraceContextMiddleware::class], new HttpMiddlewareStackBuilder($tags)->build($enabled));
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function disabledFeatures(): iterable
    {
        foreach (['correlation', 'metrics', 'access_log', 'tracing'] as $feature) {
            yield $feature . '-off-request-id-off' => [$feature, false];
            yield $feature . '-off-request-id-on' => [$feature, true];
        }

        yield 'request-id-off-other-features-on' => ['request_id', false];
    }

    #[DataProvider('requestIdEnablement')]
    public function testOptionalRecorderFailuresDoNotSuppressOtherRecordersOrAfterOrResponse(
        bool $requestIdEnabled,
    ): void {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with($handle, 'success', null)->willReturn([]);
        $response = $this->identityResponse(200, true, $requestIdEnabled);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturn($response);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException(new \RuntimeException('counter-failed'));
        $meter->expects(self::once())->method('observe')->willThrowException(new \RuntimeException('duration-failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('logging-failed'));
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willThrowException(new \RuntimeException('attribute-failed'));
        $span->expects(self::once())->method('end')->willThrowException(new \RuntimeException('span-end-failed'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->willReturn($span);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        self::assertSame(
            $response,
            self::kernel(
                $runtime,
                new Pipeline([], $terminal),
                $meter,
                $logger,
                $tracer,
                requestIds: self::requestIds($requestIdEnabled),
            )->handle($request),
        );
    }

    #[DataProvider('requestIdEnablement')]
    public function testSequentialRequestsKeepSpanHandlesAndCorrelationIdentitiesInvocationLocal(
        bool $requestIdEnabled,
    ): void {
        $secondCorrelation = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
        $secondRequestId = '01ARZ3NDEKTSV4RRFFQ69G5FAB';
        $first = self::handle();
        $second = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => $secondCorrelation,
            'type' => 'http',
            'uowId' => $secondCorrelation,
        ]);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::exactly(2))->method('beginUnitOfWork')
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
        $correlations = $this->createMock(CorrelationIdProviderInterface::class);
        $correlations->expects(self::exactly(2))->method('correlationId')
            ->willReturnOnConsecutiveCalls(self::CORRELATION, $secondCorrelation);
        $requests = [
            $this->createStub(ServerRequestInterface::class),
            $this->createStub(ServerRequestInterface::class),
        ];
        $requests[0]->method('getMethod')->willReturn('GET');
        $requests[1]->method('getMethod')->willReturn('POST');
        $responses = [
            $this->identityResponse(200, true, $requestIdEnabled),
            $this->identityResponse(
                503,
                true,
                $requestIdEnabled,
                $secondCorrelation,
                $secondRequestId,
            ),
        ];
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')
            ->willReturnOnConsecutiveCalls($responses[0], $responses[1]);
        $counters = [];
        $observations = [];
        $records = [];

        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::exactly(2))->method('increment')
            ->willReturnCallback(
                static function (string $name, int $delta, array $values) use (&$counters): void {
                    $counters[] = [$name, $delta, $values];
                },
            );
        $meter->expects(self::exactly(2))->method('observe')
            ->willReturnCallback(
                static function (string $name, int $duration, array $values) use (&$observations): void {
                    $observations[] = [$name, $duration, $values];
                },
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->willReturnCallback(
                static function (string $event, array $context) use (&$records): void {
                    $records[] = [$event, $context];
                },
            );
        $spans = [
            $this->createMock(SpanInterface::class),
            $this->createMock(SpanInterface::class),
        ];
        $attributes = [[], []];

        foreach ($spans as $index => $span) {
            $span->expects(self::exactly(3))->method('setAttribute')
                ->willReturnCallback(
                    static function (string $key, mixed $value) use (&$attributes, $index): void {
                        $attributes[$index][] = [$key, $value];
                    },
                );
            $span->expects(self::once())->method('end');
            $span->expects(self::never())->method('recordException');
        }
        $startedSpans = [];
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::exactly(2))->method('startSpan')
            ->willReturnCallback(
                static function (string $name, array $values) use (&$startedSpans, $spans): SpanInterface {
                    $startedSpans[] = [$name, $values];

                    return $spans[\count($startedSpans) - 1];
                },
            );
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($requestIdEnabled ? self::exactly(2) : self::never())
            ->method('generate')
            ->willReturnOnConsecutiveCalls(self::REQUEST_ID, $secondRequestId);

        $requestIds = self::requestIds($requestIdEnabled, $generator);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([], $terminal),
            new HttpResponseFinalizer($correlations, true, 'X-Correlation-Id', $requestIds),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
            $requestIds,
        );

        self::assertTrue(
            $kernel->handle($requests[0]) === $responses[0],
            'first-http-response-identity-mismatch',
        );
        self::assertTrue(
            $requestIds->current() === ($requestIdEnabled ? self::REQUEST_ID : null),
            'first-http-request-id-mismatch',
        );

        self::assertTrue(
            $kernel->handle($requests[1]) === $responses[1],
            'second-http-response-identity-mismatch',
        );
        self::assertTrue(
            $requestIds->current() === ($requestIdEnabled ? $secondRequestId : null),
            'second-http-request-id-mismatch',
        );
        self::assertTrue(
            $completed === [[$first, 'success', null], [$second, 'handled_error', null]],
            'sequential-http-completion-mismatch',
        );
        $expectedLabels = [
            ['method' => 'GET', 'status' => 200, 'outcome' => 'success'],
            ['method' => 'POST', 'status' => 503, 'outcome' => 'handled_error'],
        ];
        $expectedCorrelations = [self::CORRELATION, $secondCorrelation];

        self::assertCount(2, $counters);
        self::assertCount(2, $observations);
        self::assertCount(2, $records);
        self::assertTrue(
            $startedSpans === [
                ['http.request', ['method' => 'GET']],
                ['http.request', ['method' => 'POST']],
            ],
            'sequential-http-span-start-mismatch',
        );

        foreach ($expectedLabels as $index => $labels) {
            $duration = $observations[$index][1];

            self::assertTrue(
                $counters[$index] === ['http.request_total', 1, $labels],
                'sequential-http-counter-mismatch',
            );
            self::assertTrue(
                \is_int($duration) && $duration >= 0,
                'sequential-http-duration-invalid',
            );
            self::assertTrue(
                $observations[$index] === ['http.request_duration_ms', $duration, $labels],
                'sequential-http-duration-mismatch',
            );
            self::assertTrue(
                $records[$index] === [
                    'http.request.completed',
                    [
                        ...$labels,
                        'duration_ms' => $duration,
                        'route_template' => 'unknown',
                        'correlation_id' => $expectedCorrelations[$index],
                    ],
                ],
                'sequential-http-access-log-mismatch',
            );
            self::assertTrue(
                $attributes[$index] === [
                    ['method', $labels['method']],
                    ['status', $labels['status']],
                    ['outcome', $labels['outcome']],
                ],
                'sequential-http-span-attributes-mismatch',
            );
        }
    }

    #[DataProvider('requestIdEnablement')]
    public function testTracerStartFailureDoesNotSuppressResponseMetricsLogOrKernelCompletion(
        bool $requestIdEnabled,
    ): void {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with($handle, 'success', null)->willReturn([]);
        $response = $this->identityResponse(200, true, $requestIdEnabled);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $labels = ['method' => 'GET', 'status' => 200, 'outcome' => 'success'];
        $meter = $this->createMock(MeterPortInterface::class);
        $counterMatches = false;
        $durationMatches = false;

        $meter->expects(self::once())->method('increment')
            ->willReturnCallback(
                static function (string $name, int $delta, array $values) use (
                    $labels,
                    &$counterMatches,
                ): void {
                    $counterMatches = $name === 'http.request_total'
                        && $delta === 1
                        && $values === $labels;
                },
            );
        $meter->expects(self::once())->method('observe')
            ->willReturnCallback(
                static function (string $name, int $duration, array $values) use (
                    $labels,
                    &$durationMatches,
                ): void {
                    $durationMatches = $name === 'http.request_duration_ms'
                        && $duration >= 0
                        && $values === $labels;
                },
            );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->willThrowException(new \RuntimeException('optional-tracer-start-failure'));
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        self::assertSame(
            $response,
            self::kernel(
                $runtime,
                new Pipeline([], $terminal),
                $meter,
                $logger,
                $tracer,
                requestIds: self::requestIds($requestIdEnabled),
            )->handle($request),
        );
        self::assertTrue($counterMatches, 'http-counter-after-tracer-failure-mismatch');
        self::assertTrue($durationMatches, 'http-duration-after-tracer-failure-mismatch');
    }

    #[DataProvider('optionalRecorderFailureModes')]
    public function testFatalPipelineAndFailedFinalizationUseUnknownStatusForEveryRecorder(
        bool $optionalFailures,
    ): void {
        foreach (['pipeline', 'finalizer', 'request-id-preparation', 'request-id-finalization'] as $origin) {
            $failure = new \RuntimeException('original-' . $origin);
            $handle = self::handle();
            $runtime = $this->createMock(KernelRuntimeInterface::class);
            $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
            $completed = [];
            $runtime->expects(self::once())->method('afterUnitOfWork')
                ->willReturnCallback(
                    static function (UnitOfWorkHandle $handle, string $outcome, ?\Throwable $error) use (
                        &$completed,
                    ): array {
                        $completed[] = [$handle, $outcome, $error];

                        return [];
                    },
                );

            $requestIdEnabled = \str_starts_with($origin, 'request-id-');
            $generator = $this->createMock(IdGeneratorInterface::class);
            $generation = $generator->expects($requestIdEnabled ? self::once() : self::never())
                ->method('generate');

            if ($origin === 'request-id-preparation') {
                $generation->willThrowException($failure);
            } else {
                $generation->willReturn(self::REQUEST_ID);
            }

            $requestIds = self::requestIds($requestIdEnabled, $generator);

            $intermediate = $this->createMock(ResponseInterface::class);
            $intermediate->expects(self::never())->method('getStatusCode');
            $terminal = $this->createMock(RequestHandlerInterface::class);

            switch ($origin) {
                case 'request-id-preparation':
                    $terminal->expects(self::never())->method('handle');
                    $intermediate->expects(self::never())->method('withHeader');

                    break;

                case 'pipeline':
                    $terminal->expects(self::once())->method('handle')->willThrowException($failure);
                    $intermediate->expects(self::never())->method('withHeader');

                    break;

                case 'finalizer':
                    $terminal->expects(self::once())->method('handle')->willReturn($intermediate);
                    $intermediate->expects(self::once())->method('withHeader')
                        ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                            self::assertTrue($name === 'X-Correlation-Id', 'correlation-header-name-mismatch');
                            self::assertTrue($value === self::CORRELATION, 'correlation-header-value-mismatch');

                            throw $failure;
                        });

                    break;

                case 'request-id-finalization':
                    $correlated = $this->createMock(ResponseInterface::class);
                    $correlated->expects(self::never())->method('getStatusCode');
                    $terminal->expects(self::once())->method('handle')->willReturn($intermediate);
                    $intermediate->expects(self::once())->method('withHeader')
                        ->willReturnCallback(static function (string $name, mixed $value) use (
                            $correlated,
                        ): ResponseInterface {
                            self::assertTrue($name === 'X-Correlation-Id', 'correlation-header-name-mismatch');
                            self::assertTrue($value === self::CORRELATION, 'correlation-header-value-mismatch');

                            return $correlated;
                        });

                    $correlated->expects(self::once())->method('withHeader')
                        ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                            self::assertTrue($name === self::REQUEST_HEADER, 'request-id-header-name-mismatch');
                            self::assertTrue($value === self::REQUEST_ID, 'request-id-header-value-mismatch');

                            throw $failure;
                        });

                    break;
            }

            $labels = ['method' => 'OTHER', 'status' => 'unknown', 'outcome' => 'fatal_error'];
            $events = [];
            $counters = [];
            $observations = [];
            $records = [];
            $attributes = [];
            $startedSpans = [];

            $meter = $this->createMock(MeterPortInterface::class);
            $meter->expects(self::once())->method('increment')
                ->willReturnCallback(static function (string $name, int $delta, array $values) use (
                    &$events,
                    &$counters,
                    $optionalFailures,
                ): void {
                    $events[] = 'counter';
                    $counters[] = [$name, $delta, $values];

                    if ($optionalFailures) {
                        throw new \RuntimeException('counter-failed');
                    }
                });
            $meter->expects(self::once())->method('observe')
                ->willReturnCallback(static function (string $name, int $duration, array $values) use (
                    &$events,
                    &$observations,
                    $optionalFailures,
                ): void {
                    $events[] = 'duration';
                    $observations[] = [$name, $duration, $values];

                    if ($optionalFailures) {
                        throw new \RuntimeException('duration-failed');
                    }
                });

            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())->method('info')
                ->willReturnCallback(static function (string $event, array $context) use (
                    &$events,
                    &$records,
                    $optionalFailures,
                ): void {
                    $events[] = 'log';
                    $records[] = [$event, $context];

                    if ($optionalFailures) {
                        throw new \RuntimeException('logging-failed');
                    }
                });

            $span = $this->createMock(SpanInterface::class);
            $span->expects(self::exactly(3))->method('setAttribute')
                ->willReturnCallback(static function (string $key, mixed $value) use (
                    &$events,
                    &$attributes,
                    $optionalFailures,
                ): void {
                    $events[] = 'span:' . $key;
                    $attributes[] = [$key, $value];

                    if ($optionalFailures) {
                        throw new \RuntimeException('span-attribute-failed');
                    }
                });

            $span->expects(self::once())->method('end')
                ->willReturnCallback(static function () use (&$events, $optionalFailures): void {
                    $events[] = 'span:end';

                    if ($optionalFailures) {
                        throw new \RuntimeException('span-end-failed');
                    }
                });
            $span->expects(self::never())->method('recordException');

            $tracer = $this->createMock(TracerPortInterface::class);
            $tracer->expects(self::once())->method('startSpan')
                ->willReturnCallback(static function (string $name, array $values) use (
                    &$startedSpans,
                    $span,
                ): SpanInterface {
                    $startedSpans[] = [$name, $values];

                    return $span;
                });

            $request = $this->createStub(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn('attacker/private/path');

            try {
                self::kernel(
                    $runtime,
                    new Pipeline([], $terminal),
                    $meter,
                    $logger,
                    $tracer,
                    requestIds: $requestIds,
                )->handle($request);

                self::fail('fatal-http-returned-intermediate-response');
            } catch (\RuntimeException $actual) {
                self::assertTrue($actual === $failure, 'http-primary-throwable-replaced');
            }

            self::assertTrue(
                $completed === [[$handle, 'fatal_error', $failure]],
                'http-primary-throwable-completion-mismatch',
            );

            self::assertTrue(
                $counters === [['http.request_total', 1, $labels]],
                'fatal-http-counter-mismatch',
            );
            self::assertCount(1, $observations);

            $duration = $observations[0][1];
            self::assertTrue(\is_int($duration) && $duration >= 0, 'fatal-http-duration-invalid');
            self::assertTrue(
                $observations === [['http.request_duration_ms', $duration, $labels]],
                'fatal-http-duration-mismatch',
            );
            self::assertTrue(
                $records === [
                    [
                        'http.request.completed',
                        [
                            'method' => 'OTHER',
                            'status' => 'unknown',
                            'outcome' => 'fatal_error',
                            'duration_ms' => $duration,
                            'route_template' => 'unknown',
                            'correlation_id' => self::CORRELATION,
                        ],
                    ],
                ],
                'fatal-http-access-log-mismatch',
            );
            self::assertTrue(
                $startedSpans === [['http.request', ['method' => 'OTHER']]],
                'fatal-http-span-start-mismatch',
            );
            self::assertTrue(
                $attributes === [
                    ['method', 'OTHER'],
                    ['status', 'unknown'],
                    ['outcome', 'fatal_error'],
                ],
                'fatal-http-span-attributes-mismatch',
            );
            self::assertTrue(
                $events === [
                    'counter',
                    'duration',
                    'log',
                    'span:method',
                    'span:status',
                    'span:outcome',
                    'span:end',
                ],
                'fatal-http-completion-order-mismatch',
            );
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function optionalRecorderFailureModes(): iterable
    {
        yield 'recorders-successful' => [false];
        yield 'recorders-throwing' => [true];
    }

    public function testCompletionServicesCannotRetainRequestLocalIdentity(): void
    {
        $expectedProperties = [
            HttpResponseFinalizer::class => ['correlationIds', 'enabled', 'headerName', 'requestIds'],
            HttpRequestMetricsRecorder::class => ['meter', 'enabled'],
            HttpAccessLogWriter::class => ['logger', 'enabled'],
            HttpRequestSpanRecorder::class => ['tracer', 'enabled'],
            RequestIdPolicy::class => [
                'context',
                'generator',
                'enabled',
                'headerName',
                'acceptFromUpstream',
                'maxLength',
            ],
        ];

        foreach ($expectedProperties as $class => $expected) {
            $reflection = new \ReflectionClass($class);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());

            $properties = $reflection->getProperties();

            self::assertSame(
                $expected,
                \array_map(
                    static fn (\ReflectionProperty $property): string => $property->getName(),
                    $properties,
                ),
            );

            foreach ($properties as $property) {
                self::assertFalse($property->isStatic());
            }
        }
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

    private function identityResponse(
        int $status,
        bool $correlationEnabled,
        bool $requestIdEnabled,
        string $correlationId = self::CORRELATION,
        string $requestId = self::REQUEST_ID,
    ): ResponseInterface {
        $expectedHeaders = [];

        if ($correlationEnabled) {
            $expectedHeaders[] = ['X-Correlation-Id', $correlationId];
        }

        if ($requestIdEnabled) {
            $expectedHeaders[] = [self::REQUEST_HEADER, $requestId];
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->expects(self::never())->method('withAddedHeader');

        $index = 0;
        $response->expects(self::exactly(\count($expectedHeaders)))
            ->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use (
                $response,
                $expectedHeaders,
                &$index,
            ): ResponseInterface {
                self::assertTrue(isset($expectedHeaders[$index]), 'unexpected-identity-header-mutation');
                self::assertTrue($name === $expectedHeaders[$index][0], 'identity-header-name-mismatch');
                self::assertTrue($value === $expectedHeaders[$index][1], 'identity-header-value-mismatch');
                ++$index;

                return $response;
            });

        return $response;
    }

    private static function requestIds(
        bool $enabled,
        ?IdGeneratorInterface $generator = null,
        bool $acceptFromUpstream = false,
    ): RequestIdPolicy {
        return new RequestIdPolicy(
            new ContextStore(),
            $generator ?? new class() implements IdGeneratorInterface {
                public function generate(): string
                {
                    return '01ARZ3NDEKTSV4RRFFQ69G5FAA';
                }
            },
            $enabled,
            self::REQUEST_HEADER,
            $acceptFromUpstream,
            128,
        );
    }

    /** @param array<string, bool> $flags */
    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        TracerPortInterface $tracer,
        array $flags = [],
        ?RequestIdPolicy $requestIds = null,
    ): HttpKernel {
        $flags += [
            'correlation' => true,
            'metrics' => true,
            'access_log' => true,
            'tracing' => true,
            'request_id' => false,
        ];

        $requestIds ??= self::requestIds($flags['request_id']);
        $provider = new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            }
        };

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($provider, $flags['correlation'], 'X-Correlation-Id', $requestIds),
            new HttpRequestMetricsRecorder($meter, $flags['metrics']),
            new HttpAccessLogWriter($logger, $flags['access_log']),
            new HttpRequestSpanRecorder($tracer, $flags['tracing']),
            $requestIds,
        );
    }
}
