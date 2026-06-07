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

use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class ConfigPrecedenceMatrixTest extends TestCase
{
    public function testSsoTDocsDeclareTheActivePhaseBRankOrder(): void
    {
        $mergeOrder = self::readFile(self::mergeOrderDocPath());
        $matrix = self::readFile(self::precedenceMatrixDocPath());

        self::assertAppearsInOrder($mergeOrder, [
            'package defaults',
            'skeleton shared aggregate',
            'skeleton shared split root',
            'skeleton environment aggregate',
            'skeleton environment split root',
            'app shared aggregate',
            'app shared split root',
            'app environment aggregate',
            'app environment split root',
            'env overlays',
        ]);

        self::assertStringContainsString('directives before merge', $mergeOrder);
        self::assertStringContainsString('validation after final merge', $mergeOrder);
        self::assertStringContainsString('CLI/runtime overrides are reserved/future', $mergeOrder);
        self::assertStringContainsString('roots.php', $mergeOrder);
        self::assertStringContainsString('<root>.php', $mergeOrder);

        self::assertStringContainsString('rank `10`', $matrix);
        self::assertStringContainsString('rank `50`', $matrix);
        self::assertStringContainsString('rank `100`', $matrix);
        self::assertStringContainsString('rank `101`', $matrix);
        self::assertStringContainsString('rank `200`', $matrix);
        self::assertStringContainsString('rank `201`', $matrix);
        self::assertStringContainsString('rank `300`', $matrix);
        self::assertStringContainsString('rank `301`', $matrix);
        self::assertStringContainsString('rank `400`', $matrix);
        self::assertStringContainsString('rank `401`', $matrix);
        self::assertStringContainsString('rank `500`', $matrix);

        self::assertAppearsInOrder($matrix, [
            'Package defaults',
            'Preset/mode overlays',
            'Skeleton shared aggregate/root',
            'Skeleton environment aggregate/root',
            'App shared aggregate/root',
            'App environment aggregate/root',
            'Env overlays',
        ]);

        self::assertStringContainsString('Preset/mode overlays are reserved', $matrix);
        self::assertStringContainsString('NOT active Phase 1 ConfigKernel behavior', $matrix);
        self::assertStringContainsString('ConfigSourceType::PackageDefault', $matrix);
        self::assertStringContainsString('ConfigSourceType::SkeletonConfig', $matrix);
        self::assertStringContainsString('ConfigSourceType::AppConfig', $matrix);
        self::assertStringContainsString('ConfigSourceType::Env', $matrix);
    }

    public function testImplementationRankOrderMatchesPrecedenceMatrix(): void
    {
        $processor = self::processor();
        $merger = new ConfigMerger($processor);

        $entries = [
            [
                'rank' => 500,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'env_overlay',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 301,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'app_shared_root',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 100,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'skeleton_shared_aggregate',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 10,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'package_default',
                            'default_app' => 'package_app_survives_partial_overrides',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 401,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'app_environment_root',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 300,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'app_shared_aggregate',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 200,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'skeleton_environment_aggregate',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 101,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'skeleton_shared_root',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 201,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'skeleton_environment_root',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 400,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'app_environment_aggregate',
                        ],
                    ],
                ],
            ],
        ];

        \usort(
            $entries,
            static fn (array $a, array $b): int => ($a['rank'] <=> $b['rank']),
        );

        $merged = [];

        foreach ($entries as $entry) {
            $normalized = $processor->processGlobalConfig($entry['config']);
            $merged = $merger->merge($merged, $normalized);

            self::assertIsArray($merged);
        }

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'package_app_survives_partial_overrides',
                        'default_env' => 'env_overlay',
                    ],
                ],
            ],
            $merged,
        );
    }

    public function testRootSpecificEntriesOverrideAggregateEntriesAtTheSameLayer(): void
    {
        $processor = self::processor();
        $merger = new ConfigMerger($processor);

        $entries = [
            [
                'rank' => 100,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_app' => 'from_skeleton_shared_aggregate',
                            'default_env' => 'from_skeleton_shared_aggregate',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 101,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'from_skeleton_shared_root',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 200,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'from_skeleton_environment_aggregate',
                        ],
                    ],
                ],
            ],
            [
                'rank' => 201,
                'config' => [
                    'kernel' => [
                        'boot' => [
                            'default_env' => 'from_skeleton_environment_root',
                        ],
                    ],
                ],
            ],
        ];

        $merged = [];

        foreach ($entries as $entry) {
            $merged = $merger->merge(
                $merged,
                $processor->processGlobalConfig($entry['config']),
            );

            self::assertIsArray($merged);
        }

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from_skeleton_shared_aggregate',
                        'default_env' => 'from_skeleton_environment_root',
                    ],
                ],
            ],
            $merged,
        );
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: new ConfigNamespaceGuard([
                'coretsia',
                '_internal',
            ]),
        );
    }

    private static function readFile(string $path): string
    {
        $contents = \file_get_contents($path);

        self::assertIsString($contents, 'Unable to read file: ' . $path);

        return $contents;
    }

    /**
     * @param list<non-empty-string> $needles
     */
    private static function assertAppearsInOrder(string $haystack, array $needles): void
    {
        $normalizedHaystack = \strtolower($haystack);
        $offset = 0;

        foreach ($needles as $needle) {
            $normalizedNeedle = \strtolower($needle);
            $position = \strpos($normalizedHaystack, $normalizedNeedle, $offset);

            self::assertNotFalse(
                $position,
                'Expected text not found in order: ' . $needle,
            );

            $offset = $position + \strlen($normalizedNeedle);
        }
    }

    private static function mergeOrderDocPath(): string
    {
        return __DIR__ . '/../../../../../../docs/ssot/config-merge-order.md';
    }

    private static function precedenceMatrixDocPath(): string
    {
        return __DIR__ . '/../../../../../../docs/ssot/config-precedence-matrix.md';
    }
}
