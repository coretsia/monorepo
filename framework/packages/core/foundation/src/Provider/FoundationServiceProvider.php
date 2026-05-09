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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Profiling\NoopProfiler;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Psr\Log\LoggerInterface;

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
 * - noop observability and logging ports are registered as explicit instances
 *   so they remain resolvable without relying on concrete-class autowiring;
 * - context and correlation services are registered as explicit instances so
 *   they remain resolvable without relying on concrete-class autowiring;
 * - `ContextStore` is registered once and the context accessor binding points
 *   to the same object instance;
 * - `ContextStore` is tagged with the effective Foundation reset discovery tag
 *   and with the fixed `kernel.stateful` enforcement marker;
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
        $effectiveResetTag = FoundationServiceFactory::effectiveResetTag($foundationConfig);

        $contextStore = new ContextStore();

        $ulids = new UlidGenerator();
        $correlationIds = new CorrelationIdGenerator($ulids);
        $correlationIdProvider = new CorrelationIdProvider($contextStore);

        $builder->instance(TagRegistry::class, $tagRegistry);

        $builder->instance(ContextStore::class, $contextStore);
        $builder->instance(ContextAccessorInterface::class, $contextStore);

        $builder->instance(UlidGenerator::class, $ulids);
        $builder->instance(CorrelationIdGenerator::class, $correlationIds);
        $builder->instance(CorrelationIdProvider::class, $correlationIdProvider);
        $builder->instance(CorrelationIdProviderInterface::class, $correlationIdProvider);

        $builder->tag($effectiveResetTag, ContextStore::class);
        $builder->tag(Tags::KERNEL_STATEFUL, ContextStore::class);

        $builder->instance(LoggerInterface::class, new NoopLogger());
        $builder->instance(TracerPortInterface::class, new NoopTracer());
        $builder->instance(MeterPortInterface::class, new NoopMeter());
        $builder->instance(ErrorReporterPortInterface::class, new NoopErrorReporter());
        $builder->instance(ProfilerPortInterface::class, new NoopProfiler());
        $builder->instance(ContextPropagationInterface::class, new NoopContextPropagation());

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
