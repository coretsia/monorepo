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
    public function testSourceTypeValuesAreCanonicalAndOrdered(): void
    {
        self::assertSame(
            [
                'package_default',
                'skeleton_config',
                'app_config',
                'dotenv',
                'env',
                'cli',
                'runtime',
                'generated_artifact',
            ],
            ConfigSourceType::values(),
        );
    }

    public function testSourceTypeEnumCasesAreCanonical(): void
    {
        self::assertSame('package_default', ConfigSourceType::PackageDefault->value);
        self::assertSame('skeleton_config', ConfigSourceType::SkeletonConfig->value);
        self::assertSame('app_config', ConfigSourceType::AppConfig->value);
        self::assertSame('dotenv', ConfigSourceType::Dotenv->value);
        self::assertSame('env', ConfigSourceType::Env->value);
        self::assertSame('cli', ConfigSourceType::Cli->value);
        self::assertSame('runtime', ConfigSourceType::Runtime->value);
        self::assertSame('generated_artifact', ConfigSourceType::GeneratedArtifact->value);
    }

    public function testSourceTypeCasesMatchValuesWithoutExtraCases(): void
    {
        self::assertSame(
            ConfigSourceType::values(),
            array_map(
                static fn (ConfigSourceType $type): string => $type->value,
                ConfigSourceType::cases(),
            ),
        );

        self::assertCount(8, ConfigSourceType::cases());
    }

    public function testSourceTypeValuesAreLowercaseAsciiIdentifiers(): void
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

    public function testSourceTypeKnownCheckIsStrict(): void
    {
        foreach (ConfigSourceType::values() as $value) {
            self::assertTrue(ConfigSourceType::isKnown($value));
        }

        self::assertFalse(ConfigSourceType::isKnown(''));
        self::assertFalse(ConfigSourceType::isKnown('PackageDefault'));
        self::assertFalse(ConfigSourceType::isKnown('package-default'));
        self::assertFalse(ConfigSourceType::isKnown('package_default '));
        self::assertFalse(ConfigSourceType::isKnown('package_defaults'));
        self::assertFalse(ConfigSourceType::isKnown('application_config'));
        self::assertFalse(ConfigSourceType::isKnown('environment'));
        self::assertFalse(ConfigSourceType::isKnown('runtime_override'));
        self::assertFalse(ConfigSourceType::isKnown('filesystem'));
        self::assertFalse(ConfigSourceType::isKnown('composer'));
    }
}
