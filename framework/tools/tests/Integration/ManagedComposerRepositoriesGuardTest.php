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

namespace Coretsia\Tools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ManagedComposerRepositoriesGuardTest extends TestCase
{
    public function testSyncCheckPassesOnCanonicalState(): void
    {
        $sandboxRepoRoot = $this->createWorkspaceFixtureSandbox();

        $rootComposer = $sandboxRepoRoot . '/composer.json';
        $frameworkComposer = $sandboxRepoRoot . '/framework/composer.json';
        $skeletonComposer = $sandboxRepoRoot . '/skeleton/composer.json';

        self::assertFileExists($rootComposer);
        self::assertFileExists($frameworkComposer);
        self::assertFileExists($skeletonComposer);

        try {
            [$code, $out] = $this->runSync($sandboxRepoRoot, ['--check']);

            self::assertSame(
                0,
                $code,
                "Expected managed repositories check to pass on canonical fixture state.\nOutput:\n" . $out
            );
        } finally {
            $this->removeDir($sandboxRepoRoot);
        }
    }

    public function testDriftIsDetectedAndRestoredAndRerunIsNoop(): void
    {
        $sandboxRepoRoot = $this->createWorkspaceFixtureSandbox();

        $rootComposer = $sandboxRepoRoot . '/composer.json';
        $frameworkComposer = $sandboxRepoRoot . '/framework/composer.json';
        $skeletonComposer = $sandboxRepoRoot . '/skeleton/composer.json';

        $expectedRoot = $this->workspaceFixtureRoot() . '/expected_composer_root.json';
        $expectedFramework = $this->workspaceFixtureRoot() . '/expected_composer_framework.json';
        $expectedSkeleton = $this->workspaceFixtureRoot() . '/expected_composer_skeleton.json';

        $backupDir = $sandboxRepoRoot . '/framework/var/backups/workspace';

        self::assertFileExists($rootComposer);
        self::assertFileExists($frameworkComposer);
        self::assertFileExists($skeletonComposer);

        self::assertFileExists($expectedRoot);
        self::assertFileExists($expectedFramework);
        self::assertFileExists($expectedSkeleton);

        $expectedRootCanonical = $this->readBytes($expectedRoot);
        $expectedFrameworkCanonical = $this->readBytes($expectedFramework);
        $expectedSkeletonCanonical = $this->readBytes($expectedSkeleton);

        try {
            $this->introduceRepositoriesDrift($skeletonComposer);

            [$checkCode, $checkOut] = $this->runSync($sandboxRepoRoot, ['--check']);
            self::assertNotSame(
                0,
                $checkCode,
                "Expected --check to fail on drift.\nOutput:\n" . $checkOut
            );

            [$applyCode, $applyOut] = $this->runSync($sandboxRepoRoot, []);
            self::assertSame(
                0,
                $applyCode,
                "Expected apply (default mode) to restore canonical repositories.\nOutput:\n" . $applyOut
            );

            $rootAfterApply = $this->readBytes($rootComposer);
            $frameworkAfterApply = $this->readBytes($frameworkComposer);
            $skeletonAfterApply = $this->readBytes($skeletonComposer);

            self::assertSame(
                $this->normalizeEol($expectedRootCanonical),
                $this->normalizeEol($rootAfterApply),
                'Expected apply to preserve canonical root composer.json content.'
            );

            self::assertSame(
                $this->normalizeEol($expectedFrameworkCanonical),
                $this->normalizeEol($frameworkAfterApply),
                'Expected apply to preserve canonical framework composer.json content.'
            );

            self::assertSame(
                $this->normalizeEol($expectedSkeletonCanonical),
                $this->normalizeEol($skeletonAfterApply),
                'Expected apply to restore canonical skeleton composer.json content.'
            );

            self::assertFalse(
                str_contains($rootAfterApply, "\r\n"),
                'Expected root composer.json to be normalized to LF line endings (no CRLF).'
            );

            self::assertFalse(
                str_contains($frameworkAfterApply, "\r\n"),
                'Expected framework composer.json to be normalized to LF line endings (no CRLF).'
            );

            self::assertFalse(
                str_contains($skeletonAfterApply, "\r\n"),
                'Expected skeleton composer.json to be normalized to LF line endings (no CRLF).'
            );

            self::assertTrue(
                is_dir($backupDir),
                'Expected apply mode to create framework/var/backups/workspace inside sandbox.'
            );

            $skeletonBackups = $this->globSorted($backupDir . '/skeleton__composer.json.bak*');
            self::assertNotSame(
                [],
                $skeletonBackups,
                'Expected apply mode to create at least one skeleton composer backup inside sandbox.'
            );

            $frameworkBackups = $this->globSorted($backupDir . '/framework__composer.json.bak*');
            self::assertSame(
                [],
                $frameworkBackups,
                'Expected no framework composer backup when only skeleton composer.json was drifted.'
            );

            [$apply2Code, $apply2Out] = $this->runSync($sandboxRepoRoot, []);
            self::assertSame(
                0,
                $apply2Code,
                "Expected second apply rerun to succeed.\nOutput:\n" . $apply2Out
            );

            $rootAfterApply2 = $this->readBytes($rootComposer);
            $frameworkAfterApply2 = $this->readBytes($frameworkComposer);
            $skeletonAfterApply2 = $this->readBytes($skeletonComposer);

            self::assertSame($rootAfterApply, $rootAfterApply2, 'Expected root rerun to be rerun-no-diff.');
            self::assertSame($frameworkAfterApply, $frameworkAfterApply2, 'Expected framework rerun to be rerun-no-diff.');
            self::assertSame($skeletonAfterApply, $skeletonAfterApply2, 'Expected skeleton rerun to be rerun-no-diff.');

            $skeletonBackupsAfterRerun = $this->globSorted($backupDir . '/skeleton__composer.json.bak*');
            self::assertSame(
                $skeletonBackups,
                $skeletonBackupsAfterRerun,
                'Expected rerun-no-diff to avoid creating additional skeleton backup files.'
            );
        } finally {
            $this->removeDir($sandboxRepoRoot);
        }
    }

    /**
     * @param list<string> $args
     * @return array{0:int,1:string}
     */
    private function runSync(string $repoRoot, array $args): array
    {
        $frameworkRoot = $this->frameworkRoot();
        $script = $frameworkRoot . '/tools/build/sync_composer_repositories.php';

        if (!is_file($script)) {
            throw new RuntimeException('Missing sync tool: ' . $script);
        }

        $cmd = array_merge(
            [
                PHP_BINARY,
                $script,
                '--repo-root',
                $repoRoot,
            ],
            $args
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptorSpec, $pipes, $frameworkRoot);
        if (!\is_resource($proc)) {
            throw new RuntimeException('Failed to start sync tool process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($proc);

        $out = '';
        if (is_string($stdout) && $stdout !== '') {
            $out .= $stdout;
        }
        if (is_string($stderr) && $stderr !== '') {
            $out .= $stderr;
        }

        return [$code, $out];
    }

    private function introduceRepositoriesDrift(string $composerJsonPath): void
    {
        $raw = $this->readBytes($composerJsonPath);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new RuntimeException('Target composer.json is not valid JSON: ' . $composerJsonPath);
        }

        $repos = $data['repositories'] ?? null;
        if (!is_array($repos) || $repos === []) {
            throw new RuntimeException('Target composer.json has no repositories to drift: ' . $composerJsonPath);
        }

        $data['repositories'] = array_values(array_reverse($repos));

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Failed to re-encode drifted composer.json.');
        }

        $this->writeBytesExact($composerJsonPath, $encoded . "\n");
    }

    private function createWorkspaceFixtureSandbox(): string
    {
        $fixtureRoot = $this->workspaceFixtureRoot();
        $tmpBase = sys_get_temp_dir();

        if (!is_string($tmpBase) || trim($tmpBase) === '') {
            throw new RuntimeException('Failed to resolve system temp dir.');
        }

        $tmpBase = rtrim(str_replace('\\', '/', $tmpBase), '/');
        $sandbox = $tmpBase . '/coretsia-workspace-fixture-' . bin2hex(random_bytes(8));

        $this->copyDir($fixtureRoot, $sandbox);

        self::assertFileExists($sandbox . '/composer.json');
        self::assertFileExists($sandbox . '/framework/composer.json');
        self::assertFileExists($sandbox . '/skeleton/composer.json');

        return $sandbox;
    }

    private function workspaceFixtureRoot(): string
    {
        $path = $this->frameworkRoot() . '/tools/spikes/fixtures/workspace_min';
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if (!is_dir($path)) {
            throw new RuntimeException('Missing workspace fixture root: ' . $path);
        }

        return $path;
    }

    private function normalizeEol(string $bytes): string
    {
        $bytes = str_replace("\r\n", "\n", $bytes);
        return str_replace("\r", "\n", $bytes);
    }

    private function frameworkRoot(): string
    {
        $frameworkRoot = dirname(__DIR__, 3);

        return rtrim(str_replace('\\', '/', $frameworkRoot), '/');
    }

    private function readBytes(string $path): string
    {
        $bytes = @file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Failed to read file: ' . $path);
        }

        return $bytes;
    }

    private function writeBytesExact(string $path, string $bytes): void
    {
        $ok = @file_put_contents($path, $bytes);
        if ($ok === false) {
            throw new RuntimeException('Failed to write file: ' . $path);
        }
    }

    /**
     * @return list<string>
     */
    private function globSorted(string $pattern): array
    {
        $items = glob($pattern) ?: [];
        $items = array_values(array_filter($items, static fn($p) => is_string($p) && $p !== ''));
        $items = array_map(static fn(string $p): string => str_replace('\\', '/', $p), $items);
        sort($items, SORT_STRING);

        return $items;
    }

    private function copyDir(string $source, string $destination): void
    {
        $source = rtrim(str_replace('\\', '/', $source), '/');
        $destination = rtrim(str_replace('\\', '/', $destination), '/');

        if (!is_dir($source)) {
            throw new RuntimeException('Source directory does not exist: ' . $source);
        }

        if (!is_dir($destination) && !@mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException('Failed to create destination directory: ' . $destination);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $srcPath = str_replace('\\', '/', $item->getPathname());
            $relPath = substr($srcPath, strlen($source) + 1);
            $dstPath = $destination . '/' . $relPath;

            if ($item->isDir()) {
                if (!is_dir($dstPath) && !@mkdir($dstPath, 0777, true) && !is_dir($dstPath)) {
                    throw new RuntimeException('Failed to create directory: ' . $dstPath);
                }

                continue;
            }

            $parent = dirname($dstPath);
            if (!is_dir($parent) && !@mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new RuntimeException('Failed to create parent directory: ' . $parent);
            }

            if (!@copy($srcPath, $dstPath)) {
                throw new RuntimeException('Failed to copy file: ' . $srcPath . ' -> ' . $dstPath);
            }
        }
    }

    private function removeDir(string $path): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || !is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = str_replace('\\', '/', $item->getPathname());

            if ($item->isDir()) {
                @rmdir($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
