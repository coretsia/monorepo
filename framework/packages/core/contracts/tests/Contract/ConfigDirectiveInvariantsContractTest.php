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

use Coretsia\Contracts\Config\ConfigDirective;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigDirectiveInvariantsContractTest extends TestCase
{
    public function testDirectiveNamesAreCanonicalAndOrdered(): void
    {
        self::assertSame(
            [
                'append',
                'prepend',
                'remove',
                'merge',
                'replace',
            ],
            ConfigDirective::names(),
        );
    }

    public function testDirectiveKeysAreCanonicalAndOrdered(): void
    {
        self::assertSame(
            [
                '@append',
                '@prepend',
                '@remove',
                '@merge',
                '@replace',
            ],
            ConfigDirective::keys(),
        );
    }

    public function testDirectiveKeyIsPrefixedName(): void
    {
        foreach (ConfigDirective::cases() as $directive) {
            self::assertSame('@' . $directive->value, $directive->key());
        }
    }

    public function testAllowedNameDetectionIsStrict(): void
    {
        self::assertTrue(ConfigDirective::isAllowedName('append'));
        self::assertTrue(ConfigDirective::isAllowedName('prepend'));
        self::assertTrue(ConfigDirective::isAllowedName('remove'));
        self::assertTrue(ConfigDirective::isAllowedName('merge'));
        self::assertTrue(ConfigDirective::isAllowedName('replace'));

        self::assertFalse(ConfigDirective::isAllowedName('@append'));
        self::assertFalse(ConfigDirective::isAllowedName('Append'));
        self::assertFalse(ConfigDirective::isAllowedName('append '));
        self::assertFalse(ConfigDirective::isAllowedName('set'));
        self::assertFalse(ConfigDirective::isAllowedName(''));
    }

    public function testAllowedKeyDetectionIsStrict(): void
    {
        self::assertTrue(ConfigDirective::isAllowedKey('@append'));
        self::assertTrue(ConfigDirective::isAllowedKey('@prepend'));
        self::assertTrue(ConfigDirective::isAllowedKey('@remove'));
        self::assertTrue(ConfigDirective::isAllowedKey('@merge'));
        self::assertTrue(ConfigDirective::isAllowedKey('@replace'));

        self::assertFalse(ConfigDirective::isAllowedKey('append'));
        self::assertFalse(ConfigDirective::isAllowedKey('@Append'));
        self::assertFalse(ConfigDirective::isAllowedKey('@append '));
        self::assertFalse(ConfigDirective::isAllowedKey('@set'));
        self::assertFalse(ConfigDirective::isAllowedKey(''));
    }

    public function testReservedDirectiveNamespaceDetectionIsPrefixBased(): void
    {
        self::assertTrue(ConfigDirective::isReservedDirectiveKey('@append'));
        self::assertTrue(ConfigDirective::isReservedDirectiveKey('@set'));
        self::assertTrue(ConfigDirective::isReservedDirectiveKey('@'));
        self::assertTrue(ConfigDirective::isReservedDirectiveKey('@@custom'));

        self::assertFalse(ConfigDirective::isReservedDirectiveKey('append'));
        self::assertFalse(ConfigDirective::isReservedDirectiveKey('config.@append'));
        self::assertFalse(ConfigDirective::isReservedDirectiveKey(''));
    }

    public function testTryFromKeyResolvesOnlyAllowedDirectiveKeys(): void
    {
        self::assertSame(ConfigDirective::Append, ConfigDirective::tryFromKey('@append'));
        self::assertSame(ConfigDirective::Prepend, ConfigDirective::tryFromKey('@prepend'));
        self::assertSame(ConfigDirective::Remove, ConfigDirective::tryFromKey('@remove'));
        self::assertSame(ConfigDirective::Merge, ConfigDirective::tryFromKey('@merge'));
        self::assertSame(ConfigDirective::Replace, ConfigDirective::tryFromKey('@replace'));

        self::assertNull(ConfigDirective::tryFromKey('append'));
        self::assertNull(ConfigDirective::tryFromKey('@set'));
        self::assertNull(ConfigDirective::tryFromKey('@Append'));
        self::assertNull(ConfigDirective::tryFromKey(''));
    }

    public function testFromKeyRejectsUnknownOrUnprefixedKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid config directive key.');

        ConfigDirective::fromKey('@set');
    }
}
