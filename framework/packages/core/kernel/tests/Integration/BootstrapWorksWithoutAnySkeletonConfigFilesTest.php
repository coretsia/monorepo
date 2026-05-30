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

use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use PHPUnit\Framework\TestCase;

final class BootstrapWorksWithoutAnySkeletonConfigFilesTest extends TestCase
{
    private static int $sequence = 0;

    public function testBootstrapFallsBackToPackageDefaultsWithoutAnySkeletonConfigFiles(): void
    {
        $skeletonRoot = self::createBareSkeletonRoot('package-defaults');

        try {
            self::assertDirectoryExists($skeletonRoot);
            self::assertDirectoryDoesNotExist($skeletonRoot . '/config');
            self::assertDirectoryDoesNotExist($skeletonRoot . '/apps');
            self::assertFileDoesNotExist($skeletonRoot . '/.env');
            self::assertFileDoesNotExist($skeletonRoot . '/.env.local');

            $config = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
            );

            self::assertSame('local', $config->appEnv());
            self::assertSame('micro', $config->preset());
            self::assertFalse($config->debug());
            self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $config->envSourcePolicy());
            self::assertSame(AppTarget::Api, $config->appTarget());
            self::assertSame($skeletonRoot, $config->skeletonRoot());
            self::assertSame($skeletonRoot . '/apps/api', $config->appRoot());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testEnvRepositoryBuildsEmptyStrictDotenvSnapshotWithoutDotenvFiles(): void
    {
        $skeletonRoot = self::createBareSkeletonRoot('empty-env-repository');

        try {
            self::assertDirectoryExists($skeletonRoot);
            self::assertDirectoryDoesNotExist($skeletonRoot . '/config');
            self::assertDirectoryDoesNotExist($skeletonRoot . '/apps');
            self::assertFileDoesNotExist($skeletonRoot . '/.env');
            self::assertFileDoesNotExist($skeletonRoot . '/.env.local');
            self::assertFileDoesNotExist($skeletonRoot . '/.env.local.local');

            $config = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Console,
                ),
            );

            self::assertSame('local', $config->appEnv());
            self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $config->envSourcePolicy());

            $repository = self::buildEnvRepository($config);

            self::assertSame([], $repository->all());
            self::assertFalse($repository->has('CORETSIA_BARE_SKELETON_TEST'));
            self::assertNull($repository->sourceOf('CORETSIA_BARE_SKELETON_TEST'));
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testExplicitInputStillWinsWithoutSkeletonConfigFiles(): void
    {
        $skeletonRoot = self::createBareSkeletonRoot('explicit-input-wins');

        try {
            self::assertDirectoryExists($skeletonRoot);
            self::assertDirectoryDoesNotExist($skeletonRoot . '/config');

            $config = self::resolveBootstrapConfig(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Worker,
                    appEnv: 'staging',
                    preset: 'worker',
                    debug: true,
                    envSourcePolicy: BootstrapEnvSourcePolicy::AllowSystem,
                ),
            );

            self::assertSame('staging', $config->appEnv());
            self::assertSame('worker', $config->preset());
            self::assertTrue($config->debug());
            self::assertSame(BootstrapEnvSourcePolicy::AllowSystem, $config->envSourcePolicy());
            self::assertSame(AppTarget::Worker, $config->appTarget());
            self::assertSame($skeletonRoot . '/apps/worker', $config->appRoot());
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

    private static function buildEnvRepository(BootstrapConfig $config): EnvRepositoryInterface
    {
        return new EnvRepositoryBuilder(
            dotenvLoader: new DotenvLoader(),
        )->build(
            config: $config,
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

    private static function createBareSkeletonRoot(string $case): string
    {
        $root = self::normalizePath(
            \sys_get_temp_dir()
            . '/coretsia-kernel-bootstrap-bare-skeleton/'
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
