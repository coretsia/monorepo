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

use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointEarlierMiddleware;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class SystemMetricsEndpointReturns501PlaintextUntilOwnerAvailableTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMetricsModuleAloneDoesNotClaimTheHttpEndpoint(string $mode): void
    {
        foreach ([false, true] as $selected) {
            $fixture = SystemEndpointRuntimeFixture::boot(
                $mode,
                fn (string $class): object => $this->createStub($class),
                ['metricsModule' => $selected, 'recordMeter' => false],
            );

            try {
                self::assertSame(
                    $selected,
                    $fixture->container->get(ModulePlan::class)->hasEnabledModule('platform.metrics'),
                );
                self::assertInstanceOf($selected ? DefaultMeter::class : NoopMeter::class, $fixture->meter());
                self::assertSame(
                    $selected,
                    \in_array(MetricsRendererInterface::class, $fixture->container->serviceIds(), true),
                );
                $kernel = $fixture->kernel();
                $resolved = new \ReflectionProperty(Container::class, 'resolved');
                $resolvedBefore = \array_keys($resolved->getValue($fixture->container));

                foreach (['/metrics', 'metrics', '///metrics'] as $path) {
                    $response = $kernel->handle(
                        $fixture->request(
                            'GET',
                            $path,
                            'token=private-query',
                            ['Authorization' => ['Bearer private-credential']],
                        ),
                    );

                    self::assertSame(501, $response->getStatusCode(), $path);
                    self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
                    self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                    self::assertSame([
                        'Content-Type' => ['text/plain; charset=utf-8'],
                        'Cache-Control' => ['no-store'],
                        'X-Correlation-Id' => [SystemEndpointRuntimeFixture::CORRELATION_ID],
                        'X-Content-Type-Options' => ['nosniff'],
                        'X-Frame-Options' => ['DENY'],
                        'Referrer-Policy' => ['no-referrer'],
                    ], $response->getHeaders());
                    self::assertSame("owner missing\n", (string) $response->getBody());
                }

                // The selected module contributes the renderer lazily. Dispatch
                // must not resolve it merely to decide whether an owner exists.
                self::assertArrayNotHasKey(MetricsRendererInterface::class, $resolved->getValue($fixture->container));
                self::assertSame($resolvedBefore, \array_keys($resolved->getValue($fixture->container)));
                self::assertSame([501, 501, 501], $fixture->factories()->statuses);
                self::assertSame(\array_fill(0, 3, "owner missing\n"), $fixture->factories()->bodies);
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testMetricsIsAnExactDeclarationNotAPrefix(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot($mode, fn (string $class): object => $this->createStub($class));

        try {
            foreach (['/metrics/', '/metrics/prometheus', '/metrics//child', '/metrics%2F', '/metricsz'] as $path) {
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
            $response = $fixture->kernel()->handle($fixture->request('GET', '/metrics'));

            self::assertSame(202, $response->getStatusCode());
            self::assertSame('earlier response', (string) $response->getBody());
            self::assertSame(1, $fixture->container->get(SystemEndpointEarlierMiddleware::class)->calls);
            self::assertSame([202], $fixture->factories()->statuses);
            self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }
}
