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

final class SpikeConfigDebugCommandIsThinAdapterTest extends TestCase
{
    public function testSpikeConfigDebugCommandDelegatesToWorkflowAndDoesNotEmbedConfigMergeLogic(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $file = $packageRoot . '/src/Command/SpikeConfigDebugCommand.php';

        self::assertFileExists($file, 'SpikeConfigDebugCommand.php MUST exist.');

        $code = \file_get_contents($file);
        self::assertIsString($code, 'Read failed for SpikeConfigDebugCommand.php');

        self::assertStringContainsString(
            'ConfigDebugWorkflow',
            $code,
            'SpikeConfigDebugCommand MUST delegate to tools-side ConfigDebugWorkflow.'
        );

        $forbidden = [
            'ConfigMerger',
            'ConfigExplainer',
            'loadConfigMergeScenariosFixture',
            'buildSourcesFromScenarioInputs',
            'filterTraceByKey',
            'describeResolvedValue',
            'tryResolveNestedDotPath',
            'scenarios.php',
            'SpikesPaths',
        ];

        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $code,
                'SpikeConfigDebugCommand MUST NOT embed config_merge spike logic: forbidden token "' . $needle . '" found.'
            );
        }
    }
}
