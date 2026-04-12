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

final class DeptracGraphCommandIsThinAdapterTest extends TestCase
{
    public function testDeptracGraphCommandDelegatesToWorkflowAndDoesNotEmbedArtifactBuildLogic(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $file = $packageRoot . '/src/Command/DeptracGraphCommand.php';

        self::assertFileExists($file, 'DeptracGraphCommand.php MUST exist.');

        $code = \file_get_contents($file);
        self::assertIsString($code, 'Read failed for DeptracGraphCommand.php');

        self::assertStringContainsString(
            'DeptracGraphWorkflow',
            $code,
            'DeptracGraphCommand MUST delegate to tools-side DeptracGraphWorkflow.'
        );

        $forbidden = [
            'GraphArtifactBuilder',
            'deriveRepoRootFromSpikesBootstrapPath',
            'joinAbs',
            'mkdir(',
            'output-dir-create-failed',
            'extractDeterministicCodeOrNull',
        ];

        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $code,
                'DeptracGraphCommand MUST NOT embed deptrac graph spike logic: forbidden token "' . $needle . '" found.'
            );
        }
    }
}
