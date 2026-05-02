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
use Coretsia\Contracts\Config\ConfigValueSource;
use PHPUnit\Framework\TestCase;

final class ConfigSourceTypeEnumMatchesPhase0PrecedenceLockContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array SOURCE_TYPE_VALUES = [
        'package_default',
        'skeleton_config',
        'app_config',
        'dotenv',
        'env',
        'cli',
        'runtime',
        'generated_artifact',
    ];

    public function test_source_type_enum_matches_source_tracking_vocabulary(): void
    {
        self::assertSame(self::SOURCE_TYPE_VALUES, ConfigSourceType::values());
    }

    public function test_source_type_enum_does_not_define_intrinsic_precedence(): void
    {
        foreach (ConfigSourceType::cases() as $type) {
            self::assertFalse(method_exists($type, 'precedence'));
        }
    }

    public function test_precedence_is_explicit_source_trace_metadata_not_source_type_metadata(): void
    {
        $lowRank = new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'foundation',
            sourceId: 'env.runtime',
            path: 'env/runtime',
            keyPath: 'container.cache',
            precedence: 10,
        );

        $highRank = new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'foundation',
            sourceId: 'env.runtime',
            path: 'env/runtime',
            keyPath: 'container.cache',
            precedence: 40,
        );

        self::assertSame(ConfigSourceType::Env, $lowRank->type());
        self::assertSame(ConfigSourceType::Env, $highRank->type());

        self::assertSame(10, $lowRank->precedence());
        self::assertSame(40, $highRank->precedence());
    }

    public function test_source_type_vocabulary_order_is_not_a_merge_precedence_contract(): void
    {
        $trace = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'core.foundation',
            path: 'packages/core/foundation/config/foundation.php',
            keyPath: 'container.autowire',
            precedence: 90,
        );

        self::assertSame(ConfigSourceType::PackageDefault, $trace->type());
        self::assertSame(90, $trace->precedence());
        self::assertSame('package_default', $trace->toArray()['type']);
        self::assertSame(90, $trace->toArray()['precedence']);
    }

    public function test_source_type_expansion_requires_contract_test_update(): void
    {
        self::assertSame(
            self::SOURCE_TYPE_VALUES,
            array_map(
                static fn (ConfigSourceType $type): string => $type->value,
                ConfigSourceType::cases(),
            ),
        );

        self::assertCount(count(self::SOURCE_TYPE_VALUES), ConfigSourceType::cases());
    }
}
