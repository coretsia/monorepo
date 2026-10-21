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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\DebugModulesCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class CliKernelOpsReadableNestedOutputTest extends KernelOpsIntegrationTestCase
{
    public function testConfigValidationCountsUseNestedTableSectionsWithoutEmbeddedJson(): void
    {
        $data = [
            'counts' => [
                'unvalidated_root_count' => 1,
                'validated_root_count' => 7,
                'violation_count' => 0,
            ],
            'valid' => true,
        ];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'validateConfig',
            RecordingKernelOpsPort::success(
                'config.validate',
                'web',
                'micro',
                $data,
            ),
        );

        $table = $fixture->run(ConfigValidateCommand::NAME, 'web', 'table');
        self::assertSame(0, $table['exit'], $table['stderr']);
        self::assertSame('', $table['stderr']);
        foreach (
            [
                'counts',
                'unvalidated_root_count',
                'validated_root_count',
                'violation_count',
                '7',
                'valid',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $table['stdout']);
        }
        self::assertStringContainsString('| Key', $table['stdout']);
        self::assertStringContainsString('| unvalidated_root_count', $table['stdout']);
        self::assertStringNotContainsString('{', $table['stdout']);
        self::assertStringNotContainsString('}', $table['stdout']);
        self::assertStringNotContainsString('coretsia.cli-output@1', $table['stdout']);
        self::assertStringNotContainsString("\x1B", $table['stdout']);
        self::assertSame([['method' => 'validateConfig', 'appTarget' => 'web']], $fixture->ops->calls);
        self::assertPortOnlySource(ConfigValidateCommand::class);

        $json = $fixture->run(ConfigValidateCommand::NAME, 'web', 'json');
        self::assertSame(0, $json['exit'], $json['stderr']);
        self::assertSame('coretsia.cli-output@1', KernelOpsCommandFixture::document($json)['schema']);
        self::assertSame($data, KernelOpsCommandFixture::payload($json)['data']);
    }

    public function testModulePlanListsStayVisibleAndExplicitJsonKeepsTheirOriginalShape(): void
    {
        $data = [
            'enabled' => ['core.kernel', 'platform.cli'],
            'excluded' => ['platform.worker'],
            'topological_order' => ['core.kernel', 'platform.cli'],
        ];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'debugModules',
            RecordingKernelOpsPort::success(
                'modules.debug',
                'console',
                'micro',
                $data,
            ),
        );

        $table = $fixture->run(DebugModulesCommand::NAME, 'console', 'table');
        self::assertSame(0, $table['exit'], $table['stderr']);
        self::assertSame('', $table['stderr']);
        foreach (
            [
                'enabled',
                'excluded',
                'topological_order',
                'core.kernel',
                'platform.cli',
                'platform.worker',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $table['stdout']);
        }
        self::assertStringContainsString('| Index', $table['stdout']);
        self::assertStringNotContainsString('[', $table['stdout']);
        self::assertStringNotContainsString(']', $table['stdout']);
        self::assertStringNotContainsString('{', $table['stdout']);
        self::assertStringNotContainsString('}', $table['stdout']);
        self::assertStringNotContainsString("\x1B", $table['stdout']);
        self::assertPortOnlySource(DebugModulesCommand::class);

        $json = $fixture->run(DebugModulesCommand::NAME, 'console', 'json');
        self::assertSame(0, $json['exit'], $json['stderr']);
        $document = KernelOpsCommandFixture::document($json);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertSame(DebugModulesCommand::NAME, $document['meta']['command']);
        self::assertSame($data, KernelOpsCommandFixture::payload($json)['data']);
        self::assertSame([
            ['method' => 'debugModules', 'appTarget' => 'console'],
            ['method' => 'debugModules', 'appTarget' => 'console'],
        ], $fixture->ops->calls);
    }
}
