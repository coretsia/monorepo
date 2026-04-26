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
use Coretsia\Tools\Spikes\workspace\NewPackageWorkflow;
use Coretsia\Tools\Spikes\workspace\PackageIndexBuilder;
use PHPUnit\Framework\TestCase;

final class NoImplicitCwdEvidenceTest extends TestCase
{
    public function testComponentsDoNotRelyOnCurrentWorkingDirectory(): void
    {
        $cwdBefore = (string)\getcwd();

        $cwdOutside = self::makeTempDir();
        $ws1 = self::makeTempDir();
        $ws2 = self::makeTempDir();

        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $ws1);
            self::copyTreeDeterministic(self::fixtureRoot(), $ws2);

            // Change CWD to a directory that is NOT the workspace root and NOT inside it.
            \chdir($cwdOutside);

            // 1) PackageIndexBuilder uses explicit root only.
            $actualIndex = PackageIndexBuilder::build($ws1);

            /** @var list<array<string,mixed>> $expectedIndex */
            $expectedIndex = require self::fixturePath('expected_package_index.php');

            self::assertSame($expectedIndex, $actualIndex);

            // 2) ComposerRepositoriesSync uses explicit root only.
            $frameworkRel = 'framework/composer.json';
            $skeletonRel = 'skeleton/composer.json';

            $changedFramework = ComposerRepositoriesSync::sync($ws1, $frameworkRel);
            $changedSkeleton = ComposerRepositoriesSync::sync($ws1, $skeletonRel);

            self::assertFalse($changedFramework);
            self::assertFalse($changedSkeleton);

            self::assertSame(
                DeterministicFile::readBytesExact(self::fixturePath('expected_composer_framework.json')),
                DeterministicFile::readBytesExact(self::joinPath($ws1, $frameworkRel)),
            );
            self::assertSame(
                DeterministicFile::readBytesExact(self::fixturePath('expected_composer_skeleton.json')),
                DeterministicFile::readBytesExact(self::joinPath($ws1, $skeletonRel)),
            );

            // 3) NewPackageWorkflow uses explicit root only (dry-run to avoid mutations).
            $frameworkBefore = DeterministicFile::readBytesExact(self::joinPath($ws2, $frameworkRel));
            $skeletonBefore = DeterministicFile::readBytesExact(self::joinPath($ws2, $skeletonRel));

            $result = NewPackageWorkflow::run(
                $ws2,
                'core',
                'foo',
                'coretsia/core-foo',
                ['Coretsia\\Core\\Foo\\' => 'src/'],
                'library',
                null,
                false
            );

            self::assertArrayHasKey('packageIndex', $result);
            self::assertArrayHasKey('changedPaths', $result);

            // Dry-run must not touch workspace files even when CWD is outside.
            self::assertSame($frameworkBefore, DeterministicFile::readBytesExact(self::joinPath($ws2, $frameworkRel)));
            self::assertSame($skeletonBefore, DeterministicFile::readBytesExact(self::joinPath($ws2, $skeletonRel)));
            self::assertDirectoryDoesNotExist(self::joinPath($ws2, 'framework/packages/core/foo'));
        } finally {
            \chdir($cwdBefore);

            self::removeTreeBestEffort($ws2);
            self::removeTreeBestEffort($ws1);
            self::removeTreeBestEffort($cwdOutside);
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
