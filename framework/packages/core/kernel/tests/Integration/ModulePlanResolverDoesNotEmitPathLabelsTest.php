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

final class ModulePlanResolverDoesNotEmitPathLabelsTest extends TestCase
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

    public function testMetricLabelsDoNotContainPathsPresetNamesModuleIdsOrRawPayloads(): void
    {
        $packageRoot = $this->tempRoot . '/package-root-with-sensitive-name';
        $skeletonRoot = $this->tempRoot . '/skeleton-root-with-sensitive-name';
        $meter = self::recordingMeter();

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

        $resolver = new ModulePlanResolver(
            presetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: $packageRoot,
                modesConfig: [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                schemaValidator: new ModePresetSchemaValidator(),
            ),
            manifestReader: self::manifestReader(
                new ModuleManifest([
                    new ModuleDescriptor(
                        id: ModuleId::fromString('core.kernel'),
                        composerName: 'coretsia/core-kernel',
                        packageKind: 'runtime',
                        moduleClass: null,
                        capabilities: [],
                        metadata: [
                            'conflicts' => [],
                            'requires' => [],
                        ],
                    ),
                ]),
            ),
            graphResolver: new ModuleGraphResolver(new TopologicalSorter()),
            meter: $meter,
            stopwatch: new Stopwatch(),
            modulesConfig: [
                'discovery' => [
                    'source' => 'composer',
                    'allowed_sources' => [
                        'composer',
                    ],
                ],
            ],
            logger: null,
        );

        $resolver->resolve(
            new BootstrapConfig(
                appEnv: 'local',
                preset: 'micro',
                debug: false,
                envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
                appTarget: AppTarget::from('api'),
                skeletonRoot: $skeletonRoot,
            ),
        );

        $labelPayloads = [];

        foreach ($meter->increments as $increment) {
            $labelPayloads[] = $increment['labels'];
        }

        foreach ($meter->observations as $observation) {
            $labelPayloads[] = $observation['labels'];
        }

        self::assertNotSame([], $labelPayloads);

        foreach ($labelPayloads as $labels) {
            self::assertSame(['operation', 'outcome'], \array_keys($labels));
            self::assertSame('resolve', $labels['operation']);
            self::assertSame('success', $labels['outcome']);

            $encoded = \json_encode($labels, \JSON_THROW_ON_ERROR);

            self::assertStringNotContainsString($this->tempRoot, $encoded);
            self::assertStringNotContainsString($packageRoot, $encoded);
            self::assertStringNotContainsString($skeletonRoot, $encoded);
            self::assertStringNotContainsString('resources/modes', $encoded);
            self::assertStringNotContainsString('config/modes', $encoded);
            self::assertStringNotContainsString('micro', $encoded);
            self::assertStringNotContainsString('core.kernel', $encoded);
            self::assertStringNotContainsString('coretsia/core-kernel', $encoded);
            self::assertStringNotContainsString('/', $encoded);
            self::assertStringNotContainsString('\\', $encoded);
            self::assertStringNotContainsString('://', $encoded);
            self::assertStringNotContainsString('..', $encoded);
        }
    }

    private static function manifestReader(ModuleManifest $manifest): ManifestReaderInterface
    {
        return new class($manifest) implements ManifestReaderInterface {
            public function __construct(
                private ModuleManifest $manifest,
            ) {
            }

            public function read(): ModuleManifest
            {
                return $this->manifest;
            }
        };
    }

    private static function recordingMeter(): object
    {
        return new class() implements MeterPortInterface {
            public array $increments = [];
            public array $observations = [];

            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
                $this->increments[] = [
                    'name' => $name,
                    'delta' => $delta,
                    'labels' => $labels,
                ];
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
                $this->observations[] = [
                    'name' => $name,
                    'value' => $value,
                    'labels' => $labels,
                ];
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writePresetFile(string $directory, string $name, array $payload): void
    {
        self::writeFile(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($payload, true) . ";\n",
        );
    }

    private static function writeFile(string $file, string $contents): void
    {
        $directory = \dirname($file);

        if (!\is_dir($directory) && !\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-directory-create-failed');
        }

        if (\file_put_contents($file, $contents) === false) {
            throw new \RuntimeException('test-file-write-failed');
        }
    }

    private static function createTempDirectory(): string
    {
        $directory = \sys_get_temp_dir()
            . '/coretsia-module-plan-no-path-labels-'
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
