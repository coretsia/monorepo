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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverResolverRejectsInvalidRuntimeDriverConfigTest extends TestCase
{
    public function testRejectsMissingKernelRuntimeHttpDriverConfigKey(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [],
            ],
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_MISSING,
        );

        new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );
    }

    public function testRejectsNonStringKernelRuntimeHttpDriverConfigValue(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => ['http.classic'],
                ],
            ],
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
        );

        new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );
    }

    public function testRejectsUnknownKernelRuntimeHttpDriverConfigValue(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.unknown',
                ],
            ],
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
        );

        new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );
    }
}
