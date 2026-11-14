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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class NoopTracingAndMetricsNeverThrowInHttpEnvelopeContractTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testFoundationNoopTracerMeterAndLoggerPreserveSuccessfulHttpCompletion(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->with('X-Correlation-Id', self::CORRELATION)->willReturn($response);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $runtime = $this->runtime('success', null);

        $kernel = $this->kernel($runtime, $terminal, new NoopMeter(), new NoopLogger(), new NoopTracer());
        self::assertSame($response, $kernel->handle($request));
    }

    public function testAllOptionalObservabilityFailuresRemainIndependentOfSuccessfulResponse(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);
        $response->method('withHeader')->willReturn($response);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);

        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException(new \RuntimeException('counter-failed'));
        $meter->expects(self::once())->method('observe')->willThrowException(new \RuntimeException('observe-failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('logger-failed'));
        $span = $this->createMock(SpanInterface::class);
        $span->method('setAttribute')->willThrowException(new \RuntimeException('span-attribute-failed'));
        $span->expects(self::once())->method('end')->willThrowException(new \RuntimeException('span-end-failed'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->with('http.request', ['method' => 'POST'])
            ->willReturn($span);

        $kernel = $this->kernel($this->runtime('handled_error', null), $terminal, $meter, $logger, $tracer);
        self::assertSame($response, $kernel->handle($request));
    }

    public function testOptionalFailuresCannotReplaceThePrimaryApplicationThrowable(): void
    {
        $failure = new \RuntimeException('private-downstream-failure');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('PATCH');
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willThrowException($failure);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException(new \RuntimeException('metric-failed'));
        $meter->expects(self::once())->method('observe')->willThrowException(new \RuntimeException('duration-failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('logging-failed'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->willThrowException(new \RuntimeException('start-failed'));

        $kernel = $this->kernel($this->runtime('fatal_error', $failure), $terminal, $meter, $logger, $tracer);
        try {
            $kernel->handle($request);
            self::fail('Original Throwable must be propagated to the caller.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testHttpNoopServicesAreNotMiddlewareOrLifecycleRegistries(): void
    {
        $http = require __DIR__ . '/../../config/http.php';
        $builder = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($builder, new ContainerDefinitionContext(['http' => $http]));
        $observable = [
            HttpRequestMetricsRecorder::class,
            HttpAccessLogWriter::class,
            HttpRequestSpanRecorder::class,
            HttpResponseFinalizer::class,
        ];
        $actual = [];
        $dependencies = [];

        foreach ($builder->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                self::assertNotContains($operation['serviceId'], $observable);
            }

            if (($operation['kind'] ?? null) === 'service.class') {
                if (\in_array($operation['id'] ?? '', $observable, true)) {
                    $actual[] = $operation['id'];
                    $dependencies[$operation['id']] = $operation['arguments'];
                }
            }
        }

        self::assertEqualsCanonicalizing($observable, $actual);

        $reference = static fn (string $id): array => ContainerValueReference::service($id)->toArray();

        self::assertSame([
            HttpResponseFinalizer::class => [
                $reference(CorrelationIdProviderInterface::class),
                true,
                'X-Correlation-Id',
            ],
            HttpRequestMetricsRecorder::class => [$reference(MeterPortInterface::class), true],
            HttpAccessLogWriter::class => [$reference(LoggerInterface::class), true],
            HttpRequestSpanRecorder::class => [$reference(TracerPortInterface::class), true],
        ], $dependencies);

        foreach (
            [
                HttpResponseFinalizer::class => ['correlationIds', 'enabled', 'headerName'],
                HttpRequestMetricsRecorder::class => ['enabled', 'meter'],
                HttpAccessLogWriter::class => ['enabled', 'logger'],
                HttpRequestSpanRecorder::class => ['enabled', 'tracer'],
            ] as $class => $expectedProperties
        ) {
            $properties = \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                new \ReflectionClass($class)->getProperties(),
            );

            \sort($properties);
            self::assertSame($expectedProperties, $properties);
        }

        self::assertFalse(
            \is_subclass_of(HttpResponseFinalizer::class, MiddlewareInterface::class),
        );
        self::assertFalse(
            \is_subclass_of(HttpRequestMetricsRecorder::class, MiddlewareInterface::class),
        );
        self::assertFalse(\is_subclass_of(HttpAccessLogWriter::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(HttpRequestSpanRecorder::class, MiddlewareInterface::class));
    }

    private function runtime(string $outcome, ?\Throwable $failure): KernelRuntimeInterface
    {
        $handle = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => self::CORRELATION,
        ]);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with(
            self::identicalTo($handle),
            $outcome,
            $failure === null ? null : self::identicalTo($failure),
        )->willReturn([]);

        return $runtime;
    }

    private function kernel(
        KernelRuntimeInterface $runtime,
        RequestHandlerInterface $terminal,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        TracerPortInterface $tracer,
    ): HttpKernel {
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::CORRELATION);

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([], $terminal),
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
        );
    }
}
