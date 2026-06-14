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

use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class KernelRuntimeDriverNoForbiddenDepsContractTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_SOURCE_REFERENCES = [
        'Coretsia\\Platform\\',
        'Coretsia\\Integrations\\',
        'Psr\\Http\\Message\\',
        'Psr\\Http\\Server\\',
        'Coretsia\\Contracts\\Observability\\',
        'Psr\\Log\\LoggerInterface',
    ];

    public function testKernelRuntimeDriverSourceDoesNotReferenceAnyForbiddenDependency(): void
    {
        $this->assertRuntimeDriverSourceDoesNotContain(
            self::FORBIDDEN_SOURCE_REFERENCES,
        );
    }

    public function testKernelRuntimeDriverSourceDoesNotImportPlatformPackages(): void
    {
        $this->assertRuntimeDriverSourceDoesNotContain([
            'Coretsia\\Platform\\',
        ]);
    }

    public function testKernelRuntimeDriverSourceDoesNotImportPsr7OrPsr15Namespaces(): void
    {
        $this->assertRuntimeDriverSourceDoesNotContain([
            'Psr\\Http\\Message\\',
            'Psr\\Http\\Server\\',
        ]);
    }

    public function testKernelRuntimeDriverSourceDoesNotImportObservabilityPortsOrLoggerInterface(): void
    {
        $this->assertRuntimeDriverSourceDoesNotContain([
            'Coretsia\\Contracts\\Observability\\',
            'Psr\\Log\\LoggerInterface',
        ]);
    }

    public function testRuntimeDriverSourceScanCoversDriverAndRuntimeDriverExceptionFiles(): void
    {
        $relativeFiles = [];

        foreach ($this->runtimeDriverSourceFiles() as $file) {
            $relativeFiles[] = self::packageRelativePath($file);
        }

        self::assertSame(
            [
                'src/Runtime/Driver/BackgroundDriver.php',
                'src/Runtime/Driver/HttpDriver.php',
                'src/Runtime/Driver/RuntimeDriverGuard.php',
                'src/Runtime/Driver/RuntimeDrivers.php',
                'src/Runtime/Exception/RuntimeDriverConflictException.php',
                'src/Runtime/Exception/RuntimeDriverInvalidConfigException.php',
            ],
            $relativeFiles,
            'Runtime driver dependency contract test must scan all runtime-driver source files.',
        );
    }

    /**
     * @param list<non-empty-string> $forbiddenReferences
     */
    private function assertRuntimeDriverSourceDoesNotContain(array $forbiddenReferences): void
    {
        foreach ($this->runtimeDriverSourceFiles() as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            foreach ($forbiddenReferences as $forbiddenReference) {
                self::assertStringNotContainsString(
                    $forbiddenReference,
                    $source,
                    \sprintf(
                        'Runtime driver source file "%s" must not reference forbidden dependency "%s".',
                        self::normalizePath($file),
                        $forbiddenReference,
                    ),
                );
            }
        }
    }

    /**
     * @return list<non-empty-string>
     */
    private function runtimeDriverSourceFiles(): array
    {
        $packageRoot = self::kernelPackageRoot();

        $files = [];

        foreach (self::runtimeDriverSourceRoots() as $root) {
            self::assertDirectoryExists($root);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                if ($fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $path = $fileInfo->getPathname();

                if ($path === '') {
                    continue;
                }

                $files[] = self::normalizePath($path);
            }
        }

        foreach (self::runtimeDriverExceptionFiles() as $file) {
            self::assertFileExists($file);

            $files[] = self::normalizePath($file);
        }

        $files = \array_values(\array_unique($files));

        \usort(
            $files,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            self::assertStringStartsWith(
                self::normalizePath($packageRoot) . '/',
                $file,
                'Runtime driver source scan must stay inside core/kernel package.',
            );

            self::assertStringNotContainsString('/tests/', $file);
            self::assertStringNotContainsString('/Fixtures/', $file);
            self::assertStringNotContainsString('/fixtures/', $file);
        }

        /** @var list<non-empty-string> $files */
        return $files;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function runtimeDriverSourceRoots(): array
    {
        return [
            self::kernelPackageRoot() . '/src/Runtime/Driver',
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    private static function runtimeDriverExceptionFiles(): array
    {
        return [
            self::kernelPackageRoot() . '/src/Runtime/Exception/RuntimeDriverConflictException.php',
            self::kernelPackageRoot() . '/src/Runtime/Exception/RuntimeDriverInvalidConfigException.php',
        ];
    }

    private static function kernelPackageRoot(): string
    {
        $file = new ReflectionClass(RuntimeDriverGuard::class)->getFileName();

        self::assertIsString($file);

        return self::normalizePath(\dirname($file, 4));
    }

    private static function packageRelativePath(string $file): string
    {
        $packageRoot = self::normalizePath(self::kernelPackageRoot());
        $file = self::normalizePath($file);

        self::assertStringStartsWith($packageRoot . '/', $file);

        return \substr($file, \strlen($packageRoot) + 1);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
