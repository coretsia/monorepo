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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

final class RedactionEnumsContractTest extends TestCase
{
    public function testRedactionKindIsExactStringBackedEnum(): void
    {
        $reflection = new ReflectionEnum(RedactionKind::class);

        self::assertTrue($reflection->isBacked());
        self::assertSame('string', $reflection->getBackingType()?->getName());
        self::assertSame(
            [
                'Unknown' => 'unknown',
                'Secret' => 'secret',
                'SecretReference' => 'secret-reference',
                'Credential' => 'credential',
                'Authorization' => 'authorization',
                'Cookie' => 'cookie',
                'SessionId' => 'session-id',
                'Token' => 'token',
                'Payload' => 'payload',
                'Sql' => 'sql',
                'Pii' => 'pii',
                'EnvValue' => 'env-value',
                'LocalPath' => 'local-path',
            ],
            self::enumNameValueMap(RedactionKind::cases()),
        );
        self::assertSame(
            ['name', 'value'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
    }

    public function testRedactionModeIsExactStringBackedEnumWithoutRawOrDisabledModes(): void
    {
        $reflection = new ReflectionEnum(RedactionMode::class);

        self::assertTrue($reflection->isBacked());
        self::assertSame('string', $reflection->getBackingType()?->getName());
        self::assertSame(
            [
                'Placeholder' => 'placeholder',
                'Length' => 'length',
                'Hash' => 'hash',
                'HashAndLength' => 'hash-and-length',
            ],
            self::enumNameValueMap(RedactionMode::cases()),
        );

        foreach (['raw', 'none', 'disabled', 'passthrough', 'debug'] as $forbidden) {
            self::assertNull(RedactionMode::tryFrom($forbidden));
        }

        self::assertSame(
            ['name', 'value'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
    }

    /**
     * @param list<RedactionKind|RedactionMode> $cases
     *
     * @return array<string, string>
     */
    private static function enumNameValueMap(array $cases): array
    {
        $result = [];

        foreach ($cases as $case) {
            $result[$case->name] = $case->value;
        }

        return $result;
    }
}
