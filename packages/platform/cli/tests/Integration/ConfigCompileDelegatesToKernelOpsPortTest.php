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

use Coretsia\Platform\Cli\Command\ConfigCompileCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class ConfigCompileDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testCompileUsesOnePortCallWithExplicitTargetAndNoCompilerResolution(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'compileConfig',
            RecordingKernelOpsPort::success(
                'config.compile',
                'api',
                'micro',
                [
                    'artifacts' => [
                        ['basename' => 'module-manifest.php', 'identity' => 'module-manifest@1'],
                        ['basename' => 'config.php', 'identity' => 'config@1'],
                        ['basename' => 'container.php', 'identity' => 'container@1'],
                        ['basename' => 'generation-manifest.php', 'identity' => 'artifact-generation@1'],
                    ],
                    'generation_id' => 'gen-compile-1',
                ],
            ),
        );
        $result = $fixture->run(ConfigCompileCommand::NAME, 'api');
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('gen-compile-1', KernelOpsCommandFixture::payload($result)['data']['generation_id']);
        self::assertDelegatedExactlyOnce($fixture, ConfigCompileCommand::class, 'compileConfig', 'api');
        self::assertPortOnlySource(ConfigCompileCommand::class);
        self::assertTargetOnlyMetadata(ConfigCompileCommand::class);
    }

    public function testInvalidPortResultFailsClosedWithoutPostCompileVerification(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'compileConfig',
            RecordingKernelOpsPort::success(
                'config.compile',
                'worker',
                'enterprise',
                ['generation_id' => 'gen-missing-artifacts'],
            ),
        );
        $result = $fixture->run(ConfigCompileCommand::NAME, 'worker');
        self::assertSame(1, $result['exit']);
        self::assertStringNotContainsString('gen-missing-artifacts', $result['stdout']);
        self::assertDelegatedExactlyOnce($fixture, ConfigCompileCommand::class, 'compileConfig', 'worker');
    }
}
