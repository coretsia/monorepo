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

use Coretsia\Foundation\Container\Container;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointEarlierMiddleware;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class SystemHealthEndpointsReturn501UntilOwnerAvailableTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testReservedHealthFamilyUsesCanonicalMatcherAndSafePayload(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot($mode, fn (string $class): object => $this->createStub($class));

        try {
            self::assertFalse($fixture->container->get(ModulePlan::class)->hasEnabledModule('platform.health'));
            $kernel = $fixture->kernel();
            $resolved = new \ReflectionProperty(Container::class, 'resolved');
            $resolvedBefore = \array_keys($resolved->getValue($fixture->container));

            $paths = [
                '/health',
                '/health/',
                '/health/ready',
                '/health/deep/ready',
                'health',
                '//health///ready',
                '/health/%2Fready',
            ];
            foreach ($paths as $path) {
                $response = $kernel->handle(
                    $fixture->request(
                        'GET',
                        $path,
                        'token=request-secret',
                        ['Authorization' => ['Bearer credential-secret']],
                    ),
                );

                self::assertSame(501, $response->getStatusCode(), $path);
                self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                self::assertSame([
                    'Content-Type' => ['application/json'],
                    'Cache-Control' => ['no-store'],
                    'X-Correlation-Id' => [SystemEndpointRuntimeFixture::CORRELATION_ID],
                    'X-Content-Type-Options' => ['nosniff'],
                    'X-Frame-Options' => ['DENY'],
                    'Referrer-Policy' => ['no-referrer'],
                ], $response->getHeaders());
                $body = (string) $response->getBody();
                self::assertSame("{\"error\":\"owner missing\"}\n", $body);
                self::assertSame(['error' => 'owner missing'], \json_decode($body, true, 512, JSON_THROW_ON_ERROR));
            }

            // No endpoint owner, renderer, or other service needs lazy
            // discovery after the canonical terminal pipeline is resolved.
            self::assertSame($resolvedBefore, \array_keys($resolved->getValue($fixture->container)));
            self::assertSame(\array_fill(0, \count($paths), 501), $fixture->factories()->statuses);
            self::assertSame(\array_fill(0, \count($paths), [
                'http.system_endpoint_total',
                1,
                ['operation' => 'health', 'status' => 501, 'outcome' => 'owner_unavailable'],
            ]), $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testUnrelatedAndEncodedAliasesRemainUnmatched(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot($mode, fn (string $class): object => $this->createStub($class));

        try {
            foreach (
                [
                    '/healthz',
                    '/healthz/ready',
                    '/health%2Fready',
                    '/health%2f',
                    '/health%2F/ready',
                    '/%2Fhealth',
                    '/Health',
                ] as $path
            ) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                self::assertSame(404, $response->getStatusCode(), $path);
                self::assertSame('', (string) $response->getBody());
                self::assertFalse($response->hasHeader('Content-Type'));
                self::assertFalse($response->hasHeader('Cache-Control'));
            }

            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testEarlierOwnerControlledResponseTakesPrecedence(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['earlier' => true],
        );

        try {
            foreach (['/health', '/health/', '/health/ready'] as $path) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                self::assertSame(202, $response->getStatusCode());
                self::assertSame('earlier response', (string) $response->getBody());
            }

            self::assertSame(3, $fixture->container->get(SystemEndpointEarlierMiddleware::class)->calls);
            self::assertSame([202, 202, 202], $fixture->factories()->statuses);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }
}
