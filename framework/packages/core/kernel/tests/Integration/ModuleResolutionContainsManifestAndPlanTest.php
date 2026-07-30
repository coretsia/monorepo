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
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ModuleResolutionContainsManifestAndPlanTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = self::createTempDirectory();
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->tempRoot);
    }

    public function testContainsManifestAndPlanFromOneResolutionRun(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro test mode.',
                'required' => [
                    'core.kernel',
                ],
                'optional' => [],
                'disabled' => [],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );

        $manifest = new ModuleManifest([
            new ModuleDescriptor(
                id: ModuleId::fromString('core.kernel'),
                composerName: 'coretsia/core-kernel',
                packageKind: 'runtime',
                moduleClass: null,
                capabilities: [],
                metadata: [
                    'conflicts' => [],
                    'providers' => [
                        'Coretsia\\Kernel\\Provider\\KernelServiceProvider',
                    ],
                    'requires' => [],
                ],
            ),
        ]);
        $manifestReader = self::manifestReader($manifest);

        $resolution = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: $manifestReader,
        )->resolveResolution(
            self::bootstrapConfig($skeletonRoot),
        );

        self::assertSame(
            $manifest,
            $resolution->manifest(),
        );
        self::assertSame(1, $manifestReader->reads);
        self::assertSame(
            [
                'core.kernel',
            ],
            self::moduleIdValues(
                $resolution->plan()->topologicalOrder(),
            ),
        );

        $descriptor = $resolution->manifest()->get('core.kernel');

        self::assertInstanceOf(
            ModuleDescriptor::class,
            $descriptor,
        );
        self::assertSame(
            [
                'Coretsia\\Kernel\\Provider\\KernelServiceProvider',
            ],
            $descriptor->metadata()['providers'] ?? null,
        );
        self::assertArrayNotHasKey(
            'providers',
            $resolution->plan()->toArray()['modules']['core.kernel'],
        );
    }

    private static function resolver(
        string $packageRoot,
        ManifestReaderInterface $manifestReader,
    ): ModulePlanResolver {
        return new ModulePlanResolver(
            presetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: $packageRoot,
                modesConfig: [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                schemaValidator: new ModePresetSchemaValidator(),
            ),
            manifestReader: $manifestReader,
            graphResolver: new ModuleGraphResolver(new TopologicalSorter()),
            meter: self::meter(),
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: [
                'discovery' => [
                    'source' => 'composer',
                    'allowed_sources' => [
                        'composer',
                    ],
                ],
            ],
        );
    }

    private static function bootstrapConfig(string $skeletonRoot): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            artifactsCacheDir: 'var/cache',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Api,
            skeletonRoot: $skeletonRoot,
        );
    }

    private static function manifestReader(ModuleManifest $manifest): ManifestReaderInterface
    {
        return new class($manifest) implements ManifestReaderInterface {
            public int $reads = 0;

            public function __construct(
                private readonly ModuleManifest $manifest,
            ) {
            }

            public function read(): ModuleManifest
            {
                ++$this->reads;

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
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writePresetFile(
        string $directory,
        string $name,
        array $payload,
    ): void {
        self::writeFile(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . \var_export($payload, true)
            . ";\n",
        );
    }

    private static function writeFile(string $file, string $contents): void
    {
        $directory = \dirname($file);

        if (
            !\is_dir($directory)
            && !\mkdir($directory, 0777, true)
            && !\is_dir($directory)
        ) {
            throw new \RuntimeException('test-directory-create-failed');
        }

        if (\file_put_contents($file, $contents) === false) {
            throw new \RuntimeException('test-file-write-failed');
        }
    }

    private static function createTempDirectory(): string
    {
        $directory = \sys_get_temp_dir()
            . '/coretsia-module-resolution-snapshot-'
            . \bin2hex(\random_bytes(8));

        if (!\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-temp-directory-create-failed');
        }

        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $entries = \scandir($directory);

        if (!\is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (\is_dir($path)) {
                self::removeDirectory($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
    }
}
