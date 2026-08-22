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

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModuleResolution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigSourceLocationBuilderBuildsCanonicalSourceSetTest extends TestCase
{
    private string $temporaryDirectory;
    private string $skeletonRoot;
    private string $modePackageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = ArtifactPipelineTestSupport::temporaryRoot('config-source-location-builder');
        $this->skeletonRoot = $this->temporaryDirectory . '/skeleton';
        $this->modePackageRoot = $this->temporaryDirectory . '/mode-package';

        \mkdir($this->skeletonRoot, 0777, true);
        \mkdir($this->modePackageRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testBuildsCanonicalPackageDefaultCandidates(): void
    {
        [$builder, $resolution, $roots] = $this->canonicalFixture();

        $set = $builder->build($this->bootstrapConfig(), $resolution);

        self::assertSame(
            [
                self::defaultCandidate(
                    root: 'foundation',
                    packageId: 'core/foundation',
                    moduleId: 'core.foundation',
                    path: 'config/foundation.php',
                    installRoot: $roots['coretsia/core-foundation'],
                ),
                self::defaultCandidate(
                    root: 'kernel',
                    packageId: 'core/kernel',
                    moduleId: 'core.kernel',
                    path: 'config/kernel.php',
                    installRoot: $roots['coretsia/core-kernel'],
                ),
                self::defaultCandidate(
                    root: 'worker',
                    packageId: 'platform/worker',
                    moduleId: 'platform.worker',
                    path: 'config/worker.php',
                    installRoot: $roots['coretsia/platform-worker'],
                ),
            ],
            $set->packageDefaultSources(),
        );
    }

    public function testBuildsExactPackageRulesCandidates(): void
    {
        [$builder, $resolution, $roots] = $this->canonicalFixture();

        $set = $builder->build($this->bootstrapConfig(), $resolution);

        self::assertSame(
            [
                self::rulesCandidate(
                    'foundation',
                    'core/foundation',
                    'core.foundation',
                    $roots['coretsia/core-foundation']
                ),
                self::rulesCandidate('kernel', 'core/kernel', 'core.kernel', $roots['coretsia/core-kernel']),
                self::rulesCandidate(
                    'worker',
                    'platform/worker',
                    'platform.worker',
                    $roots['coretsia/platform-worker']
                ),
            ],
            $set->packageRuleSources(),
        );
    }

    public function testComposerNameIsNotPackageId(): void
    {
        [$builder, $resolution] = $this->canonicalFixture();

        $kernel = $builder->build($this->bootstrapConfig(), $resolution)->packageDefaultSources()[1];

        self::assertSame('core/kernel', $kernel['packageId']);
        self::assertNotSame('coretsia/core-kernel', $kernel['packageId']);
    }

    public function testSplitRootsAreUniqueAndByteOrderSorted(): void
    {
        $roots = [
            'coretsia/core-foundation' => $this->installRoot('foundation'),
            'coretsia/core-kernel' => $this->installRoot('kernel'),
            'coretsia/platform-worker' => $this->installRoot('worker'),
        ];
        $resolution = self::resolution(
            descriptors: [
                self::descriptor(
                    'platform.worker',
                    'coretsia/platform-worker',
                    'config/worker.php',
                ),
                self::descriptor(
                    'core.kernel',
                    'coretsia/core-kernel',
                    'config/kernel.php',
                ),
                self::descriptor(
                    'core.foundation',
                    'coretsia/core-foundation',
                    'config/foundation.php',
                ),
            ],
            enabledComposerNames: [
                'platform.worker' => 'coretsia/platform-worker',
                'core.kernel' => 'coretsia/core-kernel',
                'core.foundation' => 'coretsia/core-foundation',
            ],
            topologicalOrder: [
                'platform.worker',
                'core.kernel',
                'core.foundation',
            ],
        );

        self::assertSame(
            [
                'foundation',
                'kernel',
                'worker',
            ],
            $this->builder($roots)
                ->build($this->bootstrapConfig(), $resolution)
                ->splitRoots(),
        );
    }

    public function testModuleWithoutDefaultsConfigPathDoesNotResolveInstallRoot(): void
    {
        $descriptor = self::descriptor(
            moduleId: 'platform.cli',
            composerName: 'coretsia/platform-cli',
            defaultsConfigPath: null,
        );
        $resolution = self::resolution(
            descriptors: [$descriptor],
            enabledComposerNames: ['platform.cli' => 'coretsia/platform-cli'],
        );

        $set = $this->builder([])->build($this->bootstrapConfig(), $resolution);

        self::assertSame([], $set->packageDefaultSources());
        self::assertSame([], $set->packageRuleSources());
        self::assertSame([], $set->splitRoots());
    }

    public function testDisabledModuleIsIgnoredWithoutInstallRootLookup(): void
    {
        $enabled = self::descriptor('core.kernel', 'coretsia/core-kernel', 'config/kernel.php');
        $disabled = self::descriptor('platform.worker', 'coretsia/platform-worker', 'config/worker.php');
        $kernelRoot = $this->installRoot('kernel');
        $resolution = self::resolution(
            descriptors: [$disabled, $enabled],
            enabledComposerNames: ['core.kernel' => 'coretsia/core-kernel'],
            disabledModuleIds: ['platform.worker'],
        );

        $set = $this->builder([
            'coretsia/core-kernel' => $kernelRoot,
        ])->build($this->bootstrapConfig(), $resolution);

        self::assertSame(['kernel'], $set->splitRoots());
        self::assertCount(1, $set->packageDefaultSources());
        self::assertSame('core.kernel', $set->packageDefaultSources()[0]['moduleId']);
    }

    public function testDuplicatePackageRootsFail(): void
    {
        $one = self::descriptor('core.one', 'vendor/one', 'config/kernel.php');
        $two = self::descriptor('core.two', 'vendor/two', 'config/kernel.php');
        $resolution = self::resolution(
            descriptors: [$one, $two],
            enabledComposerNames: [
                'core.one' => 'vendor/one',
                'core.two' => 'vendor/two',
            ],
        );

        $this->assertSafeSourceInvalid(function () use ($resolution): void {
            $this->builder([
                'vendor/one' => $this->installRoot('one'),
                'vendor/two' => $this->installRoot('two'),
            ])->build($this->bootstrapConfig(), $resolution);
        });
    }

    public function testManifestPlanComposerMismatchFails(): void
    {
        $descriptor = self::descriptor(
            'core.kernel',
            'vendor/a',
            'config/kernel.php',
        );
        $resolution = self::resolution(
            descriptors: [$descriptor],
            enabledComposerNames: [
                'core.kernel' => 'vendor/b',
            ],
        );
        $installRoot = $this->installRoot('manifest-composer-mismatch');

        $this->assertSafeSourceInvalid(
            function () use ($resolution, $installRoot): void {
                $this->builder([
                    'vendor/a' => $installRoot,
                ])->build(
                    $this->bootstrapConfig(),
                    $resolution,
                );
            },
        );
    }

    #[DataProvider('invalidDefaultsConfigPaths')]
    public function testSyntheticInvalidDefaultsPathFails(string $path): void
    {
        $descriptor = self::descriptor(
            'core.kernel',
            'coretsia/core-kernel',
            $path,
        );
        $resolution = self::resolution(
            descriptors: [$descriptor],
            enabledComposerNames: [
                'core.kernel' => 'coretsia/core-kernel',
            ],
        );
        $installRoot = $this->installRoot('invalid-defaults-path');

        $this->assertSafeSourceInvalid(
            function () use ($resolution, $installRoot): void {
                $this->builder([
                    'coretsia/core-kernel' => $installRoot,
                ])->build(
                    $this->bootstrapConfig(),
                    $resolution,
                );
            },
        );
    }

    public function testDoesNotScanUndeclaredPackageConfigFiles(): void
    {
        $installRoot = $this->installRoot('kernel');
        \mkdir($installRoot . '/config', 0777, true);
        \file_put_contents($installRoot . '/config/extra.php', '<?php return [];');
        $resolution = self::resolution(
            descriptors: [
                self::descriptor('core.kernel', 'coretsia/core-kernel', 'config/kernel.php'),
            ],
            enabledComposerNames: ['core.kernel' => 'coretsia/core-kernel'],
        );

        $set = $this->builder([
            'coretsia/core-kernel' => $installRoot,
        ])->build($this->bootstrapConfig(), $resolution);

        self::assertSame(['kernel'], $set->splitRoots());
        self::assertCount(1, $set->packageDefaultSources());
        self::assertSame('config/kernel.php', $set->packageDefaultSources()[0]['path']);
    }

    public function testCurrentExplicitMechanismsAreEmpty(): void
    {
        [$builder, $resolution] = $this->canonicalFixture();

        $set = $builder->build($this->bootstrapConfig(), $resolution);

        self::assertSame([], $set->explicitRuleSources());
        self::assertSame([], $set->explicitEnvOverlayMappings());
    }

    public function testModeCandidatesContainMissingSkeletonOverrideAndFrameworkDefault(): void
    {
        [$builder, $resolution] = $this->canonicalFixture();

        $candidates = $builder->build($this->bootstrapConfig(), $resolution)->modePresetSourceCandidates();

        self::assertCount(2, $candidates);
        self::assertSame('skeleton:config/modes/micro.php', $candidates[0]['sourceId']);
        self::assertSame(20, $candidates[0]['precedence']);
        self::assertFileDoesNotExist($candidates[0]['filesystemPath']);
        self::assertSame('core/kernel:resources/modes/micro.php', $candidates[1]['sourceId']);
        self::assertSame(10, $candidates[1]['precedence']);
    }

    public function testInvalidInstallRootFailureDoesNotLeakTemporaryPath(): void
    {
        $missingRoot = $this->temporaryDirectory . '/private/missing-install-root';
        $resolution = self::resolution(
            descriptors: [
                self::descriptor('core.kernel', 'coretsia/core-kernel', 'config/kernel.php'),
            ],
            enabledComposerNames: ['core.kernel' => 'coretsia/core-kernel'],
        );

        try {
            $this->builder([
                'coretsia/core-kernel' => $missingRoot,
            ])->build($this->bootstrapConfig(), $resolution);

            self::fail('Expected invalid install root to fail.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::REASON_SOURCE_INVALID, $exception->reason());
            self::assertSame(
                'CORETSIA_CONFIG_INVALID: config-source-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($this->temporaryDirectory, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidDefaultsConfigPaths(): iterable
    {
        yield 'outside config' => ['resources/kernel.php'];
        yield 'nested config' => ['config/foo/kernel.php'];
        yield 'aggregate roots' => ['config/roots.php'];
        yield 'hyphenated root' => ['config/problem-details.php'];
    }

    /**
     * @return array{0: ConfigSourceLocationBuilder, 1: ModuleResolution, 2: array<string,string>}
     */
    private function canonicalFixture(): array
    {
        $roots = [
            'coretsia/core-foundation' => $this->installRoot('foundation'),
            'coretsia/core-kernel' => $this->installRoot('kernel'),
            'coretsia/platform-worker' => $this->installRoot('worker'),
        ];
        $resolution = self::resolution(
            descriptors: [
                self::descriptor('platform.worker', 'coretsia/platform-worker', 'config/worker.php'),
                self::descriptor('core.kernel', 'coretsia/core-kernel', 'config/kernel.php'),
                self::descriptor('core.foundation', 'coretsia/core-foundation', 'config/foundation.php'),
            ],
            enabledComposerNames: [
                'core.foundation' => 'coretsia/core-foundation',
                'core.kernel' => 'coretsia/core-kernel',
                'platform.worker' => 'coretsia/platform-worker',
            ],
            topologicalOrder: [
                'core.foundation',
                'core.kernel',
                'platform.worker',
            ],
        );

        return [$this->builder($roots), $resolution, $roots];
    }

    /**
     * @param array<string,string> $installRoots
     */
    private function builder(array $installRoots): ConfigSourceLocationBuilder
    {
        return new ConfigSourceLocationBuilder(
            installPathResolver: new ComposerPackageInstallPathResolver($installRoots),
            modePresetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: $this->modePackageRoot,
                modesConfig: [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                schemaValidator: new ModePresetSchemaValidator(),
            ),
        );
    }

    private function bootstrapConfig(): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            artifactsCacheDir: 'var/cache',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $this->skeletonRoot,
        );
    }

    private function installRoot(string $name): string
    {
        $root = $this->temporaryDirectory . '/packages/' . $name;

        if (!\is_dir($root)) {
            \mkdir($root, 0777, true);
        }

        return $root;
    }

    private function assertSafeSourceInvalid(\Closure $operation): void
    {
        try {
            $operation();

            self::fail('Expected source-invalid failure.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_SOURCE_INVALID, $exception->reason());
            self::assertSame(
                'CORETSIA_CONFIG_INVALID: config-source-invalid',
                $exception->getMessage(),
            );
        }
    }

    private static function descriptor(
        string $moduleId,
        string $composerName,
        ?string $defaultsConfigPath,
    ): ModuleDescriptor {
        $metadata = [];

        if ($defaultsConfigPath !== null) {
            $metadata['defaultsConfigPath'] = $defaultsConfigPath;
        }

        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: $composerName,
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: $metadata,
        );
    }

    /**
     * @param list<ModuleDescriptor> $descriptors
     * @param array<string,string> $enabledComposerNames
     * @param list<string> $disabledModuleIds
     * @param list<string>|null $topologicalOrder
     */
    private static function resolution(
        array $descriptors,
        array $enabledComposerNames,
        array $disabledModuleIds = [],
        ?array $topologicalOrder = null,
    ): ModuleResolution {
        $enabled = [];
        $entries = [];

        foreach ($enabledComposerNames as $moduleIdValue => $composerName) {
            $moduleId = ModuleId::fromString($moduleIdValue);
            $enabled[] = $moduleId;
            $entries[] = new ModulePlanEntry(
                moduleId: $moduleId,
                composerName: $composerName,
            );
        }

        $disabled = \array_map(
            static fn (string $moduleId): ModuleId => ModuleId::fromString($moduleId),
            $disabledModuleIds,
        );
        $order = \array_map(
            static fn (string $moduleId): ModuleId => ModuleId::fromString($moduleId),
            $topologicalOrder ?? \array_keys($enabledComposerNames),
        );

        return new ModuleResolution(
            manifest: new ModuleManifest($descriptors),
            plan: new ModulePlan(
                app: 'web',
                preset: 'micro',
                enabled: $enabled,
                disabled: $disabled,
                optionalMissing: [],
                topologicalOrder: $order,
                modules: $entries,
                warnings: [],
            ),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function defaultCandidate(
        string $root,
        string $packageId,
        string $moduleId,
        string $path,
        string $installRoot,
    ): array {
        return [
            'root' => $root,
            'packageId' => $packageId,
            'moduleId' => $moduleId,
            'path' => $path,
            'filesystemPath' => self::joinPath($installRoot, $path),
            'sourceId' => $packageId . '/config/defaults/' . $root,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function rulesCandidate(
        string $root,
        string $packageId,
        string $moduleId,
        string $installRoot,
    ): array {
        return [
            'root' => $root,
            'packageId' => $packageId,
            'moduleId' => $moduleId,
            'path' => 'config/rules.php',
            'filesystemPath' => self::joinPath($installRoot, 'config/rules.php'),
            'sourceId' => $packageId . '/config/rules/' . $root,
        ];
    }

    private static function joinPath(string $root, string $relativePath): string
    {
        return \rtrim($root, '/\\')
            . \DIRECTORY_SEPARATOR
            . \str_replace('/', \DIRECTORY_SEPARATOR, $relativePath);
    }
}
