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

use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AliveReturns200TextTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testLivenessUsesCanonicalMatchingWhenMaintenanceIsInactive(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['http' => ['maintenance' => ['enabled' => true]]],
        );

        try {
            foreach (['/_alive', '_alive', '//_alive'] as $path) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path, 'opaque=1'));
                self::assertAlive($response);
            }

            foreach (['/_alive/', '/_Alive', '/_%61live'] as $path) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                self::assertSame(404, $response->getStatusCode());
                self::assertSame('', (string) $response->getBody());
            }

            self::assertSame(\array_fill(0, 3, [
                'http.system_endpoint_total',
                1,
                ['operation' => 'alive', 'status' => 200, 'outcome' => 'served'],
            ]), $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertCount(6, $fixture->runtime()->begins);
            self::assertCount(6, $fixture->runtime()->completions);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMaintenanceBypassesOnlyTheOriginalExactLivenessPath(string $mode): void
    {
        foreach ([false, true] as $unavailable) {
            $fixture = SystemEndpointRuntimeFixture::boot(
                $mode,
                fn (string $class): object => $this->createStub($class),
                ['http' => ['maintenance' => ['enabled' => true]]],
            );

            try {
                if ($unavailable) {
                    $fixture->makeMaintenanceUnavailable();
                } else {
                    $fixture->maintenance()->enable(null);
                }

                self::assertAlive($fixture->kernel()->handle($fixture->request('GET', '/_alive', 'opaque=1')));
                foreach (['HEAD', 'POST'] as $method) {
                    $response = $fixture->kernel()->handle($fixture->request($method, '/_alive'));
                    self::assertSame(404, $response->getStatusCode());
                    self::assertSame('', (string) $response->getBody());
                    self::assertSame('', $response->getHeaderLine('Allow'));
                }

                foreach (['_alive', '//_alive', '/_alive/', '/ping', '/'] as $path) {
                    $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                    self::assertSame(503, $response->getStatusCode());
                    self::assertSame("Service Unavailable\n", (string) $response->getBody());
                    self::assertSame('60', $response->getHeaderLine('Retry-After'));
                    self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                    self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                    self::assertSame(
                        SystemEndpointRuntimeFixture::CORRELATION_ID,
                        $response->getHeaderLine('X-Correlation-Id'),
                    );
                }

                self::assertSame([
                    ['http.system_endpoint_total', 1, ['operation' => 'alive', 'status' => 200, 'outcome' => 'served']],
                ], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                $completion = $fixture->recordingMeter()->incrementsFor('http.request_total');
                self::assertSame(
                    [200, 404, 404, 503, 503, 503, 503, 503],
                    \array_column(\array_column($completion, 2), 'status')
                );
                self::assertCount(8, $fixture->runtime()->begins);
                self::assertCount(8, $fixture->runtime()->completions);
                foreach ($fixture->runtime()->completions as $index => [$handle, $outcome, $error]) {
                    self::assertSame($fixture->runtime()->begins[$index], $handle);
                    self::assertSame($index === 0 ? 'success' : 'handled_error', $outcome);
                    self::assertNull($error);
                }
                self::assertCount(8, $fixture->logger()->recordsFor('http.request.completed'));
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMaintenanceLivenessBypassDoesNotBypassTrustedHostPolicy(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            [
                'http' => [
                    'maintenance' => ['enabled' => true],
                    'hosts' => ['enabled' => true, 'allowed' => ['allowed.example']],
                ],
            ],
        );

        try {
            $fixture->maintenance()->enable(null);
            try {
                $fixture->kernel()->handle($fixture->request('GET', '/_alive'));
                self::fail('The trusted host policy must still reject the request.');
            } catch (TrustedHostRejectedException $exception) {
                self::assertSame(
                    [[$fixture->runtime()->begins[0], 'fatal_error', $exception]],
                    $fixture->runtime()->completions
                );
            }
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
            self::assertSame([], $fixture->factories()->statuses);
        } finally {
            $fixture->close();
        }
    }

    private static function assertAlive(ResponseInterface $response): void
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('ALIVE', (string) $response->getBody());
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame(SystemEndpointRuntimeFixture::CORRELATION_ID, $response->getHeaderLine('X-Correlation-Id'));
    }
}
