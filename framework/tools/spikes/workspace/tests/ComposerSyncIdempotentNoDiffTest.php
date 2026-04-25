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

namespace Coretsia\Tools\Spikes\workspace\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\workspace\ComposerRepositoriesSync;
use PHPUnit\Framework\TestCase;

final class ComposerSyncIdempotentNoDiffTest extends TestCase
{
    public function testSyncIsIdempotentAndDoesNotCreateBackupsWhenNoChange(): void
    {
        $cwd = (string)\getcwd();

        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            // Force canonical input by installing expected bytes into temp workspace.
            $frameworkRel = 'framework/composer.json';
            $skeletonRel = 'skeleton/composer.json';

            $expectedFramework = DeterministicFile::readBytesExact(self::fixturePath('expected_composer_framework.json'));
            $expectedSkeleton = DeterministicFile::readBytesExact(self::fixturePath('expected_composer_skeleton.json'));

            DeterministicFile::writeBytesExact(self::joinPath($tmp, $frameworkRel), $expectedFramework);
            DeterministicFile::writeBytesExact(self::joinPath($tmp, $skeletonRel), $expectedSkeleton);

            self::assertFileDoesNotExist(self::joinPath($tmp, $frameworkRel . '.bak'));
            self::assertFileDoesNotExist(self::joinPath($tmp, $skeletonRel . '.bak'));

            // Prove "no implicit CWD": run from a non-root directory.
            \chdir(self::joinPath($tmp, 'framework'));

            $c1 = ComposerRepositoriesSync::sync($tmp, $frameworkRel);
            $c2 = ComposerRepositoriesSync::sync($tmp, $skeletonRel);

            self::assertFalse($c1);
            self::assertFalse($c2);

            // Rerun-no-diff.
            $c3 = ComposerRepositoriesSync::sync($tmp, $frameworkRel);
            $c4 = ComposerRepositoriesSync::sync($tmp, $skeletonRel);

            self::assertFalse($c3);
            self::assertFalse($c4);

            // No backups created.
            self::assertFileDoesNotExist(self::joinPath($tmp, $frameworkRel . '.bak'));
            self::assertFileDoesNotExist(self::joinPath($tmp, $frameworkRel . '.bak.1'));
            self::assertFileDoesNotExist(self::joinPath($tmp, $skeletonRel . '.bak'));
            self::assertFileDoesNotExist(self::joinPath($tmp, $skeletonRel . '.bak.1'));

            // Bytes unchanged.
            self::assertSame($expectedFramework, DeterministicFile::readBytesExact(self::joinPath($tmp, $frameworkRel)));
            self::assertSame($expectedSkeleton, DeterministicFile::readBytesExact(self::joinPath($tmp, $skeletonRel)));
        } finally {
            \chdir($cwd);
            self::removeTreeBestEffort($tmp);
        }
    }

    private static function fixtureRoot(): string
    {
        return self::normalizePath(__DIR__ . '/../../fixtures/workspace_min');
    }

    private static function fixturePath(string $rel): string
    {
        return self::joinPath(self::fixtureRoot(), $rel);
    }

    private static function makeTempDir(): string
    {
        $base = \rtrim(self::normalizePath((string)\sys_get_temp_dir()), '/');
        $dir = $base . '/coretsia-spikes-workspace-' . \bin2hex(\random_bytes(8));

        if (!@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('failed to create temp dir');
        }

        return $dir;
    }

    private static function copyTreeDeterministic(string $srcDir, string $dstDir): void
    {
        if (!\is_dir($srcDir)) {
            self::fail('fixture root missing: ' . $srcDir);
        }

        if (!\is_dir($dstDir) && !@\mkdir($dstDir, 0777, true) && !\is_dir($dstDir)) {
            self::fail('failed to create dst dir: ' . $dstDir);
        }

        $entries = self::listDirectoryEntriesSorted($srcDir);
        foreach ($entries as $name) {
            $src = self::joinPath($srcDir, $name);
            $dst = self::joinPath($dstDir, $name);

            if (\is_dir($src)) {
                self::copyTreeDeterministic($src, $dst);
                continue;
            }

            if (\is_file($src)) {
                $bytes = DeterministicFile::readBytesExact($src);
                DeterministicFile::writeBytesExact($dst, $bytes);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function listDirectoryEntriesSorted(string $dir): array
    {
        $out = [];

        foreach (new \DirectoryIterator($dir) as $fi) {
            if ($fi->isDot()) {
                continue;
            }

            $name = $fi->getFilename();
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $out[] = $name;
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values($out);
    }

    private static function removeTreeBestEffort(string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === '' || $path === '.' || $path === '/' || $path === \DIRECTORY_SEPARATOR) {
            return;
        }

        if (\is_file($path)) {
            @\unlink($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        $entries = self::listDirectoryEntriesSorted($path);
        foreach ($entries as $name) {
            self::removeTreeBestEffort(self::joinPath($path, $name));
        }

        @\rmdir($path);
    }

    private static function joinPath(string $base, string $rel): string
    {
        $b = \rtrim(self::normalizePath($base), '/');
        $r = \ltrim(self::normalizePath($rel), '/');

        return $r === '' ? $b : ($b . '/' . $r);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
