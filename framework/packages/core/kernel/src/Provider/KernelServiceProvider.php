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

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Builders\StubContainerBuilder;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
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
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;

/**
 * Kernel DI wiring entrypoint.
 *
 * This provider registers Kernel-owned runtime, Bootstrap Phase A, and module
 * plan services without changing provider ordering semantics. ContainerBuilder
 * still preserves the exact caller-supplied provider order.
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
 * reset orchestration, must not execute Bootstrap Phase A, must not resolve a
 * ModulePlan, must not compile config, and must not start a UnitOfWork during
 * registration.
 */
final class KernelServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $kernelConfig = $builder->configRoot('kernel');
        $kernelPackageRoot = \dirname(__DIR__, 2);

        /*
         * Preserve the existing Kernel-owned config validation behavior.
         *
         * This validates only the UnitOfWork attributes defensive limits and
         * does not construct runtime lifecycle state.
         */
        KernelServiceFactory::unitOfWorkAttributeLimits($kernelConfig);

        $tagRegistry = $builder->tagRegistry();

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
            static fn (Container $container): BootstrapConfigResolver => KernelServiceFactory::bootstrapConfigResolver(
                container: $container,
            ),
        );

        $builder->factory(
            DotenvLoader::class,
            static fn (Container $_container): DotenvLoader => KernelServiceFactory::dotenvLoader(),
        );

        $builder->factory(
            EnvRepositoryBuilder::class,
            static fn (Container $container): EnvRepositoryBuilder => KernelServiceFactory::envRepositoryBuilder(
                container: $container,
            ),
        );

        /*
         * Register ModulePlan services.
         *
         * These bindings are factories only. They do not resolve ModulePlan, do
         * not read Composer installed metadata, do not read preset files, do not
         * scan filesystem paths, and do not create FilesystemModePresetLoader
         * during provider registration.
         *
         * FilesystemModePresetLoader is intentionally created only through
         * ModePresetLoaderFactory::createFor() during ModulePlanResolver::resolve()
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
            static fn (Container $container): ModePresetLoaderFactory => KernelServiceFactory::modePresetLoaderFactory(
                container: $container,
                packageRoot: $kernelPackageRoot,
            ),
        );

        $builder->factory(
            ModuleGraphResolver::class,
            static fn (Container $container): ModuleGraphResolver => KernelServiceFactory::moduleGraphResolver(
                container: $container,
            ),
        );

        $builder->factory(
            ModulePlanResolver::class,
            static fn (Container $container): ModulePlanResolver => KernelServiceFactory::modulePlanResolver(
                container: $container,
            ),
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
            static fn (Container $container): ConfigNamespaceGuard => KernelServiceFactory::configNamespaceGuard(
                container: $container,
            ),
        );

        $builder->factory(
            DirectiveProcessor::class,
            static fn (Container $container): DirectiveProcessor => KernelServiceFactory::directiveProcessor(
                container: $container,
            ),
        );

        $builder->factory(
            ConfigMerger::class,
            static fn (Container $container): ConfigMerger => KernelServiceFactory::configMerger(
                container: $container,
            ),
        );

        $builder->factory(
            ConfigRulesLoader::class,
            static fn (Container $_container): ConfigRulesLoader => KernelServiceFactory::configRulesLoader(),
        );

        $builder->factory(
            ConfigValidator::class,
            static fn (Container $_container): ConfigValidator => KernelServiceFactory::configValidator(),
        );

        $builder->factory(
            ConfigExplainer::class,
            static fn (Container $_container): ConfigExplainer => KernelServiceFactory::configExplainer(),
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
            static fn (Container $container): SkeletonConfigLoader => KernelServiceFactory::skeletonConfigLoader(
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
            static fn (Container $container): ConfigKernel => KernelServiceFactory::configKernel(
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
            static fn (Container $_container): PayloadNormalizer => KernelServiceFactory::artifactPayloadNormalizer(),
        );

        $builder->factory(
            StablePhpArrayDumper::class,
            static fn (Container $container): StablePhpArrayDumper => KernelServiceFactory::stablePhpArrayDumper(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactEnvelopeFactory::class,
            static fn (Container $container): ArtifactEnvelopeFactory => KernelServiceFactory::artifactEnvelopeFactory(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactPathResolver::class,
            static fn (Container $_container): ArtifactPathResolver => KernelServiceFactory::artifactPathResolver(),
        );

        $builder->factory(
            DeterministicFileLister::class,
            static fn (Container $_container): DeterministicFileLister => KernelServiceFactory::deterministicFileLister(
            ),
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
            static fn (Container $_container): FingerprintExplainer => KernelServiceFactory::fingerprintExplainer(),
        );

        $builder->factory(
            FingerprintCalculator::class,
            static fn (Container $container): FingerprintCalculator => KernelServiceFactory::fingerprintCalculator(
                container: $container,
            ),
        );

        $builder->factory(
            ArtifactWriter::class,
            static fn (Container $container): ArtifactWriter => KernelServiceFactory::artifactWriter(
                container: $container,
            ),
        );

        $builder->factory(
            ModuleManifestBuilder::class,
            static fn (Container $container): ModuleManifestBuilder => KernelServiceFactory::moduleManifestBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            CompiledConfigBuilder::class,
            static fn (Container $container): CompiledConfigBuilder => KernelServiceFactory::compiledConfigBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            StubContainerBuilder::class,
            static fn (Container $container): StubContainerBuilder => KernelServiceFactory::stubContainerBuilder(
                container: $container,
            ),
        );

        $builder->factory(
            PhpArtifactReader::class,
            static fn (Container $_container): PhpArtifactReader => KernelServiceFactory::phpArtifactReader(),
        );

        $builder->factory(
            ArtifactSchemaValidator::class,
            static fn (Container $_container): ArtifactSchemaValidator => KernelServiceFactory::artifactSchemaValidator(
            ),
        );

        $builder->factory(
            ArtifactCompiler::class,
            static fn (Container $container): ArtifactCompiler => KernelServiceFactory::artifactCompiler(
                container: $container,
            ),
        );

        $builder->factory(
            CacheVerifier::class,
            static fn (Container $container): CacheVerifier => KernelServiceFactory::cacheVerifier(
                container: $container,
            ),
        );

        /*
         * Register Kernel runtime services.
         *
         * These bindings are factories only. They do not enumerate hooks, do not
         * trigger reset, do not start a UnitOfWork, and do not execute runtime
         * lifecycle during provider registration.
         */
        $builder->factory(
            HookInvoker::class,
            static fn (Container $container): HookInvoker => KernelServiceFactory::hookInvoker(
                container: $container,
                tagRegistry: $tagRegistry,
            ),
        );

        $builder->factory(
            KernelRuntime::class,
            static fn (Container $container): KernelRuntime => KernelServiceFactory::kernelRuntime(
                container: $container,
            ),
        );

        $builder->factory(
            KernelRuntimeInterface::class,
            static function (Container $container): KernelRuntimeInterface {
                $runtime = $container->get(KernelRuntime::class);

                if (!$runtime instanceof KernelRuntimeInterface) {
                    throw new ContainerException('kernel-runtime-interface-binding-invalid');
                }

                return $runtime;
            },
        );
    }
}
