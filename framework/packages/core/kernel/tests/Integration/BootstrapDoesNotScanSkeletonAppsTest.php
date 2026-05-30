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
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use PHPUnit\Framework\TestCase;

final class BootstrapDoesNotScanSkeletonAppsTest extends TestCase
{
    private static int $sequence = 0;

    public function testSelectedAppRemainsExplicitInputWhenMultipleSyntheticAppDirsExist(): void
    {
        $skeletonRoot = self::createSkeletonRoot('multiple-synthetic-app-dirs');

        try {
            self::createSyntheticAppDirs($skeletonRoot, [
                'web',
                'api',
                'console',
                'worker',
                'admin',
                'legacy',
                'zzz',
            ]);

            $config = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Console,
                ),
            );

            self::assertSame(AppTarget::Console, $config->appTarget());
            self::assertSame($skeletonRoot, $config->skeletonRoot());
            self::assertSame($skeletonRoot . '/apps/console', $config->appRoot());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testSiblingAppDirectoryPresenceDoesNotAffectSelectedResult(): void
    {
        $skeletonRoot = self::createSkeletonRoot('sibling-presence-does-not-affect-result');

        try {
            self::createSyntheticAppDirs($skeletonRoot, [
                'api',
                'worker',
            ]);

            $beforeAddingMoreSiblings = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Web,
                ),
            );

            self::assertSame(AppTarget::Web, $beforeAddingMoreSiblings->appTarget());
            self::assertSame($skeletonRoot . '/apps/web', $beforeAddingMoreSiblings->appRoot());

            self::createSyntheticAppDirs($skeletonRoot, [
                'web',
                'console',
                'admin',
                'legacy',
                'zzz',
            ]);

            $afterAddingMoreSiblings = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Web,
                ),
            );

            self::assertSame(AppTarget::Web, $afterAddingMoreSiblings->appTarget());
            self::assertSame($skeletonRoot . '/apps/web', $afterAddingMoreSiblings->appRoot());

            self::removeDirectory($skeletonRoot . '/apps/api');
            self::removeDirectory($skeletonRoot . '/apps/worker');
            self::removeDirectory($skeletonRoot . '/apps/console');
            self::removeDirectory($skeletonRoot . '/apps/admin');
            self::removeDirectory($skeletonRoot . '/apps/legacy');
            self::removeDirectory($skeletonRoot . '/apps/zzz');

            $afterRemovingSiblings = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Web,
                ),
            );

            self::assertSame(AppTarget::Web, $afterRemovingSiblings->appTarget());
            self::assertSame($skeletonRoot . '/apps/web', $afterRemovingSiblings->appRoot());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    private static function resolveBootstrapConfig(BootstrapInput $input): BootstrapConfig
    {
        return new BootstrapConfigResolver(
            overridesLoader: new BootstrapOverridesLoader(),
        )->resolve(
            input: $input,
            kernelConfig: self::kernelConfig(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelRoot() . '/config/kernel.php';

        self::assertIsArray($config);

        /**
         * @var array<string,mixed> $config
         */
        return $config;
    }

    private static function createSkeletonRoot(string $case): string
    {
        $root = self::normalizePath(
            \sys_get_temp_dir()
            . '/coretsia-kernel-bootstrap-does-not-scan-apps/'
            . \getmypid()
            . '/'
            . (++self::$sequence)
            . '-'
            . $case,
        );

        self::removeDirectory($root);
        self::ensureDirectory($root);

        return $root;
    }

    /**
     * @param list<non-empty-string> $appNames
     */
    private static function createSyntheticAppDirs(string $skeletonRoot, array $appNames): void
    {
        foreach ($appNames as $appName) {
            self::ensureDirectory($skeletonRoot . '/apps/' . $appName);
            self::ensureDirectory($skeletonRoot . '/apps/' . $appName . '/config');

            $marker = $skeletonRoot . '/apps/' . $appName . '/config/phase-a-must-not-read.php';

            $written = \file_put_contents(
                $marker,
                "<?php\n\nthrow new \\RuntimeException('phase-a-scanned-app-config-" . $appName . "');\n",
            );

            self::assertIsInt($written);
        }
    }

    private static function ensureDirectory(string $directory): void
    {
        if (\is_dir($directory)) {
            return;
        }

        self::assertTrue(
            \mkdir($directory, 0777, true),
            \sprintf('Failed to create test directory "%s".', $directory),
        );
    }

    private static function removeDirectory(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        if (\is_file($path) || \is_link($path)) {
            self::assertTrue(
                \unlink($path),
                \sprintf('Failed to remove test file "%s".', $path),
            );

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            if ($fileInfo->isDir() && !$fileInfo->isLink()) {
                self::assertTrue(
                    \rmdir($filePath),
                    \sprintf('Failed to remove test directory "%s".', $filePath),
                );

                continue;
            }

            self::assertTrue(
                \unlink($filePath),
                \sprintf('Failed to remove test file "%s".', $filePath),
            );
        }

        self::assertTrue(
            \rmdir($path),
            \sprintf('Failed to remove test directory "%s".', $path),
        );
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return self::normalizePath($root);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
