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

use Coretsia\Platform\Cli\Command\ConfigDebugCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class ConfigDebugDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testDebugRendersOnlySafeOpsResultDataThroughOnePortCall(): void
    {
        $data = [
            'explain' => [
                'app_target' => 'api',
                'resolution' => ['status' => 'valid'],
            ],
        ];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'debugConfig',
            RecordingKernelOpsPort::success(
                'config.debug',
                'api',
                'hybrid',
                $data,
            ),
        );
        $result = $fixture->run(ConfigDebugCommand::NAME, 'api');
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame([
            'app_target' => 'api',
            'data' => $data,
            'operation' => 'config.debug',
            'outcome' => 'success',
            'preset' => 'hybrid',
        ], KernelOpsCommandFixture::payload($result));
        foreach (
            [
                'PRIVATE_RAW_CONFIG',
                'PRIVATE_ENV_VALUE',
                'PRIVATE_COMPOSER_METADATA',
                'PRIVATE_ARTIFACT_PAYLOAD',
            ] as $private
        ) {
            self::assertStringNotContainsString($private, $result['stdout']);
        }
        self::assertDelegatedExactlyOnce($fixture, ConfigDebugCommand::class, 'debugConfig', 'api');
        self::assertPortOnlySource(ConfigDebugCommand::class);
        self::assertTargetOnlyMetadata(ConfigDebugCommand::class);
    }

    public function testMalformedSafeResultFailsClosedWithoutFallbackConfigReads(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'debugConfig',
            RecordingKernelOpsPort::success(
                'config.debug',
                'api',
                'micro',
                ['invalid_shape' => true],
            ),
        );
        $result = $fixture->run(ConfigDebugCommand::NAME);
        self::assertSame(1, $result['exit']);
        self::assertStringNotContainsString('invalid_shape', $result['stdout']);
        self::assertDelegatedExactlyOnce($fixture, ConfigDebugCommand::class, 'debugConfig', 'api');
    }
}
