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

use Coretsia\Contracts\Env\EnvValue;
use LogicException;
use PHPUnit\Framework\TestCase;

final class EnvMissingVsEmptyIsDistinctContractTest extends TestCase
{
    public function test_missing_value_is_not_present_and_has_no_string_value(): void
    {
        $value = EnvValue::missing();

        self::assertTrue($value->isMissing());
        self::assertFalse($value->isPresent());
        self::assertFalse($value->isEmptyString());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing env value has no string value.');

        $value->value();
    }

    public function test_present_empty_string_is_present_and_distinct_from_missing(): void
    {
        $value = EnvValue::present('');

        self::assertFalse($value->isMissing());
        self::assertTrue($value->isPresent());
        self::assertTrue($value->isEmptyString());
        self::assertSame('', $value->value());
    }

    public function test_present_non_empty_string_is_present_and_not_empty_string(): void
    {
        $value = EnvValue::present('value');

        self::assertFalse($value->isMissing());
        self::assertTrue($value->isPresent());
        self::assertFalse($value->isEmptyString());
        self::assertSame('value', $value->value());
    }

    public function test_missing_empty_string_and_non_empty_string_are_three_distinct_states(): void
    {
        $missing = EnvValue::missing();
        $empty = EnvValue::present('');
        $nonEmpty = EnvValue::present('value');

        self::assertTrue($missing->isMissing());
        self::assertFalse($empty->isMissing());
        self::assertFalse($nonEmpty->isMissing());

        self::assertFalse($missing->isPresent());
        self::assertTrue($empty->isPresent());
        self::assertTrue($nonEmpty->isPresent());

        self::assertFalse($missing->isEmptyString());
        self::assertTrue($empty->isEmptyString());
        self::assertFalse($nonEmpty->isEmptyString());

        self::assertSame('', $empty->value());
        self::assertSame('value', $nonEmpty->value());
    }

    public function test_php_truthiness_must_not_define_env_presence(): void
    {
        $empty = EnvValue::present('');

        self::assertSame('', $empty->value());
        self::assertFalse((bool)$empty->value());
        self::assertTrue($empty->isPresent());
    }
}
