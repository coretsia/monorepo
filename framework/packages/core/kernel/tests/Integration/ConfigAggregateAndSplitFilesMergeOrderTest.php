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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class ConfigAggregateAndSplitFilesMergeOrderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-config-aggregate-split-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testSkeletonRootsAndSplitRootFilesAreLoadedAndSplitOverridesAggregateAtSameLayer(): void
    {
        self::writePhpReturn(
            $this->temporaryDirectory . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-skeleton-roots',
                        'default_env' => 'from-skeleton-roots',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/config/kernel.php',
            [
                'boot' => [
                    'default_env' => 'from-skeleton-split-root',
                ],
            ],
        );

        $loaded = self::loader()->load(
            bootstrapConfig: self::bootstrapConfig($this->temporaryDirectory),
            splitRoots: [
                'kernel',
            ],
        );

        self::assertSame(
            [
                'skeleton/config/roots.php',
                'skeleton/config/kernel.php',
            ],
            \array_column($loaded['entries'], 'path'),
        );

        $merged = self::foldEntries($loaded['entries']);

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-skeleton-roots',
                        'default_env' => 'from-skeleton-split-root',
                    ],
                ],
            ],
            $merged,
        );
    }

    public function testAppSplitRootOverridesAppRootsAtSameLayer(): void
    {
        self::writePhpReturn(
            $this->temporaryDirectory . '/apps/web/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-app-roots',
                        'default_env' => 'from-app-roots',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/apps/web/config/kernel.php',
            [
                'boot' => [
                    'default_env' => 'from-app-split-root',
                ],
            ],
        );

        $loaded = self::loader()->load(
            bootstrapConfig: self::bootstrapConfig($this->temporaryDirectory),
            splitRoots: [
                'kernel',
            ],
        );

        self::assertSame(
            [
                'skeleton/apps/web/config/roots.php',
                'skeleton/apps/web/config/kernel.php',
            ],
            \array_column($loaded['entries'], 'path'),
        );

        $merged = self::foldEntries($loaded['entries']);

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-app-roots',
                        'default_env' => 'from-app-split-root',
                    ],
                ],
            ],
            $merged,
        );
    }

    public function testEquivalentAggregateAndSplitStylesProduceEquivalentFinalConfig(): void
    {
        $aggregateOnlyRoot = $this->temporaryDirectory . '/aggregate-only';
        $splitOnlyRoot = $this->temporaryDirectory . '/split-only';

        self::writePhpReturn(
            $aggregateOnlyRoot . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'web',
                        'default_env' => 'prod',
                    ],
                ],
                'project' => [
                    'feature' => [
                        'enabled' => true,
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $splitOnlyRoot . '/config/kernel.php',
            [
                'boot' => [
                    'default_app' => 'web',
                    'default_env' => 'prod',
                ],
            ],
        );

        self::writePhpReturn(
            $splitOnlyRoot . '/config/project.php',
            [
                'feature' => [
                    'enabled' => true,
                ],
            ],
        );

        $aggregateConfig = self::foldEntries(
            self::loader()->load(
                bootstrapConfig: self::bootstrapConfig($aggregateOnlyRoot),
                splitRoots: [],
            )['entries'],
        );

        $splitConfig = self::foldEntries(
            self::loader()->load(
                bootstrapConfig: self::bootstrapConfig($splitOnlyRoot),
                splitRoots: [
                    'kernel',
                    'project',
                ],
            )['entries'],
        );

        self::assertSame($aggregateConfig, $splitConfig);
    }

    /**
     * @param list<array{config: array<string,mixed>, precedence: int}> $entries
     *
     * @return array<string,mixed>
     */
    private static function foldEntries(array $entries): array
    {
        \usort(
            $entries,
            static fn (array $a, array $b): int => ($a['precedence'] <=> $b['precedence'])
                ?: \strcmp($a['path'], $b['path']),
        );

        $processor = self::processor();
        $merger = new ConfigMerger($processor);
        $merged = [];

        foreach ($entries as $entry) {
            $merged = $merger->merge($merged, $entry['config']);

            self::assertIsArray($merged);
        }

        /** @var array<string,mixed> $merged */
        return $merged;
    }

    private static function loader(): SkeletonConfigLoader
    {
        return new SkeletonConfigLoader(
            directiveProcessor: self::processor(),
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

    private static function bootstrapConfig(string $skeletonRoot): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: 'default',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function writePhpReturn(string $path, array $value): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($value, true) . ";\n",
        );
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = \scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}
