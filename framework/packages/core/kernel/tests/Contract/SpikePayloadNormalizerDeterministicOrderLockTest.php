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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class SpikePayloadNormalizerDeterministicOrderLockTest extends TestCase
{
    public function testLargeNestedMixedPayloadNormalizesMapsAndPreservesLists(): void
    {
        $normalized = PayloadNormalizer::normalizePayload(
            self::payload('ok_large_nested_mixed'),
            'payload',
        );

        self::assertSame(
            [
                'a',
                'empty',
                'flags',
                'matrix',
                'strings',
                'tree',
                'z',
            ],
            \array_keys($normalized),
        );

        self::assertSame(
            [
                [1, 2, 3, 4, 5],
                [5, 4, 3, 2, 1],
                [2, 3, 5, 7, 11],
            ],
            $normalized['matrix'],
        );

        self::assertSame(
            [
                'node1',
                'node2',
                'node3',
            ],
            \array_keys($normalized['tree']),
        );

        self::assertSame(
            '4303bbc32ad17027048905230e9cf888a6c9bcd821e17d1374dbdbe31206efe8',
            \hash('sha256', StableJsonEncoder::encodeStable($normalized)),
        );
    }

    public function testHttpMiddlewarePayloadNormalizesMiddlewareSlotMapWithoutChangingLists(): void
    {
        $normalized = PayloadNormalizer::normalizePayload(
            self::payload('ok_http_middleware_config'),
            'payload',
        );

        self::assertSame(
            [
                'meta',
                'middleware',
                'opt_in',
                'schema_version',
            ],
            \array_keys($normalized),
        );

        self::assertSame(
            [
                'app',
                'app_post',
                'app_pre',
                'route',
                'route_post',
                'route_pre',
                'system',
                'system_post',
                'system_pre',
            ],
            \array_keys($normalized['middleware']),
        );

        self::assertSame(
            [
                'Coretsia\\Http\\Middleware\\CorrelationIdMiddleware',
                'Coretsia\\Http\\Middleware\\RequestIdMiddleware',
                'Coretsia\\Http\\Middleware\\TraceContextMiddleware',
                'Coretsia\\Http\\Middleware\\HttpMetricsMiddleware',
                'Coretsia\\Http\\Middleware\\AccessLogMiddleware',
                'Coretsia\\Http\\Maintenance\\MaintenanceMiddleware',
            ],
            $normalized['middleware']['system_pre'],
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function payload(string $name): array
    {
        $payloads = self::payloads();

        self::assertArrayHasKey($name, $payloads);
        self::assertIsArray($payloads[$name]);

        /** @var array<string,mixed> $payload */
        $payload = $payloads[$name];

        return $payload;
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    private static function payloads(): array
    {
        $path = self::repoRoot() . '/framework/tools/spikes/fixtures/payloads_min/payloads.php';

        self::assertFileExists($path);

        $payloads = require $path;

        self::assertIsArray($payloads);

        /** @var array<string, array<string,mixed>> $payloads */
        return $payloads;
    }

    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 6);
    }
}
