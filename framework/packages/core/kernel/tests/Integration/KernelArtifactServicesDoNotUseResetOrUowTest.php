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

use PHPUnit\Framework\TestCase;

final class KernelArtifactServicesDoNotUseResetOrUowTest extends TestCase
{
    public function testArtifactCompileDoesNotInvokeResetOrchestrator(): void
    {
        $source = self::sourceFile('src/Artifacts/Compiler/ArtifactCompiler.php');

        self::assertStringNotContainsString('ResetOrchestrator', $source);
        self::assertStringNotContainsString('resetAll(', $source);
        self::assertStringNotContainsString('ResetInterface', $source);
    }

    public function testCacheVerifyDoesNotInvokeResetOrchestrator(): void
    {
        $source = self::sourceFile('src/Artifacts/Verifier/CacheVerifier.php');

        self::assertStringNotContainsString('ResetOrchestrator', $source);
        self::assertStringNotContainsString('resetAll(', $source);
        self::assertStringNotContainsString('ResetInterface', $source);
    }

    public function testArtifactCompileDoesNotStartUnitOfWork(): void
    {
        $source = self::sourceFile('src/Artifacts/Compiler/ArtifactCompiler.php');

        self::assertStringNotContainsString('UnitOfWork', $source);
        self::assertStringNotContainsString('KernelRuntime', $source);
        self::assertStringNotContainsString('KernelRuntimeInterface', $source);
        self::assertStringNotContainsString('startUnitOfWork(', $source);
        self::assertStringNotContainsString('runUnitOfWork(', $source);
        self::assertStringNotContainsString('invokeBeforeUnitOfWork', $source);
        self::assertStringNotContainsString('invokeAfterUnitOfWork', $source);
    }

    public function testCacheVerifyDoesNotStartUnitOfWork(): void
    {
        $source = self::sourceFile('src/Artifacts/Verifier/CacheVerifier.php');

        self::assertStringNotContainsString('UnitOfWork', $source);
        self::assertStringNotContainsString('KernelRuntime', $source);
        self::assertStringNotContainsString('KernelRuntimeInterface', $source);
        self::assertStringNotContainsString('startUnitOfWork(', $source);
        self::assertStringNotContainsString('runUnitOfWork(', $source);
        self::assertStringNotContainsString('invokeBeforeUnitOfWork', $source);
        self::assertStringNotContainsString('invokeAfterUnitOfWork', $source);
    }

    public function testArtifactSourceTreeDoesNotImportResetOrRuntimeLifecycleTypes(): void
    {
        foreach (self::phpFiles(self::packageRoot() . '/src/Artifacts') as $path) {
            $source = self::readFile($path);
            $relativePath = self::relativeToPackage($path);

            self::assertStringNotContainsString(
                'Coretsia\Foundation\Runtime\Reset\ResetOrchestrator',
                $source,
                $relativePath . ' must not import ResetOrchestrator.',
            );

            self::assertStringNotContainsString(
                'Coretsia\Contracts\Runtime\UnitOfWork',
                $source,
                $relativePath . ' must not import UnitOfWork contracts.',
            );

            self::assertStringNotContainsString(
                'Coretsia\Kernel\Runtime',
                $source,
                $relativePath . ' must not import Kernel runtime lifecycle classes.',
            );
        }
    }

    private static function sourceFile(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        return self::stripPhpComments(self::readFile($path));
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

    private static function relativeToPackage(string $path): string
    {
        return \str_replace('\\', '/', \substr($path, \strlen(self::packageRoot()) + 1));
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function stripPhpComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }
}
