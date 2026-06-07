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

final class ConfigExplainSmokeIntegrationTest extends TestCase
{
    public function testExplainReturnsBaselineSafeDeterministicShape(): void
    {
        $config = [
            'custom' => [
                'feature' => [
                    'enabled' => true,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_app' => 'main',
                    'default_env' => 'prod',
                ],
            ],
        ];

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'kernel',
                sourceId: 'core/kernel/config/defaults/kernel',
                path: 'framework/packages/core/kernel/config/kernel.php',
                keyPath: 'kernel',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'packageId' => 'core/kernel',
                    'sourceOrder' => 0,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'kernel',
                sourceId: 'skeleton-config/app_environment/split_root/kernel',
                path: 'skeleton/apps/main/config/environments/prod/kernel.php',
                keyPath: 'kernel.boot.default_env',
                directive: null,
                precedence: 401,
                redacted: false,
                meta: [
                    'appEnv' => 'prod',
                    'appTarget' => 'main',
                    'kind' => 'split_root_subtree',
                    'layer' => 'app_environment',
                    'sourceOrder' => 1,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'custom',
                sourceId: 'skeleton-config/app_environment/split_root/custom',
                path: 'skeleton/apps/main/config/environments/prod/custom.php',
                keyPath: 'custom',
                directive: null,
                precedence: 401,
                redacted: false,
                meta: [
                    'appEnv' => 'prod',
                    'appTarget' => 'main',
                    'kind' => 'split_root_subtree',
                    'layer' => 'app_environment',
                    'sourceOrder' => 2,
                ],
            ),
        ];

        $explain = new ConfigExplainer()->explain(
            config: $config,
            sources: $sources,
            validationSubjects: [
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
                'unvalidated' => [
                    [
                        'ownership' => 'user_owned',
                        'root' => 'custom',
                        'validation' => 'unvalidated',
                    ],
                ],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame(1, $explain['schemaVersion']);
        self::assertArrayHasKey('paths', $explain);
        self::assertArrayHasKey('roots', $explain);
        self::assertArrayHasKey('sourceRanks', $explain);
        self::assertArrayHasKey('sourceTypes', $explain);
        self::assertArrayHasKey('precedence', $explain);
        self::assertArrayHasKey('envOverlay', $explain);
        self::assertArrayHasKey('validation', $explain);

        self::assertSame(
            [
                'app_config',
                'package_default',
            ],
            $explain['sourceTypes'],
        );

        self::assertSame(
            [
                [
                    'ownership' => 'user_owned',
                    'root' => 'custom',
                    'validation' => 'unvalidated',
                ],
                [
                    'ownership' => 'ruleset_owned',
                    'root' => 'kernel',
                    'validation' => 'validated',
                ],
            ],
            $explain['roots'],
        );

        self::assertSame(
            [
                'custom',
                'custom.feature',
                'custom.feature.enabled',
                'kernel',
                'kernel.boot',
                'kernel.boot.default_app',
                'kernel.boot.default_env',
            ],
            \array_column($explain['paths'], 'path'),
        );

        $defaultEnvPath = self::pathRow($explain, 'kernel.boot.default_env');

        self::assertSame('kernel', $defaultEnvPath['root']);
        self::assertSame('app_config', $defaultEnvPath['sourceType']);
        self::assertSame('skeleton-config/app_environment/split_root/kernel', $defaultEnvPath['sourceId']);
        self::assertSame(401, $defaultEnvPath['sourcePrecedence']);
        self::assertSame(1, $defaultEnvPath['sourceOrder']);
        self::assertSame('scalar:string', $defaultEnvPath['valueShape']);
        self::assertSame(
            [
                'ownership' => 'ruleset_owned',
                'status' => 'validated',
            ],
            $defaultEnvPath['validation'],
        );

        $customPath = self::pathRow($explain, 'custom.feature.enabled');

        self::assertSame('custom', $customPath['root']);
        self::assertSame('app_config', $customPath['sourceType']);
        self::assertSame(
            [
                'ownership' => 'user_owned',
                'status' => 'unvalidated',
            ],
            $customPath['validation'],
        );

        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('main-secret', $encoded);
        self::assertStringNotContainsString('/var/', $encoded);
        self::assertStringNotContainsString('mysql://', $encoded);
        self::assertStringNotContainsString('token', $encoded);
    }

    /**
     * @param array<string,mixed> $explain
     *
     * @return array<string,mixed>
     */
    private static function pathRow(array $explain, string $path): array
    {
        foreach ($explain['paths'] as $row) {
            if ($row['path'] === $path) {
                return $row;
            }
        }

        self::fail('Missing explain path row: ' . $path);
    }
}
