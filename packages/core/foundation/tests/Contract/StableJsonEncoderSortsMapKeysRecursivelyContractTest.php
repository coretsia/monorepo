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

final class StableJsonEncoderSortsMapKeysRecursivelyContractTest extends TestCase
{
    public function testSortsMapKeysRecursivelyAndPreservesListOrder(): void
    {
        $json = StableJsonEncoder::encodeStable([
            'z' => 'last',
            'a' => [
                'z' => 'nested-last',
                'list' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                    [
                        'd' => 4,
                        'c' => 3,
                    ],
                ],
                'a' => 'nested-first',
            ],
            'm' => [
                'b' => 'latin-b',
                'A' => 'latin-uppercase',
                'a' => 'latin-lowercase',
            ],
        ]);

        self::assertSame(
            "{\"a\":{\"a\":\"nested-first\",\"list\":[{\"a\":1,\"b\":2},{\"c\":3,\"d\":4}],\"z\":\"nested-last\"},\"m\":{\"A\":\"latin-uppercase\",\"a\":\"latin-lowercase\",\"b\":\"latin-b\"},\"z\":\"last\"}\n",
            $json,
        );
    }

    public function testUsesByteOrderStringComparisonForMapKeys(): void
    {
        $json = StableJsonEncoder::encodeStable([
            'a2' => 2,
            'A' => 0,
            'a10' => 10,
        ]);

        self::assertSame(
            "{\"A\":0,\"a10\":10,\"a2\":2}\n",
            $json,
        );
    }

    public function testPreservesTopLevelListOrder(): void
    {
        $json = StableJsonEncoder::encodeStable([
            'b',
            'a',
            [
                'z' => 1,
                'a' => 2,
            ],
        ]);

        self::assertSame(
            "[\"b\",\"a\",{\"a\":2,\"z\":1}]\n",
            $json,
        );
    }

    public function testOutputUsesFinalLfAndNoCrLf(): void
    {
        $json = StableJsonEncoder::encodeStable([
            'b' => true,
            'a' => false,
        ]);

        self::assertSame(
            "{\"a\":false,\"b\":true}\n",
            $json,
        );

        self::assertStringEndsWith("\n", $json);
        self::assertStringNotContainsString("\r", $json);
    }

    public function testDoesNotEscapeUnicodeOrSlashes(): void
    {
        $json = StableJsonEncoder::encodeStable([
            'path' => '/coretsia/foundation',
            'unicode' => 'Привіт',
        ]);

        self::assertSame(
            "{\"path\":\"/coretsia/foundation\",\"unicode\":\"Привіт\"}\n",
            $json,
        );
    }
}
