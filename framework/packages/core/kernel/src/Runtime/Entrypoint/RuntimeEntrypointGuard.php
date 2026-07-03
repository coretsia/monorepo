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

namespace Coretsia\Kernel\Runtime\Entrypoint;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;

/**
 * Kernel-owned runtime entrypoint compatibility boundary.
 *
 * Runtime adapters and production boot paths must pass through this guard after
 * config and ModulePlan are resolved and before runtime execution starts.
 *
 * This guard does not resolve config, resolve ModulePlan, inspect env, inspect
 * container services, read artifacts, start KernelRuntime, or fallback to
 * http.classic.
 *
 * Runtime-driver matrix detection remains an internal Kernel implementation
 * detail owned by RuntimeDriverGuard.
 */
final readonly class RuntimeEntrypointGuard
{
    private RuntimeDriverGuard $runtimeDrivers;

    public function __construct()
    {
        $this->runtimeDrivers = new RuntimeDriverGuard();
    }

    public function assertEntrypointAllowed(
        ConfigRepositoryInterface $config,
        ModulePlan $modulePlan,
    ): void {
        $this->runtimeDrivers->assertHttpDriverCompatibleWithModules(
            cfg: $config,
            plan: $modulePlan,
        );
    }
}
