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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Driver\RuntimeDrivers;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;

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
    private RuntimeDriverGuard $runtimeDriverGuard;

    public function __construct()
    {
        $this->runtimeDriverGuard = new RuntimeDriverGuard();
    }

    /**
     * Resolves the canonical active runtime-driver set and validates entrypoint
     * compatibility against the caller-provided ModulePlan.
     *
     * Runtime adapters that need the active driver selection must use this
     * method and must not resolve the matrix independently.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function resolveEntrypointDrivers(
        ConfigRepositoryInterface $config,
        ModulePlan $modulePlan,
        RuntimeDriverContributions $runtimeDriverContributions,
    ): RuntimeDrivers {
        return $this->runtimeDriverGuard->resolveForModules(
            cfg: $config,
            plan: $modulePlan,
            contributions: $runtimeDriverContributions,
        );
    }

    /**
     * Asserts that runtime execution may start.
     *
     * This is an assertion-only wrapper around resolveEntrypointDrivers().
     * Callers that need the resolved driver set must call
     * resolveEntrypointDrivers() directly instead of resolving twice.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function assertEntrypointAllowed(
        ConfigRepositoryInterface $config,
        ModulePlan $modulePlan,
        RuntimeDriverContributions $runtimeDriverContributions,
    ): void {
        $this->resolveEntrypointDrivers(
            config: $config,
            modulePlan: $modulePlan,
            runtimeDriverContributions: $runtimeDriverContributions,
        );
    }
}
