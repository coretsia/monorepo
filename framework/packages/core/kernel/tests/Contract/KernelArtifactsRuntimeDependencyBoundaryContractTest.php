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

final class KernelArtifactsRuntimeDependencyBoundaryContractTest extends TestCase
{
    public function testArtifactFingerprintAndCacheRuntimeCodeDoesNotImportToolingNamespaces(): void
    {
        foreach (self::artifactRuntimeFiles() as $path) {
            $source = self::readFile($path);
            $relativePath = self::relativeToRepo($path);

            self::assertStringNotContainsString(
                'Coretsia\Tools\\',
                $source,
                $relativePath . ' must not import tooling namespaces.',
            );

            self::assertStringNotContainsString(
                'Coretsia\\\\Tools\\\\',
                $source,
                $relativePath . ' must not reference escaped tooling namespaces.',
            );
        }
    }

    public function testArtifactFingerprintAndCacheRuntimeCodeDoesNotImportDevtoolsPackages(): void
    {
        foreach (self::artifactRuntimeFiles() as $path) {
            $source = self::readFile($path);
            $relativePath = self::relativeToRepo($path);

            self::assertDoesNotMatchRegularExpression(
                '/devtools|coretsia\/devtools-internal-toolkit/i',
                $source,
                $relativePath . ' must not depend on devtools packages.',
            );
        }
    }

    public function testArtifactFingerprintAndCacheRuntimeCodeDoesNotImportPlatformPackages(): void
    {
        foreach (self::artifactRuntimeFiles() as $path) {
            $source = self::readFile($path);
            $relativePath = self::relativeToRepo($path);

            self::assertStringNotContainsString(
                'Coretsia\Platform\\',
                $source,
                $relativePath . ' must not import platform namespaces.',
            );

            self::assertStringNotContainsString(
                'Coretsia\\Platform\\',
                $source,
                $relativePath . ' must not import platform namespaces.',
            );

            self::assertStringNotContainsString(
                'framework/packages/platform',
                \str_replace('\\', '/', $source),
                $relativePath . ' must not reference platform package paths.',
            );
        }
    }

    public function testArtifactFingerprintAndCacheRuntimeCodeDoesNotReadFrameworkToolsTree(): void
    {
        foreach (self::artifactRuntimeFiles() as $path) {
            $source = \str_replace('\\', '/', self::readFile($path));
            $relativePath = self::relativeToRepo($path);

            self::assertStringNotContainsString(
                'framework/tools/',
                $source,
                $relativePath . ' must not read or reference framework/tools.',
            );
        }
    }

    public function testKernelTestsDoNotReferenceFrameworkTools(): void
    {
        foreach (self::kernelTestFiles() as $path) {
            if (
                \str_replace('\\', '/', $path)
                === \str_replace('\\', '/', __FILE__)
            ) {
                continue;
            }

            $source = self::readFile($path);
            $normalizedSource = \str_replace('\\', '/', $source);
            $relativePath = self::relativeToRepo($path);

            self::assertStringNotContainsString(
                'framework/tools/',
                $normalizedSource,
                $relativePath . ' must not reference framework/tools.',
            );

            self::assertDoesNotMatchRegularExpression(
                '~(?<![A-Za-z0-9_.-])tools/~',
                $normalizedSource,
                $relativePath . ' must not reference tooling paths.',
            );

            self::assertStringNotContainsString(
                'Coretsia\Tools\\',
                $source,
                $relativePath . ' must not import tooling namespaces.',
            );

            self::assertStringNotContainsString(
                'Coretsia\\\\Tools\\\\',
                $source,
                $relativePath . ' must not reference escaped tooling namespaces.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function artifactRuntimeFiles(): array
    {
        return self::phpFiles(self::packageRoot() . '/src/Artifacts');
    }

    /**
     * @return list<string>
     */
    private static function kernelTestFiles(): array
    {
        return self::phpFiles(self::packageRoot() . '/tests');
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        $files = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private static function readFile(string $path): string
    {
        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function relativeToRepo(string $path): string
    {
        return \str_replace('\\', '/', \substr($path, \strlen(self::repoRoot()) + 1));
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function repoRoot(): string
    {
        return \dirname(self::packageRoot(), 4);
    }
}
