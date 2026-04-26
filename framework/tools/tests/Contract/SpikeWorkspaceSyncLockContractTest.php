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

final class SpikeWorkspaceSyncLockContractTest extends ToolContractTestCase
{
    public function testWorkspaceMinFixtureIsCanonicalAndApplyIsRerunNoDiff(): void
    {
        $sandbox = $this->createWorkspaceSandbox('workspace_min');

        $this->assertWorkspaceComposerFilesMatchExpectedFixtures($sandbox, 'workspace_min');

        $before = [
            'root' => $this->readBytes($sandbox . '/composer.json'),
            'framework' => $this->readBytes($sandbox . '/framework/composer.json'),
            'skeleton' => $this->readBytes($sandbox . '/skeleton/composer.json'),
        ];

        [$checkCode, $checkOutput] = $this->runWorkspaceSync($sandbox, ['--check']);
        self::assertSame(0, $checkCode, "Expected canonical workspace fixture to pass --check.\nOutput:\n" . $checkOutput);
        self::assertSame("OK\n", $this->normalizeEol($checkOutput));

        [$applyCode, $applyOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(0, $applyCode, "Expected canonical workspace apply to pass.\nOutput:\n" . $applyOutput);

        $after = [
            'root' => $this->readBytes($sandbox . '/composer.json'),
            'framework' => $this->readBytes($sandbox . '/framework/composer.json'),
            'skeleton' => $this->readBytes($sandbox . '/skeleton/composer.json'),
        ];

        self::assertSame($before, $after, 'Canonical workspace apply must be rerun-no-diff.');

        $backupDir = $sandbox . '/framework/var/backups/workspace';
        if (is_dir($backupDir)) {
            self::assertSame([], $this->globSorted($backupDir . '/*.bak*'));
        }

        [$checkAgainCode, $checkAgainOutput] = $this->runWorkspaceSync($sandbox, ['--check']);
        self::assertSame(0, $checkAgainCode, "Expected post-apply --check to pass.\nOutput:\n" . $checkAgainOutput);
        self::assertSame("OK\n", $this->normalizeEol($checkAgainOutput));
    }
}
