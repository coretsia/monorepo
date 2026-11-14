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
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testNoopTracerCallbackPreservesOriginalThrowableIdentity(): void
    {
        $tracer = new NoopTracer();
        $failure = new \RuntimeException('original-application-failure');

        try {
            $tracer->inSpan('http.request', static fn (): never => throw $failure);
            self::fail('A noop tracer must never swallow a downstream Throwable.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testNoopObservabilityPreservesDownstreamThrowableThroughHttpKernel(): void
    {
        $failure = new \RuntimeException('sensitive-application-failure');
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with(
            self::identicalTo($handle),
            'fatal_error',
            self::identicalTo($failure),
        )->willReturn([]);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willThrowException($failure);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::once())->method('process')
            ->willReturnCallback(
                static fn (
                    ServerRequestInterface $request,
                    RequestHandlerInterface $next,
                ): ResponseInterface => $next->handle($request),
            );
        $pipeline = new Pipeline([$middleware], $terminal);

        try {
            self::kernel($runtime, $pipeline)->handle($request);
            self::fail('The original application exception must escape the HTTP envelope.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testNoopServicesDoNotCorruptRepeatedSuccessAndFailureOnOneKernel(): void
    {
        $failure = new \RuntimeException('second-request-failed');
        $firstHandle = self::handle();
        $secondHandle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::exactly(2))->method('beginUnitOfWork')->with('http')
            ->willReturnOnConsecutiveCalls($firstHandle, $secondHandle);
        $completed = [];
        $runtime->expects(self::exactly(2))->method('afterUnitOfWork')
            ->willReturnCallback(static function (
                UnitOfWorkHandle $handle,
                string $outcome,
                ?\Throwable $error = null,
                array $extensions = [],
            ) use (&$completed): array {
                $completed[] = [$handle, $outcome, $error];

                return [];
            });

        $first = $this->createStub(ServerRequestInterface::class);
        $second = $this->createStub(ServerRequestInterface::class);
        $first->method('getMethod')->willReturn('POST');
        $second->method('getMethod')->willReturn('POST');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(204);
        $response->method('withHeader')->willReturn($response);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')
            ->willReturnCallback(static function (ServerRequestInterface $request) use (
                $first,
                $second,
                $failure,
                $response,
            ): ResponseInterface {
                if ($request === $first) {
                    return $response;
                }
                if ($request === $second) {
                    throw $failure;
                }

                throw new \LogicException('unexpected-test-request');
            });

        $kernel = self::kernel($runtime, new Pipeline([], $terminal));
        self::assertSame($response, $kernel->handle($first));
        try {
            $kernel->handle($second);
            self::fail('Second request must propagate its original Throwable.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertCount(2, $completed);
        self::assertSame([$firstHandle, 'success', null], $completed[0]);
        self::assertSame([$secondHandle, 'fatal_error', $failure], $completed[1]);
    }

    private static function kernel(KernelRuntimeInterface $runtime, Pipeline $pipeline): HttpKernel
    {
        $correlation = new class(self::CORRELATION) implements CorrelationIdProviderInterface {
            public function __construct(private string $id)
            {
            }

            public function correlationId(): ?string
            {
                return $this->id;
            }
        };

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );
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
}
