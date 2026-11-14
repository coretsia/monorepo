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

    public function testProviderWiresFourCompletionServicesOutsideAllHttpMiddlewareSlots(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(['http' => require __DIR__ . '/../../config/http.php']),
        );
        $operations = $definitions->build()->toDescriptorStream();
        $ref = static fn (string $id): array => ContainerValueReference::service($id)->toArray();
        $expected = [
            HttpResponseFinalizer::class => [$ref(CorrelationIdProviderInterface::class), true, 'X-Correlation-Id'],
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
            ],
        ];
        $actual = [];
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                self::assertNotContains($operation['serviceId'], \array_keys($expected));
            }
            if (($operation['kind'] ?? null) === 'service.class' && isset($expected[$operation['id']])) {
                $actual[$operation['id']] = $operation['arguments'];
            }
        }
        self::assertSame($expected, $actual);
    }

    #[DataProvider('completedStatusCases')]
    public function testCompletedResponsesObserveOnlyFinalizedStatusWithOneSafeRecord(
        int $status,
        string $method,
        string $expectedMethod,
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
        $intermediate = $this->createMock(ResponseInterface::class);
        $final = $this->createMock(ResponseInterface::class);
        $intermediate->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)
            ->willReturnCallback(static function () use (&$events, $final): ResponseInterface {
                $events[] = 'finalize';

                return $final;
            });
        $intermediate->expects(self::never())->method('getStatusCode');
        $final->expects(self::once())->method('getStatusCode')->willReturn($status);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        if ($status === 403) {
            $terminal->expects(self::never())->method('handle');
            $shortCircuit = $this->createMock(MiddlewareInterface::class);
            $shortCircuit->expects(self::once())->method('process')
                ->willReturnCallback(static function (
                    ServerRequestInterface $incoming,
                    RequestHandlerInterface $next,
                ) use ($request, &$events, $intermediate): ResponseInterface {
                    self::assertSame($request, $incoming);
                    self::assertInstanceOf(RequestHandlerInterface::class, $next);
                    $events[] = 'pipeline';

                    return $intermediate;
                });
            $pipeline = new Pipeline([$shortCircuit], $terminal);
        } else {
            $terminal->expects(self::once())->method('handle')->with($request)
                ->willReturnCallback(static function () use (&$events, $intermediate): ResponseInterface {
                    $events[] = 'pipeline';

                    return $intermediate;
                });
            $pipeline = new Pipeline([], $terminal);
        }
        $labels = ['method' => $expectedMethod, 'status' => $status, 'outcome' => $outcome];
        $measuredDuration = null;
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('http.request_total', 1, $labels)
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'counter';
            });
        $meter->expects(self::once())->method('observe')->with(
            'http.request_duration_ms',
            self::greaterThanOrEqual(0),
            $labels,
        )
            ->willReturnCallback(static function (string $name, int $duration, array $labels) use (
                &$events,
                &$measuredDuration,
            ): void {
                $measuredDuration = $duration;
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
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(
                static function (string $key, mixed $value) use (&$events, $expectedMethod, $status, $outcome): void {
                    self::assertSame(
                        ['method' => $expectedMethod, 'status' => $status, 'outcome' => $outcome][$key],
                        $value,
                    );
                    $events[] = 'span:' . $key;
                },
            );
        $span->expects(self::once())->method('end')->willReturnCallback(static function () use (&$events): void {
            $events[] = 'span:end';
        });
        $span->expects(self::never())->method('recordException');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->with('http.request', ['method' => $expectedMethod])
            ->willReturnCallback(static function () use (&$events, $span): SpanInterface {
                $events[] = 'span:start';

                return $span;
            });

        $kernel = self::kernel($runtime, $pipeline, $meter, $logger, $tracer);
        self::assertSame($final, $kernel->handle($request));
        self::assertSame([
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
        ], $loggedRecords);
        self::assertSame([
            'span:start',
            'pipeline',
            'finalize',
            'counter',
            'duration',
            'log',
            'span:method',
            'span:status',
            'span:outcome',
            'span:end',
            'after',
        ], $events);
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function completedStatusCases(): iterable
    {
        yield 'normal-200' => [200, 'GET', 'GET'];
        yield 'short-circuit-403' => [403, 'HEAD', 'HEAD'];
        yield 'mapped-problem-400' => [400, 'POST', 'POST'];
        yield 'mapped-problem-413' => [413, 'PATCH', 'PATCH'];
        yield 'emergency-problem-500' => [500, 'PUT', 'PUT'];
        yield 'untrusted-method-429' => [429, 'GET /private?token=secret', 'OTHER'];
        yield 'fallback-503' => [503, 'webdav', 'OTHER'];
    }

    #[DataProvider('realProblemDetailsCases')]
    public function testRealProblemDetailsBoundaryFeedsFinalizedStatusToHttpObservability(
        string $failureKind,
        int $expectedStatus,
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
        $response->expects(self::exactly(3))->method('withHeader')
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
        $meter = $this->createMock(MeterPortInterface::class);
        $labels = ['method' => 'GET', 'status' => $expectedStatus, 'outcome' => 'handled_error'];
        $meter->expects(self::once())->method('increment')->with('http.request_total', 1, $labels);
        $meter->expects(self::once())->method('observe')->with(
            'http.request_duration_ms',
            self::greaterThanOrEqual(0),
            $labels,
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'http.request.completed',
            self::callback(
                static fn (array $record): bool => $record['status'] === $expectedStatus
                    && $record['outcome'] === 'handled_error',
            ),
        );
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('end');
        $tracer = $this->createStub(TracerPortInterface::class);
        $tracer->method('startSpan')->willReturn($span);
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
            )->handle($request),
        );
        self::assertSame([
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Correlation-Id' => self::CORRELATION,
        ], $headers);
        self::assertCount(1, $publicBodies);
        $public = \json_decode($publicBodies[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expectedStatus, $public['status']);
        self::assertSame(['status', 'title', 'type'], \array_keys($public));
        self::assertFalse(\str_contains($publicBodies[0], 'private-primary-error'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function realProblemDetailsCases(): iterable
    {
        yield 'mapped-json-400' => ['bad-json', 400];
        yield 'mapped-payload-413' => ['body-too-large', 413];
        yield 'emergency-500' => ['emergency', 500];
    }

    #[DataProvider('disabledFeatures')]
    public function testFeatureFlagSuppressesOnlyItsOwnCompletionChannel(string $disabled): void
    {
        $flags = ['correlation' => true, 'metrics' => true, 'access_log' => true, 'tracing' => true];
        $flags[$disabled] = false;

        $http = require __DIR__ . '/../../config/http.php';
        $http[$disabled]['enabled'] = false;
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(['http' => $http]));
        $expectedFlags = [
            HttpResponseFinalizer::class => $flags['correlation'],
            HttpRequestMetricsRecorder::class => $flags['metrics'],
            HttpAccessLogWriter::class => $flags['access_log'],
            HttpRequestSpanRecorder::class => $flags['tracing'],
            TraceContextMiddleware::class => $flags['tracing'],
        ];
        $effectiveFlags = [];
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'service.class'
                && \array_key_exists($operation['id'], $expectedFlags)
            ) {
                $effectiveFlags[$operation['id']] = $operation['arguments'][1] ?? null;
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
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->expects($flags['correlation'] ? self::once() : self::never())->method('withHeader')
            ->willReturn($response);
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

    /** @return iterable<string, array{string}> */
    public static function disabledFeatures(): iterable
    {
        yield 'correlation-off' => ['correlation'];
        yield 'metrics-off' => ['metrics'];
        yield 'access-log-off' => ['access_log'];
        yield 'tracing-off' => ['tracing'];
    }

    public function testOptionalRecorderFailuresDoNotSuppressOtherRecordersOrAfterOrResponse(): void
    {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with($handle, 'success', null)->willReturn([]);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
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
            )->handle($request),
        );
    }

    public function testSequentialRequestsKeepSpanHandlesAndCorrelationIdentitiesInvocationLocal(): void
    {
        $secondCorrelation = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
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
            $this->createMock(ResponseInterface::class),
            $this->createMock(ResponseInterface::class),
        ];
        $responses[0]->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)->willReturn($responses[0]);
        $responses[1]->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', $secondCorrelation)->willReturn($responses[1]);
        $responses[0]->method('getStatusCode')->willReturn(200);
        $responses[1]->method('getStatusCode')->willReturn(503);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')
            ->willReturnOnConsecutiveCalls($responses[0], $responses[1]);
        $labels = [];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::exactly(2))->method('increment')
            ->willReturnCallback(static function (string $name, int $delta, array $values) use (&$labels): void {
                self::assertSame('http.request_total', $name);
                self::assertSame(1, $delta);
                $labels[] = $values;
            });
        $meter->expects(self::exactly(2))->method('observe')
            ->willReturnCallback(static function (string $name, int $milliseconds): void {
                self::assertSame('http.request_duration_ms', $name);
                self::assertTrue($milliseconds >= 0);
            });
        $records = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$records): void {
                self::assertSame('http.request.completed', $event);
                $records[] = $context;
            });
        $spans = [
            $this->createMock(SpanInterface::class),
            $this->createMock(SpanInterface::class),
        ];
        foreach ($spans as $span) {
            $span->expects(self::exactly(3))->method('setAttribute');
            $span->expects(self::once())->method('end');
            $span->expects(self::never())->method('recordException');
        }
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::exactly(2))->method('startSpan')
            ->willReturnOnConsecutiveCalls($spans[0], $spans[1]);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([], $terminal),
            new HttpResponseFinalizer($correlations, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
        );

        self::assertSame($responses[0], $kernel->handle($requests[0]));
        self::assertSame($responses[1], $kernel->handle($requests[1]));
        self::assertSame([[$first, 'success', null], [$second, 'handled_error', null]], $completed);
        self::assertSame([
            ['method' => 'GET', 'status' => 200, 'outcome' => 'success'],
            ['method' => 'POST', 'status' => 503, 'outcome' => 'handled_error'],
        ], $labels);
        self::assertSame([self::CORRELATION, $secondCorrelation], \array_column($records, 'correlation_id'));
    }

    public function testTracerStartFailureDoesNotSuppressResponseMetricsLogOrKernelCompletion(): void
    {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with($handle, 'success', null)->willReturn([]);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $labels = ['method' => 'GET', 'status' => 200, 'outcome' => 'success'];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('http.request_total', 1, $labels);
        $meter->expects(self::once())->method('observe')->with(
            'http.request_duration_ms',
            self::greaterThanOrEqual(0),
            $labels,
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
            )->handle($request),
        );
    }

    public function testFatalPipelineAndFailedFinalizationUseUnknownStatusForEveryRecorder(): void
    {
        foreach (['pipeline', 'finalizer'] as $origin) {
            $failure = new \RuntimeException('original-' . $origin);
            $handle = self::handle();
            $runtime = $this->createMock(KernelRuntimeInterface::class);
            $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
            $runtime->expects(self::once())->method('afterUnitOfWork')
                ->with($handle, 'fatal_error', self::identicalTo($failure))->willReturn([]);
            $intermediate = $this->createMock(ResponseInterface::class);
            $intermediate->method('withHeader')->willThrowException($failure);
            $intermediate->expects(self::never())->method('getStatusCode');
            $terminal = $this->createStub(RequestHandlerInterface::class);
            if ($origin === 'pipeline') {
                $terminal->method('handle')->willThrowException($failure);
            } else {
                $terminal->method('handle')->willReturn($intermediate);
            }
            $labels = ['method' => 'OTHER', 'status' => 'unknown', 'outcome' => 'fatal_error'];
            $meter = $this->createMock(MeterPortInterface::class);
            $meter->expects(self::once())->method('increment')->with('http.request_total', 1, $labels);
            $meter->expects(self::once())->method('observe')->with(
                'http.request_duration_ms',
                self::greaterThanOrEqual(0),
                $labels,
            );
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())->method('info')->with(
                'http.request.completed',
                self::callback(
                    static fn (array $context): bool => $context['method'] === 'OTHER'
                        && $context['status'] === 'unknown' && $context['outcome'] === 'fatal_error',
                ),
            );
            $span = $this->createMock(SpanInterface::class);
            $attributes = [];
            $span->expects(self::exactly(3))->method('setAttribute')
                ->willReturnCallback(static function (string $key, mixed $value) use (&$attributes): void {
                    $attributes[$key] = $value;
                });
            $span->expects(self::once())->method('end');
            $span->expects(self::never())->method('recordException');
            $tracer = $this->createStub(TracerPortInterface::class);
            $tracer->method('startSpan')->willReturn($span);
            $request = $this->createStub(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn('attacker/private/path');
            try {
                self::kernel($runtime, new Pipeline([], $terminal), $meter, $logger, $tracer)->handle($request);
                self::fail('Fatal HTTP request must not return an intermediate response.');
            } catch (\RuntimeException $actual) {
                self::assertSame($failure, $actual);
            }
            self::assertSame(['method' => 'OTHER', 'status' => 'unknown', 'outcome' => 'fatal_error'], $attributes);
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

    /** @param array<string, bool> $flags */
    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        TracerPortInterface $tracer,
        array $flags = [],
    ): HttpKernel {
        $flags += ['correlation' => true, 'metrics' => true, 'access_log' => true, 'tracing' => true];
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
            new HttpResponseFinalizer($provider, $flags['correlation'], 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter, $flags['metrics']),
            new HttpAccessLogWriter($logger, $flags['access_log']),
            new HttpRequestSpanRecorder($tracer, $flags['tracing']),
        );
    }
}
