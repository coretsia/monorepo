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

use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
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
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
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
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\ArtifactRuntimeSeedFactory;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ArtifactPipelineTestSupport
{
    private const string MODULE_FIXTURE = 'core.fixture';

    private function __construct()
    {
    }

    public static function temporaryRoot(string $name): string
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-'
            . $name
            . '-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        return $root;
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function writeRootConfig(string $skeletonRoot, array $config): void
    {
        self::writePhpReturn($skeletonRoot . '/config/roots.php', $config);
    }

    /**
     * @param array<string,mixed> $value
     */
    public static function writePhpReturn(string $path, array $value): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($value, true) . ";\n",
        );
    }

    /**
     * @param array<int|string, mixed> $envelope
     */
    public static function writeArtifactEnvelope(string $path, array $envelope): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents(
            $path,
            new StablePhpArrayDumper()->dumpEnvelope($envelope),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function readArtifactEnvelope(string $path): array
    {
        $read = new PhpArtifactReader()->readExact($path);

        TestCase::assertIsArray($read['envelope']);

        /** @var array<string, mixed> $envelope */
        $envelope = $read['envelope'];

        return $envelope;
    }

    public static function removeTree(string $path): void
    {
        if (\is_link($path)) {
            self::removeFilesystemLink($path);

            return;
        }

        if (!\is_dir($path)) {
            if (\file_exists($path)) {
                @\unlink($path);
            }

            return;
        }

        $items = @\scandir($path);

        if (!\is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_link($itemPath)) {
                self::removeFilesystemLink($itemPath);

                continue;
            }

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            @\unlink($itemPath);
        }

        @\rmdir($path);
    }

    private static function removeFilesystemLink(string $path): void
    {
        /*
         * Unix-like systems remove filesystem symlinks through unlink().
         * Windows requires rmdir() for directory symlinks and junctions.
         */
        if (@\unlink($path)) {
            return;
        }

        @\rmdir($path);
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaultConfig(string $value = 'safe-value'): array
    {
        return [
            'foundation' => [
                'reset' => [
                    'priority' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'custom' => [
                'container_fixture' => [
                    'value' => ContainerDefinitionProviderFixture::PARAMETER_VALUE,
                ],
                'feature' => [
                    'value' => $value,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function kernelConfig(): array
    {
        return [
            'env' => [
                'dotenv' => [
                    'files' => [],
                ],
            ],
            'fingerprint' => [
                'skeleton_ignore_prefixes' => [
                    'var/maintenance',
                ],
            ],
        ];
    }

    public static function bootstrapConfig(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: 'default',
            debug: false,
            artifactsCacheDir: $artifactsCacheDir,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    public static function modulePlan(): ModulePlan
    {
        return self::moduleResolution()->plan();
    }

    /**
     * @param list<class-string<ContainerDefinitionProviderInterface>> $providerClasses
     */
    public static function moduleResolution(
        array $providerClasses = [
            ContainerDefinitionProviderFixture::class,
        ],
    ): ModuleResolution {
        $moduleId = ModuleId::fromString(self::MODULE_FIXTURE);
        $composerName = 'coretsia/core-kernel-test-fixture';
        $manifest = new ModuleManifest([
            new ModuleDescriptor(
                id: $moduleId,
                composerName: $composerName,
                packageKind: 'runtime',
                moduleClass: null,
                capabilities: [],
                metadata: [
                    'providers' => $providerClasses,
                ],
            ),
        ]);

        return new ModuleResolution(
            manifest: $manifest,
            plan: new ModulePlan(
                app: 'web',
                preset: 'default',
                enabled: [
                    $moduleId,
                ],
                disabled: [],
                optionalMissing: [],
                topologicalOrder: [
                    $moduleId,
                ],
                modules: [
                    new ModulePlanEntry(
                        moduleId: $moduleId,
                        composerName: $composerName,
                    ),
                ],
                warnings: [],
            ),
        );
    }

    public static function envRepository(): EnvRepositoryInterface
    {
        return new class() implements EnvRepositoryInterface {
            public function has(string $name): bool
            {
                return false;
            }

            public function get(string $name): EnvValue
            {
                return EnvValue::missing();
            }

            public function all(): array
            {
                return [];
            }

            public function sourceOf(string $name): ?ConfigValueSource
            {
                return null;
            }
        };
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function compileArtifacts(
        TestCase $testCase,
        string $skeletonRoot,
        array $config,
        ?ModuleResolution $moduleResolution = null,
        string $artifactsCacheDir = 'var/cache',
        ?ConfigSourceSet $configSources = null,
    ): array {
        self::writeRootConfig($skeletonRoot, $config);
        $moduleResolution ??= self::moduleResolution();
        $configSources ??= ConfigSourceSet::empty();

        return self::artifactCompiler($testCase)->compile(
            bootstrapConfig: self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            ),
            moduleResolution: $moduleResolution,
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            configSources: $configSources,
        );
    }

    public static function verifyArtifacts(
        TestCase $testCase,
        string $skeletonRoot,
        ?ModuleResolution $moduleResolution = null,
        string $artifactsCacheDir = 'var/cache',
    ): array {
        $moduleResolution ??= self::moduleResolution();

        return self::cacheVerifier($testCase)->verify(
            bootstrapConfig: self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            ),
            moduleResolution: $moduleResolution,
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            configSources: ConfigSourceSet::empty(),
        );
    }

    public static function fingerprintForCurrentConfig(
        TestCase $testCase,
        string $skeletonRoot,
        ?ModuleResolution $moduleResolution = null,
        string $artifactsCacheDir = 'var/cache',
        ?ConfigSourceSet $configSources = null,
    ): string {
        $bootstrapConfig = self::bootstrapConfig(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );
        $moduleResolution ??= self::moduleResolution();
        $modulePlan = $moduleResolution->plan();
        $env = self::envRepository();
        $configSources ??= ConfigSourceSet::empty();

        $compiled = self::configKernel($testCase)->compile(
            bootstrapConfig: $bootstrapConfig,
            modulePlan: $modulePlan,
            env: $env,
            configSources: $configSources,
            explain: false,
        );

        $containerGraph = self::runtimeContainerGraphCompiler($testCase)->compile(
            moduleResolution: $moduleResolution,
            compiledConfig: $compiled['config'],
        );

        $input = self::fingerprintInputBuilder()->build(
            bootstrapConfig: $bootstrapConfig,
            modulePlan: $modulePlan,
            containerGraph: $containerGraph,
            env: $env,
            kernelConfig: self::kernelConfig(),
            compiledConfig: $compiled,
            configSources: $configSources,
        );

        return self::fingerprintCalculator($testCase)->calculate($input);
    }

    public static function fingerprintForContainerGraph(
        TestCase $testCase,
        DefinitionGraph $containerGraph,
    ): string {
        return self::fingerprintCalculator($testCase)->calculate([
            'schemaVersion' => 1,
            'containerGraph' => new ContainerGraphFingerprintBucketBuilder()->build($containerGraph),
        ]);
    }

    public static function artifactRoot(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): string {
        return \rtrim($skeletonRoot, '/\\') . '/' . $artifactsCacheDir . '/web';
    }

    public static function currentGeneration(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): ArtifactGeneration {
        $generationPathResolver = new ArtifactGenerationPathResolver();
        $schemaValidator = new ArtifactSchemaValidator();
        $generationValidator = new ArtifactGenerationValidator(
            artifactReader: new PhpArtifactReader(),
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );
        $generationLocator = new ArtifactGenerationLocator(
            lock: new ArtifactGenerationLock($generationPathResolver),
            pathResolver: $generationPathResolver,
            validator: $generationValidator,
        );

        $generation = $generationLocator->locate(
            self::artifactRoot(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            ),
        );

        TestCase::assertInstanceOf(
            ArtifactGeneration::class,
            $generation,
            'A current artifact generation must be selected.',
        );

        return $generation;
    }

    /**
     * @return array<string,string>
     */
    public static function currentArtifactPaths(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): array {
        $generation = self::currentGeneration(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );

        return [
            ArtifactGeneration::CONFIG_BASENAME => $generation->configPath(),
            ArtifactGeneration::CONTAINER_BASENAME => $generation->containerPath(),
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => $generation->generationManifestPath(),
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $generation->moduleManifestPath(),
        ];
    }

    public static function currentArtifactPath(
        string $skeletonRoot,
        string $basename,
        string $artifactsCacheDir = 'var/cache',
    ): string {
        $paths = self::currentArtifactPaths(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );

        if (!isset($paths[$basename])) {
            throw new \InvalidArgumentException('test-current-artifact-basename-invalid');
        }

        return $paths[$basename];
    }

    /**
     * @return array<string,string>
     */
    public static function artifactBytes(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): array {
        $paths = self::currentArtifactPaths(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );
        $bytes = [];

        foreach ($paths as $basename => $path) {
            $content = \file_get_contents($path);

            TestCase::assertIsString($content);

            $bytes[$basename] = $content;
        }

        \ksort($bytes, \SORT_STRING);

        return $bytes;
    }

    /**
     * @return array<string,string>
     */
    public static function artifactPaths(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): array {
        $directory = \rtrim($skeletonRoot, '/\\') . '/' . $artifactsCacheDir . '/web';

        return [
            'config.php' => $directory . '/config.php',
            'container.php' => $directory . '/container.php',
            'module-manifest.php' => $directory . '/module-manifest.php',
        ];
    }

    public static function artifactPath(
        string $skeletonRoot,
        string $basename,
        string $artifactsCacheDir = 'var/cache',
    ): string {
        $paths = self::artifactPaths(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );

        if (!isset($paths[$basename])) {
            throw new \InvalidArgumentException('test-artifact-basename-invalid');
        }

        return $paths[$basename];
    }

    /**
     * @return array<string, mixed>
     */
    public static function artifactEnvelope(
        string $skeletonRoot,
        string $basename,
    ): array {
        return self::artifactEnvelopeFromPath(
            self::currentArtifactPath(
                skeletonRoot: $skeletonRoot,
                basename: $basename,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function configPayloadFromArtifact(
        string $skeletonRoot,
    ): array {
        return self::artifactPayloadFromPath(
            path: self::currentArtifactPath(
                skeletonRoot: $skeletonRoot,
                basename: ArtifactGeneration::CONFIG_BASENAME,
            ),
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function moduleManifestPayloadFromArtifact(
        string $skeletonRoot,
    ): array {
        return self::artifactPayloadFromPath(
            path: self::currentArtifactPath(
                skeletonRoot: $skeletonRoot,
                basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
            ),
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function artifactEnvelopeFromPath(
        string $path,
    ): array {
        return self::readArtifactEnvelope($path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function artifactPayloadFromPath(
        string $path,
        string $expectedName,
    ): array {
        $envelope = self::artifactEnvelopeFromPath($path);

        new ArtifactSchemaValidator()->validateExpected(
            envelope: $envelope,
            expectedName: $expectedName,
            expectedSchemaVersion: 1,
        );

        $payload = $envelope['payload'] ?? null;

        TestCase::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    public static function artifactCompiler(TestCase $testCase): ArtifactCompiler
    {
        $envelopeFactory = self::envelopeFactory();
        $phpArrayDumper = new StablePhpArrayDumper(
            new PayloadNormalizer(),
        );

        return new ArtifactCompiler(
            configKernel: self::configKernel($testCase),
            fingerprintInputBuilder: self::fingerprintInputBuilder(),
            fingerprintCalculator: self::fingerprintCalculator($testCase),
            moduleManifestBuilder: new ModuleManifestBuilder($envelopeFactory),
            compiledConfigBuilder: new CompiledConfigBuilder($envelopeFactory),
            runtimeContainerGraphCompiler: self::runtimeContainerGraphCompiler($testCase),
            compiledContainerBuilder: new CompiledContainerBuilder($envelopeFactory),
            phpArrayDumper: $phpArrayDumper,
            generationPublisher: self::artifactGenerationPublisher(
                testCase: $testCase,
                envelopeFactory: $envelopeFactory,
                phpArrayDumper: $phpArrayDumper,
            ),
            pathResolver: new ArtifactPathResolver(),
        );
    }

    private static function artifactGenerationPublisher(
        TestCase $testCase,
        ArtifactEnvelopeFactory $envelopeFactory,
        StablePhpArrayDumper $phpArrayDumper,
    ): ArtifactGenerationPublisher {
        $generationPathResolver = new ArtifactGenerationPathResolver();
        $schemaValidator = new ArtifactSchemaValidator();
        $generationValidator = new ArtifactGenerationValidator(
            artifactReader: new PhpArtifactReader(),
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );

        return new ArtifactGenerationPublisher(
            artifactWriter: self::artifactWriter($testCase),
            phpArrayDumper: $phpArrayDumper,
            manifestBuilder: new ArtifactGenerationManifestBuilder($envelopeFactory),
            validator: $generationValidator,
            lock: new ArtifactGenerationLock($generationPathResolver),
            pathResolver: $generationPathResolver,
        );
    }

    public static function compiledContainerFactory(): CompiledContainerFactory
    {
        return new CompiledContainerFactory(
            schemaValidator: new ArtifactSchemaValidator(),
        );
    }

    public static function runtimeContainerFromArtifacts(
        string $skeletonRoot,
        string $artifactsCacheDir = 'var/cache',
    ): Container {
        $container = new ArtifactRuntimeBooter()->boot(
            input: new ArtifactRuntimeInput(
                skeletonRoot: $skeletonRoot,
                artifactRoot: self::artifactRoot(
                    skeletonRoot: $skeletonRoot,
                    artifactsCacheDir: $artifactsCacheDir,
                ),
            ),
        );

        TestCase::assertInstanceOf(Container::class, $container);

        return $container;
    }

    /**
     * Builds a container directly through CompiledContainerFactory.
     *
     * This helper intentionally bypasses ArtifactRuntimeBooter so tests can
     * exercise compiled-container hydration in isolation.
     *
     * @param array<string, mixed>|null $configPayload
     */
    public static function compiledContainerFromArtifacts(
        string $skeletonRoot,
        ?array $configPayload = null,
        ?ArtifactGeneration $generation = null,
        string $artifactsCacheDir = 'var/cache',
    ): Container {
        $generation ??= self::currentGeneration(
            skeletonRoot: $skeletonRoot,
            artifactsCacheDir: $artifactsCacheDir,
        );

        $configPayload ??= self::artifactPayloadFromPath(
            path: $generation->configPath(),
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
        );

        $moduleManifestPayload = self::artifactPayloadFromPath(
            path: $generation->moduleManifestPath(),
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
        );

        $seedFactory = new ArtifactRuntimeSeedFactory();

        $configRepository = $seedFactory->hydrateConfigRepository($configPayload);
        $modulePlan = $seedFactory->hydrateModulePlan($moduleManifestPayload);

        $seeds = $seedFactory->create(
            input: new ArtifactRuntimeInput(
                skeletonRoot: $skeletonRoot,
                artifactRoot: self::artifactRoot(
                    skeletonRoot: $skeletonRoot,
                    artifactsCacheDir: $artifactsCacheDir,
                ),
            ),
            configRepository: $configRepository,
            modulePlan: $modulePlan,
        );

        return self::compiledContainerFactory()->buildFromEnvelope(
            containerEnvelope: self::artifactEnvelopeFromPath(
                $generation->containerPath(),
            ),
            seeds: $seeds,
        );
    }

    public static function cacheVerifier(TestCase $testCase): CacheVerifier
    {
        $envelopeFactory = self::envelopeFactory();
        $phpArrayDumper = new StablePhpArrayDumper(
            new PayloadNormalizer(),
        );
        $artifactReader = new PhpArtifactReader();
        $schemaValidator = new ArtifactSchemaValidator();
        $generationPathResolver = new ArtifactGenerationPathResolver();
        $generationValidator = new ArtifactGenerationValidator(
            artifactReader: $artifactReader,
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );

        return new CacheVerifier(
            configKernel: self::configKernel($testCase),
            fingerprintInputBuilder: self::fingerprintInputBuilder(),
            fingerprintCalculator: self::fingerprintCalculator($testCase),
            moduleManifestBuilder: new ModuleManifestBuilder($envelopeFactory),
            compiledConfigBuilder: new CompiledConfigBuilder($envelopeFactory),
            runtimeContainerGraphCompiler: self::runtimeContainerGraphCompiler($testCase),
            compiledContainerBuilder: new CompiledContainerBuilder($envelopeFactory),
            phpArrayDumper: $phpArrayDumper,
            generationManifestBuilder: new ArtifactGenerationManifestBuilder($envelopeFactory),
            generationLocator: new ArtifactGenerationLocator(
                lock: new ArtifactGenerationLock($generationPathResolver),
                pathResolver: $generationPathResolver,
                validator: $generationValidator,
            ),
            artifactReader: $artifactReader,
            pathResolver: new ArtifactPathResolver(),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    public static function artifactWriter(TestCase $testCase): ArtifactWriter
    {
        return new ArtifactWriter(
            phpArrayDumper: new StablePhpArrayDumper(new PayloadNormalizer()),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function configKernel(TestCase $testCase): ConfigKernel
    {
        $namespaceGuard = new ConfigNamespaceGuard([
            'coretsia',
            '_internal',
        ]);

        $directiveProcessor = new DirectiveProcessor($namespaceGuard);

        return new ConfigKernel(
            merger: new ConfigMerger($directiveProcessor),
            rulesLoader: new ConfigRulesLoader(),
            validator: new ConfigValidator(),
            explainer: new ConfigExplainer(),
            packageDefaultsLoader: new PackageDefaultsConfigLoader($directiveProcessor),
            skeletonLoader: new SkeletonConfigLoader($directiveProcessor),
            environmentOverlayLoader: new EnvironmentOverlayLoader(),
            meter: self::meter(),
            tracer: self::tracer($testCase),
            stopwatch: new Stopwatch(),
            logger: self::logger(),
        );
    }

    private static function fingerprintInputBuilder(): ConfigFingerprintInputBuilder
    {
        return new ConfigFingerprintInputBuilder(
            containerGraphBucketBuilder: new ContainerGraphFingerprintBucketBuilder(),
            payloadNormalizer: new PayloadNormalizer(),
            fileLister: new DeterministicFileLister(),
        );
    }

    private static function fingerprintCalculator(TestCase $testCase): FingerprintCalculator
    {
        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    public static function runtimeContainerGraphCompiler(
        TestCase $testCase,
    ): RuntimeContainerGraphCompiler {
        return new RuntimeContainerGraphCompiler(
            providerPlanResolver: new ContainerProviderPlanResolver(),
            containerCompiler: self::containerCompiler($testCase),
            completenessValidator: new ContainerGraphCompletenessValidator(),
        );
    }

    private static function containerCompiler(TestCase $testCase): ContainerCompiler
    {
        return new ContainerCompiler(
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function envelopeFactory(): ArtifactEnvelopeFactory
    {
        return new ArtifactEnvelopeFactory(new PayloadNormalizer());
    }

    private static function tracer(TestCase $_testCase): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return ArtifactPipelineTestSupport::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = ArtifactPipelineTestSupport::span($name);

                try {
                    return $callback($span);
                } finally {
                    $span->end();
                }
            }

            public function currentSpan(): ?SpanInterface
            {
                return null;
            }
        };
    }

    public static function span(string $name = 'kernel.test'): SpanInterface
    {
        return new class($name) implements SpanInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function name(): string
            {
                return $this->name;
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
        };
    }

    private static function meter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
    }

    private static function logger(): LoggerInterface
    {
        return new NullLogger();
    }
}
