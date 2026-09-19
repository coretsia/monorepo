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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface;
use Coretsia\Contracts\Observability\Profiling\ProfilingSessionInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Profiling\NoopProfiler;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FoundationResolvesNoopObservabilityBindingsTest extends TestCase
{
    public function testFoundationProviderResolvesNoopObservabilityBindings(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(LoggerInterface::class));
        self::assertTrue($container->has(TracerPortInterface::class));
        self::assertTrue($container->has(MeterPortInterface::class));
        self::assertTrue($container->has(ErrorReporterPortInterface::class));
        self::assertTrue($container->has(ProfilerPortInterface::class));
        self::assertTrue($container->has(ContextPropagationInterface::class));

        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));
        self::assertInstanceOf(NoopErrorReporter::class, $container->get(ErrorReporterPortInterface::class));
        self::assertInstanceOf(NoopProfiler::class, $container->get(ProfilerPortInterface::class));
        self::assertInstanceOf(NoopContextPropagation::class, $container->get(ContextPropagationInterface::class));
    }

    public function testFoundationProviderDoesNotRegisterSpanOrProfilingSessionAsRootBindings(): void
    {
        $container = self::foundationContainer();

        self::assertFalse($container->has(SpanInterface::class));
        self::assertFalse($container->has(ProfilingSessionInterface::class));
    }

    private static function foundationContainer(): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
            ],
        ]);

        $builder->register(new FoundationServiceProvider());

        return $builder->build();
    }
}
