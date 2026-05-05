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

namespace Coretsia\Foundation\Provider;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;

/**
 * Foundation DI wiring entrypoint.
 *
 * This provider registers Foundation-owned runtime services without changing
 * provider ordering semantics. `ContainerBuilder` still preserves the exact
 * caller-supplied provider order.
 *
 * Wiring decisions:
 *
 * - `TagRegistry` is registered as the exact builder-owned instance;
 * - `ResetOrchestrator` is created through `FoundationServiceFactory`;
 * - `DeterministicOrder` is not registered because it is a stateless static
 *   utility and the epic marks service registration for it as optional.
 *
 * This provider must not emit stdout/stderr, must not use tooling-only
 * packages, and must not introduce static mutable snapshots.
 */
final class FoundationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $tagRegistry = $builder->tagRegistry();
        $foundationConfig = $builder->configRoot('foundation');

        $builder->instance(TagRegistry::class, $tagRegistry);

        $builder->factory(
            ResetOrchestrator::class,
            static fn (Container $container): ResetOrchestrator => FoundationServiceFactory::resetOrchestrator(
                container: $container,
                tagRegistry: $tagRegistry,
                foundationConfig: $foundationConfig,
            ),
        );
    }
}
