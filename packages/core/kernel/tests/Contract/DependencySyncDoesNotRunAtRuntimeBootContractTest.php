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

final class DependencySyncDoesNotRunAtRuntimeBootContractTest extends TestCase
{
    public function testBootCompositionSourcesDoNotDependOnDependencySync(): void
    {
        $artifactRuntimeBooter = \str_replace(
            '\\',
            '/',
            \dirname(__DIR__, 2) . '/src/Boot/ArtifactRuntimeBooter.php',
        );

        foreach (
            self::files([
                'src/Boot',
                'src/Container',
                'src/Provider',
            ]) as $file
        ) {
            if (
                \str_replace('\\', '/', $file)
                === $artifactRuntimeBooter
            ) {
                continue;
            }

            $source = \file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Coretsia\\Kernel\\DependencySync\\',
                $source,
                $file,
            );
        }
    }

    /** @param list<string> $directories @return list<string> */
    private static function files(array $directories): array
    {
        $root = \dirname(__DIR__, 2);
        $files = [];

        foreach ($directories as $directory) {
            $path = $root . '/' . $directory;
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
