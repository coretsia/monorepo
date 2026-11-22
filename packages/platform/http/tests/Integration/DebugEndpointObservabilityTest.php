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
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Debug\DebugCacheHandler;
use Coretsia\Platform\Http\Debug\DebugConfigHandler;
use Coretsia\Platform\Http\Debug\DebugGuard;
use Coretsia\Platform\Http\Debug\DebugHeadersHandler;
use Coretsia\Platform\Http\Debug\DebugModuleListHandler;
use Coretsia\Platform\Http\Debug\DebugRouter;
use Coretsia\Platform\Http\Exception\DebugForbiddenException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DebugEndpointObservabilityTest extends TestCase
{
    private const string COUNTER = 'http.debug_total';
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testExactlyOneBoundedDecisionPerEligibleAttemptAndIndependentRequestCompletion(string $mode): void
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$throwMeter, $throwLogger]) {
            $fixture = $this->boot($mode, ['throwMeter' => $throwMeter, 'throwLogger' => $throwLogger]);

            try {
                $decisions = [];
                $warnings = [];
                $requests = [];
                foreach (['headers', 'modules', 'config', 'cache'] as $operation) {
                    foreach ([true, false] as $allowed) {
                        $status = $allowed ? 200 : 403;
                        $outcome = $allowed ? 'success' : 'handled_error';
                        $response = $fixture->kernel()->handle($this->request($fixture, $operation, $allowed));
                        self::assertSame($status, $response->getStatusCode());
                        $decisions[] = self::decision($operation, $allowed ? 'allowed' : 'blocked');
                        if (!$allowed) {
                            $warnings[] = ['warning', 'http.debug.blocked', ['operation' => $operation]];
                        }
                        $requests[] = [
                            'http.request_total',
                            1,
                            [
                                'method' => 'GET',
                                'status' => $status,
                                'outcome' => $outcome,
                            ],
                        ];
                        self::assertSame($decisions, $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                        self::assertSame($warnings, $fixture->logger()->recordsFor('http.debug.blocked'));
                    }
                }
                self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame($requests, $fixture->recordingMeter()->incrementsFor('http.request_total'));
                $ordered = [];
                foreach ($decisions as $index => $decision) {
                    $ordered[] = $decision;
                    $ordered[] = $requests[$index];
                }
                self::assertSame($ordered, $fixture->recordingMeter()->increments);
                self::assertCount(8, $fixture->recordingMeter()->observations);
                self::assertCount(8, $fixture->runtime()->begins);
                self::assertCount(8, $fixture->runtime()->completions);
                $logs = $fixture->logger()->recordsFor('http.request.completed');
                self::assertCount(8, $logs);
                foreach ($requests as $index => [, , $labels]) {
                    [$name, $duration, $actualLabels] = $fixture->recordingMeter()->observations[$index];
                    self::assertSame('http.request_duration_ms', $name);
                    self::assertGreaterThanOrEqual(0, $duration);
                    self::assertSame($labels, $actualLabels);
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
                    self::assertSame([
                        $fixture->runtime()->begins[$index],
                        $labels['outcome'],
                        null,
                    ], $fixture->runtime()->completions[$index]);
                }
                self::assertSame([], $fixture->container->get(ContextStore::class)->all());
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMetricsAndAccessLoggingCanBeDisabledIndependently(string $mode): void
    {
        foreach ([[false, true], [true, false], [false, false]] as [$metrics, $logging]) {
            $fixture = $this->boot($mode, [
                'http' => [
                    'metrics' => ['enabled' => $metrics],
                    'access_log' => ['enabled' => $logging],
                ],
            ]);

            try {
                self::assertSame(
                    200,
                    $fixture->kernel()->handle($this->request($fixture, 'cache', true))->getStatusCode(),
                );
                self::assertSame(
                    403,
                    $fixture->kernel()->handle($this->request($fixture, 'cache', false))->getStatusCode(),
                );
                self::assertSame(
                    $metrics ? [
                        self::decision('cache', 'allowed'),
                        self::decision('cache', 'blocked'),
                    ] : [],
                    $fixture->recordingMeter()->incrementsFor(self::COUNTER),
                );
                self::assertSame(
                    $logging ? [
                        ['warning', 'http.debug.blocked', ['operation' => 'cache']],
                    ] : [],
                    $fixture->logger()->recordsFor('http.debug.blocked'),
                );
                self::assertCount($metrics ? 2 : 0, $fixture->recordingMeter()->incrementsFor('http.request_total'));
                self::assertCount($logging ? 2 : 0, $fixture->logger()->recordsFor('http.request.completed'));
                self::assertCount(2, $fixture->runtime()->completions);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testFoundationNoopPortsDoNotChangeEndpointDecisions(string $mode): void
    {
        $fixture = $this->boot($mode, ['recordMeter' => false]);
        try {
            self::assertInstanceOf(NoopMeter::class, $fixture->meter());
            self::assertSame(200, $fixture->kernel()->handle($this->request($fixture, 'cache', true))->getStatusCode());
            self::assertSame(
                403,
                $fixture->kernel()->handle($this->request($fixture, 'cache', false))->getStatusCode(),
            );
            $router = new DebugRouter(
                $fixture->container->get(DebugGuard::class),
                $fixture->container->get(DebugHeadersHandler::class),
                $fixture->container->get(DebugModuleListHandler::class),
                $fixture->container->get(DebugConfigHandler::class),
                $fixture->container->get(DebugCacheHandler::class),
                $fixture->meter(),
                new NoopLogger(),
                true,
                true,
            );
            $response = $router->dispatch($this->request($fixture, 'cache', true));
            self::assertInstanceOf(ResponseInterface::class, $response);
            self::assertSame(200, $response->getStatusCode());
            try {
                $router->dispatch($this->request($fixture, 'cache', false));
                self::fail('Noop ports must not suppress a forbidden decision.');
            } catch (DebugForbiddenException $exception) {
                self::assertSame('http-debug-forbidden', $exception->reason());
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testUnmatchedDisabledAndMissingAuthorizedKeysHaveNoDebugDecision(string $mode): void
    {
        foreach ([false, true] as $enabled) {
            $fixture = $this->boot($mode, ['http' => ['debug' => ['endpoints' => ['enabled' => $enabled]]]]);
            try {
                $requests = [
                    $fixture->request('GET', '/_debug/unknown'),
                    $fixture->request('POST', '/_debug/cache'),
                    $fixture->request('GET', '/_debug/cache/'),
                    $fixture->request(
                        'GET',
                        '/_debug/config',
                        'key=kernel.uow.attributes.missing',
                        ['X-Debug-Token' => [self::TOKEN]],
                        serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                    ),
                ];
                if (!$enabled) {
                    $requests[] = $this->request($fixture, 'cache', false);
                    $requests[] = $this->request($fixture, 'headers', true);
                }
                foreach ($requests as $request) {
                    $response = $fixture->kernel()->handle($request);
                    self::assertSame(404, $response->getStatusCode());
                    self::assertSame('', (string) $response->getBody());
                }
                self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame([], $fixture->logger()->recordsFor('http.debug.blocked'));
                self::assertCount(\count($requests), $fixture->recordingMeter()->incrementsFor('http.request_total'));
                self::assertCount(\count($requests), $fixture->runtime()->completions);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testConfigAuthorizationDenialsAreCompletedBlockedDecisions(string $mode): void
    {
        $fixture = $this->boot($mode);
        try {
            $expected = [];
            $warnings = [];
            foreach (
                [
                    'key=kernel.uow.attributes.max_keys&key=kernel.uow.attributes.max_keys',
                    'key=kernel.password',
                    'key=http.debug.token_sha256',
                ] as $query
            ) {
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/config',
                        $query,
                        ['X-Debug-Token' => [self::TOKEN]],
                        serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                    ),
                );
                self::assertSame(403, $response->getStatusCode());
                $expected[] = self::decision('config', 'blocked');
                $warnings[] = ['warning', 'http.debug.blocked', ['operation' => 'config']];
                self::assertSame($expected, $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame($warnings, $fixture->logger()->recordsFor('http.debug.blocked'));
            }
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertCount(3, $fixture->runtime()->completions);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testHandlerAndResponseConstructionFailuresAreNotCompletedDecisions(string $mode): void
    {
        foreach (['responseFailure', 'streamFailure', 'uriFailure'] as $failurePoint) {
            $fixture = $this->boot($mode, ['reporter' => true]);

            try {
                $failure = new \RuntimeException('PRIVATE_FAILURE');
                if ($failurePoint === 'uriFailure') {
                    // The handler fails after route and authorization have
                    // succeeded. Preserve the actual selected error boundary.
                    $request = $this->createStub(ServerRequestInterface::class);
                    $request->method('getMethod')->willReturn('GET');
                    $request->method('getBody')->willReturn($fixture->factories()->stream(''));
                    $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
                    $request->method('withoutAttribute')->willReturnSelf();
                    $request->method('getHeader')->willReturnCallback(
                        static fn (string $name): array => $name === 'X-Debug-Token' ? [self::TOKEN] : [],
                    );
                    $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
                    $uri->method('getPath')->willReturn('/_debug/config');
                    $uri->expects(self::once())->method('getQuery')->willThrowException($failure);
                    $request->method('getUri')->willReturn($uri);
                } else {
                    $fixture->factories()->{$failurePoint} = $failure;
                    $request = $this->request($fixture, 'cache', true);
                }
                $response = $fixture->kernel()->handle($request);
                self::assertSame(500, $response->getStatusCode());
                self::assertStringNotContainsString('PRIVATE_FAILURE', (string) $response->getBody());
                self::assertCount(1, $fixture->reporter()->reports);
                self::assertNotSame(DebugForbiddenException::ERROR_CODE, $fixture->reporter()->reports[0][0]->code());
                self::assertSame([
                    [$fixture->runtime()->begins[0], 'handled_error', null],
                ], $fixture->runtime()->completions);
                self::assertSame([], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame([], $fixture->logger()->recordsFor('http.debug.blocked'));
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testCompletedDecisionSurvivesFinalizationFailureAndRequestSpanRemainsCanonical(string $mode): void
    {
        foreach ([[true, false], [false, false], [true, true], [false, true]] as [$allowed, $failFinalization]) {
            $fixture = $this->boot($mode);
            try {
                $status = $failFinalization ? 'unknown' : ($allowed ? 200 : 403);
                $outcome = $failFinalization ? 'fatal_error' : ($allowed ? 'success' : 'handled_error');
                $attributes = [];
                $failure = new \RuntimeException('PRIVATE_FINALIZATION');
                $pipeline = $fixture->container->get(Pipeline::class);
                if ($failFinalization) {
                    $final = $this->createMock(ResponseInterface::class);
                    $final->expects(self::once())->method('withHeader')
                        ->with('X-Correlation-Id', SystemEndpointRuntimeFixture::CORRELATION_ID)
                        ->willThrowException($failure);
                    $terminal = $this->createMock(RequestHandlerInterface::class);
                    $terminal->expects(self::once())->method('handle')->willReturnCallback(
                        static function (ServerRequestInterface $request) use (
                            $pipeline,
                            $final,
                            $allowed,
                        ): ResponseInterface {
                            self::assertSame($allowed ? 200 : 403, $pipeline->handle($request)->getStatusCode());

                            return $final;
                        },
                    );
                    $pipeline = new Pipeline([], $terminal);
                }

                $kernel = new HttpKernel(
                    $fixture->runtime(),
                    $fixture->container->get(Stopwatch::class),
                    $pipeline,
                    $fixture->container->get(HttpResponseFinalizer::class),
                    $fixture->container->get(HttpRequestMetricsRecorder::class),
                    $fixture->container->get(HttpAccessLogWriter::class),
                    $this->spanRecorder($attributes),
                    $fixture->container->get(RequestIdPolicy::class),
                );
                try {
                    $response = $kernel->handle($this->request($fixture, 'cache', $allowed));
                    self::assertFalse($failFinalization);
                    self::assertSame($status, $response->getStatusCode());
                } catch (\RuntimeException $caught) {
                    self::assertTrue($failFinalization);
                    self::assertSame($failure, $caught);
                }
                $decision = self::decision('cache', $allowed ? 'allowed' : 'blocked');
                self::assertSame([$decision], $fixture->recordingMeter()->incrementsFor(self::COUNTER));
                self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame(
                    $allowed ? [] : [
                        ['warning', 'http.debug.blocked', ['operation' => 'cache']],
                    ],
                    $fixture->logger()->recordsFor('http.debug.blocked'),
                );
                self::assertSame([
                    $decision,
                    ['http.request_total', 1, ['method' => 'GET', 'status' => $status, 'outcome' => $outcome]],
                ], $fixture->recordingMeter()->increments);
                self::assertSame([['method', 'GET'], ['status', $status], ['outcome', $outcome]], $attributes);
                self::assertCount(1, $fixture->recordingMeter()->observations);
                $logs = $fixture->logger()->recordsFor('http.request.completed');
                self::assertCount(1, $logs);
                self::assertSame($status, $logs[0][2]['status']);
                self::assertSame($outcome, $logs[0][2]['outcome']);
                self::assertSame([
                    [$fixture->runtime()->begins[0], $outcome, $failFinalization ? $failure : null],
                ], $fixture->runtime()->completions);
            } finally {
                $fixture->close();
            }
        }
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

    /** @return array{string, int, array<string, string>} */
    private static function decision(string $operation, string $outcome): array
    {
        return [self::COUNTER, 1, ['operation' => $operation, 'outcome' => $outcome]];
    }

    private function request(
        SystemEndpointRuntimeFixture $fixture,
        string $operation,
        bool $allowed,
    ): ServerRequestInterface {
        return $fixture->request(
            'GET',
            '/_debug/' . $operation,
            $operation === 'config' ? 'key=kernel.uow.attributes.max_keys' : 'private=PRIVATE_QUERY',
            $allowed ? ['X-Debug-Token' => [self::TOKEN]] : [],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
    }

    /** @param array<string, mixed> $options */
    private function boot(string $mode, array $options = []): SystemEndpointRuntimeFixture
    {
        return SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), \array_replace_recursive([
            'appEnv' => 'local',
            'problemDetails' => true,
            'http' => [
                'debug' => [
                    'endpoints' => ['enabled' => true],
                    'token_sha256' => \hash('sha256', self::TOKEN),
                    'config' => ['allowlist' => ['kernel']],
                ],
            ],
        ], $options));
    }
}
