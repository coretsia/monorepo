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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverGuardRejectsMissingRuntimeDriverConfigTest extends TestCase
{
    public function testRejectsMissingWorkerTaskTypeConfigKey(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'frankenphp' => [
                        'enabled' => false,
                    ],
                    'swoole' => [
                        'enabled' => false,
                    ],
                    'roadrunner' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_WORKER_TASK_TYPE_MISSING,
        );

        new RuntimeDriverGuard()->detect($cfg);
    }

    public function testDetectsClassicHttpPlusWorkerQueueWhenWorkerTaskTypeIsQueue(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'frankenphp' => [
                        'enabled' => false,
                    ],
                    'swoole' => [
                        'enabled' => false,
                    ],
                    'roadrunner' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'worker' => [
                'task_type' => 'queue',
            ],
        ]);

        $drivers = new RuntimeDriverGuard()->detect($cfg);

        self::assertSame(HttpDriver::CLASSIC, $drivers->httpDriver());
        self::assertSame('http.classic', $drivers->httpDriverId());
        self::assertSame(['bg.worker_queue'], $drivers->backgroundDriverIds());
        self::assertSame(['bg.worker_queue', 'http.classic'], $drivers->driverIds());
    }
}
