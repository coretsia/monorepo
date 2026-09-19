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
use PHPUnit\Framework\TestCase;

final class DirectivesAllowlistMatchesCanonicalConfigMergeContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array CANONICAL_DIRECTIVE_NAMES = [
        'append',
        'prepend',
        'remove',
        'merge',
        'replace',
    ];

    /**
     * @var list<string>
     */
    private const array CANONICAL_DIRECTIVE_KEYS = [
        '@append',
        '@prepend',
        '@remove',
        '@merge',
        '@replace',
    ];

    public function testDirectiveNamesMatchCanonicalConfigMergePolicy(): void
    {
        self::assertSame(self::CANONICAL_DIRECTIVE_NAMES, ConfigDirective::names());
    }

    public function testDirectiveKeysMatchCanonicalConfigMergePolicy(): void
    {
        self::assertSame(self::CANONICAL_DIRECTIVE_KEYS, ConfigDirective::keys());
    }

    public function testDirectiveEnumCasesMatchCanonicalConfigMergePolicyWithoutExtraCases(): void
    {
        self::assertSame(
            self::CANONICAL_DIRECTIVE_NAMES,
            array_map(
                static fn (ConfigDirective $directive): string => $directive->value,
                ConfigDirective::cases(),
            ),
        );

        self::assertCount(5, ConfigDirective::cases());
    }

    public function testUnsupportedOrNonCanonicalDirectivesAreNotAllowed(): void
    {
        foreach (['set', 'delete', 'override', 'custom', 'env', 'clear', 'push'] as $name) {
            self::assertFalse(ConfigDirective::isAllowedName($name));
            self::assertFalse(ConfigDirective::isAllowedKey('@' . $name));
            self::assertTrue(ConfigDirective::isReservedDirectiveKey('@' . $name));
        }
    }

    public function testDirectiveValuesAreLowercaseAsciiAndUnprefixed(): void
    {
        foreach (ConfigDirective::cases() as $directive) {
            self::assertMatchesRegularExpression('/^[a-z]+$/', $directive->value);
            self::assertStringStartsNotWith('@', $directive->value);
            self::assertMatchesRegularExpression('/^@[a-z]+$/', $directive->key());
        }
    }
}
