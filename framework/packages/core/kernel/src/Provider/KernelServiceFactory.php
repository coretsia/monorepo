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

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless Kernel service factory.
 *
 * This helper centralizes Kernel runtime wiring/validation that needs DI
 * services and already-merged Kernel config.
 *
 * It intentionally keeps no mutable runtime state:
 *
 * - no static snapshots;
 * - no caches;
 * - no buffers;
 * - no retained container instance;
 * - no retained config payload;
 * - no request-local or unit-of-work-local data.
 *
 * The caller owns when this factory is invoked and which config snapshot is
 * supplied. The factory only validates the small Kernel-owned config subset and
 * constructs Kernel-owned runtime services from already-registered DI services.
 *
 * This factory does not invent UnitOfWork lifecycle behavior. Lifecycle
 * orchestration belongs to KernelRuntime.
 *
 * @internal Kernel provider wiring helper. Not part of the public Kernel API.
 */
final class KernelServiceFactory
{
    private const int DEFAULT_UOW_ATTRIBUTES_MAX_DEPTH = 10;

    private const int DEFAULT_UOW_ATTRIBUTES_MAX_KEYS = 200;

    private function __construct()
    {
    }

    /**
     * Creates the bootstrap-only overrides loader.
     *
     * This factory performs construction only. It does not read
     * skeleton/config/app.php and keeps no mutable runtime state.
     */
    public static function bootstrapOverridesLoader(): BootstrapOverridesLoader
    {
        return new BootstrapOverridesLoader();
    }

    /**
     * Creates the BootstrapConfig resolver from already-registered boot
     * services.
     *
     * This factory performs wiring only. It does not resolve BootstrapConfig,
     * read skeleton/config/app.php, read package defaults, parse dotenv files,
     * read system env, or keep mutable runtime state.
     */
    public static function bootstrapConfigResolver(ContainerInterface $container): BootstrapConfigResolver
    {
        $overridesLoader = self::bootService($container, BootstrapOverridesLoader::class);

        if (!$overridesLoader instanceof BootstrapOverridesLoader) {
            throw new ContainerException('kernel-boot-dependency-invalid');
        }

        return new BootstrapConfigResolver(
            overridesLoader: $overridesLoader,
        );
    }

    /**
     * Creates the dotenv loader.
     *
     * This factory performs construction only. It does not parse dotenv files
     * and keeps no mutable runtime state.
     */
    public static function dotenvLoader(): DotenvLoader
    {
        return new DotenvLoader();
    }

    /**
     * Creates the EnvRepository builder from already-registered boot services.
     *
     * This factory performs wiring only. It does not build an env repository,
     * read dotenv files, snapshot system env, or keep mutable runtime state.
     */
    public static function envRepositoryBuilder(ContainerInterface $container): EnvRepositoryBuilder
    {
        $dotenvLoader = self::bootService($container, DotenvLoader::class);

        if (!$dotenvLoader instanceof DotenvLoader) {
            throw new ContainerException('kernel-boot-dependency-invalid');
        }

        return new EnvRepositoryBuilder(
            dotenvLoader: $dotenvLoader,
        );
    }

    /**
     * Creates the Kernel hook invoker.
     *
     * The supplied TagRegistry MUST be the builder-owned registry instance so
     * hook discovery order stays owned by Foundation TagRegistry.
     */
    public static function hookInvoker(
        ContainerInterface $container,
        TagRegistry $tagRegistry,
    ): HookInvoker {
        return new HookInvoker(
            container: $container,
            tags: $tagRegistry,
        );
    }

    /**
     * Creates the KernelRuntime orchestrator from already-registered services.
     *
     * This method performs wiring only. It does not read runtime config, does
     * not enumerate hooks, does not trigger reset, and does not start a UoW.
     */
    public static function kernelRuntime(ContainerInterface $container): KernelRuntime
    {
        return new KernelRuntime(
            contextStore: self::contextStore($container),
            resetOrchestrator: self::resetOrchestrator($container),
            stopwatch: self::stopwatch($container),
            uowIds: self::uowIds($container),
            correlationIdProvider: self::correlationIdProvider($container),
            correlationIds: self::correlationIds($container),
            hooks: self::hooks($container),
            logger: self::logger($container),
            tracer: self::tracer($container),
            meter: self::meter($container),
        );
    }

    /**
     * Resolves UnitOfWorkContext.attributes defensive limits.
     *
     * The values are read from the supplied Kernel config subtree:
     *
     *     kernel.uow.attributes.max_depth
     *     kernel.uow.attributes.max_keys
     *
     * If the keys are absent, the defaults are used.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return array{maxDepth: int<1, max>, maxKeys: int<1, max>}
     */
    public static function unitOfWorkAttributeLimits(array $kernelConfig): array
    {
        return [
            'maxDepth' => self::unitOfWorkAttributesMaxDepth($kernelConfig),
            'maxKeys' => self::unitOfWorkAttributesMaxKeys($kernelConfig),
        ];
    }

    /**
     * Resolves the maximum allowed depth for UnitOfWorkContext.attributes.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return int<1, max>
     */
    public static function unitOfWorkAttributesMaxDepth(array $kernelConfig): int
    {
        $attributesConfig = self::uowAttributesConfig($kernelConfig);

        $maxDepth = $attributesConfig['max_depth'] ?? self::DEFAULT_UOW_ATTRIBUTES_MAX_DEPTH;

        if (!\is_int($maxDepth) || $maxDepth < 1) {
            throw new ContainerException('kernel-uow-attributes-max-depth-invalid');
        }

        return $maxDepth;
    }

    /**
     * Resolves the maximum allowed key count for UnitOfWorkContext.attributes.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return int<1, max>
     */
    public static function unitOfWorkAttributesMaxKeys(array $kernelConfig): int
    {
        $attributesConfig = self::uowAttributesConfig($kernelConfig);

        $maxKeys = $attributesConfig['max_keys'] ?? self::DEFAULT_UOW_ATTRIBUTES_MAX_KEYS;

        if (!\is_int($maxKeys) || $maxKeys < 1) {
            throw new ContainerException('kernel-uow-attributes-max-keys-invalid');
        }

        return $maxKeys;
    }

    private static function contextStore(ContainerInterface $container): ContextStore
    {
        $service = self::service($container, ContextStore::class);

        if (!$service instanceof ContextStore) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function resetOrchestrator(ContainerInterface $container): ResetOrchestrator
    {
        $service = self::service($container, ResetOrchestrator::class);

        if (!$service instanceof ResetOrchestrator) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function stopwatch(ContainerInterface $container): Stopwatch
    {
        $service = self::service($container, Stopwatch::class);

        if (!$service instanceof Stopwatch) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function uowIds(ContainerInterface $container): IdGeneratorInterface
    {
        $service = self::service($container, IdGeneratorInterface::class);

        if (!$service instanceof IdGeneratorInterface) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function correlationIdProvider(ContainerInterface $container): CorrelationIdProviderInterface
    {
        $service = self::service($container, CorrelationIdProviderInterface::class);

        if (!$service instanceof CorrelationIdProviderInterface) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function correlationIds(ContainerInterface $container): CorrelationIdGenerator
    {
        $service = self::service($container, CorrelationIdGenerator::class);

        if (!$service instanceof CorrelationIdGenerator) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function hooks(ContainerInterface $container): HookInvoker
    {
        $service = self::service($container, HookInvoker::class);

        if (!$service instanceof HookInvoker) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function logger(ContainerInterface $container): LoggerInterface
    {
        $service = self::service($container, LoggerInterface::class);

        if (!$service instanceof LoggerInterface) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function tracer(ContainerInterface $container): TracerPortInterface
    {
        $service = self::service($container, TracerPortInterface::class);

        if (!$service instanceof TracerPortInterface) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function meter(ContainerInterface $container): MeterPortInterface
    {
        $service = self::service($container, MeterPortInterface::class);

        if (!$service instanceof MeterPortInterface) {
            throw new ContainerException('kernel-runtime-dependency-invalid');
        }

        return $service;
    }

    private static function bootService(
        ContainerInterface $container,
        string $id,
    ): mixed {
        try {
            if (!$container->has($id)) {
                throw new ContainerException('kernel-boot-dependency-not-found');
            }

            return $container->get($id);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-boot-dependency-not-found',
                $throwable,
            );
        }
    }

    private static function service(
        ContainerInterface $container,
        string $id,
    ): mixed {
        try {
            if (!$container->has($id)) {
                throw new ContainerException('kernel-runtime-dependency-not-found');
            }

            return $container->get($id);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-runtime-dependency-not-found',
                $throwable,
            );
        }
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function uowAttributesConfig(array $kernelConfig): array
    {
        $uowConfig = $kernelConfig['uow'] ?? [];

        if (!\is_array($uowConfig)) {
            throw new ContainerException('kernel-uow-config-invalid');
        }

        $attributesConfig = $uowConfig['attributes'] ?? [];

        if (!\is_array($attributesConfig)) {
            throw new ContainerException('kernel-uow-attributes-config-invalid');
        }

        return $attributesConfig;
    }
}
