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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use PHPUnit\Framework\TestCase;

final class ConfigExplainReturnsStableSourceTypesTest extends TestCase
{
    public function testConfigSourceTypeVocabularyIsStable(): void
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

        foreach (ConfigSourceType::values() as $value) {
            self::assertTrue(ConfigSourceType::isKnown($value));
        }

        self::assertFalse(ConfigSourceType::isKnown('unknown'));
        self::assertFalse(ConfigSourceType::isKnown('package-default'));
        self::assertFalse(ConfigSourceType::isKnown('skeleton'));
    }

    public function testExplainReturnsKnownSourceTypesSortedAndDeduplicated(): void
    {
        $sources = [
            self::source(ConfigSourceType::Runtime, 'runtime/kernel', 700, 7),
            self::source(ConfigSourceType::PackageDefault, 'package/kernel', 10, 0),
            self::source(ConfigSourceType::Env, 'env/kernel', 500, 5),
            self::source(ConfigSourceType::SkeletonConfig, 'skeleton/kernel', 100, 1),
            self::source(ConfigSourceType::AppConfig, 'app/kernel', 300, 3),
            self::source(ConfigSourceType::GeneratedArtifact, 'artifact/kernel', 800, 8),
            self::source(ConfigSourceType::Dotenv, 'dotenv/kernel', 450, 4),
            self::source(ConfigSourceType::Cli, 'cli/kernel', 600, 6),
            self::source(ConfigSourceType::PackageDefault, 'package/kernel/duplicate', 11, 9),
        ];

        $explain = new ConfigExplainer()->explain(
            config: [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
            ],
            sources: $sources,
            validationSubjects: [
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
                'unvalidated' => [],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame(
            [
                'app_config',
                'cli',
                'dotenv',
                'env',
                'generated_artifact',
                'package_default',
                'runtime',
                'skeleton_config',
            ],
            $explain['sourceTypes'],
        );

        self::assertSame(
            self::sorted($explain['sourceTypes']),
            $explain['sourceTypes'],
        );

        self::assertSame(
            \array_values(\array_unique($explain['sourceTypes'])),
            $explain['sourceTypes'],
        );
    }

    public function testExplainSourceRanksAreDeterministicByPrecedencePathSourceIdAndType(): void
    {
        $sources = [
            self::source(ConfigSourceType::Env, 'z/env', 500, 3, 'kernel.boot.default_env'),
            self::source(ConfigSourceType::PackageDefault, 'b/package', 10, 1, 'kernel.boot.default_env'),
            self::source(ConfigSourceType::PackageDefault, 'a/package', 10, 0, 'kernel.boot.default_app'),
            self::source(ConfigSourceType::SkeletonConfig, 'a/skeleton', 100, 2, 'kernel.boot.default_env'),
        ];

        $explain = new ConfigExplainer()->explain(
            config: [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'main',
                        'default_env' => 'prod',
                    ],
                ],
            ],
            sources: $sources,
            validationSubjects: [
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
                'unvalidated' => [],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame(
            [
                'a/package',
                'b/package',
                'a/skeleton',
                'z/env',
            ],
            \array_column($explain['sourceRanks'], 'sourceId'),
        );
    }

    private static function source(
        ConfigSourceType $type,
        string $sourceId,
        int $precedence,
        int $sourceOrder,
        string $keyPath = 'kernel.boot.default_env',
    ): ConfigValueSource {
        return new ConfigValueSource(
            type: $type,
            root: 'kernel',
            sourceId: $sourceId,
            path: 'framework/packages/core/kernel/config/kernel.php',
            keyPath: $keyPath,
            directive: null,
            precedence: $precedence,
            redacted: $type === ConfigSourceType::Env,
            meta: [
                'kind' => 'source_type_fixture',
                'sourceOrder' => $sourceOrder,
            ],
        );
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        \sort($values, \SORT_STRING);

        return $values;
    }
}
