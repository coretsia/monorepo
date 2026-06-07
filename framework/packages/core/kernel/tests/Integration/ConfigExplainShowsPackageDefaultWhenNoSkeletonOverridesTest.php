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

final class ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest extends TestCase
{
    public function testExplainShowsPackageDefaultAsEffectiveSourceWhenNoStrongerSourceTouchesPath(): void
    {
        $config = [
            'kernel' => [
                'boot' => [
                    'default_app' => 'main',
                    'default_env' => 'prod',
                ],
                'config' => [
                    'forbidden_top_level_roots' => [
                        'coretsia',
                        '_internal',
                    ],
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
                    'moduleId' => 'core.kernel',
                    'packageId' => 'core/kernel',
                    'sourceOrder' => 0,
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
                'unvalidated' => [],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [
                [
                    'kind' => 'package_default',
                    'moduleId' => 'core.kernel',
                    'packageId' => 'core/kernel',
                    'path' => 'framework/packages/core/kernel/config/kernel.php',
                    'root' => 'kernel',
                    'sourceId' => 'core/kernel/config/defaults/kernel',
                    'type' => 'package_default',
                ],
            ],
        );

        self::assertSame(
            [
                'package_default',
            ],
            $explain['sourceTypes'],
        );

        $defaultEnv = self::pathRow($explain, 'kernel.boot.default_env');
        $defaultApp = self::pathRow($explain, 'kernel.boot.default_app');
        $forbiddenRoots = self::pathRow($explain, 'kernel.config.forbidden_top_level_roots');

        foreach ([$defaultEnv, $defaultApp, $forbiddenRoots] as $row) {
            self::assertSame('package_default', $row['sourceType']);
            self::assertSame('core/kernel/config/defaults/kernel', $row['sourceId']);
            self::assertSame('framework/packages/core/kernel/config/kernel.php', $row['sourcePath']);
            self::assertSame(10, $row['sourcePrecedence']);
            self::assertSame(0, $row['sourceOrder']);
            self::assertFalse($row['redacted']);
            self::assertSame(
                [
                    'ownership' => 'ruleset_owned',
                    'status' => 'validated',
                ],
                $row['validation'],
            );
        }

        self::assertSame(
            [
                [
                    'kind' => 'package_default',
                    'moduleId' => 'core.kernel',
                    'packageId' => 'core/kernel',
                    'path' => 'framework/packages/core/kernel/config/kernel.php',
                    'root' => 'kernel',
                    'sourceId' => 'core/kernel/config/defaults/kernel',
                    'type' => 'package_default',
                ],
            ],
            $explain['owners'],
        );

        self::assertSame(
            [
                [
                    'directive' => null,
                    'keyPath' => 'kernel',
                    'meta' => [
                        'kind' => 'package_default',
                        'moduleId' => 'core.kernel',
                        'packageId' => 'core/kernel',
                        'sourceOrder' => 0,
                    ],
                    'order' => 0,
                    'path' => 'framework/packages/core/kernel/config/kernel.php',
                    'precedence' => 10,
                    'redacted' => false,
                    'root' => 'kernel',
                    'sourceId' => 'core/kernel/config/defaults/kernel',
                    'type' => 'package_default',
                ],
            ],
            $explain['sourceRanks'],
        );
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
