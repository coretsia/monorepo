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

use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Debug\DebugRouter;
use Coretsia\Platform\Http\Exception\IntentionalErrorException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Routing\FallbackRouter;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class SystemEndpointObservabilityTest extends TestCase
{
    private const string COUNTER = 'http.system_endpoint_total';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testCompletedDecisionsHaveExactLabelsAndIndependentRequestCompletion(string $mode): void
    {
        foreach ([false, true] as $throwMeter) {
            $fixture = $this->boot($mode, ['throwMeter' => $throwMeter]);

            try {
                $cases = [
                    ['/ping', 'ping', 200, 'served', 'success'],
                    ['/_alive', 'alive', 200, 'served', 'success'],
                    ['/health', 'health', 501, 'owner_unavailable', 'handled_error'],
                    ['/health/private-path', 'health', 501, 'owner_unavailable', 'handled_error'],
                    ['/metrics', 'metrics', 501, 'owner_unavailable', 'handled_error'],
                ];
                $decisions = [];
                $requests = [];
                foreach ($cases as [$path, $operation, $status, $decision, $outcome]) {
                    $response = $fixture->kernel()->handle(
                        $fixture->request(
                            'GET',
                            $path,
                            'token=private-query',
                            ['Authorization' => ['Bearer private-credential'], 'X-Private' => ['private-header']],
                        ),
                    );

                    self::assertSame($status, $response->getStatusCode());
                    self::assertSame(
                        SystemEndpointRuntimeFixture::CORRELATION_ID,
                        $response->getHeaderLine('X-Correlation-Id'),
                    );
                    $decisions[] = self::decision($operation, $status, $decision);
                    $requests[] = [
                        'http.request_total',
                        1,
                        ['method' => 'GET', 'status' => $status, 'outcome' => $outcome],
                    ];
                    self::assertSame($decisions, $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                }

                try {
                    $fixture->kernel()->handle($fixture->request('GET', '/error', 'secret=private-query'));
                    self::fail('Expected the intentional endpoint failure.');
                } catch (IntentionalErrorException $failure) {
                    self::assertSame('http-intentional-error', $failure->reason());
                }

                $decisions[] = self::decision('error', 'unknown', 'intentional_error');
                $requests[] = [
                    'http.request_total',
                    1,
                    ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error'],
                ];
                self::assertSame($decisions, $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame($requests, $fixture->recordingMeter()->incrementsFor('http.request_total'));
                $expectedIncrements = [];
                foreach ($decisions as $index => $decision) {
                    $expectedIncrements[] = $decision;
                    $expectedIncrements[] = $requests[$index];
                }

                self::assertSame($expectedIncrements, $fixture->recordingMeter()->increments);
                self::assertCount(\count($requests), $fixture->recordingMeter()->observations);
                self::assertCount(\count($requests), $fixture->runtime()->begins);
                self::assertCount(\count($requests), $fixture->runtime()->completions);
                $logs = $fixture->logger()->recordsFor('http.request.completed');
                self::assertCount(\count($requests), $logs);
                self::assertSame($logs, $fixture->logger()->records);

                foreach ($requests as $index => [, , $labels]) {
                    [$metric, $duration, $durationLabels] = $fixture->recordingMeter()->observations[$index];
                    self::assertSame('http.request_duration_ms', $metric);
                    self::assertGreaterThanOrEqual(0, $duration);
                    self::assertSame($labels, $durationLabels);
                    self::assertSame([
                        'info',
                        'http.request.completed',
                        [
                            ...$labels,
                            'duration_ms' => $duration,
                            'route_template' => 'unknown',
                            'correlation_id' => SystemEndpointRuntimeFixture::CORRELATION_ID,
                        ],
                    ], $logs[$index]);
                    self::assertSame($fixture->runtime()->begins[$index], $fixture->runtime()->completions[$index][0]);
                    self::assertSame($labels['outcome'], $fixture->runtime()->completions[$index][1]);
                }
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testDisabledMetricsAndFoundationNoopMeterDoNotAlterDispatch(string $mode): void
    {
        foreach ([['http' => ['metrics' => ['enabled' => false]]], ['recordMeter' => false]] as $options) {
            $fixture = $this->boot($mode, $options);

            try {
                foreach (
                    [
                        ['/', 404],
                        ['/ping', 200],
                        ['/_alive', 200],
                        ['/health', 501],
                        ['/metrics', 501],
                    ] as [$path, $status]
                ) {
                    $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                    self::assertSame($status, $response->getStatusCode());
                }

                try {
                    $fixture->kernel()->handle($fixture->request('GET', '/error'));
                    self::fail('Expected the intentional endpoint failure.');
                } catch (IntentionalErrorException $failure) {
                    self::assertSame('http-intentional-error', $failure->reason());
                }

                if (isset($options['recordMeter'])) {
                    self::assertInstanceOf(NoopMeter::class, $fixture->meter());
                } else {
                    self::assertSame([], $fixture->recordingMeter()->increments);
                    self::assertSame([], $fixture->recordingMeter()->observations);
                }
                self::assertCount(6, $fixture->logger()->recordsFor('http.request.completed'));
                self::assertCount(6, $fixture->runtime()->completions);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testUnclaimedAndShortCircuitedRequestsHaveNoEndpointDecision(string $mode): void
    {
        $fixture = $this->boot($mode);

        try {
            foreach (
                [
                    ['POST', '/ping'],
                    ['HEAD', '/health'],
                    ['GET', '/missing'],
                    ['GET', '/bad%2'],
                    ['GET', '/_debug'],
                    ['GET', '/_debug/info'],
                ] as [$method, $path]
            ) {
                $response = $fixture->kernel()->handle($fixture->request($method, $path));
                self::assertSame(404, $response->getStatusCode());
            }

            self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
            self::assertSame([
                ['http.request_total', 1, ['method' => 'POST', 'status' => 404, 'outcome' => 'handled_error']],
                ['http.request_total', 1, ['method' => 'HEAD', 'status' => 404, 'outcome' => 'handled_error']],
                ...\array_fill(0, 4, [
                    'http.request_total',
                    1,
                    ['method' => 'GET', 'status' => 404, 'outcome' => 'handled_error'],
                ]),
            ], $fixture->recordingMeter()->increments);
            self::assertCount(6, $fixture->logger()->recordsFor('http.request.completed'));
        } finally {
            $fixture->close();
        }

        $fixture = $this->boot($mode, ['earlier' => true]);

        try {
            foreach (['/', '/ping', '/_alive', '/error', '/health', '/metrics'] as $path) {
                self::assertSame(202, $fixture->kernel()->handle($fixture->request('GET', $path))->getStatusCode());
            }

            self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
            self::assertSame(\array_fill(0, 6, 202), $fixture->factories()->statuses);
            self::assertSame(\array_fill(0, 6, [
                'http.request_total',
                1,
                ['method' => 'GET', 'status' => 202, 'outcome' => 'success'],
            ]), $fixture->recordingMeter()->increments);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testEveryConstructionFailurePreservesPrimaryThrowableAndRecordsNoDecision(string $mode): void
    {
        foreach (['response', 'content-type', 'cache-control', 'stream', 'body'] as $stage) {
            $fixture = $this->boot($mode);

            try {
                $failure = new \InvalidArgumentException('http-reserved-path-invalid');
                if ($stage === 'response') {
                    $fixture->factories()->responseFailure = $failure;
                    $kernel = $fixture->kernel();
                } elseif ($stage === 'stream') {
                    $fixture->factories()->streamFailure = $failure;
                    $kernel = $fixture->kernel();
                } else {
                    [$responses] = $this->responseChain($stage, $failure);
                    $kernel = $this->kernel($fixture, $this->pipeline($fixture, $responses));
                }

                try {
                    $kernel->handle($fixture->request('GET', '/ping'));
                    self::fail('Response construction must propagate the original Throwable: ' . $stage);
                } catch (\InvalidArgumentException $caught) {
                    self::assertSame($failure, $caught);
                }

                self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame(
                    [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                    $fixture->runtime()->completions,
                );
                self::assertSame([
                    ['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']],
                ], $fixture->recordingMeter()->increments);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testIncompatibleImmutableMutationResultsAreRejectedAtEachStep(string $mode): void
    {
        foreach (['content-type', 'cache-control', 'body'] as $stage) {
            $fixture = $this->boot($mode);

            try {
                $incompatible = $this->createStub(MessageInterface::class);
                [$responses] = $this->responseChain($stage, $incompatible);
                $kernel = $this->kernel($fixture, $this->pipeline($fixture, $responses));

                try {
                    $kernel->handle($fixture->request('GET', '/ping'));
                    self::fail('Expected incompatible immutable mutation rejection: ' . $stage);
                } catch (\RuntimeException $failure) {
                    self::assertSame('http-fallback-response-invalid', $failure->getMessage());
                    self::assertNull($failure->getPrevious());
                    self::assertSame(
                        [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                        $fixture->runtime()->completions,
                    );
                }

                self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame([
                    ['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']],
                ], $fixture->recordingMeter()->increments);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testUnmatchedResponseAlsoValidatesImmutableBodyMutation(string $mode): void
    {
        $fixture = $this->boot($mode);

        try {
            $factory = $this->createMock(ResponseFactoryInterface::class);
            $streams = $this->createMock(StreamFactoryInterface::class);
            $response = $this->createMock(ResponseInterface::class);
            $stream = $this->createStub(StreamInterface::class);
            $factory->expects(self::once())->method('createResponse')->with(404)->willReturn($response);
            $streams->expects(self::once())->method('createStream')->with('')->willReturn($stream);
            $response->expects(self::never())->method('withHeader');
            $response->expects(self::once())->method('withBody')->with(self::identicalTo($stream))
                ->willReturn($this->createStub(MessageInterface::class));

            $kernel = $this->kernel($fixture, $this->pipeline($fixture, new FallbackResponses($factory, $streams)));

            try {
                $kernel->handle($fixture->request('GET', '/not-found'));
                self::fail('The unmatched response must validate its mutation result.');
            } catch (\RuntimeException $failure) {
                self::assertSame('http-fallback-response-invalid', $failure->getMessage());
                self::assertSame(
                    [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                    $fixture->runtime()->completions,
                );
            }

            self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
            self::assertSame([
                ['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']],
            ], $fixture->recordingMeter()->increments);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testFinalImmutableResponseIsReturnedAndDecisionSurvivesLaterFinalizationFailure(string $mode): void
    {
        $fixture = $this->boot($mode);

        try {
            [$responses, $final] = $this->responseChain();
            $router = new FallbackRouter(
                $responses,
                $fixture->clock(),
                $fixture->meter(),
                true,
                $fixture->container->get(DebugRouter::class),
            );
            self::assertSame($final, $router->dispatch($fixture->request('GET', '/ping')));
            self::assertSame([
                self::decision('ping', 200, 'served'),
            ], $fixture->recordingMeter()->increments);
        } finally {
            $fixture->close();
        }

        $fixture = $this->boot($mode);

        try {
            [$responses, $final] = $this->responseChain();
            $failure = new \RuntimeException('private-finalization-failure');
            $final->expects(self::once())->method('withHeader')
                ->with('X-Correlation-Id', SystemEndpointRuntimeFixture::CORRELATION_ID)
                ->willThrowException($failure);
            $attributes = [];
            $kernel = $this->kernel(
                $fixture,
                $this->pipeline($fixture, $responses),
                $this->spanRecorder($attributes),
            );

            try {
                $kernel->handle($fixture->request('GET', '/ping'));
                self::fail('Finalization failure must propagate after the completed endpoint decision.');
            } catch (\RuntimeException $caught) {
                self::assertSame($failure, $caught);
            }

            self::assertSame(
                [self::decision('ping', 200, 'served')],
                $fixture->recordingMeter()->incrementsFor(self::COUNTER),
            );
            self::assertSame(
                [['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']]],
                $fixture->recordingMeter()->incrementsFor('http.request_total'),
            );
            self::assertCount(1, $fixture->recordingMeter()->observations);
            self::assertCount(1, $fixture->runtime()->begins);
            self::assertSame(
                [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                $fixture->runtime()->completions,
            );
            $logs = $fixture->logger()->recordsFor('http.request.completed');
            self::assertCount(1, $logs);
            self::assertSame('unknown', $logs[0][2]['status']);
            self::assertSame('fatal_error', $logs[0][2]['outcome']);
            self::assertSame([['method', 'GET'], ['status', 'unknown'], ['outcome', 'fatal_error']], $attributes);
            self::assertSame([
                self::decision('ping', 200, 'served'),
                ['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']],
            ], $fixture->recordingMeter()->increments);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testCanonicalRequestSpanRemainsOnePerRequestAndNoNewLifecycleTagsExist(string $mode): void
    {
        foreach ([['/', 404, 'handled_error'], ['/health', 501, 'handled_error']] as [$path, $status, $outcome]) {
            $fixture = $this->boot($mode);

            try {
                $attributes = [];
                $kernel = $this->kernel(
                    $fixture,
                    $fixture->container->get(Pipeline::class),
                    $this->spanRecorder($attributes),
                );

                self::assertSame(
                    $status,
                    $kernel->handle($fixture->request('GET', $path, 'secret=private-query'))->getStatusCode(),
                );
                self::assertSame([['method', 'GET'], ['status', $status], ['outcome', $outcome]], $attributes);
                self::assertCount(1, $fixture->runtime()->begins);
                self::assertSame([[$fixture->runtime()->begins[0], $outcome, null]], $fixture->runtime()->completions);
                self::assertCount(1, $fixture->logger()->recordsFor('http.request.completed'));

                $registry = $fixture->container->get(TagRegistry::class);
                foreach ([ReservedTags::KERNEL_RESET, ReservedTags::KERNEL_STATEFUL] as $tag) {
                    self::assertSame(
                        [ContextStore::class],
                        \array_map(static fn ($entry): string => $entry->id, $registry->all($tag)),
                    );
                }
                self::assertSame([], $registry->all(ReservedTags::KERNEL_HOOK_BEFORE_UOW));
                self::assertSame([], $registry->all(ReservedTags::KERNEL_HOOK_AFTER_UOW));
            } finally {
                $fixture->close();
            }
        }
    }

    /** @param array<string, mixed> $options */
    private function boot(string $mode, array $options = []): SystemEndpointRuntimeFixture
    {
        return SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            $options,
        );
    }

    /** @return array{string, int, array{operation: string, status: int|string, outcome: string}} */
    private static function decision(string $operation, int|string $status, string $outcome): array
    {
        return [self::COUNTER, 1, ['operation' => $operation, 'status' => $status, 'outcome' => $outcome]];
    }

    /**
     * An explicit external factory chain for the otherwise inaccessible
     * immutable mutation failures. Each successful step returns a different
     * response and each subsequent step is expected only on that response.
     *
     * @return array{FallbackResponses, ResponseInterface&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function responseChain(?string $stage = null, \Throwable|MessageInterface|null $failure = null): array
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $initial = $this->createMock(ResponseInterface::class);
        $typed = $this->createMock(ResponseInterface::class);
        $cached = $this->createMock(ResponseInterface::class);
        $final = $this->createMock(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);

        $steps = [
            ['response', $factory, 'createResponse', [200], $initial],
            ['content-type', $initial, 'withHeader', ['Content-Type', 'application/json'], $typed],
            ['cache-control', $typed, 'withHeader', ['Cache-Control', 'no-store'], $cached],
            ['stream', $streams, 'createStream', ["{\"ok\":true,\"ts\":1700000000}\n"], $stream],
            ['body', $cached, 'withBody', [self::identicalTo($stream)], $final],
        ];
        $reached = true;
        foreach ($steps as [$name, $mock, $method, $arguments, $next]) {
            if (!$reached) {
                $mock->expects(self::never())->method($method);
                continue;
            }

            $expectation = $mock->expects(self::once())->method($method)->with(...$arguments);
            if ($name === $stage) {
                if ($failure instanceof \Throwable) {
                    $expectation->willThrowException($failure);
                } else {
                    $expectation->willReturn($failure);
                }
                $reached = false;
            } else {
                $expectation->willReturn($next);
            }
        }

        // Mutation results are distinct; no mutation may be applied to
        // a stale response or skipped in favor of an earlier instance.
        $initial->expects(self::never())->method('withBody');
        $typed->expects(self::never())->method('withBody');
        $cached->expects(self::never())->method('withHeader');
        $final->expects(self::never())->method('withBody');

        return [new FallbackResponses($factory, $streams), $final];
    }

    private function pipeline(SystemEndpointRuntimeFixture $fixture, FallbackResponses $responses): Pipeline
    {
        return new Pipeline(
            [],
            new FallbackRouterHandler(
                new FallbackRouter(
                    $responses,
                    $fixture->clock(),
                    $fixture->meter(),
                    true,
                    $fixture->container->get(DebugRouter::class),
                ),
                $responses,
            ),
            false,
            [],
            false,
        );
    }

    /**
     * Reuse the actual resolved runtime and completion policies. Only the
     * external factory chain or tracing port is substituted by the test.
     */
    private function kernel(
        SystemEndpointRuntimeFixture $fixture,
        Pipeline $pipeline,
        ?HttpRequestSpanRecorder $spans = null,
    ): HttpKernel {
        return new HttpKernel(
            $fixture->runtime(),
            $fixture->container->get(Stopwatch::class),
            $pipeline,
            $fixture->container->get(HttpResponseFinalizer::class),
            $fixture->container->get(HttpRequestMetricsRecorder::class),
            $fixture->container->get(HttpAccessLogWriter::class),
            $spans ?? $fixture->container->get(HttpRequestSpanRecorder::class),
            $fixture->container->get(RequestIdPolicy::class),
        );
    }

    /** @param list<array{string, mixed}> $attributes */
    private function spanRecorder(array &$attributes): HttpRequestSpanRecorder
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[] = [$key, $value];
            },
        );
        $span->expects(self::never())->method('setAttributes');
        $span->expects(self::never())->method('recordException');
        $span->expects(self::never())->method('addEvent');
        $span->expects(self::once())->method('end');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->with('http.request', ['method' => 'GET'])->willReturn(
            $span,
        );
        $tracer->expects(self::never())->method('inSpan');

        return new HttpRequestSpanRecorder($tracer, true);
    }
}
