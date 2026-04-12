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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class WorkspaceSyncCommandsUseEntryWorkflowTest extends TestCase
{
    public function testWorkspaceSyncCommandsDelegateToEntryWorkflowInsteadOfEngine(): void
    {
        $packageRoot = \dirname(__DIR__, 2);

        $files = [
            $packageRoot . '/src/Command/WorkspaceSyncDryRunCommand.php',
            $packageRoot . '/src/Command/WorkspaceSyncApplyCommand.php',
        ];

        foreach ($files as $file) {
            self::assertFileExists($file, 'Workspace sync command source MUST exist.');

            $code = \file_get_contents($file);
            self::assertIsString($code, 'Read failed: ' . $file);

            self::assertStringContainsString(
                'WorkspaceSyncEntryWorkflow',
                $code,
                'Workspace sync commands MUST delegate to tools-side WorkspaceSyncEntryWorkflow.'
            );

            self::assertStringNotContainsString(
                'WorkspaceSyncWorkflow::run',
                $code,
                'Workspace sync commands MUST NOT call WorkspaceSyncWorkflow directly.'
            );
        }
    }
}
