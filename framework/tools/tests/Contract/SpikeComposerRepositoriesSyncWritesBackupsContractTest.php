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

final class SpikeComposerRepositoriesSyncWritesBackupsContractTest extends ToolContractTestCase
{
    public function testDriftedWorkspaceApplyWritesBackupsAndRestoresExpectedComposerFiles(): void
    {
        $sandbox = $this->createWorkspaceSandbox('workspace_drifted_min');

        $originalBytes = [
            $this->readBytes($sandbox . '/composer.json'),
            $this->readBytes($sandbox . '/framework/composer.json'),
            $this->readBytes($sandbox . '/skeleton/composer.json'),
        ];

        [$checkCode, $checkOutput] = $this->runWorkspaceSync($sandbox, ['--check']);
        self::assertNotSame(0, $checkCode, "Expected drifted workspace fixture to fail --check.\nOutput:\n" . $checkOutput);

        [$applyCode, $applyOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(0, $applyCode, "Expected drifted workspace fixture to be restored.\nOutput:\n" . $applyOutput);

        $backupDir = $sandbox . '/framework/var/backups/workspace';
        self::assertDirectoryExists($backupDir);

        $backups = $this->globSorted($backupDir . '/*.bak*');
        self::assertNotSame([], $backups, 'Expected sync apply to create at least one backup file.');

        foreach ($backups as $backup) {
            self::assertContains(
                $this->readBytes($backup),
                $originalBytes,
                'Every backup must contain exact pre-apply bytes from one drifted composer.json file.',
            );
        }

        $this->assertWorkspaceComposerFilesMatchExpectedFixtures($sandbox, 'workspace_drifted_min');

        [$applyAgainCode, $applyAgainOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(0, $applyAgainCode, "Expected second apply to be a deterministic no-op.\nOutput:\n" . $applyAgainOutput);

        self::assertSame(
            $backups,
            $this->globSorted($backupDir . '/*.bak*'),
            'Second apply must not create additional backup files.',
        );
    }
}
