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

final class StableJsonEncoderRejectsNonJsonLikeValuesContractTest extends TestCase
{
    public function testRejectsObjectsWithStableMessage(): void
    {
        self::assertEncodingFailsWith(
            new \stdClass(),
            'stable-json-object-forbidden',
        );
    }

    public function testRejectsClosuresWithStableMessage(): void
    {
        self::assertEncodingFailsWith(
            static fn (): string => 'unsafe',
            'stable-json-closure-forbidden',
        );
    }

    public function testRejectsResourcesWithStableMessage(): void
    {
        $resource = \fopen('php://memory', 'rb');

        if ($resource === false) {
            throw new \RuntimeException('test-resource-open-failed');
        }

        try {
            self::assertEncodingFailsWith(
                $resource,
                'stable-json-resource-forbidden',
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testRejectsNestedObjectsAndClosuresWithStableMessages(): void
    {
        self::assertEncodingFailsWith(
            [
                'safe' => true,
                'nested' => [
                    'value' => new \stdClass(),
                ],
            ],
            'stable-json-object-forbidden',
        );

        self::assertEncodingFailsWith(
            [
                'safe',
                [
                    'value' => static fn (): string => 'unsafe',
                ],
            ],
            'stable-json-closure-forbidden',
        );
    }

    public function testRejectsNonStringMapKeysOutsideListSemanticsWithStableMessage(): void
    {
        self::assertEncodingFailsWith(
            [
                1 => 'one',
            ],
            'stable-json-map-key-must-be-string',
        );

        self::assertEncodingFailsWith(
            [
                0 => 'zero',
                2 => 'two',
            ],
            'stable-json-map-key-must-be-string',
        );

        self::assertEncodingFailsWith(
            [
                'safe' => true,
                0 => 'not-a-list-map-key',
            ],
            'stable-json-map-key-must-be-string',
        );
    }

    public function testCallableLookingStringsRemainPlainStrings(): void
    {
        self::assertSame(
            "\"trim\"\n",
            StableJsonEncoder::encodeStable('trim'),
        );
    }

    private static function assertEncodingFailsWith(mixed $value, string $expectedMessage): void
    {
        try {
            StableJsonEncoder::encodeStable($value);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());

            return;
        }

        self::fail('Expected StableJsonEncoder to reject value with message: ' . $expectedMessage);
    }
}
