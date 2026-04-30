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

use Coretsia\Contracts\Env\EnvPolicy;
use Coretsia\Contracts\Env\EnvValue;
use PHPUnit\Framework\TestCase;

final class EnvPolicyPrecedenceContractTest extends TestCase
{
    public function test_env_policy_values_are_canonical_and_ordered(): void
    {
        self::assertSame(
            [
                'required',
                'optional',
                'defaulted',
            ],
            EnvPolicy::values(),
        );

        self::assertSame('required', EnvPolicy::Required->value);
        self::assertSame('optional', EnvPolicy::Optional->value);
        self::assertSame('defaulted', EnvPolicy::Defaulted->value);
    }

    public function test_required_policy_treats_missing_value_as_validation_violation(): void
    {
        $policy = EnvPolicy::Required;

        self::assertTrue($policy->missingIsViolation());
        self::assertFalse($policy->missingMayUseDefault());
        self::assertFalse($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function test_optional_policy_keeps_missing_value_missing(): void
    {
        $policy = EnvPolicy::Optional;

        self::assertFalse($policy->missingIsViolation());
        self::assertFalse($policy->missingMayUseDefault());
        self::assertTrue($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function test_defaulted_policy_allows_safe_default_only_for_missing_value(): void
    {
        $policy = EnvPolicy::Defaulted;

        self::assertFalse($policy->missingIsViolation());
        self::assertTrue($policy->missingMayUseDefault());
        self::assertFalse($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function test_present_empty_string_wins_over_default_for_every_policy(): void
    {
        $value = EnvValue::present('');

        self::assertTrue($value->isPresent());
        self::assertTrue($value->isEmptyString());
        self::assertSame('', $value->value());

        foreach ([EnvPolicy::Required, EnvPolicy::Optional, EnvPolicy::Defaulted] as $policy) {
            self::assertTrue($policy->presentValueWins());
        }
    }

    public function test_policy_known_check_is_strict(): void
    {
        self::assertTrue(EnvPolicy::isKnown('required'));
        self::assertTrue(EnvPolicy::isKnown('optional'));
        self::assertTrue(EnvPolicy::isKnown('defaulted'));

        self::assertFalse(EnvPolicy::isKnown(''));
        self::assertFalse(EnvPolicy::isKnown('REQUIRED'));
        self::assertFalse(EnvPolicy::isKnown('required '));
        self::assertFalse(EnvPolicy::isKnown('fallback'));
    }
}
