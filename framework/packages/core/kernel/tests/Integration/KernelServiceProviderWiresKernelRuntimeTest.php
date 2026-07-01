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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
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
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class KernelServiceProviderWiresKernelRuntimeTest extends TestCase
{
    public function testProviderRegistersKernelRuntimeServicesAndInterfaceBinding(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $container = self::container($resetSpy, self::validConfig());

        self::assertTrue($container->has(KernelRuntime::class));
        self::assertTrue($container->has(HookInvoker::class));
        self::assertTrue($container->has(KernelRuntimeInterface::class));
        self::assertTrue($container->has(RuntimeEntrypointGuard::class));

        $runtime = $container->get(KernelRuntime::class);
        $hooks = $container->get(HookInvoker::class);
        $runtimePort = $container->get(KernelRuntimeInterface::class);
        $entrypointGuard = $container->get(RuntimeEntrypointGuard::class);

        self::assertInstanceOf(KernelRuntime::class, $runtime);
        self::assertInstanceOf(HookInvoker::class, $hooks);
        self::assertInstanceOf(KernelRuntime::class, $runtimePort);
        self::assertInstanceOf(KernelRuntimeInterface::class, $runtimePort);
        self::assertInstanceOf(RuntimeEntrypointGuard::class, $entrypointGuard);
    }

    public function testKernelRuntimeReceivesRequiredDependenciesThroughDi(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $container = self::container($resetSpy, self::validConfig());

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
        $container = self::container($resetSpy, self::validConfig());

        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));

        self::assertInstanceOf(KernelRuntime::class, $container->get(KernelRuntime::class));
    }

    public function testProviderDoesNotStartUnitOfWorkOrTriggerResetDuringRegistrationBuildOrResolution(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();
        $builder = self::builder($resetSpy, self::validConfig());

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

    public function testKernelRuntimeUsesConfiguredUnitOfWorkAttributeMaxDepthThroughDi(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();

        $config = self::validConfig();
        $config['kernel']['uow']['attributes']['max_depth'] = 1;
        $config['kernel']['uow']['attributes']['max_keys'] = 200;

        $container = self::container($resetSpy, $config);

        $runtime = $container->get(KernelRuntime::class);

        self::assertInstanceOf(KernelRuntime::class, $runtime);

        self::assertSame(1, self::privateProperty($runtime, 'attributesMaxDepth'));
        self::assertSame(200, self::privateProperty($runtime, 'attributesMaxKeys'));

        try {
            $runtime->runUnitOfWork(
                type: UnitOfWorkType::HTTP,
                body: static fn (): string => 'unreachable',
                attributes: [
                    'outer' => [
                        'inner' => true,
                    ],
                ],
            );

            self::fail('Expected KernelRuntimeException was not thrown.');
        } catch (KernelRuntimeException $exception) {
            self::assertSame(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
                $exception->reason(),
            );

            self::assertInstanceOf(
                UnitOfWorkContextInvalidException::class,
                $exception->getPrevious(),
            );

            self::assertSame(
                UnitOfWorkContextInvalidException::REASON_ATTRIBUTES_MAX_DEPTH_EXCEEDED,
                $exception->getPrevious()->reason(),
            );
        }
    }

    public function testKernelRuntimeUsesConfiguredUnitOfWorkAttributeMaxKeysThroughDi(): void
    {
        $resetSpy = new KernelServiceProviderWiresKernelRuntimeResetSpy();

        $config = self::validConfig();
        $config['kernel']['uow']['attributes']['max_depth'] = 10;
        $config['kernel']['uow']['attributes']['max_keys'] = 1;

        $container = self::container($resetSpy, $config);

        $runtime = $container->get(KernelRuntime::class);

        self::assertInstanceOf(KernelRuntime::class, $runtime);

        self::assertSame(10, self::privateProperty($runtime, 'attributesMaxDepth'));
        self::assertSame(1, self::privateProperty($runtime, 'attributesMaxKeys'));

        try {
            $runtime->runUnitOfWork(
                type: UnitOfWorkType::HTTP,
                body: static fn (): string => 'unreachable',
                attributes: [
                    'first' => 1,
                    'second' => 2,
                ],
            );

            self::fail('Expected KernelRuntimeException was not thrown.');
        } catch (KernelRuntimeException $exception) {
            self::assertSame(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
                $exception->reason(),
            );

            self::assertInstanceOf(
                UnitOfWorkContextInvalidException::class,
                $exception->getPrevious(),
            );

            self::assertSame(
                UnitOfWorkContextInvalidException::REASON_ATTRIBUTES_MAX_KEYS_EXCEEDED,
                $exception->getPrevious()->reason(),
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function container(
        KernelServiceProviderWiresKernelRuntimeResetSpy $resetSpy,
        array $config,
    ): Container {
        $builder = self::builder($resetSpy, $config);

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

    /**
     * @param array<string, mixed> $config
     */
    private static function builder(
        KernelServiceProviderWiresKernelRuntimeResetSpy $resetSpy,
        array $config,
    ): ContainerBuilder {
        return new ContainerBuilder(config: $config);
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
