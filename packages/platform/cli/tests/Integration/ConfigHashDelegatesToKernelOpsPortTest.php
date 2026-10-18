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

use Coretsia\Platform\Cli\Command\ConfigHashCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class ConfigHashDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testHashUsesOneKernelPortCallAndNoLocalFingerprintOrModuleResolution(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'hashConfig',
            RecordingKernelOpsPort::success(
                'config.hash',
                'console',
                'micro',
                ['generation_id' => 'expected-hash-2'],
            ),
        );
        $result = $fixture->run(ConfigHashCommand::NAME, 'console');
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('expected-hash-2', KernelOpsCommandFixture::payload($result)['data']['generation_id']);
        self::assertDelegatedExactlyOnce($fixture, ConfigHashCommand::class, 'hashConfig', 'console');
        self::assertPortOnlySource(ConfigHashCommand::class);
        self::assertTargetOnlyMetadata(ConfigHashCommand::class);
    }

    public function testMissingGenerationIdDoesNotFallBackToLocalHashCalculation(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'hashConfig',
            RecordingKernelOpsPort::success(
                'config.hash',
                'console',
                'micro',
                [],
            ),
        );
        $result = $fixture->run(ConfigHashCommand::NAME, 'console');
        self::assertSame(1, $result['exit']);
        self::assertDelegatedExactlyOnce($fixture, ConfigHashCommand::class, 'hashConfig', 'console');
    }
}
