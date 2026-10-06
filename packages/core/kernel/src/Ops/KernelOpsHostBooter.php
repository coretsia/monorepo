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

namespace Coretsia\Kernel\Ops;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlan;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Public bootstrap façade for the Kernel source operations host.
 *
 * The host is built entirely from source configuration and canonical provider
 * composition. It never boots generated runtime artifacts and never invokes a
 * Kernel operation, UnitOfWork lifecycle, hook, or reset orchestration itself.
 */
final class KernelOpsHostBooter
{
    private const array HOST_OWNED_TAG_TARGETS = [
        BootstrapConfig::class => true,
        EnvRepositoryInterface::class => true,
        ConfigRepositoryInterface::class => true,
        ModulePlan::class => true,
        RuntimePathContext::class => true,
        self::class => true,
        KernelOpsHostInput::class => true,
        KernelOpsHostSeedConfigLoader::class => true,
        KernelOpsExecutionServices::class => true,
        KernelOpsSourceDefinitionProviderAdapter::class => true,
        KernelOpsFacade::class => true,
        KernelOpsInterface::class => true,
    ];

    public function boot(KernelOpsHostInput $input): ContainerInterface
    {
        try {
            $seedConfigLoader = new KernelOpsHostSeedConfigLoader(
                KernelServiceFactory::composerPackageInstallPathResolver(),
            );
            $seedConfig = $seedConfigLoader->load();
            $baselineProviderClasses = self::baselineProviderClasses();
            $baselineProviders = self::baselineProviders($baselineProviderClasses);

            $seedBuilder = new ContainerBuilder(config: $seedConfig);
            $seedBuilder->registerProviders($baselineProviders);
            $seedContainer = $seedBuilder->build();

            $kernelConfig = self::kernelConfig($seedConfig);
            $bootstrapConfigResolver = self::service(
                $seedContainer,
                BootstrapConfigResolver::class,
            );
            $envRepositoryBuilder = self::service(
                $seedContainer,
                EnvRepositoryBuilder::class,
            );
            $moduleResolutionOrchestrator = self::service(
                $seedContainer,
                ModuleResolutionOrchestrator::class,
            );
            $configSourceLocationBuilder = self::service(
                $seedContainer,
                ConfigSourceLocationBuilder::class,
            );
            $configKernel = self::service(
                $seedContainer,
                ConfigKernel::class,
            );
            $providerPlanResolver = self::service(
                $seedContainer,
                ContainerProviderPlanResolver::class,
            );

            $consoleInput = new BootstrapInput(
                applicationRoot: $input->applicationRoot(),
                appTarget: AppTarget::Console,
                preset: null,
            );
            $consoleBootstrapConfig = $bootstrapConfigResolver->resolve(
                $consoleInput,
                $kernelConfig,
            );
            $consoleEnv = $envRepositoryBuilder->build(
                $consoleBootstrapConfig,
                $kernelConfig,
            );
            $consoleModuleResolution = $moduleResolutionOrchestrator->resolve($consoleBootstrapConfig);
            $consoleConfigSources = $configSourceLocationBuilder->build(
                $consoleBootstrapConfig,
                $consoleModuleResolution,
            );
            $compiledConfig = $configKernel->compile(
                bootstrapConfig: $consoleBootstrapConfig,
                modulePlan: $consoleModuleResolution->plan(),
                env: $consoleEnv,
                configSources: $consoleConfigSources,
                explain: false,
            );

            $validation = $compiledConfig['validation'] ?? null;

            if (!$validation instanceof ConfigValidationResult) {
                throw new \UnexpectedValueException('kernel-ops-host-validation-result-invalid');
            }

            if ($validation->isFailure()) {
                throw ConfigInvalidException::fromValidationResult($validation);
            }

            $completeConfig = $compiledConfig['config'] ?? null;

            if (!\is_array($completeConfig) || !self::isStringMap($completeConfig)) {
                throw new \UnexpectedValueException('kernel-ops-host-compiled-config-invalid');
            }

            $configRepository = new ArrayConfigRepository($completeConfig);
            $providerPlan = $providerPlanResolver->resolve($consoleModuleResolution);
            $sourceProviders = self::sourceProviders($providerPlan);

            $finalBuilder = new ContainerBuilder(config: $completeConfig);
            $finalBuilder->registerProviders($sourceProviders);

            self::assertNoHostOwnedTagTargets($finalBuilder);

            self::installHostOwnedFactories(
                builder: $finalBuilder,
                baselineConfig: $seedConfig,
                baselineProviderClasses: $baselineProviderClasses,
            );

            $finalBuilder
                ->instance(KernelOpsHostInput::class, $input)
                ->instance(BootstrapConfig::class, $consoleBootstrapConfig)
                ->instance(EnvRepositoryInterface::class, $consoleEnv)
                ->instance(ConfigRepositoryInterface::class, $configRepository)
                ->instance(ModulePlan::class, $consoleModuleResolution->plan());

            $container = $finalBuilder->build();
            $ops = $container->get(KernelOpsInterface::class);

            if (!$ops instanceof KernelOpsInterface) {
                throw new \UnexpectedValueException('kernel-ops-interface-preflight-invalid');
            }

            return $container;
        } catch (\Throwable) {
            throw new KernelOpsFailedException(KernelOpsFailedException::REASON_HOST_BOOT_FAILED);
        }
    }

    /**
     * @return list<class-string<ServiceProviderInterface>>
     */
    private static function baselineProviderClasses(): array
    {
        return [
            ...new FoundationModule()->providers(),
            ...new KernelModule()->providers(),
        ];
    }

    /**
     * @param list<class-string<ServiceProviderInterface>> $providerClasses
     *
     * @return list<ServiceProviderInterface>
     */
    private static function baselineProviders(array $providerClasses): array
    {
        $providers = [];

        foreach ($providerClasses as $providerClass) {
            $provider = new $providerClass();

            if (
                !$provider instanceof ServiceProviderInterface
                || !$provider instanceof ContainerDefinitionProviderInterface
            ) {
                throw new \UnexpectedValueException('kernel-ops-baseline-provider-invalid');
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return list<ServiceProviderInterface>
     */
    private static function sourceProviders(
        ContainerProviderPlan $providerPlan,
    ): array {
        $providers = [];

        foreach ($providerPlan->providerClasses() as $providerClass) {
            $provider = new $providerClass();

            if (!$provider instanceof ContainerDefinitionProviderInterface) {
                throw new \UnexpectedValueException('kernel-ops-source-provider-invalid');
            }

            $sourceProvider = $provider instanceof ServiceProviderInterface
                ? $provider
                : new KernelOpsSourceDefinitionProviderAdapter($provider);

            $providers[] = $sourceProvider;
        }

        return $providers;
    }

    private static function assertNoHostOwnedTagTargets(
        ContainerBuilder $builder,
    ): void {
        $tagRegistry = $builder->tagRegistry();

        foreach ($tagRegistry->tagNames() as $tag) {
            foreach ($tagRegistry->all($tag) as $taggedService) {
                if (isset(self::HOST_OWNED_TAG_TARGETS[$taggedService->id()])) {
                    throw new \UnexpectedValueException('kernel-ops-host-tag-target-conflict');
                }
            }
        }
    }

    /**
     * @param array{
     *     foundation: array<string,mixed>,
     *     kernel: array<string,mixed>
     * } $baselineConfig
     * @param list<class-string<ServiceProviderInterface>> $baselineProviderClasses
     */
    private static function installHostOwnedFactories(
        ContainerBuilder $builder,
        array $baselineConfig,
        array $baselineProviderClasses,
    ): void {
        $builder->factory(
            RuntimePathContext::class,
            static fn (
                Container $container,
            ): RuntimePathContext => KernelServiceFactory::runtimePathContext(
                container: $container,
            ),
        );

        $builder->factory(
            KernelOpsExecutionServices::class,
            static function (
                Container $container,
            ) use (
                $baselineConfig,
                $baselineProviderClasses,
            ): KernelOpsExecutionServices {
                $logger = $container->get(LoggerInterface::class);
                $tracer = $container->get(TracerPortInterface::class);
                $meter = $container->get(MeterPortInterface::class);
                $stopwatch = $container->get(Stopwatch::class);

                if (
                    !$logger instanceof LoggerInterface
                    || !$tracer instanceof TracerPortInterface
                    || !$meter instanceof MeterPortInterface
                    || !$stopwatch instanceof Stopwatch
                ) {
                    throw new \UnexpectedValueException('kernel-ops-observability-service-invalid');
                }

                return KernelServiceFactory::kernelOpsExecutionServices(
                    baselineConfig: $baselineConfig,
                    baselineProviderClasses: $baselineProviderClasses,
                    logger: $logger,
                    tracer: $tracer,
                    meter: $meter,
                    stopwatch: $stopwatch,
                );
            },
        );

        $builder->factory(
            KernelOpsFacade::class,
            static fn (
                Container $container,
            ): KernelOpsFacade => KernelServiceFactory::kernelOpsFacade(
                container: $container,
            ),
        );

        $builder->factory(
            KernelOpsInterface::class,
            static function (Container $container): KernelOpsInterface {
                $facade = $container->get(KernelOpsFacade::class);

                if (!$facade instanceof KernelOpsInterface) {
                    throw new \UnexpectedValueException('kernel-ops-interface-binding-invalid');
                }

                return $facade;
            },
        );
    }

    /**
     * @param array{
     *     foundation: array<string,mixed>,
     *     kernel: array<string,mixed>
     * } $seedConfig
     *
     * @return array<string,mixed>
     */
    private static function kernelConfig(array $seedConfig): array
    {
        $kernelConfig = $seedConfig[KernelModule::CONFIG_ROOT] ?? null;

        if (!\is_array($kernelConfig) || !self::isStringMap($kernelConfig)) {
            throw new \UnexpectedValueException('kernel-ops-host-kernel-config-invalid');
        }

        return $kernelConfig;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(
        ContainerInterface $container,
        string $id,
    ): object {
        $service = $container->get($id);

        if (
            !\is_object($service)
            || !$service instanceof $id
        ) {
            throw new \UnexpectedValueException('kernel-ops-host-service-invalid');
        }

        /** @var T $service */
        return $service;
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function isStringMap(array $value): bool
    {
        if ($value !== [] && \array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
