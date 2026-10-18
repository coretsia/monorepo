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

use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class ConfigValidateDelegatesToKernelOpsPortTest extends KernelOpsIntegrationTestCase
{
    public function testValidateCallsOnlyKernelOpsPortForExplicitTarget(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'validateConfig',
            RecordingKernelOpsPort::success(
                'config.validate',
                'worker',
                'hybrid',
                [
                    'counts' => [
                        'unvalidated_root_count' => 0,
                        'validated_root_count' => 2,
                        'violation_count' => 0,
                    ],
                    'valid' => true,
                ],
            ),
        );
        $result = $fixture->run(ConfigValidateCommand::NAME, 'worker');
        self::assertSame(0, $result['exit'], $result['stderr']);
        $payload = KernelOpsCommandFixture::payload($result);
        self::assertSame('success', $payload['outcome']);
        self::assertSame(true, $payload['data']['valid']);
        self::assertSame(0, $payload['data']['counts']['violation_count']);
        self::assertDelegatedExactlyOnce($fixture, ConfigValidateCommand::class, 'validateConfig', 'worker');
        self::assertPortOnlySource(ConfigValidateCommand::class);
        self::assertTargetOnlyMetadata(ConfigValidateCommand::class);
    }

    public function testHandledKernelValidationFailureKeepsItsOwnFailureBoundary(): void
    {
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'validateConfig',
            new OpsResult(
                'config.validate',
                'api',
                'micro',
                'handled_error',
                'config-validation-failed',
                [
                    'counts' => [
                        'unvalidated_root_count' => 0,
                        'validated_root_count' => 2,
                        'violation_count' => 1,
                    ],
                    'valid' => false,
                ],
            ),
        );
        $result = $fixture->run(ConfigValidateCommand::NAME, 'api');
        self::assertSame(1, $result['exit'], $result['stderr']);
        $payload = KernelOpsCommandFixture::payload($result);
        self::assertSame('handled_error', $payload['outcome']);
        self::assertSame('config-validation-failed', $payload['reason']);
        self::assertSame(false, $payload['data']['valid']);
        self::assertSame(1, $payload['data']['counts']['violation_count']);
        self::assertDelegatedExactlyOnce($fixture, ConfigValidateCommand::class, 'validateConfig', 'api');
    }
}
