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
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
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
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
            ->with('X-Correlation-Id', self::CORRELATION)
            ->willReturnCallback(static function () use (&$events, $final): ResponseInterface {
                $events[] = 'finalize';

                return $final;
            });
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
        $response->method('withHeader')->with('X-Correlation-Id', self::CORRELATION)->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')->willReturn($response);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::exactly(2))->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                $context,
            ): ResponseInterface {
                self::assertSame(self::CORRELATION, $context->get(ContextKeys::CORRELATION_ID));
                self::assertSame(self::CORRELATION, $context->get(ContextKeys::UOW_ID));
                self::assertSame('http', $context->get(ContextKeys::UOW_TYPE));
                $context->set(ContextKeys::PATH, '/request-local');

                return $next->handle($request);
            },
        );
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([$middleware], $terminal),
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );

        self::assertSame($response, $kernel->handle($request));
        self::assertSame([], $context->all());
        self::assertSame(1, $probe->calls);
        self::assertSame($response, $kernel->handle($request));
        self::assertSame([], $context->all());
        self::assertSame(2, $probe->calls);
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

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter ?? new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );
    }
}
