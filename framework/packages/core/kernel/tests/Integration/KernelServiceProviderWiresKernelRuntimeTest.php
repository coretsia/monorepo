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

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class KernelServiceProviderWiresKernelRuntimeTest extends TestCase
{
    public function testProviderRegistersKernelRuntimeServicesAndInterfaceBinding(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $container = self::container($resetSpy);

        self::assertTrue($container->has(KernelRuntime::class));
        self::assertTrue($container->has(HookInvoker::class));
        self::assertTrue($container->has(KernelRuntimeInterface::class));

        $runtime = $container->get(KernelRuntime::class);
        $hooks = $container->get(HookInvoker::class);
        $runtimePort = $container->get(KernelRuntimeInterface::class);

        self::assertInstanceOf(KernelRuntime::class, $runtime);
        self::assertInstanceOf(HookInvoker::class, $hooks);
        self::assertInstanceOf(KernelRuntime::class, $runtimePort);
        self::assertInstanceOf(KernelRuntimeInterface::class, $runtimePort);
    }

    public function testKernelRuntimeReceivesRequiredDependenciesThroughDi(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $container = self::container($resetSpy);

        $runtime = $container->get(KernelRuntime::class);

        self::assertInstanceOf(KernelRuntime::class, $runtime);

        self::assertPrivatePropertyInstanceOf(
            runtime: $runtime,
            property: 'resetOrchestrator',
            expectedType: ResetOrchestrator::class,
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'contextStore',
            expected: self::service($container, ContextStore::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'stopwatch',
            expected: self::service($container, Stopwatch::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'uowIds',
            expected: self::service($container, IdGeneratorInterface::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'correlationIdProvider',
            expected: self::service($container, CorrelationIdProviderInterface::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'correlationIds',
            expected: self::service($container, CorrelationIdGenerator::class),
        );
        self::assertPrivatePropertyInstanceOf(
            runtime: $runtime,
            property: 'hooks',
            expectedType: HookInvoker::class,
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'logger',
            expected: self::service($container, LoggerInterface::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'tracer',
            expected: self::service($container, TracerPortInterface::class),
        );
        self::assertPrivatePropertySame(
            runtime: $runtime,
            property: 'meter',
            expected: self::service($container, MeterPortInterface::class),
        );
    }

    public function testFoundationNoopObservabilityBindingsAreResolvableForKernelRuntime(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $container = self::container($resetSpy);

        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));

        self::assertInstanceOf(KernelRuntime::class, $container->get(KernelRuntime::class));
    }

    public function testProviderDoesNotStartUnitOfWorkOrTriggerResetDuringRegistrationBuildOrResolution(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $builder = self::builder($resetSpy);

        new FoundationServiceProvider()->register($builder);

        $builder->instance(
            KernelServiceProviderWiresKernelRuntimeResetSpy::class,
            $resetSpy,
        );
        $builder->tag(
            ReservedTags::KERNEL_RESET,
            KernelServiceProviderWiresKernelRuntimeResetSpy::class,
        );

        self::assertSame(0, $resetSpy->resetCount());

        new KernelServiceProvider()->register($builder);

        self::assertSame(0, $resetSpy->resetCount());

        $container = $builder->build();

        self::assertSame(0, $resetSpy->resetCount());

        $contextStore = $container->get(ContextStore::class);

        self::assertInstanceOf(ContextStore::class, $contextStore);
        self::assertBaseContextKeysAreAbsent($contextStore);

        self::assertInstanceOf(HookInvoker::class, $container->get(HookInvoker::class));
        self::assertInstanceOf(KernelRuntime::class, $container->get(KernelRuntime::class));
        self::assertInstanceOf(KernelRuntime::class, $container->get(KernelRuntimeInterface::class));

        self::assertSame(0, $resetSpy->resetCount());
        self::assertBaseContextKeysAreAbsent($contextStore);
    }

    private static function container(KernelServiceProviderWiresKernelRuntimeResetSpy $resetSpy): Container
    {
        $builder = self::builder($resetSpy);

        new FoundationServiceProvider()->register($builder);

        $builder->instance(
            KernelServiceProviderWiresKernelRuntimeResetSpy::class,
            $resetSpy,
        );
        $builder->tag(
            ReservedTags::KERNEL_RESET,
            KernelServiceProviderWiresKernelRuntimeResetSpy::class,
        );

        new KernelServiceProvider()->register($builder);

        return $builder->build();
    }

    private static function builder(KernelServiceProviderWiresKernelRuntimeResetSpy $resetSpy): ContainerBuilder
    {
        return new ContainerBuilder(config: self::validConfig());
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
                'ids' => [
                    'default' => 'ulid',
                ],
                'reset' => [
                    'tag' => ReservedTags::KERNEL_RESET,
                    'priority' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'kernel' => [
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }

    private static function service(Container $container, string $id): object
    {
        self::assertTrue($container->has($id));

        $service = $container->get($id);

        self::assertIsObject($service);

        return $service;
    }

    private static function assertPrivatePropertySame(
        KernelRuntime $runtime,
        string $property,
        object $expected,
    ): void {
        self::assertSame(
            $expected,
            self::privateProperty($runtime, $property),
        );
    }

    private static function assertPrivatePropertyInstanceOf(
        KernelRuntime $runtime,
        string $property,
        string $expectedType,
    ): void {
        self::assertInstanceOf(
            $expectedType,
            self::privateProperty($runtime, $property),
        );
    }

    private static function privateProperty(KernelRuntime $runtime, string $property): mixed
    {
        $reflection = new \ReflectionProperty($runtime, $property);

        return $reflection->getValue($runtime);
    }

    private static function assertBaseContextKeysAreAbsent(ContextStore $contextStore): void
    {
        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }
}

final class KernelServiceProviderWiresKernelRuntimeResetSpy implements ResetInterface
{
    private int $resetCount = 0;

    public function reset(): void
    {
        ++$this->resetCount;
    }

    public function resetCount(): int
    {
        return $this->resetCount;
    }
}
