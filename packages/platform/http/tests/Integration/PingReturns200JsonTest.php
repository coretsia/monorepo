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

use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Foundation\Clock\FrozenClock;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRecordingClock;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointSequentialClock;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointThrowingClock;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class PingReturns200JsonTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testPingHasDeterministicJsonAndCanonicalNormalizedMatching(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['http' => ['https_redirect' => ['target_authority' => 'config-secret.example']]],
        );
        $previous = \getenv('CORETSIA_TEST_ENDPOINT_SECRET');

        try {
            \putenv('CORETSIA_TEST_ENDPOINT_SECRET=environment-secret');
            self::assertInstanceOf(FrozenClock::class, $fixture->clock());
            foreach (['/ping', 'ping', '//ping', '///ping'] as $path) {
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        $path,
                        'secret=query-secret',
                        ['Authorization' => ['Bearer header-secret']],
                    ),
                );
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                $body = (string) $response->getBody();
                self::assertSame("{\"ok\":true,\"ts\":1700000000}\n", $body);
                self::assertSame(
                    ['ok' => true, 'ts' => SystemEndpointRuntimeFixture::TIMESTAMP],
                    \json_decode(
                        $body,
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    )
                );
                foreach (['query-secret', 'header-secret', 'config-secret', 'environment-secret'] as $secret) {
                    self::assertStringNotContainsString($secret, $body);
                }
            }

            foreach (['/ping/', '/Ping', '/PING', '/%70ing', '/p%69ng'] as $path) {
                $response = $fixture->kernel()->handle($fixture->request('GET', $path));
                self::assertSame(404, $response->getStatusCode());
                self::assertSame('', (string) $response->getBody());
            }

            self::assertCount(4, $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            \putenv(
                $previous === false ? 'CORETSIA_TEST_ENDPOINT_SECRET' : 'CORETSIA_TEST_ENDPOINT_SECRET=' . $previous,
            );
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testSameResolvedRouterReadsTheLogicalClockOncePerClaimedRequest(string $mode): void
    {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            ['clock' => 'sequential', 'timestamps' => [1700000000, 1700000123]],
        );

        try {
            $router = $fixture->router();
            $clock = $fixture->clock();
            self::assertInstanceOf(SystemEndpointSequentialClock::class, $clock);

            // Only the terminal dispatcher runs here: unrelated lifecycle services
            // cannot contribute to the clock invocation count.
            self::assertSame(0, $clock->calls);
            self::assertNull($router->dispatch($fixture->request('POST', '/ping')));
            self::assertNull($router->dispatch($fixture->request('GET', '/ping/')));
            self::assertSame(0, $clock->calls);

            $first = $router->dispatch($fixture->request('GET', '/ping'));
            self::assertNotNull($first);
            self::assertSame("{\"ok\":true,\"ts\":1700000000}\n", (string) $first->getBody());
            self::assertSame(1, $clock->calls);

            self::assertSame($router, $fixture->router());
            self::assertSame($clock, $fixture->clock());
            $second = $router->dispatch($fixture->request('GET', '//ping'));
            self::assertNotNull($second);
            self::assertSame("{\"ok\":true,\"ts\":1700000123}\n", (string) $second->getBody());
            self::assertSame(2, $clock->calls);
            self::assertSame("{\"ok\":true,\"ts\":1700000000}\n", (string) $first->getBody());
            self::assertCount(2, $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testConstructionFailuresAreNotMatcherRejections(string $mode): void
    {
        foreach (['clock', 'response', 'stream'] as $stage) {
            foreach ([false, true] as $boundary) {
                foreach (['http-reserved-path-invalid', 'private-failure-diagnostic'] as $diagnostic) {
                    $fixture = SystemEndpointRuntimeFixture::boot(
                        $mode,
                        fn (string $class): object => $this->createStub($class),
                        [
                            'clock' => $stage === 'clock' ? 'throwing' : 'recording',
                            'problemDetails' => $boundary,
                            'reporter' => $boundary,
                        ],
                    );

                    try {
                        $failure = new \InvalidArgumentException($diagnostic);
                        $clock = $fixture->clock();
                        if ($stage === 'clock') {
                            self::assertInstanceOf(SystemEndpointThrowingClock::class, $clock);
                            $clock->failure = $failure;
                        } else {
                            self::assertInstanceOf(SystemEndpointRecordingClock::class, $clock);
                            if ($stage === 'response') {
                                $fixture->factories()->responseFailure = $failure;
                            } else {
                                $fixture->factories()->streamFailure = $failure;
                            }
                        }

                        if ($boundary) {
                            $response = $fixture->kernel()->handle($fixture->request('GET', '/ping'));
                            self::assertSame(500, $response->getStatusCode());
                            self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
                            self::assertStringNotContainsString($diagnostic, (string) $response->getBody());
                            self::assertCount(1, $fixture->reporter()->reports);
                            $descriptor = $fixture->reporter()->reports[0][0];
                            self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
                            self::assertSame('Unhandled exception.', $descriptor->message());
                            self::assertSame(ErrorSeverity::Error, $descriptor->severity());
                            self::assertSame([], $descriptor->extensions());
                            self::assertSame(
                                [[$fixture->runtime()->begins[0], 'handled_error', null]],
                                $fixture->runtime()->completions
                            );
                        } else {
                            $caught = null;
                            try {
                                $fixture->kernel()->handle($fixture->request('GET', '/ping'));
                            } catch (\Throwable $throwable) {
                                $caught = $throwable;
                            }
                            self::assertSame($failure, $caught);
                            self::assertSame(
                                [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                                $fixture->runtime()->completions
                            );
                        }

                        self::assertCount(1, $fixture->runtime()->begins);
                        self::assertSame(1, $clock->calls);
                        self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                    } finally {
                        $fixture->close();
                    }
                }
            }
        }
    }
}
