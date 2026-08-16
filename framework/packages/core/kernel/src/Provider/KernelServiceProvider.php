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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\ContainerGraphFingerprintBucketBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Kernel DI wiring entrypoint.
 *
 * This provider registers Kernel-owned runtime, Bootstrap Phase A,
 * module-resolution, and provider-plan services without changing
 * provider ordering semantics. ContainerBuilder still preserves
 * the exact caller-supplied provider order.
 *
 * Wiring decisions:
 *
 * - the Kernel-owned config subset used by UnitOfWork shapes is validated
 *   early and deterministically;
 * - Bootstrap Phase A boot services are registered as factories only;
 * - registering boot services does not execute Phase A boot;
 * - module plan services are registered as factories only;
 * - registering module plan services does not resolve ModulePlan;
 * - registering module plan services does not read Composer installed metadata;
 * - registering module plan services does not read preset files;
 * - registering module plan services does not scan filesystem paths;
 * - ConfigKernel Phase B services are registered as factories only;
 * - registering ConfigKernel services does not run config compilation;
 * - registering ConfigKernel services does not resolve BootstrapConfig;
 * - registering ConfigKernel services does not resolve ModulePlan;
 * - registering ConfigKernel services does not build EnvRepositoryInterface;
 * - registering ConfigKernel services does not load package config files;
 * - registering ConfigKernel services does not load skeleton/app config files;
 * - registering ConfigKernel services does not build env overlays;
 * - registering ConfigKernel services does not merge, validate, or explain config;
 * - artifact/fingerprint/cache services are registered as factories only;
 * - registering artifact services does not write or read generated artifacts;
 * - registering fingerprint services does not calculate fingerprints;
 * - registering cache services does not run cache verification;
 * - registering artifact/fingerprint/cache services does not emit spans,
 *   metrics, logs, stdout, or stderr;
 * - RuntimeDriverResolver is registered as a factory-only stateless runtime
 *   service;
 * - registering RuntimeDriverResolver does not run runtime-driver resolution;
 * - registering RuntimeDriverResolver does not inspect config values;
 * - registering RuntimeDriverResolver does not resolve ModulePlan;
 * - FilesystemModePresetLoader is not registered globally because skeleton
 *   override path resolution is BootstrapConfig-specific;
 * - ModePresetLoaderInterface is not bound globally for the same reason;
 * - HookInvoker is registered as the Kernel hook invocation service;
 * - KernelRuntime is registered as the Kernel-owned UnitOfWork lifecycle
 *   orchestrator;
 * - KernelRuntimeInterface is bound to the KernelRuntime concrete service;
 * - HookInvoker receives the builder-owned TagRegistry so hook discovery order
 *   stays owned by Foundation TagRegistry;
 * - KernelRuntime receives context, reset, id, time, hook, logging, tracing,
 *   and metrics dependencies through DI via KernelServiceFactory;
 * - core/kernel does not define reset tag constants; the reset discovery tag
 *   remains owned by core/foundation.
 *
 * This provider must not emit stdout/stderr, must not use tooling-only
 * packages, must not introduce static mutable snapshots, must not trigger
 * reset orchestration, must not execute Bootstrap Phase A, must not inspect
 * runtime driver config, must not run runtime driver detection, must not resolve
 * a ModulePlan, must not compile config, and must not start a UnitOfWork during
 * registration.
 */
final class KernelServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    private const string PARAM_UOW_ATTRIBUTES_MAX_DEPTH = 'kernel.uow.attributes.max_depth';
    private const string PARAM_UOW_ATTRIBUTES_MAX_KEYS = 'kernel.uow.attributes.max_keys';

    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();

        /*
         * Fail closed before compile-host registrations mutate the builder.
         *
         * `define()` remains the single runtime wiring source and repeats the same
         * deterministic validation while producing canonical parameter operations.
         */
        KernelServiceFactory::unitOfWorkAttributeLimits(
            $builder->configRoot('kernel'),
        );

        $kernelPackageRoot = \dirname(__DIR__, 2);

        /*
         * Register Bootstrap Phase A services.
         *
         * These bindings are factories only. They do not resolve BootstrapInput,
         * do not load skeleton/config/app.php, do not parse dotenv files, do not
         * snapshot system env, and do not build EnvRepositoryInterface during
         * provider registration.
         */
        $builder->factory(
            BootstrapOverridesLoader::class,
            static fn (
                Container $_container
            ): BootstrapOverridesLoader => KernelServiceFactory::bootstrapOverridesLoader(),
        );

        $builder->factory(
            BootstrapConfigResolver::class,
            static fn (
                Container $container
            ): BootstrapConfigResolver => KernelServiceFactory::bootstrapConfigResolver(
                container: $container,
            ),
        );

        $builder->factory(
            DotenvLoader::class,
            static fn (
                Container $_container
            ): DotenvLoader => KernelServiceFactory::dotenvLoader(),
        );

        $builder->factory(
            EnvRepositoryBuilder::class,
            static fn (
                Container $container
            ): EnvRepositoryBuilder => KernelServiceFactory::envRepositoryBuilder(
                container: $container,
            ),
        );

        /*
         * Register the source-host runtime path seed.
         *
         * RuntimePathContext is derived from an already-resolved BootstrapConfig.
         * It is not a Bootstrap Phase A result and does not enter Kernel runtime
         * definitions.
         */
        $builder->factory(
            RuntimePathContext::class,
            static fn (
                Container $container
            ): RuntimePathContext => KernelServiceFactory::runtimePathContext(
                container: $container,
            ),
        );

        /*
         * Register module-resolution and provider-plan services.
         *
         * These bindings are factories only. They do not resolve ModuleResolution,
         * ModulePlan, or ContainerProviderPlan, do not read Composer installed metadata,
         * do not read preset files, do not scan filesystem paths, and do not create
         * FilesystemModePresetLoader during provider registration.
         *
         * FilesystemModePresetLoader is intentionally created only through
         * ModePresetLoaderFactory::createFor() during ModulePlanResolver::resolveResolution()
         * for the current BootstrapConfig.
         */
        $builder->factory(
            ModePresetSchemaValidator::class,
            static fn (
                Container $_container
            ): ModePresetSchemaValidator => KernelServiceFactory::modePresetSchemaValidator(),
        );

        $builder->factory(
            TopologicalSorter::class,
            static fn (
                Container $_container
            ): TopologicalSorter => KernelServiceFactory::topologicalSorter(),
        );

        $builder->factory(
            ComposerManifestReader::class,
            static fn (
                Container $_container
            ): ComposerManifestReader => KernelServiceFactory::composerManifestReader(),
        );

        $builder->factory(
            ManifestReaderInterface::class,
            static function (Container $container): ManifestReaderInterface {
                $reader = $container->get(ComposerManifestReader::class);

                if (!$reader instanceof ManifestReaderInterface) {
                    throw new ContainerException('kernel-manifest-reader-interface-binding-invalid');
                }

                return $reader;
            },
        );

        $builder->factory(
            ModePresetLoaderFactory::class,
            static fn (
                Container $container
            ): ModePresetLoaderFactory => KernelServiceFactory::modePresetLoaderFactory(
                container: $container,
                packageRoot: $kernelPackageRoot,
            ),
        );

        $builder->factory(
            ModuleGraphResolver::class,
            static fn (
                Container $container
            ): ModuleGraphResolver => KernelServiceFactory::moduleGraphResolver(
                container: $container,
            ),
        );

        $builder->factory(
            ModulePlanResolver::class,
            static fn (
                Container $container
            ): ModulePlanResolver => KernelServiceFactory::modulePlanResolver(
                container: $container,
            ),
        );

        $builder->factory(
            ContainerProviderPlanResolver::class,
            static fn (
                Container $_container
            ): ContainerProviderPlanResolver => KernelServiceFactory::containerProviderPlanResolver(),
        );

        /*
         * Register ConfigKernel Phase B services.
         *
         * These bindings are factories only. They do not run config compilation,
         * do not resolve BootstrapConfig, do not resolve ModulePlan, do not build
         * EnvRepositoryInterface snapshots, do not load package/skeleton config
         * files, do not build env overlays, do not merge config, do not validate
         * config, and do not build explain traces during provider registration.
         */
        $builder->factory(
            ConfigNamespaceGuard::class,
            static fn (
                Container $container
            ): ConfigNamespaceGuard => KernelServiceFactory::configNamespaceGuard(
                container: $container,
            ),
        );

        $builder->factory(
            DirectiveProcessor::class,
            static fn (
                Container $container
            ): DirectiveProcessor => KernelServiceFactory::directiveProcessor(
                container: $container,
            ),
        );

        $builder->factory(
            ConfigMerger::class,
            static fn (
                Container $container
            ): ConfigMerger => KernelServiceFactory::configMerger(
                container: $container,
            ),
        );

        $builder->factory(
            ConfigRulesLoader::class,
            static fn (
                Container $_container
            ): ConfigRulesLoader => KernelServiceFactory::configRulesLoader(),
        );

        $builder->factory(
            ConfigValidator::class,
            static fn (
                Container $_container
            ): ConfigValidator => KernelServiceFactory::configValidator(),
        );

        $builder->factory(
            ConfigExplainer::class,
            static fn (
                Container $_container
            ): ConfigExplainer => KernelServiceFactory::configExplainer(),
        );

        $builder->factory(
            PackageDefaultsConfigLoader::class,
            static fn (
                Container $container
            ): PackageDefaultsConfigLoader => KernelServiceFactory::packageDefaultsConfigLoader(
                container: $container,
            ),
        );

        $builder->factory(
            SkeletonConfigLoader::class,
            static fn (
                Container $container
            ): SkeletonConfigLoader => KernelServiceFactory::skeletonConfigLoader(
                container: $container,
            ),
        );

        $builder->factory(
            EnvironmentOverlayLoader::class,
            static fn (
                Container $_container
            ): EnvironmentOverlayLoader => KernelServiceFactory::environmentOverlayLoader(),
        );

        $builder->factory(
            ConfigKernel::class,
            static fn (
                Container $container
            ): ConfigKernel => KernelServiceFactory::configKernel(
                container: $container,
            ),
        );

        /*
         * Register Kernel artifact/fingerprint/cache services.
         *
         * These bindings are factories only. They do not write artifacts, read
         * generated artifacts, calculate fingerprints, run cache verification,
         * resolve BootstrapConfig, resolve ModulePlan, build EnvRepositoryInterface,
         * run ConfigKernel::compile(), invoke ResetOrchestrator, start a
         * UnitOfWork, emit stdout/stderr, or emit artifact/fingerprint/cache
         * observability during provider registration.
         *
         * Artifact/fingerprint/cache observability-aware services receive their
         * dependencies only when the service is resolved by the container. The
         * provider itself does not start kernel.artifacts_write,
         * kernel.fingerprint_calculate, or kernel.cache_verify spans and does not
         * emit artifact/fingerprint/cache metrics or logs.
         */
        $builder->factory(
            PayloadNormalizer::class,
            static fn (
                Container $_container
            ): PayloadNormalizer => KernelServiceFactory::artifactPayloadNormalizer(),
        );

        $builder->factory(
            StablePhpArrayDumper::class,
            static fn (
                Container $container
            ): StablePhpArrayDumper => KernelServiceFactory::stablePhpArrayDumper(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactEnvelopeFactory::class,
            static fn (
                Container $container
            ): ArtifactEnvelopeFactory => KernelServiceFactory::artifactEnvelopeFactory(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactPathResolver::class,
            static fn (
                Container $_container
            ): ArtifactPathResolver => KernelServiceFactory::artifactPathResolver(),
        );

        $builder->factory(
            ArtifactGenerationPathResolver::class,
            static fn (
                Container $_container
            ): ArtifactGenerationPathResolver => KernelServiceFactory::artifactGenerationPathResolver(),
        );

        $builder->factory(
            DeterministicFileLister::class,
            static fn (
                Container $_container
            ): DeterministicFileLister => KernelServiceFactory::deterministicFileLister(),
        );

        $builder->factory(
            ContainerGraphFingerprintBucketBuilder::class,
            static fn (
                Container $_container
            ): ContainerGraphFingerprintBucketBuilder => KernelServiceFactory::containerGraphFingerprintBucketBuilder(),
        );

        $builder->factory(
            ConfigFingerprintInputBuilder::class,
            static fn (
                Container $container
            ): ConfigFingerprintInputBuilder => KernelServiceFactory::configFingerprintInputBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            FingerprintExplainer::class,
            static fn (
                Container $_container
            ): FingerprintExplainer => KernelServiceFactory::fingerprintExplainer(),
        );

        $builder->factory(
            FingerprintCalculator::class,
            static fn (
                Container $container
            ): FingerprintCalculator => KernelServiceFactory::fingerprintCalculator(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactWriter::class,
            static fn (
                Container $container
            ): ArtifactWriter => KernelServiceFactory::artifactWriter(
                container: $container,
            ),
        );

        $builder->factory(
            ModuleManifestBuilder::class,
            static fn (
                Container $container
            ): ModuleManifestBuilder => KernelServiceFactory::moduleManifestBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            CompiledConfigBuilder::class,
            static fn (
                Container $container
            ): CompiledConfigBuilder => KernelServiceFactory::compiledConfigBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            CompiledContainerBuilder::class,
            static fn (
                Container $container
            ): CompiledContainerBuilder => KernelServiceFactory::compiledContainerBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            PhpArtifactReader::class,
            static fn (
                Container $_container
            ): PhpArtifactReader => KernelServiceFactory::phpArtifactReader(),
        );

        $builder->factory(
            ArtifactSchemaValidator::class,
            static fn (
                Container $_container
            ): ArtifactSchemaValidator => KernelServiceFactory::artifactSchemaValidator(),
        );

        $builder->factory(
            ArtifactGenerationManifestBuilder::class,
            static fn (
                Container $container
            ): ArtifactGenerationManifestBuilder => KernelServiceFactory::artifactGenerationManifestBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactGenerationManifestValidator::class,
            static fn (
                Container $container
            ): ArtifactGenerationManifestValidator => KernelServiceFactory::artifactGenerationManifestValidator(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactGenerationLock::class,
            static fn (
                Container $container
            ): ArtifactGenerationLock => KernelServiceFactory::artifactGenerationLock(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactGenerationValidator::class,
            static fn (
                Container $container
            ): ArtifactGenerationValidator => KernelServiceFactory::artifactGenerationValidator(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactGenerationPublisher::class,
            static fn (
                Container $container
            ): ArtifactGenerationPublisher => KernelServiceFactory::artifactGenerationPublisher(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactGenerationLocator::class,
            static fn (
                Container $container
            ): ArtifactGenerationLocator => KernelServiceFactory::artifactGenerationLocator(
                container: $container,
            ),
        );

        $builder->factory(
            CompiledContainerFactory::class,
            static fn (
                Container $container
            ): CompiledContainerFactory => KernelServiceFactory::compiledContainerFactory(
                container: $container,
            ),
        );

        $builder->factory(
            ContainerCompiler::class,
            static fn (
                Container $container
            ): ContainerCompiler => KernelServiceFactory::containerCompiler(
                container: $container,
            ),
        );

        $builder->factory(
            ContainerGraphCompletenessValidator::class,
            static fn (
                Container $_container
            ): ContainerGraphCompletenessValidator => KernelServiceFactory::containerGraphCompletenessValidator(),
        );

        $builder->factory(
            RuntimeContainerGraphCompiler::class,
            static fn (
                Container $container
            ): RuntimeContainerGraphCompiler => KernelServiceFactory::runtimeContainerGraphCompiler(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactCompiler::class,
            static fn (
                Container $container
            ): ArtifactCompiler => KernelServiceFactory::artifactCompiler(
                container: $container,
            ),
        );

        $builder->factory(
            CacheVerifier::class,
            static fn (
                Container $container
            ): CacheVerifier => KernelServiceFactory::cacheVerifier(
                container: $container,
            ),
        );

        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $attributeLimits = KernelServiceFactory::unitOfWorkAttributeLimits(
            $context->configRoot('kernel'),
        );

        $definitions
            ->parameter(
                self::PARAM_UOW_ATTRIBUTES_MAX_DEPTH,
                $attributeLimits['maxDepth'],
            )
            ->parameter(
                self::PARAM_UOW_ATTRIBUTES_MAX_KEYS,
                $attributeLimits['maxKeys'],
            )
            ->requireService(ContainerInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(ContextAccessorInterface::class)
            ->requireService(ResetOrchestrator::class)
            ->requireService(Stopwatch::class)
            ->requireService(IdGeneratorInterface::class)
            ->requireService(CorrelationIdProviderInterface::class)
            ->requireService(CorrelationIdGenerator::class)
            ->requireService(HookInvoker::class)
            ->requireService(LoggerInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->classMethodFactory(
                id: RuntimeDriverResolver::class,
                factoryClass: KernelServiceFactory::class,
                method: 'runtimeDriverResolver',
            )
            ->classMethodFactory(
                id: HookInvoker::class,
                factoryClass: KernelServiceFactory::class,
                method: 'hookInvoker',
                arguments: [
                    ContainerValueReference::service(ContainerInterface::class),
                    ContainerValueReference::service(TagRegistry::class),
                ],
            )
            ->classMethodFactory(
                id: KernelRuntime::class,
                factoryClass: KernelServiceFactory::class,
                method: 'kernelRuntime',
                arguments: [
                    ContainerValueReference::service(ContainerInterface::class),
                    ContainerValueReference::parameter(self::PARAM_UOW_ATTRIBUTES_MAX_DEPTH),
                    ContainerValueReference::parameter(self::PARAM_UOW_ATTRIBUTES_MAX_KEYS),
                ],
            )
            ->alias(
                KernelRuntimeInterface::class,
                KernelRuntime::class,
            );
    }
}
