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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use PHPUnit\Framework\TestCase;

final class DependencySyncErrorCodesTest extends TestCase
{
    public function testRegistryMatchesPublicConstantsExactlyWithoutDuplicatesAndIsSorted(): void
    {
        $reflection = new \ReflectionClass(DependencySyncErrorCodes::class);
        $expected = [];

        foreach (
            $reflection->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC) as $constant
        ) {
            $value = $constant->getValue();

            if (\is_string($value)) {
                $expected[] = $value;
            }
        }

        self::assertSame(\array_values(\array_unique($expected)), $expected);

        \usort(
            $expected,
            static fn (string $left, string $right): int => \strcmp(
                $left,
                $right,
            ),
        );

        $exact = [
            'APPLICATION_SET_INVALID',
            'BASELINE_NOT_INSTALLED',
            'CATALOG_INVALID',
            'CATALOG_MODULE_UNKNOWN',
            'COMPOSER_EXECUTION_FAILED',
            'COMPOSER_LOCK_STALE',
            'COMPOSER_MANIFEST_INVALID',
            'COMPOSER_MANIFEST_WRITE_FAILED',
            'COMPOSER_UNSUPPORTED_POLICY',
            'EXCLUDED_REQUIRED_DEPENDENCY',
            'INSTALLATION_INTENT_INVALID',
            'INSTALLED_METADATA_INVALID',
            'INSTALLED_PLAN_MISMATCH',
            'INSTALLED_VENDOR_INCOMPLETE',
            'MANAGED_STATE_CONFLICT',
            'MANAGED_STATE_INVALID',
            'MODULE_GRAPH_CONFLICT',
            'PRESET_POLICY_INVALID',
            'PROJECT_ROOT_INVALID',
            'PROJECT_STATE_CHANGED',
            'PROJECT_SYNC_LOCKED',
            'PROJECT_SYNC_LOCK_FAILED',
            'PROTECTED_THIRD_PARTY_ROOT_CHANGED',
            'RECOVERY_REQUIRED',
            'RECOVERY_STORAGE_FAILED',
            'VERIFICATION_EXECUTION_FAILED',
            'VERIFICATION_INPUT_INVALID',
        ];
        \usort(
            $exact,
            static fn (string $left, string $right): int => \strcmp(
                $left,
                $right,
            ),
        );

        self::assertSame($exact, DependencySyncErrorCodes::all());
        self::assertSame($expected, $exact);

        foreach ($exact as $code) {
            self::assertTrue(DependencySyncErrorCodes::has($code));
        }

        self::assertFalse(DependencySyncErrorCodes::has('UNKNOWN'));
    }
}
