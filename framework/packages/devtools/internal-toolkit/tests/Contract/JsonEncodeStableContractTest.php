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

namespace Coretsia\Devtools\InternalToolkit\Tests\Contract;

use Coretsia\Devtools\InternalToolkit\Json;
use PHPUnit\Framework\TestCase;

final class JsonEncodeStableContractTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function testEncodeStableSortsMapKeysRecursively(): void
    {
        $in = [
            'b' => 1,
            'a' => [
                'd' => 1,
                'c' => 2,
            ],
        ];

        self::assertSame('{"a":{"c":2,"d":1},"b":1}', Json::encodeStable($in));
    }

    /**
     * @throws \JsonException
     */
    public function testEncodeStablePreservesListOrder(): void
    {
        $in = [
            'list' => [3, 2, 1],
        ];

        self::assertSame('{"list":[3,2,1]}', Json::encodeStable($in));
    }

    /**
     * @throws \JsonException
     */
    public function testEncodeStablePreservesUnicodeAndSlashes(): void
    {
        $in = [
            'text' => 'Привіт/世界',
        ];

        self::assertSame('{"text":"Привіт/世界"}', Json::encodeStable($in));
    }

    /**
     * @throws \JsonException
     */
    public function testEncodeStableEncodesEmptyArrayAsList(): void
    {
        self::assertSame('[]', Json::encodeStable([]));
    }

    /**
     * @throws \JsonException
     */
    public function testEncodeStableRejectsFloatDeterministically(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CORETSIA_JSON_FLOAT_FORBIDDEN:a');

        Json::encodeStable(['a' => 1.25]);
    }

    /**
     * @throws \JsonException
     */
    public function testEncodeStableRejectsNonStringMapKeysDeterministically(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CORETSIA_INTERNAL_TOOLKIT_JSON_UNSUPPORTED_TYPE:1');

        // Not a list (keys are not 0..n-1), therefore treated as map; non-string key is forbidden by policy.
        Json::encodeStable([1 => 'x']);
    }
}
