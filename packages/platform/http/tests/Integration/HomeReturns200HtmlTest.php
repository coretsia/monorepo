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

use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointEarlierMiddleware;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class HomeReturns200HtmlTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testHomeIsServedByTheCanonicalTerminalThroughHttpKernel(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot($mode, fn (string $class): object => $this->createStub($class));

        try {
            self::assertSame(
                $fixture->container->get(FallbackRouterHandler::class),
                $fixture->container->get(RequestHandlerInterface::class),
            );

            foreach (['/', '', '////'] as $index => $path) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path, 'secret=opaque'));

                self::assertSame(200, $response->getStatusCode());
                self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
                self::assertSame("<h1>Hello Coretsia</h1>\n", (string) $response->getBody());
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame(
                    SystemEndpointRuntimeFixture::CORRELATION_ID,
                    $response->getHeaderLine('X-Correlation-Id'),
                );
                self::assertCount($index + 1, $fixture->runtime()->begins);
                self::assertCount($index + 1, $fixture->runtime()->completions);
                self::assertSame('http', $fixture->runtime()->begins[$index]->context()['type']);
                self::assertSame(
                    [$fixture->runtime()->begins[$index], 'success', null],
                    $fixture->runtime()->completions[$index]
                );
            }

            self::assertSame([200, 200, 200], $fixture->factories()->statuses);
            self::assertSame(\array_fill(0, 3, "<h1>Hello Coretsia</h1>\n"), $fixture->factories()->bodies);
            self::assertSame(\array_fill(0, 3, [
                'http.system_endpoint_total',
                1,
                ['operation' => 'home', 'status' => 200, 'outcome' => 'served'],
            ]), $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertSame(\array_fill(0, 3, [
                'http.request_total',
                1,
                ['method' => 'GET', 'status' => 200, 'outcome' => 'success'],
            ]), $fixture->recordingMeter()->incrementsFor('http.request_total'));
            self::assertCount(3, $fixture->recordingMeter()->observations);
            foreach ($fixture->recordingMeter()->observations as [$name, $duration, $labels]) {
                self::assertSame('http.request_duration_ms', $name);
                self::assertGreaterThanOrEqual(0, $duration);
                self::assertSame(['method' => 'GET', 'status' => 200, 'outcome' => 'success'], $labels);
            }

            $logs = $fixture->logger()->recordsFor('http.request.completed');
            self::assertCount(3, $logs);
            foreach ($logs as [$level, $message, $context]) {
                self::assertSame('info', $level);
                self::assertSame('GET', $context['method']);
                self::assertSame(200, $context['status']);
                self::assertSame('success', $context['outcome']);
                self::assertSame('unknown', $context['route_template']);
                self::assertSame(SystemEndpointRuntimeFixture::CORRELATION_ID, $context['correlation_id']);
                self::assertGreaterThanOrEqual(0, $context['duration_ms']);
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testEarlierApplicationResponseBypassesTerminalButNotFinalization(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['earlier' => true],
        );

        try {
            $response = $fixture->kernel()->handle($fixture->request('GET', '/'));

            self::assertSame(202, $response->getStatusCode());
            self::assertSame('earlier response', (string) $response->getBody());
            self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
            self::assertSame(
                SystemEndpointRuntimeFixture::CORRELATION_ID,
                $response->getHeaderLine('X-Correlation-Id'),
            );
            self::assertSame(1, $fixture->container->get(SystemEndpointEarlierMiddleware::class)->calls);
            self::assertSame([202], $fixture->factories()->statuses);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertSame([
                ['http.request_total', 1, ['method' => 'GET', 'status' => 202, 'outcome' => 'success']],
            ], $fixture->recordingMeter()->incrementsFor('http.request_total'));
            $logs = $fixture->logger()->recordsFor('http.request.completed');
            self::assertCount(1, $logs);
            self::assertSame(202, $logs[0][2]['status']);
            self::assertCount(1, $fixture->runtime()->begins);
            self::assertSame([[$fixture->runtime()->begins[0], 'success', null]], $fixture->runtime()->completions);
        } finally {
            $fixture->close();
        }
    }
}
