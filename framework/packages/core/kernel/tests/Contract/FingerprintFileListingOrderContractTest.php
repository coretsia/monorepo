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

use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

final class FingerprintFileListingOrderContractTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = \sys_get_temp_dir()
            . '/coretsia-fingerprint-listing-order-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryRoot);

        parent::tearDown();
    }

    public function testListsFilesInBytewiseDeterministicOrderRegardlessOfCreationOrder(): void
    {
        self::writeFile('zeta.txt', "zeta\n");
        self::writeFile('nested/z.txt', "nested z\n");
        self::writeFile('alpha.txt', "alpha\n");
        self::writeFile('nested/a.txt', "nested a\n");
        self::writeFile('middle.txt', "middle\n");

        self::assertSame(
            [
                'alpha.txt',
                'middle.txt',
                'nested/a.txt',
                'nested/z.txt',
                'zeta.txt',
            ],
            new DeterministicFileLister()->listFiles($this->temporaryRoot),
        );
    }

    public function testReturnedPathsAreRelativeForwardSlashPathsOnly(): void
    {
        self::writeFile('config/app.php', "<?php\nreturn [];\n");
        self::writeFile('apps/web/config/http.php', "<?php\nreturn [];\n");

        $paths = new DeterministicFileLister()->listFiles($this->temporaryRoot);

        foreach ($paths as $path) {
            self::assertStringNotContainsString('\\', $path);
            self::assertStringNotContainsString('//', $path);
            self::assertFalse(
                \str_starts_with($path, '/'),
                'Fingerprint listed paths must stay relative.',
            );
            self::assertFalse(
                \str_contains($path, ':'),
                'Fingerprint listed paths must not contain drive/path separator metadata.',
            );
        }

        self::assertSame(
            [
                'apps/web/config/http.php',
                'config/app.php',
            ],
            $paths,
        );
    }

    public function testSkipCallbackRemovesGeneratedSubtreesBeforeListing(): void
    {
        self::writeFile('config/app.php', "<?php\nreturn [];\n");
        self::writeFile('var/cache/web/config.php', "<?php\nreturn [];\n");
        self::writeFile('var/maintenance/flag', "on\n");

        $paths = new DeterministicFileLister()->listFiles(
            declaredRoot: $this->temporaryRoot,
            skipRelativePath: static fn (string $relativePath): bool => \str_starts_with($relativePath, 'var/cache')
                || \str_starts_with($relativePath, 'var/maintenance'),
        );

        self::assertSame(
            [
                'config/app.php',
            ],
            $paths,
        );
    }

    public function testSingleFileCandidateReturnsOnlyNormalizedBasename(): void
    {
        self::writeFile('config/kernel.php', "<?php\nreturn [];\n");

        self::assertSame(
            [
                'kernel.php',
            ],
            new DeterministicFileLister()->listFileCandidate(
                $this->temporaryRoot . '/config/kernel.php',
            ),
        );
    }

    private function writeFile(string $relativePath, string $bytes): void
    {
        $path = $this->temporaryRoot . '/' . $relativePath;
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents($path, $bytes);
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

            if (\is_dir($itemPath) && !\is_link($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}
