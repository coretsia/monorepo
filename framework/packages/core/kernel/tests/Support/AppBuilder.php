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

namespace Coretsia\Kernel\Tests\Support;

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Integration\ArtifactPipelineTestSupport;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class AppBuilder
{
    private const string PRESET_MICRO = 'micro';
    private const string PRESET_EXPRESS = 'express';
    private const string PRESET_WORKER_ONLY = 'worker-only';

    private const string MODULE_CORE_FOUNDATION = 'core.foundation';
    private const string MODULE_CORE_KERNEL = 'core.kernel';
    private const string MODULE_PLATFORM_CLI = 'platform.cli';
    private const string MODULE_PLATFORM_HTTP = 'platform.http';

    private function __construct()
    {
    }

    public static function bootMicro(TestCase $testCase): AppBuilderBootResult
    {
        $skeletonRoot = self::temporarySkeletonRoot('boot-micro');

        self::assertCanonicalSkeletonModeOverrideAbsent($skeletonRoot, self::PRESET_MICRO);
        self::assertFrameworkDefaultPresetFileExists(self::PRESET_MICRO);

        $bootstrapConfig = self::bootstrapConfig(
            skeletonRoot: $skeletonRoot,
            preset: self::PRESET_MICRO,
        );

        $modulePlan = self::modulePlanResolver(
            manifest: self::microFixtureManifest(),
        )->resolve($bootstrapConfig);

        self::compileRuntimeArtifacts(
            testCase: $testCase,
            skeletonRoot: $skeletonRoot,
            bootstrapConfig: $bootstrapConfig,
            modulePlan: $modulePlan,
            presetName: self::PRESET_MICRO,
        );

        self::assertRuntimeArtifactsExist($skeletonRoot);

        $artifactPaths = ArtifactPipelineTestSupport::artifactPaths($skeletonRoot);

        $container = new ArtifactRuntimeBooter()->boot(
            configArtifactPath: $artifactPaths['config.php'],
            containerArtifactPath: $artifactPaths['container.php'],
        );

        return new AppBuilderBootResult(
            skeletonRoot: $skeletonRoot,
            modulePlan: $modulePlan,
            container: $container,
            artifactPaths: $artifactPaths,
        );
    }

    public static function bootExpressExpectingRequiredMissing(TestCase $_testCase): AppBuilderRequiredMissingResult
    {
        $skeletonRoot = self::temporarySkeletonRoot('boot-express');

        self::assertCanonicalSkeletonModeOverrideAbsent($skeletonRoot, self::PRESET_EXPRESS);
        self::assertFrameworkDefaultPresetFileExists(self::PRESET_EXPRESS);

        $bootstrapConfig = self::bootstrapConfig(
            skeletonRoot: $skeletonRoot,
            preset: self::PRESET_EXPRESS,
        );

        try {
            self::modulePlanResolver(
                manifest: self::microFixtureManifest(),
            )->resolve($bootstrapConfig);
        } catch (ModuleRequiredMissingException $exception) {
            TestCase::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_REQUIRED_MISSING,
                $exception->errorCode(),
            );

            TestCase::assertSame(
                self::MODULE_PLATFORM_HTTP,
                $exception->context()['missingModuleId'] ?? null,
            );

            return new AppBuilderRequiredMissingResult(
                skeletonRoot: $skeletonRoot,
                exception: $exception,
                artifactPaths: ArtifactPipelineTestSupport::artifactPaths($skeletonRoot),
            );
        }

        throw new \LogicException('app-builder-express-required-missing-not-raised');
    }

    public static function resolveSkeletonOnlyPreset(
        TestCase $_testCase,
        string $presetName = self::PRESET_WORKER_ONLY,
    ): AppBuilderModulePlanResult {
        $skeletonRoot = self::temporarySkeletonRoot('skeleton-only-preset');

        if (\is_file(self::frameworkModeFile($presetName))) {
            throw new \LogicException('app-builder-framework-custom-preset-unexpectedly-present');
        }

        ArtifactPipelineTestSupport::writePhpReturn(
            $skeletonRoot . '/config/modes/' . $presetName . '.php',
            [
                'schemaVersion' => 1,
                'name' => $presetName,
                'description' => 'Worker-only test mode.',
                'required' => [
                    self::MODULE_CORE_FOUNDATION,
                    self::MODULE_CORE_KERNEL,
                    self::MODULE_PLATFORM_CLI,
                ],
                'optional' => [],
                'disabled' => [],
                'featureBundles' => [
                    'observability' => 'minimal',
                ],
                'metadata' => [],
            ],
        );

        $bootstrapConfig = self::bootstrapConfig(
            skeletonRoot: $skeletonRoot,
            preset: $presetName,
        );

        $modulePlan = self::modulePlanResolver(
            manifest: self::microFixtureManifest(),
        )->resolve($bootstrapConfig);

        return new AppBuilderModulePlanResult(
            skeletonRoot: $skeletonRoot,
            modulePlan: $modulePlan,
        );
    }

    public static function removeTree(string $path): void
    {
        ArtifactPipelineTestSupport::removeTree($path);
    }

    /**
     * @return array<string,string>
     */
    public static function artifactPaths(string $skeletonRoot): array
    {
        return ArtifactPipelineTestSupport::artifactPaths($skeletonRoot);
    }

    private static function temporarySkeletonRoot(string $name): string
    {
        return ArtifactPipelineTestSupport::temporaryRoot($name);
    }

    private static function bootstrapConfig(
        string $skeletonRoot,
        string $preset,
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: $preset,
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    private static function modulePlanResolver(ModuleManifest $manifest): ModulePlanResolver
    {
        return new ModulePlanResolver(
            presetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: self::packageRoot(),
                modesConfig: self::modesConfig(),
                schemaValidator: new ModePresetSchemaValidator(),
            ),
            manifestReader: self::manifestReader($manifest),
            graphResolver: new ModuleGraphResolver(new TopologicalSorter()),
            meter: self::meter(),
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: self::modulesConfig(),
        );
    }

    private static function compileRuntimeArtifacts(
        TestCase $testCase,
        string $skeletonRoot,
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        string $presetName,
    ): void {
        ArtifactPipelineTestSupport::writeRootConfig(
            skeletonRoot: $skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        ArtifactPipelineTestSupport::artifactCompiler($testCase)->compile(
            bootstrapConfig: $bootstrapConfig,
            modulePlan: $modulePlan,
            env: ArtifactPipelineTestSupport::envRepository(),
            kernelConfig: self::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: self::frameworkModePresetSourceCandidates($presetName),
            containerDescriptors: [],
        );
    }

    private static function assertRuntimeArtifactsExist(string $skeletonRoot): void
    {
        foreach (
            [
                'module-manifest.php',
                'config.php',
                'container.php',
            ] as $basename
        ) {
            $path = ArtifactPipelineTestSupport::artifactPath($skeletonRoot, $basename);

            if (!\is_file($path)) {
                throw new \LogicException('app-builder-runtime-artifact-missing');
            }
        }
    }

    private static function assertCanonicalSkeletonModeOverrideAbsent(
        string $skeletonRoot,
        string $presetName,
    ): void {
        if (\is_file($skeletonRoot . '/config/modes/' . $presetName . '.php')) {
            throw new \LogicException('app-builder-canonical-skeleton-mode-override-present');
        }
    }

    private static function assertFrameworkDefaultPresetFileExists(string $presetName): void
    {
        if (!\is_file(self::frameworkModeFile($presetName))) {
            throw new \LogicException('app-builder-framework-default-preset-missing');
        }
    }

    private static function frameworkModeFile(string $presetName): string
    {
        return self::packageRoot() . '/resources/modes/' . $presetName . '.php';
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $kernelConfig = ArtifactPipelineTestSupport::kernelConfig();

        $kernelConfig['modes'] = self::modesConfig();
        $kernelConfig['modules'] = self::modulesConfig();

        return $kernelConfig;
    }

    /**
     * @return array<string,mixed>
     */
    private static function modesConfig(): array
    {
        return [
            'schema_version' => 1,
            'defaults_path' => 'resources/modes',
            'overrides_path' => 'config/modes',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function modulesConfig(): array
    {
        return [
            'discovery' => [
                'source' => 'composer',
                'allowed_sources' => [
                    'composer',
                ],
            ],
        ];
    }

    /**
     * @return list<array{
     *     path: string,
     *     filesystemPath: string,
     *     sourceId: string,
     *     precedence: int
     * }>
     */
    private static function frameworkModePresetSourceCandidates(string $presetName): array
    {
        return [
            [
                'path' => 'kernel.modes.' . $presetName,
                'filesystemPath' => self::frameworkModeFile($presetName),
                'sourceId' => 'core/kernel:resources/modes/' . $presetName . '.php',
                'precedence' => 10,
            ],
        ];
    }

    private static function microFixtureManifest(): ModuleManifest
    {
        return self::manifest(
            [
                self::MODULE_CORE_FOUNDATION,
                self::MODULE_CORE_KERNEL,
                self::MODULE_PLATFORM_CLI,
            ],
        );
    }

    /**
     * @param list<string> $moduleIds
     */
    private static function manifest(array $moduleIds): ModuleManifest
    {
        $descriptors = [];

        foreach (self::sortModuleIds($moduleIds) as $moduleId) {
            $descriptors[] = self::descriptor($moduleId);
        }

        return new ModuleManifest($descriptors);
    }

    private static function descriptor(string $moduleId): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: self::composerName($moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'requires' => self::requires($moduleId),
                'conflicts' => [],
            ],
        );
    }

    private static function composerName(string $moduleId): string
    {
        return match ($moduleId) {
            self::MODULE_CORE_FOUNDATION => 'coretsia/core-foundation',
            self::MODULE_CORE_KERNEL => 'coretsia/core-kernel',
            self::MODULE_PLATFORM_CLI => 'coretsia/platform-cli',
            self::MODULE_PLATFORM_HTTP => 'coretsia/platform-http',
            default => throw new \LogicException('app-builder-fixture-module-unknown'),
        };
    }

    /**
     * @return list<string>
     */
    private static function requires(string $moduleId): array
    {
        return match ($moduleId) {
            self::MODULE_CORE_FOUNDATION => [],
            self::MODULE_CORE_KERNEL => [
                self::MODULE_CORE_FOUNDATION,
            ],
            self::MODULE_PLATFORM_CLI,
            self::MODULE_PLATFORM_HTTP => [
                self::MODULE_CORE_KERNEL,
            ],
            default => throw new \LogicException('app-builder-fixture-module-unknown'),
        };
    }

    private static function manifestReader(ModuleManifest $manifest): ManifestReaderInterface
    {
        return new class($manifest) implements ManifestReaderInterface {
            public function __construct(
                private readonly ModuleManifest $manifest,
            ) {
            }

            public function read(): ModuleManifest
            {
                return $this->manifest;
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

    /**
     * @param list<string> $moduleIds
     *
     * @return list<string>
     */
    private static function sortModuleIds(array $moduleIds): array
    {
        $moduleIds = \array_values(\array_unique($moduleIds));

        \usort(
            $moduleIds,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $moduleIds;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}

final readonly class AppBuilderBootResult
{
    /**
     * @param array<string,string> $artifactPaths
     */
    public function __construct(
        private string $skeletonRoot,
        private ModulePlan $modulePlan,
        private ContainerInterface $container,
        private array $artifactPaths,
    ) {
    }

    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    public function modulePlan(): ModulePlan
    {
        return $this->modulePlan;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @return array<string,string>
     */
    public function artifactPaths(): array
    {
        return $this->artifactPaths;
    }
}

final readonly class AppBuilderRequiredMissingResult
{
    /**
     * @param array<string,string> $artifactPaths
     */
    public function __construct(
        private string $skeletonRoot,
        private ModuleRequiredMissingException $exception,
        private array $artifactPaths,
    ) {
    }

    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    public function exception(): ModuleRequiredMissingException
    {
        return $this->exception;
    }

    /**
     * @return array<string,string>
     */
    public function artifactPaths(): array
    {
        return $this->artifactPaths;
    }
}

final readonly class AppBuilderModulePlanResult
{
    public function __construct(
        private string $skeletonRoot,
        private ModulePlan $modulePlan,
    ) {
    }

    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    public function modulePlan(): ModulePlan
    {
        return $this->modulePlan;
    }
}
