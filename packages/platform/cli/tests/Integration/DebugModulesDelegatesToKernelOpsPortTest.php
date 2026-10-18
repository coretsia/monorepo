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

use Coretsia\Platform\Cli\Command\DebugModulesCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class DebugModulesDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testDebugModulesDelegatesExactlyOnceWithoutResolvingModulePlanner(): void
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
        $result = $fixture->run(DebugModulesCommand::NAME, 'console');
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame($data, KernelOpsCommandFixture::payload($result)['data']);
        self::assertDelegatedExactlyOnce($fixture, DebugModulesCommand::class, 'debugModules', 'console');
        self::assertPortOnlySource(DebugModulesCommand::class);
        self::assertTargetOnlyMetadata(DebugModulesCommand::class);
    }
}
