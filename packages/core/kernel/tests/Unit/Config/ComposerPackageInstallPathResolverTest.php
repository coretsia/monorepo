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

namespace Coretsia\Kernel\Tests\Unit\Config;

use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use PHPUnit\Framework\TestCase;

final class ComposerPackageInstallPathResolverTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-composer-install-root-resolver-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testInjectedInstallRootIsResolvedExactly(): void
    {
        $installRoot = $this->temporaryDirectory . '/package';
        \mkdir($installRoot, 0777, true);

        $resolver = new ComposerPackageInstallPathResolver([
            'coretsia/core-kernel' => $installRoot,
        ]);

        self::assertSame(
            $installRoot,
            $resolver->resolve('coretsia/core-kernel'),
        );
    }

    public function testMissingPackageFailsWithSafeSourceInvalid(): void
    {
        self::assertSafeFailure(
            resolver: new ComposerPackageInstallPathResolver([]),
            composerName: 'coretsia/core-kernel',
        );
    }

    public function testUnsafeComposerNameFailsWithSafeSourceInvalid(): void
    {
        $installRoot = $this->temporaryDirectory . '/unsafe-name-package';
        \mkdir($installRoot, 0777, true);

        self::assertSafeFailure(
            resolver: new ComposerPackageInstallPathResolver([
                'coretsia/../kernel' => $installRoot,
            ]),
            composerName: 'coretsia/../kernel',
        );
    }

    public function testEmptyInstallRootFailsWithSafeSourceInvalid(): void
    {
        self::assertSafeFailure(
            resolver: new ComposerPackageInstallPathResolver([
                'coretsia/core-kernel' => '',
            ]),
            composerName: 'coretsia/core-kernel',
        );
    }

    public function testUnsafeInstallRootFailsWithSafeSourceInvalid(): void
    {
        $filesystemPath = \str_replace('\\', '/', $this->temporaryDirectory);
        $unsafeRoot = 'file://'
            . (\DIRECTORY_SEPARATOR === '\\' ? '/' : '')
            . $filesystemPath;

        self::assertTrue(\is_dir($unsafeRoot));

        self::assertSafeFailure(
            resolver: new ComposerPackageInstallPathResolver([
                'coretsia/core-kernel' => $unsafeRoot,
            ]),
            composerName: 'coretsia/core-kernel',
        );
    }

    public function testNonDirectoryInstallRootFailsWithSafeSourceInvalid(): void
    {
        $file = $this->temporaryDirectory . '/not-a-directory';
        \file_put_contents($file, 'fixture');

        self::assertSafeFailure(
            resolver: new ComposerPackageInstallPathResolver([
                'coretsia/core-kernel' => $file,
            ]),
            composerName: 'coretsia/core-kernel',
        );
    }

    private function assertSafeFailure(
        ComposerPackageInstallPathResolver $resolver,
        string $composerName,
    ): void {
        try {
            $resolver->resolve($composerName);

            self::fail('Expected ConfigInvalidException was not thrown.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_SOURCE_INVALID, $exception->reason());
            self::assertSame(
                'CORETSIA_CONFIG_INVALID: config-source-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $this->temporaryDirectory,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($composerName, $exception->getMessage());
        }
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

            @\unlink($itemPath);
        }

        @\rmdir($path);
    }
}
