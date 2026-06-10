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

use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpikeJsonFloatForbiddenLockTest extends TestCase
{
    /**
     * @param list<string> $forbiddenNeedles
     */
    #[DataProvider('forbiddenFloatPayloadProvider')]
    public function testSpikeFloatPayloadsAreRejectedWithSafeDiagnostics(
        string $payloadName,
        string $expectedPath,
        array $forbiddenNeedles,
    ): void {
        try {
            PayloadNormalizer::normalizePayload(
                self::payload($payloadName),
                'artifact',
            );

            self::fail('Expected JsonFloatForbiddenException was not thrown.');
        } catch (JsonFloatForbiddenException $exception) {
            self::assertSame(JsonFloatForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(JsonFloatForbiddenException::REASON_FLOAT_FORBIDDEN, $exception->reason());
            self::assertSame($expectedPath, $exception->path());
            self::assertStringContainsString($expectedPath, $exception->getMessage());

            foreach ($forbiddenNeedles as $forbiddenNeedle) {
                self::assertStringNotContainsString(
                    $forbiddenNeedle,
                    $exception->getMessage(),
                    'Float diagnostics must not leak raw rejected values.',
                );
            }
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function forbiddenFloatPayloadProvider(): iterable
    {
        yield 'nested float' => [
            'forbidden_float_nested',
            'artifact.a.b[3].c',
            [
                '1.25',
            ],
        ];

        yield 'nan' => [
            'forbidden_float_nan',
            'artifact.meta.value',
            [
                'NAN',
                'nan',
            ],
        ];

        yield 'inf' => [
            'forbidden_float_inf',
            'artifact.meta.value',
            [
                'INF',
                'inf',
            ],
        ];

        yield 'negative inf' => [
            'forbidden_float_ninf',
            'artifact.meta.value',
            [
                '-INF',
                '-inf',
                'INF',
                'inf',
            ],
        ];
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
