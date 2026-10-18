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

final class ConfigHashRendersGenerationIdTest extends KernelOpsIntegrationTestCase
{
    public function testHashRendersOnlyExpectedGenerationFromKernelWithoutPublicationClaims(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'hashConfig',
            RecordingKernelOpsPort::success(
                'config.hash',
                'web',
                'express',
                ['generation_id' => 'expected-gen-71'],
            ),
        );

        $result = $fixture->run(ConfigHashCommand::NAME, 'web');
        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        $payload = KernelOpsCommandFixture::payload($result);
        self::assertSame([
            'app_target' => 'web',
            'data' => ['generation_id' => 'expected-gen-71'],
            'operation' => 'config.hash',
            'outcome' => 'success',
            'preset' => 'express',
        ], $payload);
        self::assertSame(['generation_id'], \array_keys($payload['data']));
        foreach (
            [
                'current_generation_id',
                'published_generation_id',
                'artifacts',
                'path',
                'generations/current/',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $result['stdout']);
        }
        self::assertDelegatedExactlyOnce($fixture, ConfigHashCommand::class, 'hashConfig', 'web');
        self::assertPortOnlySource(ConfigHashCommand::class);
        self::assertTargetOnlyMetadata(ConfigHashCommand::class);
    }
}
