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

use Coretsia\Platform\Http\Tests\Support\SystemEndpointRecordingClock;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UnsupportedMethodForSystemEndpointFallsThroughAsNotMatchedTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testUnsupportedMethodsAndNonMatchingPathsRetainCanonical404(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['clock' => 'recording'],
        );

        try {
            foreach (['HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'get', 'Get', 'gEt'] as $method) {
                foreach (
                    [
                        '/',
                        '/ping',
                        '/_alive',
                        '/error',
                        '/health',
                        '/health/',
                        '/health/ready',
                        '/metrics',
                    ] as $path
                ) {
                    self::assertMiss($fixture->kernel()->handle($fixture->request($method, $path)));
                }
            }

            foreach (['/other', '/healthz', '/metrics/', '/_debug', '/_debug/', '/_debug/toolbar'] as $path) {
                self::assertMiss($fixture->kernel()->handle($fixture->request('GET', $path)));
            }

            foreach (
                [
                    '*',
                    'https://example.test/ping',
                    '/ping?private-query',
                    '/ping#private-fragment',
                    '/private/../ping',
                    '/private/%ZZ',
                    "/private\x00",
                ] as $path
            ) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                self::assertMiss($response);
                self::assertStringNotContainsString($path, (string) $response->getBody());
            }

            $clock = $fixture->clock();
            self::assertInstanceOf(SystemEndpointRecordingClock::class, $clock);
            self::assertSame(0, $clock->calls);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertSame(\array_fill(0, 85, 404), $fixture->factories()->statuses);
            self::assertSame(\array_fill(0, 85, ''), $fixture->factories()->bodies);
            self::assertCount(85, $fixture->runtime()->begins);
            self::assertCount(85, $fixture->runtime()->completions);
            $logs = $fixture->logger()->records;
            $increments = $fixture->recordingMeter()->increments;
            $observations = $fixture->recordingMeter()->observations;

            self::assertCount(85, $logs);
            self::assertCount(85, $increments);
            self::assertCount(85, $observations);

            foreach ($logs as $index => [$level, $event, $context]) {
                self::assertSame('info', $level);
                self::assertSame('http.request.completed', $event);
                self::assertContains($context['method'], ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
                self::assertIsInt($context['duration_ms']);

                self::assertSame([
                    'method' => $context['method'],
                    'status' => 404,
                    'outcome' => 'handled_error',
                    'duration_ms' => $context['duration_ms'],
                    'route_template' => 'unknown',
                    'correlation_id' => SystemEndpointRuntimeFixture::CORRELATION_ID,
                ], $context);

                $labels = [
                    'method' => $context['method'],
                    'status' => 404,
                    'outcome' => 'handled_error',
                ];

                self::assertSame(['http.request_total', 1, $labels], $increments[$index]);
                self::assertSame('http.request_duration_ms', $observations[$index][0]);
                self::assertIsInt($observations[$index][1]);
                self::assertSame($labels, $observations[$index][2]);
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMethodFilteringPrecedesUriAccessAndUriFailuresPropagate(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
        );

        try {
            $router = $fixture->router();
            $invalid = new \InvalidArgumentException('http-reserved-path-invalid');
            $unrelated = new \RuntimeException('private-uri-failure');

            foreach (['HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'get', 'Get', 'gEt'] as $method) {
                $request = $this->createMock(ServerRequestInterface::class);
                $request->method('getMethod')->willReturn($method);
                $request->expects(self::never())->method('getUri');

                self::assertNull($router->dispatch($request));
            }

            foreach ([$invalid, $unrelated] as $failure) {
                foreach (['uriFailure', 'pathFailure'] as $stage) {
                    $failures = [$stage => $failure];
                    $request = $fixture->request('GET', '/ping', ...$failures);
                    $caught = null;
                    try {
                        $router->dispatch($request);
                    } catch (\Throwable $throwable) {
                        $caught = $throwable;
                    }
                    self::assertSame($failure, $caught);

                    $caught = null;
                    try {
                        $fixture->kernel()->handle($request);
                    } catch (\Throwable $throwable) {
                        $caught = $throwable;
                    }
                    self::assertSame($failure, $caught);
                }
            }

            self::assertSame([], $fixture->factories()->statuses);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertCount(4, $fixture->runtime()->begins);
            self::assertCount(4, $fixture->runtime()->completions);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testEarlierMaintenanceResponseRetainsPrecedence(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['http' => ['maintenance' => ['enabled' => true]]],
        );

        try {
            $fixture->maintenance()->enable(null);
            foreach (['POST', 'HEAD'] as $method) {
                $response = $fixture->kernel()->handle($fixture->request($method, '/ping'));
                self::assertSame(503, $response->getStatusCode());
                self::assertSame($method === 'HEAD' ? '' : "Service Unavailable\n", (string) $response->getBody());
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                self::assertSame('', $response->getHeaderLine('Allow'));
            }
            self::assertSame([503, 503], $fixture->factories()->statuses);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }

    private static function assertMiss(ResponseInterface $response): void
    {
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertFalse($response->hasHeader('Allow'));
        self::assertFalse($response->hasHeader('Content-Type'));
        self::assertFalse($response->hasHeader('Cache-Control'));
        self::assertSame([
            'X-Correlation-Id' => [SystemEndpointRuntimeFixture::CORRELATION_ID],
            'X-Content-Type-Options' => ['nosniff'],
            'X-Frame-Options' => ['DENY'],
            'Referrer-Policy' => ['no-referrer'],
        ], $response->getHeaders());
    }
}
