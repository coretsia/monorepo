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

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\Preset\PresetNamespaceResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class KernelArtifactOperationUsesCanonicalCompileInputsTest extends TestCase
{
    private string $temporaryRoot;
    private string $packageRoot;
    private string $applicationRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = ArtifactPipelineTestSupport::temporaryRoot('kernel-artifact-operation-canonical-inputs');
        $this->packageRoot = $this->temporaryRoot . '/package';
        $this->applicationRoot = $this->temporaryRoot . '/application';

        \mkdir($this->packageRoot, 0777, true);
        \mkdir($this->applicationRoot, 0777, true);

        $this->writePackageConfig('v1');
        $this->writeModePreset($this->applicationRoot . '/config/modes/test.php');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->temporaryRoot);
    }

    public function testCompileUsesCanonicalInputsAndPackageDefaultReachesArtifact(): void
    {
        $fixture = $this->operationFixture([
            'coretsia/core-kernel' => $this->packageRoot,
        ]);

        $fixture['operation']->compile($this->bootstrapInput());

        self::assertSame(1, $fixture['manifestReader']->readCount);

        $payload = ArtifactPipelineTestSupport::configPayloadFromArtifact($this->applicationRoot);

        self::assertSame(
            'v1',
            $payload['config']['kernel']['source_probe'] ?? null,
        );
    }

    public function testPackageSourceChangeMakesVerifyDirtyAndStartsNewResolution(): void
    {
        $fixture = $this->operationFixture([
            'coretsia/core-kernel' => $this->packageRoot,
        ]);
        $input = $this->bootstrapInput();

        $fixture['operation']->compile($input);

        self::assertSame(1, $fixture['manifestReader']->readCount);

        ArtifactPipelineTestSupport::writePhpReturn(
            $this->packageRoot . '/config/kernel.php',
            [
                'source_probe' => 'v2',
            ],
        );

        $result = $fixture['operation']->verify($input);

        self::assertSame(2, $fixture['manifestReader']->readCount);
        self::assertSame('dirty', $result['outcome']);
        self::assertFalse($result['clean']);
        self::assertTrue($result['dirty']);
        self::assertFalse($result['invalid']);
        self::assertNotSame(
            $result['expectedGenerationId'],
            $result['currentGenerationId'],
        );
    }

    public function testSelectedCustomPresetContentChangeMakesGenerationDirty(): void
    {
        $fixture = $this->operationFixture([
            'coretsia/core-kernel' => $this->packageRoot,
        ]);
        $input = $this->bootstrapInput();

        $fixture['operation']->compile($input);

        self::assertSame(1, $fixture['manifestReader']->readCount);
        self::assertFileExists($this->applicationRoot . '/config/modes/test.php');

        $this->writeModePreset(
            $this->applicationRoot . '/config/modes/test.php',
            'Selected custom source changed.',
        );

        $result = $fixture['operation']->verify($input);

        self::assertSame(2, $fixture['manifestReader']->readCount);
        self::assertSame('dirty', $result['outcome']);
        self::assertTrue($result['dirty']);
        self::assertFalse($result['invalid']);
        self::assertNotSame(
            $result['expectedGenerationId'],
            $result['currentGenerationId'],
        );
    }

    public function testMissingInstallRootFailsBeforeAnyGenerationIsPublished(): void
    {
        $fixture = $this->operationFixture([]);
        $artifactRoot = ArtifactPipelineTestSupport::artifactRoot($this->applicationRoot);

        try {
            $fixture['operation']->compile($this->bootstrapInput());
            self::fail('Expected config source location failure.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(
                ConfigInvalidException::REASON_SOURCE_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONFIG_INVALID: config-source-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $this->packageRoot,
                $exception->getMessage(),
            );
        }

        self::assertSame(1, $fixture['manifestReader']->readCount);
        self::assertDirectoryDoesNotExist($artifactRoot);
    }

    public function testMissingSelectedCustomPresetFailsBeforeConfigSourceDiscovery(): void
    {
        $fixture = $this->operationFixture([]);

        \unlink($this->applicationRoot . '/config/modes/test.php');

        $artifactRoot = ArtifactPipelineTestSupport::artifactRoot($this->applicationRoot);

        try {
            $fixture['operation']->compile($this->bootstrapInput());

            self::fail('Missing custom preset must fail before config-source discovery.');
        } catch (ModePresetNotFoundException $exception) {
            self::assertSame(['preset' => 'test'], $exception->context());
        }

        self::assertSame(0, $fixture['manifestReader']->readCount);
        self::assertDirectoryDoesNotExist($artifactRoot);
    }

    /**
     * @param array<string,string> $installRoots
     *
     * @return array{
     *     operation: KernelArtifactOperation,
     *     manifestReader: KernelArtifactOperationManifestReaderCounter
     * }
     */
    private function operationFixture(array $installRoots): array
    {
        $composerReader = new ComposerManifestReader(
            new ComposerInstalledMetadataProvider([
                [
                    'root' => [
                        'name' => 'coretsia/test-app',
                        'type' => 'project',
                        'extra' => [],
                    ],
                    'versions' => [
                        'coretsia/core-kernel' => [
                            'type' => 'library',
                            'install_path' => '/poison/install/path/must-not-be-used',
                            'extra' => [
                                'coretsia' => [
                                    'moduleId' => 'core.kernel',
                                    'kind' => 'runtime',
                                    'defaultsConfigPath' => 'config/kernel.php',
                                    'providers' => [],
                                    'requires' => [],
                                    'conflicts' => [],
                                ],
                            ],
                            'dev_requirement' => false,
                        ],
                    ],
                ],
            ]),
        );
        $manifestReader = new KernelArtifactOperationManifestReaderCounter($composerReader);
        $modePresetLoaderFactory = new ModePresetLoaderFactory(
            packageRoot: $this->packageRoot,
            modesConfig: $this->kernelConfig()['modes'],
            schemaValidator: new ModePresetSchemaValidator(),
        );
        $moduleResolutionOrchestrator = new ModuleResolutionOrchestrator(
            presetLoaderFactory: $modePresetLoaderFactory,
            presetNamespaceResolver: new PresetNamespaceResolver(),
            moduleSelectionFactory: new ModuleSelectionFactory(new ModuleIdSetNormalizer()),
            manifestReader: $manifestReader,
            modulePlanResolver: new ModulePlanResolver(graphResolver: new ModuleGraphResolver(new TopologicalSorter())),
            tracer: new NoopTracer(),
            meter: self::meter(),
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: $this->kernelConfig()['modules'],
        );
        $sourceBuilder = new ConfigSourceLocationBuilder(
            installPathResolver: new ComposerPackageInstallPathResolver($installRoots),
            modePresetLoaderFactory: $modePresetLoaderFactory,
            presetNamespaceResolver: new PresetNamespaceResolver(),
        );

        return [
            'operation' => new KernelArtifactOperation(
                bootstrapConfigResolver: new BootstrapConfigResolver(
                    new BootstrapOverridesLoader(),
                    new ModuleIdSetNormalizer(),
                ),
                envRepositoryBuilder: new EnvRepositoryBuilder(new DotenvLoader()),
                moduleResolutionOrchestrator: $moduleResolutionOrchestrator,
                configSourceLocationBuilder: $sourceBuilder,
                artifactCompiler: ArtifactPipelineTestSupport::artifactCompiler($this),
                cacheVerifier: ArtifactPipelineTestSupport::cacheVerifier($this),
                kernelConfig: $this->kernelConfig(),
            ),
            'manifestReader' => $manifestReader,
        ];
    }

    private function bootstrapInput(): BootstrapInput
    {
        return new BootstrapInput(
            applicationRoot: $this->applicationRoot,
            appTarget: AppTarget::Web,
            appEnv: 'prod',
            preset: 'test',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            artifactsCacheDir: 'var/cache',
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function kernelConfig(): array
    {
        return ArtifactPipelineTestSupport::kernelConfig() + [
                'modes' => [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                'modules' => [
                    'discovery' => [
                        'source' => 'composer',
                        'allowed_sources' => [
                            'composer',
                        ],
                    ],
                ],
            ];
    }

    private function writePackageConfig(string $value): void
    {
        ArtifactPipelineTestSupport::writePhpReturn(
            $this->packageRoot . '/config/kernel.php',
            [
                'source_probe' => $value,
            ],
        );
        ArtifactPipelineTestSupport::writePhpReturn(
            $this->packageRoot . '/config/rules.php',
            [
                'schemaVersion' => 1,
                'configRoot' => 'kernel',
                'additionalKeys' => false,
                'keys' => [
                    'source_probe' => [
                        'required' => true,
                        'type' => 'non-empty-string',
                    ],
                ],
            ],
        );
    }

    private function writeModePreset(
        string $path,
        string $description = 'Canonical config-source operation fixture.',
    ): void {
        ArtifactPipelineTestSupport::writePhpReturn(
            $path,
            [
                'schemaVersion' => 1,
                'name' => 'test',
                'description' => $description,
                'required' => [
                    'core.kernel',
                ],
                'modules' => [],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );
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
}

final class KernelArtifactOperationManifestReaderCounter implements ManifestReaderInterface
{
    public int $readCount = 0;

    public function __construct(
        private readonly ComposerManifestReader $reader,
    ) {
    }

    public function read(): ModuleManifest
    {
        ++$this->readCount;

        return $this->reader->read();
    }
}
