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
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Container as FoundationContainer;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Context\ContextStore;
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
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
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
     * Creates the Composer installed metadata provider.
     *
     * This factory performs construction only. It does not read Composer
     * installed metadata, scan packages, or cache composer metadata.
     */
    public static function composerInstalledMetadataProvider(): ComposerInstalledMetadataProvider
    {
        return new ComposerInstalledMetadataProvider();
    }

    /**
     * Creates the Composer-backed module manifest reader.
     *
     * This factory wires the reader to the runtime-safe Composer installed
     * metadata provider. It does not read composer metadata during factory
     * construction and keeps no composer metadata cache.
     */
    public static function composerManifestReader(): ComposerManifestReader
    {
        return new ComposerManifestReader(
            metadataProvider: self::composerInstalledMetadataProvider(),
        );
    }

    /**
     * Creates the mode preset schema validator.
     *
     * This factory performs construction only. It does not read preset files,
     * resolve skeleton paths, or cache loaded presets.
     */
    public static function modePresetSchemaValidator(): ModePresetSchemaValidator
    {
        return new ModePresetSchemaValidator();
    }

    /**
     * Creates the per-resolution mode preset loader factory.
     *
     * This method reads only the Kernel-owned `kernel.modes` config subtree from
     * the already-built Foundation container config snapshot and passes that
     * subtree to ModePresetLoaderFactory.
     *
     * FilesystemModePresetLoader MUST NOT be registered globally. It is created
     * only by ModePresetLoaderFactory::createFor() for the current
     * BootstrapConfig during ModulePlanResolver::resolve().
     */
    public static function modePresetLoaderFactory(
        ContainerInterface $container,
        string $packageRoot,
    ): ModePresetLoaderFactory {
        $schemaValidator = self::modulePlanService($container, ModePresetSchemaValidator::class);

        if (!$schemaValidator instanceof ModePresetSchemaValidator) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
        }

        $kernelConfig = self::kernelConfig($container);

        return new ModePresetLoaderFactory(
            packageRoot: $packageRoot,
            modesConfig: self::modesConfig($kernelConfig),
            schemaValidator: $schemaValidator,
        );
    }

    /**
     * Creates the deterministic topological sorter.
     *
     * This factory performs construction only and keeps no graph state.
     */
    public static function topologicalSorter(): TopologicalSorter
    {
        return new TopologicalSorter();
    }

    /**
     * Creates the module graph resolver from already-registered module plan
     * services.
     *
     * This factory performs wiring only. It does not read composer metadata,
     * load presets, resolve ModulePlan, or keep mutable graph state.
     */
    public static function moduleGraphResolver(ContainerInterface $container): ModuleGraphResolver
    {
        $topologicalSorter = self::modulePlanService($container, TopologicalSorter::class);

        if (!$topologicalSorter instanceof TopologicalSorter) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
        }

        return new ModuleGraphResolver(
            topologicalSorter: $topologicalSorter,
        );
    }

    /**
     * Creates the ModulePlan resolver.
     *
     * This factory performs wiring only. It does not resolve a ModulePlan, load
     * preset files, read Composer installed metadata, scan filesystem paths,
     * retain BootstrapConfig, retain the container, or retain the full Kernel
     * config payload beyond construction.
     */
    public static function modulePlanResolver(
        ContainerInterface $container,
    ): ModulePlanResolver {
        $presetLoaderFactory = self::modulePlanService($container, ModePresetLoaderFactory::class);

        if (!$presetLoaderFactory instanceof ModePresetLoaderFactory) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
        }

        $manifestReader = self::modulePlanService($container, ManifestReaderInterface::class);

        if (!$manifestReader instanceof ManifestReaderInterface) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
        }

        $graphResolver = self::modulePlanService($container, ModuleGraphResolver::class);

        if (!$graphResolver instanceof ModuleGraphResolver) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
        }

        $meter = self::meter($container);
        $stopwatch = self::stopwatch($container);
        $logger = self::modulePlanLogger($container);
        $kernelConfig = self::kernelConfig($container);

        return new ModulePlanResolver(
            presetLoaderFactory: $presetLoaderFactory,
            manifestReader: $manifestReader,
            graphResolver: $graphResolver,
            meter: $meter,
            stopwatch: $stopwatch,
            logger: $logger,
            modulesConfig: self::modulesConfig($kernelConfig),
        );
    }

    /**
     * Creates the Kernel config namespace guard.
     *
     * The forbidden top-level roots are read from the already-built Kernel config
     * snapshot:
     *
     *     kernel.config.forbidden_top_level_roots
     *
     * This factory performs deterministic construction only. It does not run the
     * config pipeline, does not mutate guard state, and does not reconfigure the
     * guard after construction.
     */
    public static function configNamespaceGuard(ContainerInterface $container): ConfigNamespaceGuard
    {
        $kernelConfig = self::kernelConfig($container);

        try {
            return new ConfigNamespaceGuard(
                forbiddenTopLevelRoots: self::forbiddenTopLevelRoots($kernelConfig),
            );
        } catch (\InvalidArgumentException $exception) {
            throw new ContainerException(
                'kernel-config-forbidden-top-level-roots-invalid',
                $exception,
            );
        }
    }

    /**
     * Creates the per-file directive processor.
     *
     * This factory wires DirectiveProcessor to the already-registered
     * ConfigNamespaceGuard. It does not process config files and keeps no mutable
     * runtime state.
     */
    public static function directiveProcessor(ContainerInterface $container): DirectiveProcessor
    {
        $namespaceGuard = self::configService($container, ConfigNamespaceGuard::class);

        if (!$namespaceGuard instanceof ConfigNamespaceGuard) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return new DirectiveProcessor(
            namespaceGuard: $namespaceGuard,
        );
    }

    /**
     * Creates the deterministic config merger.
     *
     * This factory wires ConfigMerger to the already-registered DirectiveProcessor.
     * It does not merge config values and keeps no mutable runtime state.
     */
    public static function configMerger(ContainerInterface $container): ConfigMerger
    {
        $directiveProcessor = self::configService($container, DirectiveProcessor::class);

        if (!$directiveProcessor instanceof DirectiveProcessor) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return new ConfigMerger(
            directiveProcessor: $directiveProcessor,
        );
    }

    /**
     * Creates the config rules loader.
     *
     * This factory performs construction only. It does not load rules files and
     * keeps no mutable runtime state.
     */
    public static function configRulesLoader(): ConfigRulesLoader
    {
        return new ConfigRulesLoader();
    }

    /**
     * Creates the declarative config validator.
     *
     * This factory performs construction only. It does not validate config and
     * keeps no mutable runtime state.
     */
    public static function configValidator(): ConfigValidator
    {
        return new ConfigValidator();
    }

    /**
     * Creates the safe config explainer.
     *
     * This factory performs construction only. It does not build explain traces and
     * keeps no mutable runtime state.
     */
    public static function configExplainer(): ConfigExplainer
    {
        return new ConfigExplainer();
    }

    /**
     * Creates the package defaults config loader.
     *
     * This factory wires the loader to DirectiveProcessor. It does not discover
     * package paths, does not load package config files, and keeps no mutable
     * runtime state.
     */
    public static function packageDefaultsConfigLoader(ContainerInterface $container): PackageDefaultsConfigLoader
    {
        $directiveProcessor = self::configService($container, DirectiveProcessor::class);

        if (!$directiveProcessor instanceof DirectiveProcessor) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return new PackageDefaultsConfigLoader(
            directiveProcessor: $directiveProcessor,
        );
    }

    /**
     * Creates the skeleton/application config loader.
     *
     * This factory wires the loader to DirectiveProcessor. It does not scan
     * skeleton/app directories, does not read skeleton/config/app.php, and keeps no
     * mutable runtime state.
     */
    public static function skeletonConfigLoader(ContainerInterface $container): SkeletonConfigLoader
    {
        $directiveProcessor = self::configService($container, DirectiveProcessor::class);

        if (!$directiveProcessor instanceof DirectiveProcessor) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return new SkeletonConfigLoader(
            directiveProcessor: $directiveProcessor,
        );
    }

    /**
     * Creates the env overlay loader.
     *
     * This factory performs construction only. It does not read $_ENV, $_SERVER,
     * getenv(), dotenv files, or EnvRepository snapshots, and keeps no mutable
     * runtime state.
     */
    public static function environmentOverlayLoader(): EnvironmentOverlayLoader
    {
        return new EnvironmentOverlayLoader();
    }

    /**
     * Creates the Phase B ConfigKernel orchestrator.
     *
     * This factory performs deterministic wiring only. It does not run config
     * compilation, does not receive BootstrapConfig, does not receive ModulePlan,
     * does not receive EnvRepositoryInterface snapshots, and does not keep mutable
     * runtime state.
     */
    public static function configKernel(ContainerInterface $container): ConfigKernel
    {
        $merger = self::configService($container, ConfigMerger::class);

        if (!$merger instanceof ConfigMerger) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $rulesLoader = self::configService($container, ConfigRulesLoader::class);

        if (!$rulesLoader instanceof ConfigRulesLoader) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $validator = self::configService($container, ConfigValidator::class);

        if (!$validator instanceof ConfigValidator) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $explainer = self::configService($container, ConfigExplainer::class);

        if (!$explainer instanceof ConfigExplainer) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $packageDefaultsLoader = self::configService($container, PackageDefaultsConfigLoader::class);

        if (!$packageDefaultsLoader instanceof PackageDefaultsConfigLoader) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $skeletonLoader = self::configService($container, SkeletonConfigLoader::class);

        if (!$skeletonLoader instanceof SkeletonConfigLoader) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        $environmentOverlayLoader = self::configService($container, EnvironmentOverlayLoader::class);

        if (!$environmentOverlayLoader instanceof EnvironmentOverlayLoader) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return new ConfigKernel(
            merger: $merger,
            rulesLoader: $rulesLoader,
            validator: $validator,
            explainer: $explainer,
            packageDefaultsLoader: $packageDefaultsLoader,
            skeletonLoader: $skeletonLoader,
            environmentOverlayLoader: $environmentOverlayLoader,
            meter: self::meter($container),
            tracer: self::tracer($container),
            stopwatch: self::stopwatch($container),
            logger: self::configLogger($container),
        );
    }

    /**
     * Creates the Kernel artifact payload normalizer.
     *
     * This factory performs construction only. PayloadNormalizer is the
     * Kernel-owned json-like normalization boundary used before stable PHP/JSON
     * artifact byte emission. It does not emit bytes, calculate fingerprints,
     * read files, write files, or keep mutable runtime state.
     */
    public static function artifactPayloadNormalizer(): PayloadNormalizer
    {
        return new PayloadNormalizer();
    }

    /**
     * Creates the deterministic PHP artifact array dumper.
     *
     * This factory wires the dumper to PayloadNormalizer so Kernel PHP artifact
     * emission stays aligned with the Foundation json-like normalization rules.
     * It does not dump artifacts during factory construction.
     */
    public static function stablePhpArrayDumper(ContainerInterface $container): StablePhpArrayDumper
    {
        $payloadNormalizer = self::artifactService($container, PayloadNormalizer::class);

        if (!$payloadNormalizer instanceof PayloadNormalizer) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new StablePhpArrayDumper(
            payloadNormalizer: $payloadNormalizer,
        );
    }

    /**
     * Creates the canonical Kernel artifact envelope factory.
     *
     * This factory performs construction only. It does not assemble envelopes
     * during provider registration and keeps no mutable artifact state.
     */
    public static function artifactEnvelopeFactory(ContainerInterface $container): ArtifactEnvelopeFactory
    {
        $payloadNormalizer = self::artifactService($container, PayloadNormalizer::class);

        if (!$payloadNormalizer instanceof PayloadNormalizer) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new ArtifactEnvelopeFactory(
            payloadNormalizer: $payloadNormalizer,
        );
    }

    /**
     * Creates the Kernel artifact path resolver.
     *
     * This factory performs construction only. It does not resolve paths during
     * provider registration and keeps no BootstrapConfig or config snapshot.
     */
    public static function artifactPathResolver(): ArtifactPathResolver
    {
        return new ArtifactPathResolver();
    }

    /**
     * Creates the deterministic declared-input file lister.
     *
     * This factory performs construction only. It does not list files during
     * provider registration.
     */
    public static function deterministicFileLister(): DeterministicFileLister
    {
        return new DeterministicFileLister();
    }

    /**
     * Creates the deterministic fingerprint input builder.
     *
     * This factory performs wiring only. It does not resolve BootstrapConfig,
     * resolve ModulePlan, compile config, read env, discover files, or calculate
     * fingerprints during provider registration.
     */
    public static function configFingerprintInputBuilder(ContainerInterface $container): ConfigFingerprintInputBuilder
    {
        $payloadNormalizer = self::artifactService($container, PayloadNormalizer::class);

        if (!$payloadNormalizer instanceof PayloadNormalizer) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $fileLister = self::artifactService($container, DeterministicFileLister::class);

        if (!$fileLister instanceof DeterministicFileLister) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new ConfigFingerprintInputBuilder(
            payloadNormalizer: $payloadNormalizer,
            fileLister: $fileLister,
        );
    }

    /**
     * Creates the safe fingerprint explainer.
     *
     * This factory performs construction only. It does not calculate
     * fingerprints, diff inputs, read files, write files, or render output.
     */
    public static function fingerprintExplainer(): FingerprintExplainer
    {
        return new FingerprintExplainer();
    }

    /**
     * Creates the deterministic fingerprint calculator.
     *
     * This factory wires only public observability ports/interfaces and
     * Stopwatch. It does not calculate fingerprints during provider
     * registration and does not decide whether observability adapters are real
     * or Noop.
     */
    public static function fingerprintCalculator(ContainerInterface $container): FingerprintCalculator
    {
        $payloadNormalizer = self::artifactService($container, PayloadNormalizer::class);

        if (!$payloadNormalizer instanceof PayloadNormalizer) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new FingerprintCalculator(
            payloadNormalizer: $payloadNormalizer,
            tracer: self::tracer($container),
            meter: self::meter($container),
            logger: self::logger($container),
            stopwatch: self::stopwatch($container),
        );
    }

    /**
     * Creates the atomic Kernel artifact writer.
     *
     * This factory performs wiring only. It does not write files, dump artifacts,
     * start spans, emit metrics, or write logs during provider registration.
     */
    public static function artifactWriter(ContainerInterface $container): ArtifactWriter
    {
        $phpArrayDumper = self::artifactService($container, StablePhpArrayDumper::class);

        if (!$phpArrayDumper instanceof StablePhpArrayDumper) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new ArtifactWriter(
            phpArrayDumper: $phpArrayDumper,
            tracer: self::tracer($container),
            meter: self::meter($container),
            logger: self::logger($container),
            stopwatch: self::stopwatch($container),
        );
    }

    /**
     * Creates the module-manifest artifact builder.
     *
     * This factory performs wiring only. It does not resolve modules or build an
     * artifact envelope during provider registration.
     */
    public static function moduleManifestBuilder(ContainerInterface $container): ModuleManifestBuilder
    {
        $envelopeFactory = self::artifactService($container, ArtifactEnvelopeFactory::class);

        if (!$envelopeFactory instanceof ArtifactEnvelopeFactory) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new ModuleManifestBuilder(
            envelopeFactory: $envelopeFactory,
        );
    }

    /**
     * Creates the compiled-config artifact builder.
     *
     * This factory performs wiring only. It does not run ConfigKernel or build
     * config artifacts during provider registration.
     */
    public static function compiledConfigBuilder(ContainerInterface $container): CompiledConfigBuilder
    {
        $envelopeFactory = self::artifactService($container, ArtifactEnvelopeFactory::class);

        if (!$envelopeFactory instanceof ArtifactEnvelopeFactory) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new CompiledConfigBuilder(
            envelopeFactory: $envelopeFactory,
        );
    }

    /**
     * Creates the compiled-container artifact builder.
     *
     * This factory performs wiring only. It does not compile the container graph,
     * calculate fingerprints, read files, write files, validate existing artifacts,
     * inspect runtime containers, instantiate runtime services, or emit stdout/stderr
     * during provider registration.
     */
    public static function compiledContainerBuilder(ContainerInterface $container): CompiledContainerBuilder
    {
        $envelopeFactory = self::artifactService($container, ArtifactEnvelopeFactory::class);

        if (!$envelopeFactory instanceof ArtifactEnvelopeFactory) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new CompiledContainerBuilder(
            envelopeFactory: $envelopeFactory,
        );
    }

    /**
     * Creates the PHP artifact reader.
     *
     * This factory performs construction only. It does not read generated
     * artifacts during provider registration.
     */
    public static function phpArtifactReader(): PhpArtifactReader
    {
        return new PhpArtifactReader();
    }

    /**
     * Creates the artifact schema validator.
     *
     * This factory performs construction only. It does not validate artifacts
     * during provider registration.
     */
    public static function artifactSchemaValidator(): ArtifactSchemaValidator
    {
        return new ArtifactSchemaValidator();
    }

    /**
     * Creates the compiled-container runtime factory.
     *
     * This factory performs wiring only. It does not read container.php, read
     * config.php, validate artifacts, build a runtime container, run providers,
     * compile a new container graph, calculate fingerprints, write artifacts,
     * mutate artifacts, or emit stdout/stderr during provider registration.
     */
    public static function compiledContainerFactory(ContainerInterface $container): CompiledContainerFactory
    {
        $artifactReader = self::artifactService($container, PhpArtifactReader::class);

        if (!$artifactReader instanceof PhpArtifactReader) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $schemaValidator = self::artifactService($container, ArtifactSchemaValidator::class);

        if (!$schemaValidator instanceof ArtifactSchemaValidator) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new CompiledContainerFactory(
            artifactReader: $artifactReader,
            schemaValidator: $schemaValidator,
        );
    }

    /**
     * Creates the deterministic compiled-container graph compiler.
     *
     * This factory performs wiring only. It does not compile descriptors, inspect
     * runtime providers, read source config files, read generated artifacts, write
     * artifacts, calculate fingerprints, instantiate runtime services, or emit
     * stdout/stderr during provider registration.
     */
    public static function containerCompiler(ContainerInterface $container): ContainerCompiler
    {
        return new ContainerCompiler(
            tracer: self::tracer($container),
            meter: self::meter($container),
            logger: self::logger($container),
            stopwatch: self::stopwatch($container),
        );
    }

    /**
     * Builds the production runtime Foundation container through compiled-artifact
     * boot only.
     *
     * The caller MUST provide:
     *
     * - the resolved container.php artifact path;
     * - an already-read and already-validated config@1 payload.
     *
     * This method intentionally does not read source config files, run source
     * config discovery, run module discovery, register runtime providers as a
     * fallback, compile a new container graph, calculate fingerprints, write
     * artifacts, mutate artifacts, or emit stdout/stderr.
     *
     * Missing container.php is surfaced by CompiledContainerFactory as:
     *
     *     CORETSIA_CONTAINER_ARTIFACT_MISSING: container-artifact-missing
     *
     * There is deliberately no implicit non-artifact fallback in this production
     * runtime path. Any future developer-mode fallback requires a separate
     * epic/ADR and MUST NOT be implied here.
     *
     * @param non-empty-string $containerArtifactPath
     * @param array<string, mixed> $configPayload Already-read/validated config@1 payload.
     *
     * @throws \Coretsia\Kernel\Container\Exception\ContainerArtifactMissingException
     * @throws \Coretsia\Kernel\Container\Exception\ContainerArtifactInvalidException
     */
    public static function productionRuntimeContainer(
        ContainerInterface $container,
        string $containerArtifactPath,
        array $configPayload,
    ): FoundationContainer {
        $compiledContainerFactory = self::artifactService($container, CompiledContainerFactory::class);

        if (!$compiledContainerFactory instanceof CompiledContainerFactory) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return $compiledContainerFactory->build(
            containerArtifactPath: $containerArtifactPath,
            configPayload: $configPayload,
        );
    }

    /**
     * Creates the Kernel-owned artifact compiler.
     *
     * This factory performs wiring only. It does not resolve BootstrapConfig,
     * resolve ModulePlan, compile config, calculate fingerprints, write
     * artifacts, read artifacts, verify cache, trigger reset, or start a
     * UnitOfWork during provider registration.
     */
    public static function artifactCompiler(ContainerInterface $container): ArtifactCompiler
    {
        $configKernel = self::configService($container, ConfigKernel::class);

        if (!$configKernel instanceof ConfigKernel) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $fingerprintInputBuilder = self::artifactService($container, ConfigFingerprintInputBuilder::class);

        if (!$fingerprintInputBuilder instanceof ConfigFingerprintInputBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $fingerprintCalculator = self::artifactService($container, FingerprintCalculator::class);

        if (!$fingerprintCalculator instanceof FingerprintCalculator) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $moduleManifestBuilder = self::artifactService($container, ModuleManifestBuilder::class);

        if (!$moduleManifestBuilder instanceof ModuleManifestBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $compiledConfigBuilder = self::artifactService($container, CompiledConfigBuilder::class);

        if (!$compiledConfigBuilder instanceof CompiledConfigBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $containerCompiler = self::artifactService($container, ContainerCompiler::class);

        if (!$containerCompiler instanceof ContainerCompiler) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $compiledContainerBuilder = self::artifactService($container, CompiledContainerBuilder::class);

        if (!$compiledContainerBuilder instanceof CompiledContainerBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $artifactWriter = self::artifactService($container, ArtifactWriter::class);

        if (!$artifactWriter instanceof ArtifactWriter) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $pathResolver = self::artifactService($container, ArtifactPathResolver::class);

        if (!$pathResolver instanceof ArtifactPathResolver) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new ArtifactCompiler(
            configKernel: $configKernel,
            fingerprintInputBuilder: $fingerprintInputBuilder,
            fingerprintCalculator: $fingerprintCalculator,
            moduleManifestBuilder: $moduleManifestBuilder,
            compiledConfigBuilder: $compiledConfigBuilder,
            containerCompiler: $containerCompiler,
            compiledContainerBuilder: $compiledContainerBuilder,
            artifactWriter: $artifactWriter,
            pathResolver: $pathResolver,
        );
    }

    /**
     * Creates the Kernel-owned cache verifier.
     *
     * This factory performs wiring only. It does not resolve BootstrapConfig,
     * resolve ModulePlan, compile config, calculate fingerprints, read generated
     * artifacts, validate artifacts, compare bytes, run cache verification,
     * trigger reset, or start a UnitOfWork during provider registration.
     */
    public static function cacheVerifier(ContainerInterface $container): CacheVerifier
    {
        $configKernel = self::configService($container, ConfigKernel::class);

        if (!$configKernel instanceof ConfigKernel) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $fingerprintInputBuilder = self::artifactService($container, ConfigFingerprintInputBuilder::class);

        if (!$fingerprintInputBuilder instanceof ConfigFingerprintInputBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $fingerprintCalculator = self::artifactService($container, FingerprintCalculator::class);

        if (!$fingerprintCalculator instanceof FingerprintCalculator) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $moduleManifestBuilder = self::artifactService($container, ModuleManifestBuilder::class);

        if (!$moduleManifestBuilder instanceof ModuleManifestBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $compiledConfigBuilder = self::artifactService($container, CompiledConfigBuilder::class);

        if (!$compiledConfigBuilder instanceof CompiledConfigBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $containerCompiler = self::artifactService($container, ContainerCompiler::class);

        if (!$containerCompiler instanceof ContainerCompiler) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $compiledContainerBuilder = self::artifactService($container, CompiledContainerBuilder::class);

        if (!$compiledContainerBuilder instanceof CompiledContainerBuilder) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $phpArrayDumper = self::artifactService($container, StablePhpArrayDumper::class);

        if (!$phpArrayDumper instanceof StablePhpArrayDumper) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $artifactReader = self::artifactService($container, PhpArtifactReader::class);

        if (!$artifactReader instanceof PhpArtifactReader) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $schemaValidator = self::artifactService($container, ArtifactSchemaValidator::class);

        if (!$schemaValidator instanceof ArtifactSchemaValidator) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        $pathResolver = self::artifactService($container, ArtifactPathResolver::class);

        if (!$pathResolver instanceof ArtifactPathResolver) {
            throw new ContainerException('kernel-artifacts-dependency-invalid');
        }

        return new CacheVerifier(
            configKernel: $configKernel,
            fingerprintInputBuilder: $fingerprintInputBuilder,
            fingerprintCalculator: $fingerprintCalculator,
            moduleManifestBuilder: $moduleManifestBuilder,
            compiledConfigBuilder: $compiledConfigBuilder,
            containerCompiler: $containerCompiler,
            compiledContainerBuilder: $compiledContainerBuilder,
            phpArrayDumper: $phpArrayDumper,
            artifactReader: $artifactReader,
            schemaValidator: $schemaValidator,
            pathResolver: $pathResolver,
            tracer: self::tracer($container),
            meter: self::meter($container),
            logger: self::logger($container),
            stopwatch: self::stopwatch($container),
        );
    }

    /**
     * Creates the canonical runtime driver matrix guard.
     *
     * This factory performs construction only. It does not read config values,
     * does not resolve ModulePlan, does not detect active drivers, does not cache
     * guard results, and keeps no mutable runtime state.
     */
    public static function runtimeDriverGuard(): RuntimeDriverGuard
    {
        return new RuntimeDriverGuard();
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
     * This method reads only the already-built Kernel config snapshot needed for
     * UnitOfWork attribute defensive limits. It does not enumerate hooks, does
     * not trigger reset, and does not start a UoW.
     */
    public static function kernelRuntime(ContainerInterface $container): KernelRuntime
    {
        $attributeLimits = self::unitOfWorkAttributeLimits(self::kernelConfig($container));

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
            attributesMaxDepth: $attributeLimits['maxDepth'],
            attributesMaxKeys: $attributeLimits['maxKeys'],
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
     * Missing keys are invalid: this factory must not silently fall back to
     * hardcoded defensive limits.
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

        if (!\array_key_exists('max_depth', $attributesConfig)) {
            throw new ContainerException('kernel-uow-attributes-max-depth-missing');
        }

        $maxDepth = $attributesConfig['max_depth'];

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

        if (!\array_key_exists('max_keys', $attributesConfig)) {
            throw new ContainerException('kernel-uow-attributes-max-keys-missing');
        }

        $maxKeys = $attributesConfig['max_keys'];

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

    private static function configLogger(ContainerInterface $container): LoggerInterface
    {
        try {
            if (!$container->has(LoggerInterface::class)) {
                throw new ContainerException('kernel-config-dependency-not-found');
            }

            $service = $container->get(LoggerInterface::class);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-config-dependency-not-found',
                $throwable,
            );
        }

        if (!$service instanceof LoggerInterface) {
            throw new ContainerException('kernel-config-dependency-invalid');
        }

        return $service;
    }

    private static function modulePlanLogger(ContainerInterface $container): LoggerInterface
    {
        try {
            if (!$container->has(LoggerInterface::class)) {
                throw new ContainerException('kernel-module-plan-dependency-not-found');
            }

            $service = $container->get(LoggerInterface::class);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-module-plan-dependency-not-found',
                $throwable,
            );
        }

        if (!$service instanceof LoggerInterface) {
            throw new ContainerException('kernel-module-plan-dependency-invalid');
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

    private static function modulePlanService(
        ContainerInterface $container,
        string $id,
    ): mixed {
        try {
            if (!$container->has($id)) {
                throw new ContainerException('kernel-module-plan-dependency-not-found');
            }

            return $container->get($id);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-module-plan-dependency-not-found',
                $throwable,
            );
        }
    }

    private static function configService(
        ContainerInterface $container,
        string $id,
    ): mixed {
        try {
            if (!$container->has($id)) {
                throw new ContainerException('kernel-config-dependency-not-found');
            }

            return $container->get($id);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-config-dependency-not-found',
                $throwable,
            );
        }
    }

    private static function artifactService(
        ContainerInterface $container,
        string $id,
    ): mixed {
        try {
            if (!$container->has($id)) {
                throw new ContainerException('kernel-artifacts-dependency-not-found');
            }

            return $container->get($id);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ContainerException(
                'kernel-artifacts-dependency-not-found',
                $throwable,
            );
        }
    }

    /**
     * Reads the strict `kernel` config root from the Foundation container config
     * snapshot.
     *
     * This method exists so KernelServiceProvider closures do not retain config
     * arrays. The full config snapshot remains owned by the Foundation container;
     * Kernel module-plan services receive only their minimal validated subtrees.
     *
     * @return array<string, mixed>
     */
    private static function kernelConfig(ContainerInterface $container): array
    {
        if (!$container instanceof FoundationContainer) {
            throw new ContainerException('kernel-container-invalid');
        }

        $config = $container->config();

        return self::requiredMapConfig(
            config: $config,
            key: 'kernel',
            reason: 'kernel-config-root-missing',
        );
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function modulesConfig(array $kernelConfig): array
    {
        return self::optionalMapConfig(
            config: $kernelConfig,
            key: 'modules',
            reason: 'kernel-modules-config-invalid',
        );
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function modesConfig(array $kernelConfig): array
    {
        return self::optionalMapConfig(
            config: $kernelConfig,
            key: 'modes',
            reason: 'kernel-modes-config-invalid',
        );
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function kernelConfigConfig(array $kernelConfig): array
    {
        return self::requiredMapConfig(
            config: $kernelConfig,
            key: 'config',
            reason: 'kernel-config-config-invalid',
        );
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return list<non-empty-string>
     */
    private static function forbiddenTopLevelRoots(array $kernelConfig): array
    {
        $configConfig = self::kernelConfigConfig($kernelConfig);
        $roots = $configConfig['forbidden_top_level_roots'] ?? null;

        if (!\is_array($roots) || !\array_is_list($roots) || $roots === []) {
            throw new ContainerException('kernel-config-forbidden-top-level-roots-invalid');
        }

        $normalized = [];

        foreach ($roots as $root) {
            if (!\is_string($root) || $root === '') {
                throw new ContainerException('kernel-config-forbidden-top-level-roots-invalid');
            }

            if (\trim($root) !== $root || \preg_match('/\s/u', $root) === 1) {
                throw new ContainerException('kernel-config-forbidden-top-level-roots-invalid');
            }

            if (\preg_match('/[\x00-\x1F\x7F]/', $root) === 1) {
                throw new ContainerException('kernel-config-forbidden-top-level-roots-invalid');
            }

            $normalized[] = $root;
        }

        $normalized = \array_values(\array_unique($normalized));

        \usort(
            $normalized,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        /** @var list<non-empty-string> $normalized */
        return $normalized;
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function uowAttributesConfig(array $kernelConfig): array
    {
        $uowConfig = self::requiredMapConfig(
            config: $kernelConfig,
            key: 'uow',
            reason: 'kernel-uow-config-invalid',
        );

        return self::requiredMapConfig(
            config: $uowConfig,
            key: 'attributes',
            reason: 'kernel-uow-attributes-config-invalid',
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function requiredMapConfig(
        array $config,
        string $key,
        string $reason,
    ): array {
        if (!\array_key_exists($key, $config)) {
            throw new ContainerException($reason);
        }

        $value = $config[$key];

        if (!\is_array($value) || !self::isMapArray($value)) {
            throw new ContainerException($reason);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function optionalMapConfig(
        array $config,
        string $key,
        string $reason,
    ): array {
        if (!\array_key_exists($key, $config)) {
            return [];
        }

        $value = $config[$key];

        if (!\is_array($value) || !self::isMapArray($value)) {
            throw new ContainerException($reason);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }
}
