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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorrelationHeaderAlwaysPresentTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('completionCases')]
    public function testFinalizerCompletesEveryReturnedResponseBeforeKernelReset(int $status, bool $shortCircuit): void
    {
        $events = [];
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), $status >= 400 ? 'handled_error' : 'success', null)
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'reset';

                return [];
            });
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $original = $this->createMock(ResponseInterface::class);
        $final = $this->createMock(ResponseInterface::class);
        $original->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)
            ->willReturnCallback(static function () use (&$events, $final): ResponseInterface {
                $events[] = 'header';

                return $final;
            });
        $original->expects(self::never())->method('withAddedHeader');
        $original->expects(self::never())->method('withStatus');
        $original->expects(self::never())->method('withBody');
        $final->method('getStatusCode')->willReturn($status);
        $final->method('getHeaderLine')->with('X-Correlation-Id')->willReturn(self::CORRELATION);
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')->willReturn(self::CORRELATION);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        if ($shortCircuit) {
            $terminal->expects(self::never())->method('handle');
            $middleware = $this->createMock(MiddlewareInterface::class);
            $middleware->expects(self::once())->method('process')->willReturn($original);
            $pipeline = new Pipeline([$middleware], $terminal);
        } else {
            $terminal->expects(self::once())->method('handle')->with($request)->willReturn($original);
            $pipeline = new Pipeline([], $terminal);
        }

        $actual = self::kernel($runtime, $pipeline, $provider, true)->handle($request);
        self::assertSame($final, $actual);
        self::assertSame(self::CORRELATION, $actual->getHeaderLine('X-Correlation-Id'));
        self::assertSame(['header', 'reset'], $events);
    }

    /** @return iterable<string, array{int, bool}> */
    public static function completionCases(): iterable
    {
        yield 'successful-response' => [200, false];
        yield 'short-circuit-response' => [403, true];
        yield 'problem-details-400' => [400, false];
        yield 'problem-details-413' => [413, false];
        yield 'emergency-problem-details-500' => [500, false];
    }

    public function testDisabledHeaderDoesNotDisableKernelCorrelationIdentityOrUnitOfWork(): void
    {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'success', null)->willReturn([]);
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->expects(self::never())->method('withHeader');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');

        self::assertSame(
            $response,
            self::kernel($runtime, new Pipeline([], $terminal), $provider, false)->handle($request),
        );
        self::assertSame(self::CORRELATION, $handle->context()['correlationId']);
    }

    public function testInvalidIdentityNeverTriggersIndependentGenerationOrHeaderEmission(): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')->willReturn(null);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        self::assertSame(
            $response,
            new HttpResponseFinalizer($provider, true, 'X-Correlation-Id')->finalize($response),
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

    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        CorrelationIdProviderInterface $provider,
        bool $enabled,
    ): HttpKernel {
        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($provider, $enabled, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );
    }
}
