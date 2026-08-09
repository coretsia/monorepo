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
    public function testEnvPolicyValuesAreCanonicalAndOrdered(): void
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

    public function testRequiredPolicyTreatsMissingValueAsValidationViolation(): void
    {
        $policy = EnvPolicy::Required;

        self::assertTrue($policy->missingIsViolation());
        self::assertFalse($policy->missingMayUseDefault());
        self::assertFalse($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function testOptionalPolicyKeepsMissingValueMissing(): void
    {
        $policy = EnvPolicy::Optional;

        self::assertFalse($policy->missingIsViolation());
        self::assertFalse($policy->missingMayUseDefault());
        self::assertTrue($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function testDefaultedPolicyAllowsSafeDefaultOnlyForMissingValue(): void
    {
        $policy = EnvPolicy::Defaulted;

        self::assertFalse($policy->missingIsViolation());
        self::assertTrue($policy->missingMayUseDefault());
        self::assertFalse($policy->missingRemainsMissing());
        self::assertTrue($policy->presentValueWins());
    }

    public function testPresentEmptyStringWinsOverDefaultForEveryPolicy(): void
    {
        $value = EnvValue::present('');

        self::assertTrue($value->isPresent());
        self::assertTrue($value->isEmptyString());
        self::assertSame('', $value->value());

        foreach ([EnvPolicy::Required, EnvPolicy::Optional, EnvPolicy::Defaulted] as $policy) {
            self::assertTrue($policy->presentValueWins());
        }
    }

    public function testPolicyKnownCheckIsStrict(): void
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
