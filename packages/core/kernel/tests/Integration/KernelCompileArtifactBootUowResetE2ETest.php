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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\Preset\PresetNamespaceResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Kernel\Tests\Fixtures\LifecycleFixturePackage\LifecycleFailingObservability;
use Coretsia\Kernel\Tests\Fixtures\LifecycleFixturePackage\LifecycleFixtureServiceProvider;
use Coretsia\Kernel\Tests\Fixtures\LifecycleFixturePackage\LifecycleGraphVariantProvider;
use Coretsia\Kernel\Tests\Fixtures\LifecycleFixturePackage\LifecycleStatefulService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KernelCompileArtifactBootUowResetE2ETest extends TestCase
{
    public function testCompileArtifactBootUnitOfWorkResetLifecycleIsClosedEndToEnd(): void
    {
        LifecycleFixtureServiceProvider::resetInvocations();
        LifecycleFailingObservability::resetFailures();

        $temporaryApplicationRoot = ArtifactPipelineTestSupport::temporaryRoot('lifecycle-fixture-application');
        $temporaryFixturePackageRoot = ArtifactPipelineTestSupport::temporaryRoot('lifecycle-fixture-package');

        try {
            $staticFixturePackageRoot = \dirname(__DIR__)
                . \DIRECTORY_SEPARATOR
                . 'Fixtures'
                . \DIRECTORY_SEPARATOR
                . 'LifecycleFixturePackage';

            self::copyFixturePackage(
                $staticFixturePackageRoot,
                $temporaryFixturePackageRoot,
            );

            ArtifactPipelineTestSupport::writePhpReturn(
                $temporaryApplicationRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'modes'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle-fixture.php',
                [
                    'schemaVersion' => 1,
                    'name' => 'lifecycle-fixture',
                    'description' => 'Kernel lifecycle E2E fixture.',
                    'required' => [
                        'platform.lifecycle-fixture',
                    ],
                    'modules' => [],
                    'featureBundles' => [],
                    'metadata' => [],
                ],
            );

            self::assertFileDoesNotExist(
                $temporaryApplicationRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'roots.php',
            );

            $bootstrapInput = new BootstrapInput(
                applicationRoot: $temporaryApplicationRoot,
                appTarget: AppTarget::Web,
                appEnv: 'test',
                preset: 'lifecycle-fixture',
                debug: false,
                envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
                artifactsCacheDir: 'var/cache',
            );

            $kernelPackageRoot = \dirname(__DIR__, 2);
            $corePackagesRoot = \dirname($kernelPackageRoot);
            $foundationPackageRoot = $corePackagesRoot
                . \DIRECTORY_SEPARATOR
                . 'foundation';

            $kernelConfig = require $kernelPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'kernel.php';

            self::assertIsArray($kernelConfig);

            $modesConfig = $kernelConfig['modes'] ?? null;
            $modulesConfig = $kernelConfig['modules'] ?? null;

            self::assertIsArray($modesConfig);
            self::assertIsArray($modulesConfig);

            $foundationComposer = self::readComposerJson(
                $foundationPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );
            $kernelComposer = self::readComposerJson(
                $kernelPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );
            $fixtureComposer = self::readComposerJson(
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );

            self::assertSame('coretsia/core-foundation', $foundationComposer['name'] ?? null);
            self::assertSame('library', $foundationComposer['type'] ?? null);
            self::assertSame('coretsia/core-kernel', $kernelComposer['name'] ?? null);
            self::assertSame('library', $kernelComposer['type'] ?? null);
            self::assertSame('coretsia/kernel-lifecycle-fixture', $fixtureComposer['name'] ?? null);
            self::assertSame('library', $fixtureComposer['type'] ?? null);

            $installedData = [
                [
                    'root' => [
                        'name' => 'coretsia/test-app',
                        'type' => 'project',
                        'extra' => [],
                    ],
                    'versions' => [
                        'coretsia/core-foundation' => [
                            'type' => $foundationComposer['type'],
                            'extra' => $foundationComposer['extra'],
                        ],
                        'coretsia/core-kernel' => [
                            'type' => $kernelComposer['type'],
                            'extra' => $kernelComposer['extra'],
                        ],
                        'coretsia/kernel-lifecycle-fixture' => [
                            'type' => $fixtureComposer['type'],
                            'extra' => $fixtureComposer['extra'],
                        ],
                    ],
                ],
            ];

            $metadataProvider = new ComposerInstalledMetadataProvider($installedData);
            $manifestReader = new ComposerManifestReader($metadataProvider);
            $installPathResolver = new ComposerPackageInstallPathResolver([
                'coretsia/core-foundation' => $foundationPackageRoot,
                'coretsia/core-kernel' => $kernelPackageRoot,
                'coretsia/kernel-lifecycle-fixture' => $temporaryFixturePackageRoot,
            ]);
            $modePresetLoaderFactory = new ModePresetLoaderFactory(
                packageRoot: $kernelPackageRoot,
                modesConfig: $modesConfig,
                schemaValidator: new ModePresetSchemaValidator(),
            );
            $configSourceLocationBuilder = new ConfigSourceLocationBuilder(
                installPathResolver: $installPathResolver,
                modePresetLoaderFactory: $modePresetLoaderFactory,
                presetNamespaceResolver: new PresetNamespaceResolver(),
            );
            $moduleResolutionOrchestrator = new ModuleResolutionOrchestrator(
                presetLoaderFactory: $modePresetLoaderFactory,
                presetNamespaceResolver: new PresetNamespaceResolver(),
                moduleSelectionFactory: new ModuleSelectionFactory(new ModuleIdSetNormalizer()),
                manifestReader: $manifestReader,
                modulePlanResolver: new ModulePlanResolver(
                    graphResolver: new ModuleGraphResolver(
                        new TopologicalSorter(),
                    ),
                ),
                tracer: new NoopTracer(),
                meter: new NoopMeter(),
                stopwatch: new Stopwatch(),
                logger: new NullLogger(),
                modulesConfig: $modulesConfig,
            );
            $bootstrapConfigResolver = new BootstrapConfigResolver(
                new BootstrapOverridesLoader(),
                new ModuleIdSetNormalizer(),
            );
            $envRepositoryBuilder = new EnvRepositoryBuilder(
                new DotenvLoader(),
            );
            $operation = new KernelArtifactOperation(
                bootstrapConfigResolver: $bootstrapConfigResolver,
                envRepositoryBuilder: $envRepositoryBuilder,
                moduleResolutionOrchestrator: $moduleResolutionOrchestrator,
                configSourceLocationBuilder: $configSourceLocationBuilder,
                artifactCompiler: ArtifactPipelineTestSupport::artifactCompiler($this),
                cacheVerifier: ArtifactPipelineTestSupport::cacheVerifier($this),
                kernelConfig: $kernelConfig,
            );

            $compileResult = $operation->compile($bootstrapInput);

            self::assertSame(
                1,
                LifecycleFixtureServiceProvider::defineInvocations(),
            );

            $moduleManifestPayload = ArtifactPipelineTestSupport::moduleManifestPayloadFromArtifact(
                $temporaryApplicationRoot,
            );

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.lifecycle-fixture',
                ],
                $moduleManifestPayload['enabled'] ?? null,
            );
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.lifecycle-fixture',
                ],
                $moduleManifestPayload['topologicalOrder'] ?? null,
            );

            $configPayload = ArtifactPipelineTestSupport::configPayloadFromArtifact($temporaryApplicationRoot);

            self::assertSame(
                'fixture-package-default',
                $configPayload['config']['lifecycle_fixture']['seed'] ?? null,
            );

            $validatedSubjects = $configPayload['validationSubjects']['validated'] ?? null;
            $unvalidatedSubjects = $configPayload['validationSubjects']['unvalidated'] ?? null;

            self::assertIsArray($validatedSubjects);
            self::assertIsArray($unvalidatedSubjects);
            self::assertContains(
                [
                    'ownership' => 'ruleset_owned',
                    'root' => 'lifecycle_fixture',
                    'validation' => 'validated',
                ],
                $validatedSubjects,
            );

            foreach ($unvalidatedSubjects as $subject) {
                self::assertIsArray($subject);
                self::assertNotSame(
                    'lifecycle_fixture',
                    $subject['root'] ?? null,
                );
            }

            $containerEnvelope = ArtifactPipelineTestSupport::artifactEnvelope(
                $temporaryApplicationRoot,
                ArtifactGeneration::CONTAINER_BASENAME,
            );
            $containerPayload = $containerEnvelope['payload'] ?? null;

            self::assertIsArray($containerPayload);

            $statefulDefinition = $containerPayload['services'][LifecycleStatefulService::class] ?? null;

            self::assertIsArray($statefulDefinition);
            self::assertSame('class', $statefulDefinition['type'] ?? null);
            self::assertSame(
                LifecycleStatefulService::class,
                $statefulDefinition['construction']['class'] ?? null,
            );
            self::assertTrue($statefulDefinition['shared'] ?? false);
            self::assertSame(
                [
                    [
                        'name' => 'test.lifecycle_fixture.seed',
                        'type' => 'parameter',
                    ],
                ],
                $statefulDefinition['arguments'] ?? null,
            );
            self::assertSame(
                'fixture-package-default',
                $containerPayload['parameters']['test.lifecycle_fixture.seed'] ?? null,
            );

            $statefulTaggedServices = $containerPayload['tags'][ReservedTags::KERNEL_STATEFUL] ?? null;
            $resetTaggedServices = $containerPayload['tags'][ReservedTags::KERNEL_RESET] ?? null;

            self::assertIsArray($statefulTaggedServices);
            self::assertContains(
                [
                    'id' => LifecycleStatefulService::class,
                    'meta' => [],
                    'priority' => 0,
                ],
                $statefulTaggedServices,
            );
            self::assertIsArray($resetTaggedServices);
            self::assertContains(
                [
                    'id' => LifecycleStatefulService::class,
                    'meta' => [],
                    'priority' => 0,
                ],
                $resetTaggedServices,
            );

            foreach (
                [
                    LoggerInterface::class,
                    TracerPortInterface::class,
                    MeterPortInterface::class,
                ] as $observabilityServiceId
            ) {
                $observabilityDefinition = $containerPayload['services'][$observabilityServiceId] ?? null;

                self::assertIsArray($observabilityDefinition);
                self::assertSame('class', $observabilityDefinition['type'] ?? null);
                self::assertSame(
                    LifecycleFailingObservability::class,
                    $observabilityDefinition['construction']['class'] ?? null,
                );
                self::assertTrue($observabilityDefinition['shared'] ?? false);
            }

            $generationId = $compileResult['generationId'] ?? null;

            self::assertIsString($generationId);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $generationId);

            $currentGeneration = ArtifactPipelineTestSupport::currentGeneration($temporaryApplicationRoot);

            self::assertSame(
                $generationId,
                $currentGeneration->generationId()->value(),
            );

            $artifactPaths = ArtifactPipelineTestSupport::currentArtifactPaths($temporaryApplicationRoot);

            self::assertSame(
                [
                    ArtifactGeneration::CONFIG_BASENAME,
                    ArtifactGeneration::CONTAINER_BASENAME,
                    ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
                    ArtifactGeneration::MODULE_MANIFEST_BASENAME,
                ],
                \array_keys($artifactPaths),
            );

            foreach ($artifactPaths as $artifactPath) {
                self::assertFileExists($artifactPath);
            }

            $verification = $operation->verify($bootstrapInput);

            self::assertSame('clean', $verification['outcome'] ?? null);
            self::assertTrue($verification['clean'] ?? false);
            self::assertFalse($verification['dirty'] ?? true);
            self::assertFalse($verification['invalid'] ?? true);
            self::assertSame(
                $generationId,
                $verification['expectedGenerationId'] ?? null,
            );
            self::assertSame(
                $generationId,
                $verification['currentGenerationId'] ?? null,
            );

            $verifiedArtifacts = $verification['artifacts'] ?? null;

            self::assertIsArray($verifiedArtifacts);
            self::assertCount(4, $verifiedArtifacts);
            self::assertSame(
                [
                    'expected_artifact_count' => 4,
                    'existing_artifact_count' => 4,
                    'missing_artifact_count' => 0,
                    'dirty_artifact_count' => 0,
                    'invalid_artifact_count' => 0,
                ],
                $verification['counts'] ?? null,
            );

            $providerCallsBeforeBoot = LifecycleFixtureServiceProvider::defineInvocations();

            self::assertSame(2, $providerCallsBeforeBoot);

            $sourceFilesToRemove = [
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle_fixture.php',
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'rules.php',
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
                $temporaryApplicationRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'modes'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle-fixture.php',
            ];

            foreach ($sourceFilesToRemove as $sourceFile) {
                self::assertFileExists($sourceFile);
                self::assertTrue(\unlink($sourceFile));
                self::assertFileDoesNotExist($sourceFile);
            }

            $container = new ArtifactRuntimeBooter()->boot(
                new ArtifactRuntimeInput(
                    applicationRoot: $temporaryApplicationRoot,
                    artifactRoot: ArtifactPipelineTestSupport::artifactRoot($temporaryApplicationRoot),
                ),
            );

            self::assertSame(
                $providerCallsBeforeBoot,
                LifecycleFixtureServiceProvider::defineInvocations(),
            );

            $service = $container->get(LifecycleStatefulService::class);

            self::assertInstanceOf(
                LifecycleStatefulService::class,
                $service,
            );
            self::assertSame(
                'fixture-package-default',
                $service->seed(),
            );
            self::assertSame(
                $service,
                $container->get(LifecycleStatefulService::class),
            );

            $configRepository = $container->get(ConfigRepositoryInterface::class);

            self::assertInstanceOf(
                ConfigRepositoryInterface::class,
                $configRepository,
            );
            self::assertSame(
                'fixture-package-default',
                $configRepository->get('lifecycle_fixture.seed'),
            );

            $runtimeModulePlan = $container->get(ModulePlan::class);

            self::assertInstanceOf(
                ModulePlan::class,
                $runtimeModulePlan,
            );
            self::assertSame(
                $moduleManifestPayload,
                $runtimeModulePlan->toArray(),
            );

            $runtimePathContext = $container->get(RuntimePathContext::class);

            self::assertInstanceOf(
                RuntimePathContext::class,
                $runtimePathContext,
            );
            self::assertSame(
                \rtrim(
                    \str_replace('\\', '/', $temporaryApplicationRoot),
                    '/',
                ),
                $runtimePathContext->applicationRoot(),
            );
            self::assertSame(
                \rtrim(
                    \str_replace(
                        '\\',
                        '/',
                        ArtifactPipelineTestSupport::artifactRoot($temporaryApplicationRoot),
                    ),
                    '/',
                ),
                $runtimePathContext->artifactRoot(),
            );

            $runtime = $container->get(KernelRuntime::class);

            self::assertInstanceOf(KernelRuntime::class, $runtime);
            self::assertSame(0, LifecycleFailingObservability::tracerFailures());
            self::assertSame(0, LifecycleFailingObservability::meterFailures());
            self::assertSame(0, LifecycleFailingObservability::loggerFailures());
            self::assertNull($service->state());

            $firstResult = $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($service): string {
                    self::assertNull($service->state());

                    $service->remember('uow-1');

                    self::assertSame('uow-1', $service->state());

                    return 'first';
                },
            );

            self::assertSame('first', $firstResult);
            self::assertNull($service->state());

            $secondResult = $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($service): string {
                    self::assertNull($service->state());

                    $service->remember('uow-2');

                    self::assertSame('uow-2', $service->state());

                    return 'second';
                },
            );

            self::assertSame('second', $secondResult);
            self::assertNull($service->state());

            $bodyFailure = null;

            try {
                $runtime->runUnitOfWork(
                    UnitOfWorkType::HTTP,
                    static function () use ($service): void {
                        self::assertNull($service->state());

                        $service->remember('uow-failure');

                        self::assertSame(
                            'uow-failure',
                            $service->state(),
                        );

                        throw new \RuntimeException('lifecycle-fixture-uow-body-failure');
                    },
                );

                self::fail('Expected UnitOfWork body failure was not propagated.');
            } catch (\RuntimeException $exception) {
                $bodyFailure = $exception;
            }

            self::assertSame(
                'lifecycle-fixture-uow-body-failure',
                $bodyFailure->getMessage(),
            );
            self::assertNull($service->state());

            $recoveryResult = $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($service): string {
                    self::assertNull($service->state());

                    $service->remember('uow-after-failure');

                    self::assertSame('uow-after-failure', $service->state());

                    return 'recovered';
                },
            );

            self::assertSame('recovered', $recoveryResult);
            self::assertNull($service->state());

            self::assertGreaterThan(
                0,
                LifecycleFailingObservability::tracerFailures(),
            );
            self::assertGreaterThan(
                0,
                LifecycleFailingObservability::meterFailures(),
            );
            self::assertGreaterThan(
                0,
                LifecycleFailingObservability::loggerFailures(),
            );
            self::assertSame(
                $providerCallsBeforeBoot,
                LifecycleFixtureServiceProvider::defineInvocations(),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($temporaryApplicationRoot);
            ArtifactPipelineTestSupport::removeTree($temporaryFixturePackageRoot);
            LifecycleFixtureServiceProvider::resetInvocations();
            LifecycleFailingObservability::resetFailures();
        }
    }

    public function testProviderGraphOnlyChangeMakesPublishedGenerationDirty(): void
    {
        LifecycleFixtureServiceProvider::resetInvocations();
        LifecycleFailingObservability::resetFailures();

        $temporaryApplicationRoot = ArtifactPipelineTestSupport::temporaryRoot('lifecycle-fixture-graph-application');
        $temporaryFixturePackageRoot = ArtifactPipelineTestSupport::temporaryRoot('lifecycle-fixture-graph-package');

        try {
            $staticFixturePackageRoot = \dirname(__DIR__)
                . \DIRECTORY_SEPARATOR
                . 'Fixtures'
                . \DIRECTORY_SEPARATOR
                . 'LifecycleFixturePackage';

            self::copyFixturePackage(
                $staticFixturePackageRoot,
                $temporaryFixturePackageRoot,
            );

            ArtifactPipelineTestSupport::writePhpReturn(
                $temporaryApplicationRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'modes'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle-fixture.php',
                [
                    'schemaVersion' => 1,
                    'name' => 'lifecycle-fixture',
                    'description' => 'Kernel lifecycle E2E fixture.',
                    'required' => [
                        'platform.lifecycle-fixture',
                    ],
                    'modules' => [],
                    'featureBundles' => [],
                    'metadata' => [],
                ],
            );

            $graphSourcePaths = [
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',

                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle_fixture.php',

                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'rules.php',

                $temporaryApplicationRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'modes'
                . \DIRECTORY_SEPARATOR
                . 'lifecycle-fixture.php',
            ];

            $baselineSourceHashes = self::sha256Files($graphSourcePaths);

            $bootstrapInput = new BootstrapInput(
                applicationRoot: $temporaryApplicationRoot,
                appTarget: AppTarget::Web,
                appEnv: 'test',
                preset: 'lifecycle-fixture',
                debug: false,
                envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
                artifactsCacheDir: 'var/cache',
            );

            $kernelPackageRoot = \dirname(__DIR__, 2);
            $corePackagesRoot = \dirname($kernelPackageRoot);
            $foundationPackageRoot = $corePackagesRoot
                . \DIRECTORY_SEPARATOR
                . 'foundation';

            $kernelConfig = require $kernelPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'config'
                . \DIRECTORY_SEPARATOR
                . 'kernel.php';

            self::assertIsArray($kernelConfig);

            $modesConfig = $kernelConfig['modes'] ?? null;
            $modulesConfig = $kernelConfig['modules'] ?? null;

            self::assertIsArray($modesConfig);
            self::assertIsArray($modulesConfig);

            $foundationComposer = self::readComposerJson(
                $foundationPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );
            $kernelComposer = self::readComposerJson(
                $kernelPackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );
            $fixtureComposer = self::readComposerJson(
                $temporaryFixturePackageRoot
                . \DIRECTORY_SEPARATOR
                . 'composer.json',
            );

            self::assertSame(
                'coretsia/core-foundation',
                $foundationComposer['name'] ?? null,
            );
            self::assertSame('library', $foundationComposer['type'] ?? null);
            self::assertSame(
                'coretsia/core-kernel',
                $kernelComposer['name'] ?? null,
            );
            self::assertSame('library', $kernelComposer['type'] ?? null);
            self::assertSame(
                'coretsia/kernel-lifecycle-fixture',
                $fixtureComposer['name'] ?? null,
            );
            self::assertSame('library', $fixtureComposer['type'] ?? null);

            $installedData = [
                [
                    'root' => [
                        'name' => 'coretsia/test-app',
                        'type' => 'project',
                        'extra' => [],
                    ],
                    'versions' => [
                        'coretsia/core-foundation' => [
                            'type' => $foundationComposer['type'],
                            'extra' => $foundationComposer['extra'],
                        ],
                        'coretsia/core-kernel' => [
                            'type' => $kernelComposer['type'],
                            'extra' => $kernelComposer['extra'],
                        ],
                        'coretsia/kernel-lifecycle-fixture' => [
                            'type' => $fixtureComposer['type'],
                            'extra' => $fixtureComposer['extra'],
                        ],
                    ],
                ],
            ];

            $metadataProvider = new ComposerInstalledMetadataProvider($installedData);
            $manifestReader = new ComposerManifestReader($metadataProvider);
            $installPathResolver = new ComposerPackageInstallPathResolver([
                'coretsia/core-foundation' => $foundationPackageRoot,
                'coretsia/core-kernel' => $kernelPackageRoot,
                'coretsia/kernel-lifecycle-fixture' => $temporaryFixturePackageRoot,
            ]);
            $modePresetLoaderFactory = new ModePresetLoaderFactory(
                packageRoot: $kernelPackageRoot,
                modesConfig: $modesConfig,
                schemaValidator: new ModePresetSchemaValidator(),
            );
            $configSourceLocationBuilder = new ConfigSourceLocationBuilder(
                installPathResolver: $installPathResolver,
                modePresetLoaderFactory: $modePresetLoaderFactory,
                presetNamespaceResolver: new PresetNamespaceResolver(),
            );
            $moduleResolutionOrchestrator = new ModuleResolutionOrchestrator(
                presetLoaderFactory: $modePresetLoaderFactory,
                presetNamespaceResolver: new PresetNamespaceResolver(),
                moduleSelectionFactory: new ModuleSelectionFactory(new ModuleIdSetNormalizer()),
                manifestReader: $manifestReader,
                modulePlanResolver: new ModulePlanResolver(
                    graphResolver: new ModuleGraphResolver(
                        new TopologicalSorter(),
                    ),
                ),
                tracer: new NoopTracer(),
                meter: new NoopMeter(),
                stopwatch: new Stopwatch(),
                logger: new NullLogger(),
                modulesConfig: $modulesConfig,
            );
            $bootstrapConfigResolver = new BootstrapConfigResolver(
                new BootstrapOverridesLoader(),
                new ModuleIdSetNormalizer(),
            );
            $envRepositoryBuilder = new EnvRepositoryBuilder(
                new DotenvLoader(),
            );
            $operation = new KernelArtifactOperation(
                bootstrapConfigResolver: $bootstrapConfigResolver,
                envRepositoryBuilder: $envRepositoryBuilder,
                moduleResolutionOrchestrator: $moduleResolutionOrchestrator,
                configSourceLocationBuilder: $configSourceLocationBuilder,
                artifactCompiler: ArtifactPipelineTestSupport::artifactCompiler($this),
                cacheVerifier: ArtifactPipelineTestSupport::cacheVerifier($this),
                kernelConfig: $kernelConfig,
            );

            $baselineCompileResult = $operation->compile($bootstrapInput);
            $baselineVerification = $operation->verify($bootstrapInput);

            self::assertSame(
                'clean',
                $baselineVerification['outcome'] ?? null,
            );
            self::assertTrue($baselineVerification['clean'] ?? false);
            self::assertFalse($baselineVerification['dirty'] ?? true);
            self::assertFalse($baselineVerification['invalid'] ?? true);
            self::assertSame(
                $baselineCompileResult['generationId'] ?? null,
                $baselineVerification['expectedGenerationId'] ?? null,
            );
            self::assertSame(
                $baselineCompileResult['generationId'] ?? null,
                $baselineVerification['currentGenerationId'] ?? null,
            );

            $baselineArtifacts = $baselineVerification['artifacts'] ?? null;

            self::assertIsArray($baselineArtifacts);
            self::assertCount(4, $baselineArtifacts);
            self::assertSame(
                [
                    'expected_artifact_count' => 4,
                    'existing_artifact_count' => 4,
                    'missing_artifact_count' => 0,
                    'dirty_artifact_count' => 0,
                    'invalid_artifact_count' => 0,
                ],
                $baselineVerification['counts'] ?? null,
            );

            $variantInstalledData = $installedData;
            $variantInstalledData[0]
            ['versions']
            ['coretsia/kernel-lifecycle-fixture']
            ['extra']
            ['coretsia']
            ['providers'] = [
                LifecycleFixtureServiceProvider::class,
                LifecycleGraphVariantProvider::class,
            ];

            $variantMetadataProvider = new ComposerInstalledMetadataProvider($variantInstalledData);
            $variantManifestReader = new ComposerManifestReader($variantMetadataProvider);
            $variantModuleResolutionOrchestrator = new ModuleResolutionOrchestrator(
                presetLoaderFactory: $modePresetLoaderFactory,
                presetNamespaceResolver: new PresetNamespaceResolver(),
                moduleSelectionFactory: new ModuleSelectionFactory(new ModuleIdSetNormalizer()),
                manifestReader: $variantManifestReader,
                modulePlanResolver: new ModulePlanResolver(
                    graphResolver: new ModuleGraphResolver(
                        new TopologicalSorter(),
                    ),
                ),
                tracer: new NoopTracer(),
                meter: new NoopMeter(),
                stopwatch: new Stopwatch(),
                logger: new NullLogger(),
                modulesConfig: $modulesConfig,
            );

            $graphProofBootstrapConfig = $bootstrapConfigResolver->resolve(
                $bootstrapInput,
                $kernelConfig,
            );
            $baselineResolutionForGraphProof = $moduleResolutionOrchestrator->resolve($graphProofBootstrapConfig);
            $variantResolutionForGraphProof = $variantModuleResolutionOrchestrator->resolve($graphProofBootstrapConfig);

            self::assertSame(
                $baselineResolutionForGraphProof->plan()->toArray(),
                $variantResolutionForGraphProof->plan()->toArray(),
            );

            $baselineSourcesForGraphProof = $configSourceLocationBuilder->build(
                $graphProofBootstrapConfig,
                $baselineResolutionForGraphProof,
            );
            $variantSourcesForGraphProof = $configSourceLocationBuilder->build(
                $graphProofBootstrapConfig,
                $variantResolutionForGraphProof,
            );

            self::assertSame(
                $baselineSourcesForGraphProof->packageDefaultSources(),
                $variantSourcesForGraphProof->packageDefaultSources(),
            );
            self::assertSame(
                $baselineSourcesForGraphProof->packageRuleSources(),
                $variantSourcesForGraphProof->packageRuleSources(),
            );
            self::assertSame(
                $baselineSourcesForGraphProof->splitRoots(),
                $variantSourcesForGraphProof->splitRoots(),
            );
            self::assertSame(
                $baselineSourcesForGraphProof->explicitRuleSources(),
                $variantSourcesForGraphProof->explicitRuleSources(),
            );
            self::assertSame(
                $baselineSourcesForGraphProof->explicitEnvOverlayMappings(),
                $variantSourcesForGraphProof->explicitEnvOverlayMappings(),
            );
            self::assertSame(
                $baselineSourcesForGraphProof->modePresetSourceCandidates(),
                $variantSourcesForGraphProof->modePresetSourceCandidates(),
            );

            $variantOperation = new KernelArtifactOperation(
                bootstrapConfigResolver: $bootstrapConfigResolver,
                envRepositoryBuilder: $envRepositoryBuilder,
                moduleResolutionOrchestrator: $variantModuleResolutionOrchestrator,
                configSourceLocationBuilder: $configSourceLocationBuilder,
                artifactCompiler: ArtifactPipelineTestSupport::artifactCompiler($this),
                cacheVerifier: ArtifactPipelineTestSupport::cacheVerifier($this),
                kernelConfig: $kernelConfig,
            );

            self::assertSame(
                $baselineSourceHashes,
                self::sha256Files($graphSourcePaths),
            );

            $variantVerification = $variantOperation->verify($bootstrapInput);

            self::assertSame('dirty', $variantVerification['outcome'] ?? null);
            self::assertFalse($variantVerification['clean'] ?? true);
            self::assertTrue($variantVerification['dirty'] ?? false);
            self::assertFalse($variantVerification['invalid'] ?? true);
            self::assertNotSame(
                $variantVerification['expectedGenerationId'] ?? null,
                $variantVerification['currentGenerationId'] ?? null,
            );

            $variantArtifacts = $variantVerification['artifacts'] ?? null;

            self::assertIsArray($variantArtifacts);
            self::assertCount(4, $variantArtifacts);
            self::assertSame(
                [
                    'expected_artifact_count' => 4,
                    'existing_artifact_count' => 4,
                    'missing_artifact_count' => 0,
                    'dirty_artifact_count' => 4,
                    'invalid_artifact_count' => 0,
                ],
                $variantVerification['counts'] ?? null,
            );

            foreach ($variantArtifacts as $artifact) {
                self::assertIsArray($artifact);
                self::assertSame(
                    'dirty',
                    $artifact['status'] ?? null,
                );
                self::assertSame(
                    'fingerprint_mismatch',
                    $artifact['reason'] ?? null,
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($temporaryApplicationRoot);
            ArtifactPipelineTestSupport::removeTree($temporaryFixturePackageRoot);
            LifecycleFixtureServiceProvider::resetInvocations();
            LifecycleFailingObservability::resetFailures();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function readComposerJson(string $path): array
    {
        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);

        $decoded = \json_decode(
            $bytes,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertFalse(\array_is_list($decoded));

        return $decoded;
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string,string>
     */
    private static function sha256Files(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            $bytes = \file_get_contents($path);

            self::assertIsString($bytes);

            $hashes[$path] = \hash('sha256', $bytes);
        }

        return $hashes;
    }

    private static function copyFixturePackage(
        string $sourceRoot,
        string $targetRoot,
    ): void {
        foreach (
            [
                'composer.json',
                'config/lifecycle_fixture.php',
                'config/rules.php',
                'LifecycleFixtureServiceProvider.php',
                'LifecycleStatefulService.php',
                'LifecycleFailingObservability.php',
                'LifecycleGraphVariantProvider.php',
            ] as $relativePath
        ) {
            $filesystemRelativePath = \str_replace(
                '/',
                \DIRECTORY_SEPARATOR,
                $relativePath,
            );
            $sourcePath = $sourceRoot
                . \DIRECTORY_SEPARATOR
                . $filesystemRelativePath;
            $targetPath = $targetRoot
                . \DIRECTORY_SEPARATOR
                . $filesystemRelativePath;

            self::assertFileExists($sourcePath);

            $targetDirectory = \dirname($targetPath);

            if (!\is_dir($targetDirectory)) {
                self::assertTrue(
                    \mkdir(
                        $targetDirectory,
                        0777,
                        true,
                    ),
                );
            }

            self::assertTrue(
                \copy(
                    $sourcePath,
                    $targetPath,
                ),
            );
            self::assertFileExists($targetPath);
        }
    }
}
