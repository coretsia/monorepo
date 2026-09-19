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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use PHPUnit\Framework\TestCase;

final class StableJsonEncoderRejectsFloatValuesContractTest extends TestCase
{
    public function testRejectsTopLevelFloatValuesWithStableMessage(): void
    {
        self::assertEncodingFailsWith(1.25, 'stable-json-float-forbidden', 'value');
        self::assertEncodingFailsWith(-1.25, 'stable-json-float-forbidden', 'value');
        self::assertEncodingFailsWith(\NAN, 'stable-json-float-forbidden', 'value');
        self::assertEncodingFailsWith(\INF, 'stable-json-float-forbidden', 'value');
        self::assertEncodingFailsWith(-\INF, 'stable-json-float-forbidden', 'value');
    }

    public function testRejectsNestedFloatValuesWithStableMessage(): void
    {
        self::assertEncodingFailsWith(
            [
                'safe' => true,
                'nested' => [
                    'value' => 1.25,
                ],
            ],
            'stable-json-float-forbidden',
            'value.nested.value',
        );

        self::assertEncodingFailsWith(
            [
                'safe',
                [
                    'nested' => \INF,
                ],
            ],
            'stable-json-float-forbidden',
            'value[1].nested',
        );
    }

    private static function assertEncodingFailsWith(
        mixed $value,
        string $expectedReason,
        string $expectedPath,
    ): void {
        try {
            StableJsonEncoder::encodeStable($value);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                $expectedReason . ': value at ' . $expectedPath,
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'Expected StableJsonEncoder to reject value with reason: '
            . $expectedReason
            . ' at path: '
            . $expectedPath,
        );
    }
}
