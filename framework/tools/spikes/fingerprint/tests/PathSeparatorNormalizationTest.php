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

namespace Coretsia\Tools\Spikes\fingerprint\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - normalize paths via InternalToolkit Path::normalizeRelative(...)
 *  - output MUST be repo-relative forward slashes, never backslashes
 */
final class PathSeparatorNormalizationTest extends TestCase
{
    private ?string $tmpRoot = null;

    protected function tearDown(): void
    {
        if ($this->tmpRoot !== null) {
            $this->rmTree($this->tmpRoot);
        }

        $this->tmpRoot = null;
    }

    public function test_lister_emits_forward_slashes_and_repo_relative_paths_only(): void
    {
        $repoRoot = $this->makeTempDir('repo_root');

        // Create a nested tree using OS separators; the lister MUST output forward slashes.
        $this->mkdir($repoRoot . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b');
        DeterministicFile::writeTextLf($repoRoot . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'one.txt', "one\n");

        $this->mkdir($repoRoot . DIRECTORY_SEPARATOR . 'x');
        DeterministicFile::writeTextLf($repoRoot . DIRECTORY_SEPARATOR . 'x' . DIRECTORY_SEPARATOR . 'two.txt', "two\n");

        $lister = new DeterministicFileLister($repoRoot);
        $paths = $lister->listAllFiles();

        self::assertSame(
            [
                'a/b/one.txt',
                'x/two.txt',
            ],
            $paths
        );

        foreach ($paths as $p) {
            self::assertIsString($p);
            self::assertNotSame('', $p);

            self::assertFalse(str_contains($p, "\\"));
            self::assertFalse(str_starts_with($p, '/'));
            self::assertFalse((bool)preg_match('~(?i)\A[A-Z]:[\\\\/]~', $p)); // no "C:\"
            self::assertFalse(str_contains($p, "\0"));

            // No parent traversals.
            self::assertFalse(str_contains($p, '/../'));
            self::assertFalse(str_contains($p, '/..'));
            self::assertNotSame('..', $p);
            self::assertNotSame('.', $p);
            self::assertFalse(str_starts_with($p, './'));
        }
    }

    private function makeTempDir(string $suffix): string
    {
        if ($this->tmpRoot === null) {
            $base = rtrim(sys_get_temp_dir(), '/\\');
            $this->tmpRoot = $base . DIRECTORY_SEPARATOR . 'coretsia_spikes_pathsep_' . bin2hex(random_bytes(8));
            $this->mkdir($this->tmpRoot);
        }

        $dir = $this->tmpRoot . DIRECTORY_SEPARATOR . $suffix;
        $this->mkdir($dir);

        return $dir;
    }

    private function mkdir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (@mkdir($dir, 0777, true) !== true && !is_dir($dir)) {
            self::fail('mkdir-failed');
        }
    }

    private function rmTree(string $path): void
    {
        if ($path === '' || $path === DIRECTORY_SEPARATOR) {
            return;
        }

        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $info) {
            if (!$info instanceof \SplFileInfo) {
                continue;
            }

            $p = $info->getPathname();

            if ($info->isLink() || $info->isFile()) {
                @unlink($p);
                continue;
            }

            if ($info->isDir()) {
                @rmdir($p);
            }
        }

        @rmdir($path);
    }
}
