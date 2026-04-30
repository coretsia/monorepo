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

use Coretsia\Contracts\Config\ConfigSourceType;
use PHPUnit\Framework\TestCase;

final class ConfigSourceTypeIsStableContractTest extends TestCase
{
    public function test_source_type_values_are_canonical_and_ordered(): void
    {
        self::assertSame(
            [
                'package_defaults',
                'application_config',
                'environment',
                'runtime_override',
                'generated_artifact',
            ],
            ConfigSourceType::values(),
        );
    }

    public function test_source_type_enum_cases_are_canonical(): void
    {
        self::assertSame('package_defaults', ConfigSourceType::PackageDefaults->value);
        self::assertSame('application_config', ConfigSourceType::ApplicationConfig->value);
        self::assertSame('environment', ConfigSourceType::Environment->value);
        self::assertSame('runtime_override', ConfigSourceType::RuntimeOverride->value);
        self::assertSame('generated_artifact', ConfigSourceType::GeneratedArtifact->value);
    }

    public function test_source_type_cases_match_values_without_extra_cases(): void
    {
        self::assertSame(
            ConfigSourceType::values(),
            array_map(
                static fn (ConfigSourceType $type): string => $type->value,
                ConfigSourceType::cases(),
            ),
        );

        self::assertCount(5, ConfigSourceType::cases());
    }

    public function test_source_type_values_are_lowercase_ascii_identifiers(): void
    {
        foreach (ConfigSourceType::values() as $value) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $value);
            self::assertStringNotContainsString('-', $value);
            self::assertStringNotContainsString('@', $value);
            self::assertStringNotContainsString('/', $value);
            self::assertStringNotContainsString('\\', $value);
            self::assertStringNotContainsString(':', $value);
        }
    }

    public function test_source_type_known_check_is_strict(): void
    {
        self::assertTrue(ConfigSourceType::isKnown('package_defaults'));
        self::assertTrue(ConfigSourceType::isKnown('application_config'));
        self::assertTrue(ConfigSourceType::isKnown('environment'));
        self::assertTrue(ConfigSourceType::isKnown('runtime_override'));
        self::assertTrue(ConfigSourceType::isKnown('generated_artifact'));

        self::assertFalse(ConfigSourceType::isKnown(''));
        self::assertFalse(ConfigSourceType::isKnown('PackageDefaults'));
        self::assertFalse(ConfigSourceType::isKnown('package-defaults'));
        self::assertFalse(ConfigSourceType::isKnown('package_defaults '));
        self::assertFalse(ConfigSourceType::isKnown('filesystem'));
        self::assertFalse(ConfigSourceType::isKnown('composer'));
        self::assertFalse(ConfigSourceType::isKnown('dotenv'));
    }
}
