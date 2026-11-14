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

namespace Coretsia\Platform\Http\Tests\Unit;

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
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpResponseFinalizerTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('completedStatuses')]
    public function testAllCompletedResponsesUseTheSameCanonicalHeaderPolicy(int $status): void
    {
        $input = $this->createMock(ResponseInterface::class);
        $output = $this->createMock(ResponseInterface::class);
        $body = $this->createStub(StreamInterface::class);
        foreach ([$input, $output] as $response) {
            $response->expects(self::never())->method('withStatus');
            $response->expects(self::never())->method('withBody');
            $response->expects(self::never())->method('withoutHeader');
            $response->expects(self::never())->method('withAddedHeader');
        }
        $input->method('getStatusCode')->willReturn($status);
        $input->method('getBody')->willReturn($body);
        $input->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Application' => 'retained',
            default => '',
        });
        $input->expects(self::once())
            ->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)
            ->willReturn($output);
        $output->method('getStatusCode')->willReturn($status);
        $output->method('getBody')->willReturn($body);
        $output->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Application' => 'retained',
            'X-Correlation-Id' => self::CORRELATION,
            default => '',
        });
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')->willReturn(self::CORRELATION);

        $actual = new HttpResponseFinalizer($provider, true, 'X-Correlation-Id')->finalize($input);
        self::assertSame($output, $actual);
        self::assertSame($status, $actual->getStatusCode());
        self::assertSame($body, $actual->getBody());
        foreach (['Content-Type', 'Cache-Control', 'X-Application'] as $name) {
            self::assertSame($input->getHeaderLine($name), $actual->getHeaderLine($name));
        }
    }

    /** @return iterable<string, array{int}> */
    public static function completedStatuses(): iterable
    {
        yield 'successful-200' => [200];
        yield 'short-circuit-403' => [403];
        yield 'problem-json-400' => [400];
        yield 'problem-payload-too-large-413' => [413];
        yield 'problem-emergency-500' => [500];
    }

    public function testDisabledCorrelationPreservesResponseIdentityAndNeverReadsProvider(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        self::assertSame(
            $response,
            new HttpResponseFinalizer($provider, false, 'X-Correlation-Id')->finalize($response),
        );
    }

    public function testRepeatingFinalizationReplacesHeaderThroughPsr7WithHeader(): void
    {
        $initial = $this->createMock(ResponseInterface::class);
        $first = $this->createMock(ResponseInterface::class);
        $second = $this->createStub(ResponseInterface::class);
        $initial->method('getHeader')->with('X-Correlation-Id')->willReturn(['untrusted']);
        $initial->expects(self::once())->method('withHeader')->with('X-Correlation-Id', self::CORRELATION)->willReturn(
            $first,
        );
        $first->expects(self::once())->method('withHeader')->with('X-Correlation-Id', self::CORRELATION)->willReturn(
            $second,
        );
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::exactly(2))->method('correlationId')->willReturn(self::CORRELATION);
        $finalizer = new HttpResponseFinalizer($provider, true, 'X-Correlation-Id');

        self::assertSame($second, $finalizer->finalize($finalizer->finalize($initial)));
        // PSR-7 withHeader() REPLACES existing values; withAddedHeader() is never used.
    }

    public function testReusedFinalizerReadsTheCurrentIdentityWithoutRetainingPreviousResponse(): void
    {
        $nextCorrelation = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
        $first = $this->createMock(ResponseInterface::class);
        $firstFinal = $this->createStub(ResponseInterface::class);
        $second = $this->createMock(ResponseInterface::class);
        $secondFinal = $this->createStub(ResponseInterface::class);
        $first->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)->willReturn($firstFinal);
        $second->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', $nextCorrelation)->willReturn($secondFinal);

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::exactly(2))->method('correlationId')
            ->willReturnOnConsecutiveCalls(self::CORRELATION, $nextCorrelation);
        $finalizer = new HttpResponseFinalizer($provider, true, 'X-Correlation-Id');

        self::assertSame($firstFinal, $finalizer->finalize($first));
        self::assertSame($secondFinal, $finalizer->finalize($second));

        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionObject($finalizer)->getProperties(),
        );
        \sort($properties);
        self::assertSame(['correlationIds', 'enabled', 'headerName'], $properties);
    }

    #[DataProvider('invalidCorrelations')]
    public function testAbsentOrInvalidCorrelationNeverEmitsHeader(?string $correlation): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn($correlation);

        self::assertSame(
            $response,
            new HttpResponseFinalizer($provider, true, 'X-Correlation-Id')->finalize($response),
        );
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidCorrelations(): iterable
    {
        yield 'absent' => [null];
        yield 'raw-request-value' => ['from-request-header'];
        yield 'control' => ["01ARZ3NDEKTSV4RRFFQ69G5FAV\r\nInjected: true"];
        yield 'noncanonical-ulid' => ['01arz3ndektsv4rrffq69g5fav'];
        yield 'oversized' => [\str_repeat('A', 200)];
    }

    public function testResponseFinalizerPropagatesTheOriginalPsr7Failure(): void
    {
        $failure = new \RuntimeException('test-finalizer-failure');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willThrowException($failure);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);

        try {
            new HttpResponseFinalizer($provider, true, 'X-Correlation-Id')->finalize($response);
            self::fail('Response header failure must propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testKernelCompletesExactlyOnceAndPreservesFinalizerFailureIdentity(): void
    {
        $failure = new \RuntimeException('header-mutation-failed');
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())
            ->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'fatal_error', self::identicalTo($failure))
            ->willThrowException(new \RuntimeException('kernel-after-failed'));
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willThrowException($failure);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);
        $kernel = self::kernel($runtime, new Pipeline([], $terminal), $provider);

        try {
            $kernel->handle($request);
            self::fail('HttpKernel must not return intermediate response after failed finalization.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testKernelSuccessfulFinalizationAlsoCompletesExactlyOnce(): void
    {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'handled_error', null)->willReturn([]);
        $response = $this->createStub(ResponseInterface::class);
        $final = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($final);
        $final->method('getStatusCode')->willReturn(413);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturn($response);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);

        self::assertSame($final, self::kernel($runtime, new Pipeline([], $terminal), $provider)->handle($request));
    }

    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        CorrelationIdProviderInterface $correlations,
    ): HttpKernel {
        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($correlations, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), false),
            new HttpAccessLogWriter(new NoopLogger(), false),
            new HttpRequestSpanRecorder(new NoopTracer(), false),
        );
    }

    private static function handle(): UnitOfWorkHandle
    {
        return new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
    }
}
