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

namespace Coretsia\Tools\Tests\Contract;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class ComposerRepositoriesSyncWritesBackupsContractTest extends ToolContractTestCase
{
    public function testCanonicalWorkspaceApplyIsNoopAndCreatesNoBackups(): void
    {
        $sandbox = $this->createWorkspaceSandbox('Workspace/Canonical');

        $before = [
            'root' => $this->readBytes($sandbox . '/composer.json'),
            'framework' => $this->readBytes($sandbox . '/packages/framework/composer.json'),
            'skeleton' => $this->readBytes($sandbox . '/packages/applications/skeleton/composer.json'),
        ];

        [$applyCode, $applyOutput] = $this->runWorkspaceSync(
            $sandbox,
            [],
        );

        self::assertSame(
            0,
            $applyCode,
            "Expected canonical workspace apply to pass.\nOutput:\n" . $applyOutput,
        );

        self::assertSame(
            $before,
            [
                'root' => $this->readBytes($sandbox . '/composer.json'),
                'framework' => $this->readBytes($sandbox . '/packages/framework/composer.json'),
                'skeleton' => $this->readBytes($sandbox . '/packages/applications/skeleton/composer.json'),
            ],
            'Canonical workspace apply must be byte-for-byte no-op.',
        );

        $backupDir = $sandbox . '/var/backups/workspace';

        if (\is_dir($backupDir)) {
            self::assertSame(
                [],
                $this->globSorted(
                    $backupDir . '/*.bak*',
                ),
                'Canonical workspace apply must not create backup files.',
            );
        }
    }

    public function testDriftedWorkspaceApplyWritesBackupsAndRestoresExpectedComposerFiles(): void
    {
        $sandbox = $this->createWorkspaceSandbox('Workspace/Canonical');
        $rootComposer = $sandbox . '/composer.json';
        $canonicalRootBytes = $this->readBytes($rootComposer);

        $driftedRootBytes = str_replace(
            '"url": "packages/core/contracts"',
            '"url": "packages/core/contracts-drifted"',
            $canonicalRootBytes,
            $replacementCount,
        );

        self::assertSame(
            1,
            $replacementCount,
            'Fixture precondition: canonical root composer must contain packages/core/contracts exactly once.',
        );

        $this->writeBytesExact($rootComposer, $driftedRootBytes);

        [$checkCode, $checkOutput] = $this->runWorkspaceSync($sandbox, ['--check']);
        self::assertNotSame(
            0,
            $checkCode,
            "Expected drifted workspace fixture to fail --check.\nOutput:\n" . $checkOutput,
        );

        [$applyCode, $applyOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(0, $applyCode, "Expected drifted workspace fixture to be restored.\nOutput:\n" . $applyOutput);

        $backupDir = $sandbox . '/var/backups/workspace';
        self::assertDirectoryExists($backupDir);

        $backups = $this->globSorted($backupDir . '/*.bak*');
        self::assertCount(
            1,
            $backups,
            'Only root composer.json is managed by workspace repository sync.',
        );

        self::assertSame(
            $driftedRootBytes,
            $this->readBytes($backups[0]),
            'Backup must contain exact pre-apply root composer.json bytes.',
        );

        $this->assertWorkspaceComposerFilesMatchExpectedFixtures(
            $sandbox,
            'Workspace/Canonical',
        );

        [$applyAgainCode, $applyAgainOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(
            0,
            $applyAgainCode,
            "Expected second apply to be a deterministic no-op.\nOutput:\n" . $applyAgainOutput,
        );

        self::assertSame(
            $backups,
            $this->globSorted($backupDir . '/*.bak*'),
            'Second apply must not create additional backup files.',
        );
    }
}
