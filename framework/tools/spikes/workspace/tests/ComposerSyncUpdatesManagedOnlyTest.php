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
use Coretsia\Tools\Spikes\workspace\WorkspacePolicy;
use PHPUnit\Framework\TestCase;

final class ComposerSyncUpdatesManagedOnlyTest extends TestCase
{
    public function testSyncRebuildsManagedBlockOnlyAndLeavesUserOwnedUntouched(): void
    {
        $cwd = (string)\getcwd();

        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            $frameworkRel = 'framework/composer.json';
            $skeletonRel = 'skeleton/composer.json';

            $frameworkBefore = self::decodeJsonMap(DeterministicFile::readBytesExact(self::joinPath($tmp, $frameworkRel)));
            $skeletonBefore = self::decodeJsonMap(DeterministicFile::readBytesExact(self::joinPath($tmp, $skeletonRel)));

            $frameworkUserOwnedBefore = self::extractUserOwnedRepos($frameworkBefore);
            $skeletonUserOwnedBefore = self::extractUserOwnedRepos($skeletonBefore);

            // Prove "no implicit CWD": run from a non-root directory.
            \chdir(self::joinPath($tmp, 'framework'));

            $changedFramework = ComposerRepositoriesSync::sync($tmp, $frameworkRel);
            $changedSkeleton = ComposerRepositoriesSync::sync($tmp, $skeletonRel);

            self::assertTrue($changedFramework);
            self::assertTrue($changedSkeleton);

            $frameworkAfterBytes = DeterministicFile::readBytesExact(self::joinPath($tmp, $frameworkRel));
            $skeletonAfterBytes = DeterministicFile::readBytesExact(self::joinPath($tmp, $skeletonRel));

            // Golden-byte evidence (full file).
            self::assertSame(
                DeterministicFile::readBytesExact(self::fixturePath('expected_composer_framework.json')),
                $frameworkAfterBytes
            );
            self::assertSame(
                DeterministicFile::readBytesExact(self::fixturePath('expected_composer_skeleton.json')),
                $skeletonAfterBytes
            );

            $frameworkAfter = self::decodeJsonMap($frameworkAfterBytes);
            $skeletonAfter = self::decodeJsonMap($skeletonAfterBytes);

            // Top-level key insertion order preserved (non-managed).
            self::assertSame(\array_keys($frameworkBefore), \array_keys($frameworkAfter));
            self::assertSame(\array_keys($skeletonBefore), \array_keys($skeletonAfter));

            // User-owned repositories preserved exactly (values + key order per map, and relative order).
            self::assertSame($frameworkUserOwnedBefore, self::extractUserOwnedRepos($frameworkAfter));
            self::assertSame($skeletonUserOwnedBefore, self::extractUserOwnedRepos($skeletonAfter));

            // Managed block remains contiguous after sync (invariant remains true).
            if (\array_key_exists(WorkspacePolicy::KEY_REPOSITORIES, $frameworkAfter)) {
                WorkspacePolicy::assertRepositoriesListOfMaps($frameworkAfter[WorkspacePolicy::KEY_REPOSITORIES]);
                WorkspacePolicy::splitIntoUserAndManagedBlocks($frameworkAfter[WorkspacePolicy::KEY_REPOSITORIES]);
            }
            if (\array_key_exists(WorkspacePolicy::KEY_REPOSITORIES, $skeletonAfter)) {
                WorkspacePolicy::assertRepositoriesListOfMaps($skeletonAfter[WorkspacePolicy::KEY_REPOSITORIES]);
                WorkspacePolicy::splitIntoUserAndManagedBlocks($skeletonAfter[WorkspacePolicy::KEY_REPOSITORIES]);
            }
        } finally {
            \chdir($cwd);
            self::removeTreeBestEffort($tmp);
        }
    }

    /**
     * @return array<string,mixed>
     * @throws \JsonException
     */
    private static function decodeJsonMap(string $bytes): array
    {
        $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertFalse(\array_is_list($decoded));

        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    /**
     * Extract user-owned repo entries deterministically (preserving order + key insertion order).
     *
     * @param array<string,mixed> $composer
     * @return list<array>
     */
    private static function extractUserOwnedRepos(array $composer): array
    {
        if (!\array_key_exists(WorkspacePolicy::KEY_REPOSITORIES, $composer)) {
            return [];
        }

        WorkspacePolicy::assertRepositoriesListOfMaps($composer[WorkspacePolicy::KEY_REPOSITORIES]);

        /** @var list<array> $repos */
        $repos = $composer[WorkspacePolicy::KEY_REPOSITORIES];

        $out = [];
        foreach ($repos as $item) {
            if (WorkspacePolicy::isManagedRepositoryEntry($item)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
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
