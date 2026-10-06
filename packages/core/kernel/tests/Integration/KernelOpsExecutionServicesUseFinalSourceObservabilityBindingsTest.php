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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;

final class KernelOpsExecutionServicesUseFinalSourceObservabilityBindingsTest extends TestCase
{
    public function testExecutionServicesReuseExactFinalObservabilityBindingsWithoutExecutingOperations(): void
    {
        $baseline = self::baselineConfig();

        $finalBuilder = new ContainerBuilder(config: $baseline);
        $finalBuilder->registerProviders([
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
            new KernelOpsFinalObservabilityProvider(),
        ]);

        $installHostOwnedFactories = new ReflectionMethod(
            KernelOpsHostBooter::class,
            'installHostOwnedFactories',
        );

        $installHostOwnedFactories->invoke(
            null,
            $finalBuilder,
            $baseline,
            [
                FoundationServiceProvider::class,
                KernelServiceProvider::class,
            ],
        );

        $finalContainer = $finalBuilder->build();

        $logger = $finalContainer->get(LoggerInterface::class);
        $tracer = $finalContainer->get(TracerPortInterface::class);
        $meter = $finalContainer->get(MeterPortInterface::class);
        $stopwatch = $finalContainer->get(Stopwatch::class);
        $execution = $finalContainer->get(KernelOpsExecutionServices::class);

        self::assertInstanceOf(KernelOpsFinalBindingLogger::class, $logger);
        self::assertInstanceOf(KernelOpsFinalBindingTracer::class, $tracer);
        self::assertInstanceOf(KernelOpsFinalBindingMeter::class, $meter);
        self::assertInstanceOf(Stopwatch::class, $stopwatch);
        self::assertInstanceOf(KernelOpsExecutionServices::class, $execution);

        self::assertNotInstanceOf(NoopLogger::class, $logger);
        self::assertNotInstanceOf(NoopTracer::class, $tracer);
        self::assertNotInstanceOf(NoopMeter::class, $meter);

        self::assertSame($baseline['kernel'], $execution->kernelConfig());

        foreach (
            [
                $execution->moduleResolutionOrchestrator(),
                $execution->configKernel(),
            ] as $service
        ) {
            self::assertSame($logger, self::property($service, 'logger'));
            self::assertSame($tracer, self::property($service, 'tracer'));
            self::assertSame($meter, self::property($service, 'meter'));
            self::assertSame($stopwatch, self::property($service, 'stopwatch'));
        }

        $operation = $execution->kernelArtifactOperation();
        $compiler = self::property($operation, 'artifactCompiler');
        $verifier = self::property($operation, 'cacheVerifier');
        $compilerConfigKernel = self::property($compiler, 'configKernel');

        foreach ([$compilerConfigKernel, $verifier] as $service) {
            self::assertSame($logger, self::property($service, 'logger'));
            self::assertSame($tracer, self::property($service, 'tracer'));
            self::assertSame($meter, self::property($service, 'meter'));
            self::assertSame($stopwatch, self::property($service, 'stopwatch'));
        }

        self::assertSame(
            $execution->fingerprintCalculator(),
            self::property($compiler, 'fingerprintCalculator'),
        );
        self::assertSame(
            $execution->runtimeContainerGraphCompiler(),
            self::property($compiler, 'runtimeContainerGraphCompiler'),
        );

        $hostSource = self::source('../../src/Ops/KernelOpsHostBooter.php');
        $factorySource = self::source('../../src/Provider/KernelServiceFactory.php');

        foreach (
            [
                '$logger = $container->get(LoggerInterface::class);',
                '$tracer = $container->get(TracerPortInterface::class);',
                '$meter = $container->get(MeterPortInterface::class);',
                '$stopwatch = $container->get(Stopwatch::class);',
                'logger: $logger',
                'tracer: $tracer',
                'meter: $meter',
                'stopwatch: $stopwatch',
            ] as $finalBindingWiring
        ) {
            self::assertStringContainsString($finalBindingWiring, $hostSource);
        }

        $executionFactory = self::between(
            $factorySource,
            'public static function kernelOpsExecutionServices',
            'public static function kernelOpsFacade',
        );

        foreach (
            [
                '->resolve(',
                '->compile(',
                '->calculate(',
                '->publish(',
                '->verify(',
            ] as $forbiddenExecution
        ) {
            self::assertStringNotContainsString($forbiddenExecution, $executionFactory);
        }

        self::assertStringContainsString(
            'new ContainerBuilder(config: $baselineConfig)',
            $executionFactory,
        );

        self::assertSame(0, $logger->calls);
        self::assertSame(0, $tracer->calls);
        self::assertSame(0, $meter->calls);
    }

    private static function property(object $object, string $name): mixed
    {
        return new ReflectionProperty($object, $name)->getValue($object);
    }

    private static function source(string $relative): string
    {
        $source = \file_get_contents(__DIR__ . '/' . $relative);

        self::assertIsString($source);

        return $source;
    }

    private static function between(string $source, string $start, string $end): string
    {
        $from = \strpos($source, $start);
        self::assertIsInt($from);
        $to = \strpos($source, $end, $from + \strlen($start));

        return $to === false
            ? \substr($source, $from)
            : \substr($source, $from, $to - $from);
    }

    private static function baselineConfig(): array
    {
        $foundation = require \dirname(__DIR__, 3) . '/foundation/config/foundation.php';
        $kernel = require \dirname(__DIR__, 2) . '/config/kernel.php';

        self::assertIsArray($foundation);
        self::assertIsArray($kernel);

        return [
            FoundationModule::CONFIG_ROOT => $foundation,
            KernelModule::CONFIG_ROOT => $kernel,
        ];
    }
}

final readonly class KernelOpsFinalObservabilityProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->classService(
                LoggerInterface::class,
                KernelOpsFinalBindingLogger::class,
            )
            ->classService(
                TracerPortInterface::class,
                KernelOpsFinalBindingTracer::class,
            )
            ->classService(
                MeterPortInterface::class,
                KernelOpsFinalBindingMeter::class,
            );
    }
}

final class KernelOpsFinalBindingLogger extends AbstractLogger
{
    public int $calls = 0;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        ++$this->calls;
    }
}

final class KernelOpsFinalBindingTracer implements TracerPortInterface
{
    public int $calls = 0;

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        ++$this->calls;

        return new KernelOpsFinalBindingSpan($name);
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        ++$this->calls;

        return $callback(new KernelOpsFinalBindingSpan($name));
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsFinalBindingSpan implements SpanInterface
{
    public function __construct(private readonly string $spanName)
    {
    }

    public function name(): string
    {
        return $this->spanName;
    }

    public function setAttribute(string $key, mixed $value): void
    {
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
    }
}

final class KernelOpsFinalBindingMeter implements MeterPortInterface
{
    public int $calls = 0;

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        ++$this->calls;
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        ++$this->calls;
    }
}
