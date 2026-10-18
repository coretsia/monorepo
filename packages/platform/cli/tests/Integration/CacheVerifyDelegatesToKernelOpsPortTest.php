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

use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class CacheVerifyDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testVerifyCallsOnlyKernelOpsPortWithExplicitTarget(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'verifyCache',
            RecordingKernelOpsPort::success(
                'cache.verify',
                'web',
                'express',
                [
                    'artifacts' => [
                        self::artifact('module-manifest', 'module-manifest.php'),
                        self::artifact('config', 'config.php'),
                        self::artifact('container', 'container.php'),
                        self::artifact('artifact-generation', 'generation-manifest.php'),
                    ],
                    'current_generation_id' => 'gen-verify-1',
                    'expected_generation_id' => 'gen-verify-1',
                    'state' => 'clean',
                ],
            ),
        );
        $result = $fixture->run(CacheVerifyCommand::NAME, 'web');
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('clean', KernelOpsCommandFixture::payload($result)['data']['state']);
        self::assertDelegatedExactlyOnce($fixture, CacheVerifyCommand::class, 'verifyCache', 'web');
        self::assertPortOnlySource(CacheVerifyCommand::class);
        self::assertTargetOnlyMetadata(CacheVerifyCommand::class);
    }

    /** @return array<string,mixed> */
    private static function artifact(string $name, string $basename): array
    {
        return [
            'basename' => $basename,
            'existing_byte_count' => 10,
            'expected_byte_count' => 10,
            'name' => $name,
            'reason' => 'ok',
            'status' => 'clean',
        ];
    }
}
