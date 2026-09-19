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

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;
use RuntimeException;

final class ManagedComposerRepositoriesGuardTest extends ToolContractTestCase
{
    public function testSyncCheckPassesOnCanonicalState(): void
    {
        $sandboxRepoRoot = $this->createWorkspaceFixtureSandbox();

        $rootComposer = $sandboxRepoRoot . '/composer.json';
        $frameworkComposer = $sandboxRepoRoot . '/packages/framework/composer.json';
        $skeletonComposer = $sandboxRepoRoot . '/packages/applications/skeleton/composer.json';

        self::assertFileExists($rootComposer);
        self::assertFileExists($frameworkComposer);
        self::assertFileExists($skeletonComposer);

        try {
            [$code, $out] = $this->runSync($sandboxRepoRoot, ['--check']);

            self::assertSame(
                0,
                $code,
                "Expected managed repositories check to pass on canonical fixture state.\nOutput:\n" . $out,
            );

            self::assertSame(
                '',
                $this->normalizeEol($out),
                'Canonical workspace --check success must be silent.',
            );
        } finally {
            $this->removeDir($sandboxRepoRoot);
        }
    }

    public function testDriftIsDetectedAndRestoredAndRerunIsNoop(): void
    {
        $sandboxRepoRoot = $this->createWorkspaceFixtureSandbox();

        $rootComposer = $sandboxRepoRoot . '/composer.json';
        $frameworkComposer = $sandboxRepoRoot . '/packages/framework/composer.json';
        $skeletonComposer = $sandboxRepoRoot . '/packages/applications/skeleton/composer.json';

        $expectedRoot = $this->workspaceFixtureRoot() . '/expected_composer_root.json';
        $expectedFramework = $this->workspaceFixtureRoot() . '/expected_composer_framework.json';
        $expectedSkeleton = $this->workspaceFixtureRoot() . '/expected_composer_skeleton.json';

        $backupDir = $sandboxRepoRoot . '/var/backups/workspace';

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
            $this->introduceRepositoriesDrift($rootComposer);

            [$checkCode, $checkOut] = $this->runSync($sandboxRepoRoot, ['--check']);
            self::assertNotSame(
                0,
                $checkCode,
                "Expected --check to fail on drift.\nOutput:\n" . $checkOut,
            );

            [$applyCode, $applyOut] = $this->runSync($sandboxRepoRoot, []);
            self::assertSame(
                0,
                $applyCode,
                "Expected apply (default mode) to restore canonical repositories.\nOutput:\n" . $applyOut,
            );

            $rootAfterApply = $this->readBytes($rootComposer);
            $frameworkAfterApply = $this->readBytes($frameworkComposer);
            $skeletonAfterApply = $this->readBytes($skeletonComposer);

            self::assertSame(
                $this->normalizeEol($expectedRootCanonical),
                $this->normalizeEol($rootAfterApply),
                'Expected apply to restore canonical root composer.json content.',
            );

            self::assertSame(
                $this->normalizeEol($expectedFrameworkCanonical),
                $this->normalizeEol($frameworkAfterApply),
                'Expected apply to preserve canonical framework composer.json content.',
            );

            self::assertSame(
                $this->normalizeEol($expectedSkeletonCanonical),
                $this->normalizeEol($skeletonAfterApply),
                'Expected apply to preserve canonical skeleton composer.json content.',
            );

            self::assertFalse(
                str_contains($rootAfterApply, "\r\n"),
                'Expected root composer.json to be normalized to LF line endings (no CRLF).',
            );

            self::assertFalse(
                str_contains($frameworkAfterApply, "\r\n"),
                'Expected framework composer.json to be normalized to LF line endings (no CRLF).',
            );

            self::assertFalse(
                str_contains($skeletonAfterApply, "\r\n"),
                'Expected skeleton composer.json to be normalized to LF line endings (no CRLF).',
            );

            self::assertTrue(
                is_dir($backupDir),
                'Expected apply mode to create var/backups/workspace inside sandbox.',
            );

            $rootBackups = $this->globSorted(
                $backupDir . '/root__composer.json.bak*',
            );
            self::assertNotSame(
                [],
                $rootBackups,
                'Expected apply mode to create at least one root composer backup inside sandbox.',
            );

            $frameworkBackups = $this->globSorted(
                $backupDir . '/packages__framework__composer.json.bak*',
            );
            self::assertSame(
                [],
                $frameworkBackups,
                'Expected no framework composer backup because public manifests are unmanaged.',
            );

            $skeletonBackups = $this->globSorted(
                $backupDir . '/packages__applications__skeleton__composer.json.bak*',
            );
            self::assertSame(
                [],
                $skeletonBackups,
                'Expected no skeleton composer backup because public manifests are unmanaged.',
            );

            [$apply2Code, $apply2Out] = $this->runSync($sandboxRepoRoot, []);
            self::assertSame(
                0,
                $apply2Code,
                "Expected second apply rerun to succeed.\nOutput:\n" . $apply2Out,
            );

            $rootAfterApply2 = $this->readBytes($rootComposer);
            $frameworkAfterApply2 = $this->readBytes($frameworkComposer);
            $skeletonAfterApply2 = $this->readBytes($skeletonComposer);

            self::assertSame($rootAfterApply, $rootAfterApply2, 'Expected root rerun to be rerun-no-diff.');
            self::assertSame(
                $frameworkAfterApply,
                $frameworkAfterApply2,
                'Expected framework rerun to be rerun-no-diff.',
            );
            self::assertSame($skeletonAfterApply, $skeletonAfterApply2, 'Expected skeleton rerun to be rerun-no-diff.');

            $rootBackupsAfterRerun = $this->globSorted($backupDir . '/root__composer.json.bak*');
            self::assertSame(
                $rootBackups,
                $rootBackupsAfterRerun,
                'Expected rerun-no-diff to avoid creating additional root backup files.',
            );
        } finally {
            $this->removeDir($sandboxRepoRoot);
        }
    }

    /**
     * @param list<string> $args
     *
     * @return array{0:int,1:string}
     */
    private function runSync(string $repoRoot, array $args): array
    {
        return $this->runWorkspaceSync($repoRoot, $args);
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
        return $this->createWorkspaceSandbox('Workspace/Canonical');
    }

    private function workspaceFixtureRoot(): string
    {
        return $this->fixturePath('Workspace/Canonical');
    }
}
