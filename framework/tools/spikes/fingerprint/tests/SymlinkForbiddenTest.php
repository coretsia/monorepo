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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

final class SymlinkForbiddenTest extends TestCase
{
    private ?string $tmpRoot = null;

    protected function tearDown(): void
    {
        if ($this->tmpRoot !== null) {
            $this->rmTree($this->tmpRoot);
        }

        $this->tmpRoot = null;
    }

    public function test_symlink_in_tree_is_forbidden_and_fails_deterministically(): void
    {
        $repoRoot = $this->makeTempDir('repo_root');

        $this->mkdir($repoRoot . '/a');
        $this->mkdir($repoRoot . '/b');

        $targetFile = $repoRoot . '/b/target.txt';
        $this->writeBytes($targetFile, "hello\n");

        $symlinkPath = $repoRoot . '/a/link_to_target.txt';
        $this->createSymlinkOrFail($targetFile, $symlinkPath);

        $lister = new DeterministicFileLister($repoRoot);

        try {
            $lister->listAllFiles();
            self::fail('expected-deterministic-exception');
        } catch (DeterministicException $e) {
            self::assertSame(ErrorCodes::CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN, $e->code());
            self::assertSame('fingerprint-symlink-forbidden', $e->getMessage());

            // Safety: MUST NOT leak any paths.
            self::assertStringNotContainsString($repoRoot, $e->getMessage());
            self::assertStringNotContainsString($symlinkPath, $e->getMessage());
            self::assertStringNotContainsString($targetFile, $e->getMessage());
        }
    }

    public function test_repo_root_symlink_is_forbidden(): void
    {
        $realRoot = $this->makeTempDir('real_root');
        $this->mkdir($realRoot . '/x');
        $this->writeBytes($realRoot . '/x/file.txt', "data\n");

        $linkRoot = $this->makeTempDir('link_holder') . '/repo_root_symlink';
        $this->createSymlinkOrFail($realRoot, $linkRoot);

        try {
            new DeterministicFileLister($linkRoot);
            self::fail('expected-deterministic-exception');
        } catch (DeterministicException $e) {
            self::assertSame(ErrorCodes::CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN, $e->code());
            self::assertSame('fingerprint-symlink-forbidden', $e->getMessage());

            // Safety: MUST NOT leak any paths.
            self::assertStringNotContainsString($realRoot, $e->getMessage());
            self::assertStringNotContainsString($linkRoot, $e->getMessage());
        }
    }

    private function makeTempDir(string $suffix): string
    {
        if ($this->tmpRoot === null) {
            $base = rtrim(sys_get_temp_dir(), '/\\');
            $this->tmpRoot = $base . DIRECTORY_SEPARATOR . 'coretsia_spikes_symlink_' . bin2hex(random_bytes(8));
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

    private function writeBytes(string $path, string $bytes): void
    {
        $dir = dirname($path);
        $this->mkdir($dir);

        try {
            DeterministicFile::writeBytesExact($path, $bytes);
        } catch (\Throwable) {
            self::fail('write-file-failed');
        }
    }

    /**
     * MUST NOT skip: if symlinks are not supported in this environment, this test MUST hard-fail.
     */
    private function createSymlinkOrFail(string $target, string $link): void
    {
        $dir = dirname($link);
        $this->mkdir($dir);

        // If link already exists (shouldn't), clean it up deterministically.
        if (file_exists($link) || is_link($link)) {
            @unlink($link);
        }

        if (@symlink($target, $link) !== true) {
            self::fail('symlink-creation-not-supported');
        }

        if (!is_link($link)) {
            self::fail('symlink-creation-not-supported');
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
