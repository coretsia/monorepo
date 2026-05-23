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

namespace Coretsia\Kernel\Provider;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;

/**
 * Kernel DI wiring entrypoint.
 *
 * This provider registers Kernel-owned runtime services without changing
 * provider ordering semantics. `ContainerBuilder` still preserves the exact
 * caller-supplied provider order.
 *
 * 1.270.0 intentionally keeps this provider minimal:
 *
 * - it validates the Kernel-owned config subset used by UnitOfWork shapes;
 * - it does not register a UnitOfWork lifecycle executor;
 * - it does not dispatch before/after hooks;
 * - it does not trigger reset orchestration;
 * - it does not define reset tag constants;
 * - it does not derive HTTP or CLI outcomes;
 * - it does not depend on PSR-7, PSR-15, or platform adapters.
 *
 * Runtime lifecycle execution is introduced by later Kernel runtime epics.
 *
 * This provider must not emit stdout/stderr, must not use tooling-only
 * packages, and must not introduce static mutable snapshots.
 */
final class KernelServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $kernelConfig = $builder->configRoot('kernel');

        /*
         * Validate the Kernel-owned config subset early and deterministically.
         *
         * The returned limits are consumed by UnitOfWork shape construction in
         * later runtime wiring. 1.270.0 only cements the config contract and
         * keeps lifecycle executor registration out of scope.
         */
        KernelServiceFactory::unitOfWorkAttributeLimits($kernelConfig);
    }
}
