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
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
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
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Foundation runtime DI definition provider.
 *
 * `define()` is the single source of Foundation runtime wiring for source-mode
 * container application and future compiled-container contribution.
 *
 * `register()` does not maintain a parallel imperative runtime graph. It only
 * delegates this provider to the ContainerBuilder declarative adapter.
 *
 * Wiring decisions:
 *
 * - `TagRegistry` is a builder-owned source-runtime seed and is not emitted as
 *   a canonical provider definition;
 * - runtime service definitions are shared by default;
 * - `SystemClock` is the baseline `ClockInterface` target;
 * - `FrozenClock` remains test/support infrastructure and is not registered;
 * - `IdGeneratorInterface` aliases the configured shared ULID or UUID target;
 * - `foundation.ids.default` does not affect correlation-id generation;
 * - `ContextAccessorInterface` aliases the shared `ContextStore`;
 * - correlation services remain ULID-backed;
 * - noop logging and observability implementations are defined directly under
 *   their public port/interface service IDs;
 * - reset orchestrators are represented through public static class-method
 *   factories; container-resolved dependencies are declared through
 *   `requireService()`;
 * - `ContextStore` is tagged with the effective Foundation reset tag and the
 *   fixed `kernel.stateful` enforcement tag;
 * - `DeterministicOrder` is not registered because it is a stateless static
 *   utility.
 *
 * This provider must not emit stdout/stderr, use tooling-only packages, read
 * filesystem or environment sources, resolve services, start runtime
 * lifecycle, or introduce static mutable snapshots during definition
 * production.
 */
final class FoundationServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    private const string PARAM_FOUNDATION_CONFIG = 'foundation.config';

    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();

        $foundationConfig = $builder->configRoot('foundation');

        FoundationServiceFactory::effectiveResetTag($foundationConfig);
        FoundationServiceFactory::defaultIdGeneratorServiceId($foundationConfig);

        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $foundationConfig = $context->configRoot('foundation');
        $effectiveResetTag = FoundationServiceFactory::effectiveResetTag($foundationConfig);
        $defaultIdGeneratorServiceId = FoundationServiceFactory::defaultIdGeneratorServiceId($foundationConfig);
        $foundationConfigReference = ContainerValueReference::parameter(self::PARAM_FOUNDATION_CONFIG);

        $definitions
            ->parameter(
                self::PARAM_FOUNDATION_CONFIG,
                $foundationConfig,
            )
            ->requireService(ContainerInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(Stopwatch::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
            ->classService(
                SystemClock::class,
                SystemClock::class,
            )
            ->alias(
                ClockInterface::class,
                SystemClock::class,
            )
            ->classService(
                Stopwatch::class,
                Stopwatch::class,
            )
            ->classService(
                UlidGenerator::class,
                UlidGenerator::class,
            )
            ->classService(
                UuidGenerator::class,
                UuidGenerator::class,
            )
            ->alias(
                IdGeneratorInterface::class,
                $defaultIdGeneratorServiceId,
            )
            ->classService(
                ContextStore::class,
                ContextStore::class,
            )
            ->alias(
                ContextAccessorInterface::class,
                ContextStore::class,
            )
            ->classService(
                id: CorrelationIdGenerator::class,
                class: CorrelationIdGenerator::class,
                arguments: [
                    ContainerValueReference::service(UlidGenerator::class),
                ],
            )
            ->classService(
                id: CorrelationIdProvider::class,
                class: CorrelationIdProvider::class,
                arguments: [
                    ContainerValueReference::service(ContextAccessorInterface::class),
                ],
            )
            ->alias(
                CorrelationIdProviderInterface::class,
                CorrelationIdProvider::class,
            )
            ->tag(
                $effectiveResetTag,
                ContextStore::class,
            )
            ->tag(
                ReservedTags::KERNEL_STATEFUL,
                ContextStore::class,
            )
            ->classService(
                id: LoggerInterface::class,
                class: NoopLogger::class,
            )
            ->classService(
                id: TracerPortInterface::class,
                class: NoopTracer::class,
            )
            ->classService(
                id: MeterPortInterface::class,
                class: NoopMeter::class,
            )
            ->classService(
                id: ErrorReporterPortInterface::class,
                class: NoopErrorReporter::class,
            )
            ->classService(
                id: ProfilerPortInterface::class,
                class: NoopProfiler::class,
            )
            ->classService(
                id: ContextPropagationInterface::class,
                class: NoopContextPropagation::class,
            )
            ->classMethodFactory(
                id: PriorityResetOrchestrator::class,
                factoryClass: FoundationServiceFactory::class,
                method: 'priorityResetOrchestrator',
                arguments: [
                    ContainerValueReference::service(ContainerInterface::class),
                    ContainerValueReference::service(TagRegistry::class),
                    $foundationConfigReference,
                ],
            )
            ->classMethodFactory(
                id: ResetOrchestrator::class,
                factoryClass: FoundationServiceFactory::class,
                method: 'resetOrchestrator',
                arguments: [
                    ContainerValueReference::service(ContainerInterface::class),
                    ContainerValueReference::service(TagRegistry::class),
                    $foundationConfigReference,
                ],
            );
    }
}
