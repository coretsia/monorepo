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

namespace Coretsia\Kernel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class DependencySyncArtifactIsolationContractTest extends TestCase
{
    public function testDependencySyncAndArtifactRuntimeBoundariesAreBidirectionallyIsolated(): void
    {
        $root = \dirname(__DIR__, 2);

        self::assertDirectoryExists(
            $root . '/src/DependencySync',
        );
        self::assertFileExists(
            $root . '/bin/dependency-sync-verify.php',
        );
        self::assertDirectoryExists(
            $root . '/src/Artifacts',
        );
        self::assertFileExists(
            $root . '/src/Boot/ArtifactRuntimeBooter.php',
        );
        self::assertDirectoryExists(
            $root . '/src/Runtime',
        );

        foreach (
            self::phpFiles([
                $root . '/src/DependencySync',
                $root . '/bin/dependency-sync-verify.php',
            ]) as $file
        ) {
            $source = \file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Coretsia\\Kernel\\Artifacts\\',
                $source,
                $file,
            );
            self::assertStringNotContainsString(
                'Coretsia\\Kernel\\Runtime\\',
                $source,
                $file,
            );
            self::assertStringNotContainsString(
                'ArtifactRuntimeBooter',
                $source,
                $file,
            );
        }

        foreach (
            self::phpFiles([
                $root . '/src/Artifacts',
                $root . '/src/Boot/ArtifactRuntimeBooter.php',
                $root . '/src/Runtime',
            ]) as $file
        ) {
            $source = \file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Coretsia\\Kernel\\DependencySync\\',
                $source,
                $file,
            );
        }
    }

    /** @param list<string> $paths @return list<string> */
    private static function phpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
                continue;
            }

            if (!\is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $path,
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        \sort($files, \SORT_STRING);

        return $files;
    }
}
