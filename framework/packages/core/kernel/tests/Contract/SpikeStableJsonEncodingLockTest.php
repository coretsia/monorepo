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
use PHPUnit\Framework\TestCase;

final class SpikeStableJsonEncodingLockTest extends TestCase
{
    public function testLargeNestedMixedPayloadKeepsPhaseZeroStableJsonHash(): void
    {
        $json = StableJsonEncoder::encodeStable(self::payload('ok_large_nested_mixed'));

        self::assertSame(
            '4303bbc32ad17027048905230e9cf888a6c9bcd821e17d1374dbdbe31206efe8',
            \hash('sha256', $json),
        );

        self::assertStringNotContainsString("\r", $json);
        self::assertStringEndsWith("\n", $json);
        self::assertFalse(
            \str_ends_with($json, "\n\n"),
            'Stable JSON bytes must end with exactly one final LF.',
        );
    }

    public function testHttpMiddlewarePayloadKeepsPhaseZeroStableJsonHash(): void
    {
        $json = StableJsonEncoder::encodeStable(self::payload('ok_http_middleware_config'));

        self::assertSame(
            '2844ad7e182865687ed4306e591788a8024102188dd0990b7e28c1aa4c073d03',
            \hash('sha256', $json),
        );

        self::assertStringContainsString('"middleware"', $json);
        self::assertStringContainsString('"system_pre"', $json);
        self::assertStringContainsString('"route_post"', $json);
        self::assertStringNotContainsString("\r", $json);
        self::assertStringEndsWith("\n", $json);
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
