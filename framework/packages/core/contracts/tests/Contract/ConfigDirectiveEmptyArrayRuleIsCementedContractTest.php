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

final class ConfigDirectiveEmptyArrayRuleIsCementedContractTest extends TestCase
{
    private const string EMPTY_ARRAY_NO_OP = 'no_op';
    private const string EMPTY_ARRAY_REPLACES_TARGET = 'replaces_target_with_empty_array';

    /**
     * @var array<string,string>
     */
    private const array EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE = [
        'append' => self::EMPTY_ARRAY_NO_OP,
        'prepend' => self::EMPTY_ARRAY_NO_OP,
        'remove' => self::EMPTY_ARRAY_NO_OP,
        'merge' => self::EMPTY_ARRAY_NO_OP,
        'replace' => self::EMPTY_ARRAY_REPLACES_TARGET,
    ];

    public function testEmptyArrayRuleCoversExactlyTheDirectiveAllowlist(): void
    {
        self::assertSame(
            ConfigDirective::names(),
            array_keys(self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE),
        );
    }

    public function testAppendEmptyArrayIsCementedAsNoOp(): void
    {
        self::assertSame(
            self::EMPTY_ARRAY_NO_OP,
            self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE[ConfigDirective::Append->value]
        );
    }

    public function testPrependEmptyArrayIsCementedAsNoOp(): void
    {
        self::assertSame(
            self::EMPTY_ARRAY_NO_OP,
            self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE[ConfigDirective::Prepend->value]
        );
    }

    public function testRemoveEmptyArrayIsCementedAsNoOp(): void
    {
        self::assertSame(
            self::EMPTY_ARRAY_NO_OP,
            self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE[ConfigDirective::Remove->value]
        );
    }

    public function testMergeEmptyArrayIsCementedAsNoOp(): void
    {
        self::assertSame(
            self::EMPTY_ARRAY_NO_OP,
            self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE[ConfigDirective::Merge->value]
        );
    }

    public function testReplaceEmptyArrayIsCementedAsReplacesTargetWithEmptyArray(): void
    {
        self::assertSame(
            self::EMPTY_ARRAY_REPLACES_TARGET,
            self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE[ConfigDirective::Replace->value],
        );
    }

    public function testEmptyArrayPayloadIsExplicitAndNotMissing(): void
    {
        $payload = [];

        self::assertTrue(array_is_list($payload));
        self::assertSame([], $payload);
        self::assertArrayHasKey(ConfigDirective::Append->value, self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE);
        self::assertArrayHasKey(ConfigDirective::Replace->value, self::EMPTY_ARRAY_BEHAVIOR_BY_DIRECTIVE);
    }
}
