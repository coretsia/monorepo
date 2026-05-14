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
use Coretsia\Foundation\Clock\SystemClock;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Profiling\NoopProfiler;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Psr\Clock\ClockInterface;
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
 * - `PriorityResetOrchestrator` is created through `FoundationServiceFactory`;
 * - `ResetOrchestrator` remains the stable public reset entrypoint and is
 *   created through `FoundationServiceFactory`;
 * - noop observability and logging ports are registered as explicit instances
 *   so they remain resolvable without relying on concrete-class autowiring;
 * - Foundation clock/id/stopwatch services are registered as explicit
 *   instances so they remain resolvable without relying on concrete-class
 *   autowiring;
 * - `SystemClock` is the baseline runtime `ClockInterface` binding;
 * - `FrozenClock` is test/support infrastructure and is not registered as the
 *   default runtime clock binding by this provider;
 * - `IdGeneratorInterface` is selected only by `foundation.ids.default`;
 * - `foundation.ids.default` does not affect correlation id generation;
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

        $clock = new SystemClock();
        $stopwatch = new Stopwatch();

        $ulids = new UlidGenerator();
        $uuids = new UuidGenerator();
        $defaultIds = FoundationServiceFactory::defaultIdGenerator(
            foundationConfig: $foundationConfig,
            ulids: $ulids,
            uuids: $uuids,
        );

        $correlationIds = new CorrelationIdGenerator($ulids);
        $correlationIdProvider = new CorrelationIdProvider($contextStore);

        $logger = new NoopLogger();
        $tracer = new NoopTracer();
        $meter = new NoopMeter();
        $errorReporter = new NoopErrorReporter();
        $profiler = new NoopProfiler();
        $contextPropagation = new NoopContextPropagation();

        $builder->instance(TagRegistry::class, $tagRegistry);

        $builder->instance(SystemClock::class, $clock);
        $builder->instance(ClockInterface::class, $clock);
        $builder->instance(Stopwatch::class, $stopwatch);

        $builder->instance(UlidGenerator::class, $ulids);
        $builder->instance(UuidGenerator::class, $uuids);
        $builder->instance(IdGeneratorInterface::class, $defaultIds);

        $builder->instance(ContextStore::class, $contextStore);
        $builder->instance(ContextAccessorInterface::class, $contextStore);

        $builder->instance(CorrelationIdGenerator::class, $correlationIds);
        $builder->instance(CorrelationIdProvider::class, $correlationIdProvider);
        $builder->instance(CorrelationIdProviderInterface::class, $correlationIdProvider);

        $builder->tag($effectiveResetTag, ContextStore::class);
        $builder->tag(Tags::KERNEL_STATEFUL, ContextStore::class);

        $builder->instance(LoggerInterface::class, $logger);
        $builder->instance(TracerPortInterface::class, $tracer);
        $builder->instance(MeterPortInterface::class, $meter);
        $builder->instance(ErrorReporterPortInterface::class, $errorReporter);
        $builder->instance(ProfilerPortInterface::class, $profiler);
        $builder->instance(ContextPropagationInterface::class, $contextPropagation);

        $builder->factory(
            PriorityResetOrchestrator::class,
            static fn (
                Container $container
            ): PriorityResetOrchestrator => FoundationServiceFactory::priorityResetOrchestrator(
                container: $container,
                tagRegistry: $tagRegistry,
                foundationConfig: $foundationConfig,
                stopwatch: $stopwatch,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        $builder->factory(
            ResetOrchestrator::class,
            static fn (Container $container): ResetOrchestrator => FoundationServiceFactory::resetOrchestrator(
                container: $container,
                tagRegistry: $tagRegistry,
                foundationConfig: $foundationConfig,
                stopwatch: $stopwatch,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );
    }
}
