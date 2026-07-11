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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverGuardRejectsInvalidRuntimeDriverConfigTest extends TestCase
{
    public function testRejectsMissingKernelRuntimeHttpDriverConfigKey(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [],
            ],
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);

        new RuntimeDriverGuard()->detect($cfg);
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

        new RuntimeDriverGuard()->detect($cfg);
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

        new RuntimeDriverGuard()->detect($cfg);
    }
}
