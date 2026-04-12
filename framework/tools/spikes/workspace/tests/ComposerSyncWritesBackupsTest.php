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

final class ComposerSyncWritesBackupsTest extends TestCase
{
    public function testSyncWritesWorkspaceBackupsWithExactPreSyncBytes(): void
    {
        $cwd = (string)\getcwd();

        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            $frameworkRel = 'framework/composer.json';
            $skeletonRel = 'skeleton/composer.json';

            $frameworkPath = self::joinPath($tmp, $frameworkRel);
            $skeletonPath = self::joinPath($tmp, $skeletonRel);

            $frameworkOriginal = DeterministicFile::readBytesExact($frameworkPath);
            $skeletonOriginal = DeterministicFile::readBytesExact($skeletonPath);

            $expectedFramework = DeterministicFile::readBytesExact(self::fixturePath('expected_composer_framework.json'));
            $expectedSkeleton = DeterministicFile::readBytesExact(self::fixturePath('expected_composer_skeleton.json'));

            // Prove "no implicit CWD": run from a non-root directory.
            \chdir(self::joinPath($tmp, 'framework'));

            $changedFramework = ComposerRepositoriesSync::sync($tmp, $frameworkRel);
            $changedSkeleton = ComposerRepositoriesSync::sync($tmp, $skeletonRel);

            self::assertTrue($changedFramework);
            self::assertTrue($changedSkeleton);

            $backupDir = self::joinPath($tmp, 'framework/var/backups/workspace');
            $frameworkBak = self::joinPath($backupDir, 'framework__composer.json.bak');
            $skeletonBak = self::joinPath($backupDir, 'skeleton__composer.json.bak');

            self::assertDirectoryExists($backupDir);
            self::assertFileExists($frameworkBak);
            self::assertFileExists($skeletonBak);

            self::assertSame($frameworkOriginal, DeterministicFile::readBytesExact($frameworkBak));
            self::assertSame($skeletonOriginal, DeterministicFile::readBytesExact($skeletonBak));

            // Target bytes updated to the golden expected bytes.
            self::assertSame($expectedFramework, DeterministicFile::readBytesExact($frameworkPath));
            self::assertSame($expectedSkeleton, DeterministicFile::readBytesExact($skeletonPath));

            // Idempotent rerun => no extra backups.
            $changedFramework2 = ComposerRepositoriesSync::sync($tmp, $frameworkRel);
            $changedSkeleton2 = ComposerRepositoriesSync::sync($tmp, $skeletonRel);

            self::assertFalse($changedFramework2);
            self::assertFalse($changedSkeleton2);

            self::assertFileDoesNotExist(self::joinPath($backupDir, 'framework__composer.json.bak.1'));
            self::assertFileDoesNotExist(self::joinPath($backupDir, 'skeleton__composer.json.bak.1'));
        } finally {
            \chdir($cwd);
            self::removeTreeBestEffort($tmp);
        }
    }

    private static function fixtureRoot(): string
    {
        return self::normalizePath(__DIR__ . '/../../fixtures/workspace_drifted_min');
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

        \usort($out, static fn(string $a, string $b): int => \strcmp($a, $b));

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
