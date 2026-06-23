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

final class ModulePlanResolverUsesBootstrapPresetAsOnlySelectionSourceTest extends TestCase
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

    public function testUsesBootstrapPresetAsOnlyPresetSelectionInput(): void
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
                    'core.foundation',
                ],
                'optional' => [],
                'disabled' => [],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'express',
            payload: [
                'schemaVersion' => 1,
                'name' => 'express',
                'description' => 'Express test mode.',
                'required' => [
                    'core.kernel',
                ],
                'optional' => [
                    'platform.http',
                ],
                'disabled' => [],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );

        /*
         * This file would fail if ModulePlanResolver used app-local module
         * selection. The resolver must ignore it entirely.
         */
        self::writeFile(
            $skeletonRoot . '/apps/worker/config/modules.php',
            "<?php\nthrow new \\RuntimeException('app-local-modules-php-must-not-be-read');\n",
        );

        $manifestReader = self::manifestReader(
            self::manifest([
                self::descriptor('core.foundation'),
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor('platform.http'),
            ]),
        );

        $meter = self::meter();

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: $manifestReader,
            meter: $meter,
        );

        $plan = $resolver->resolve(
            self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                preset: 'express',
                appTarget: 'worker',
            ),
        );

        self::assertSame('express', $plan->preset());
        self::assertSame('worker', $plan->app());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.http',
            ],
            self::moduleIdValues($plan->enabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.http',
            ],
            self::moduleIdValues($plan->topologicalOrder()),
        );

        self::assertSame([], self::moduleIdValues($plan->optionalMissing()));
        self::assertSame([], $plan->warnings());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.http',
            ],
            \array_keys($plan->modules()),
        );

        self::assertSame(1, $manifestReader->reads);
        self::assertMetricOutcomes($meter, ['success']);
    }

    private static function resolver(
        string $packageRoot,
        ManifestReaderInterface $manifestReader,
        MeterPortInterface $meter,
        array $modulesConfig = [
            'discovery' => [
                'source' => 'composer',
                'allowed_sources' => [
                    'composer',
                ],
            ],
        ],
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
            meter: $meter,
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: $modulesConfig,
        );
    }

    private static function bootstrapConfig(
        string $skeletonRoot,
        string $preset,
        string $appTarget = 'api',
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: $preset,
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
            appTarget: AppTarget::from($appTarget),
            skeletonRoot: $skeletonRoot,
        );
    }

    /**
     * @param list<ModuleDescriptor> $modules
     */
    private static function manifest(array $modules): ModuleManifest
    {
        return new ModuleManifest($modules);
    }

    /**
     * @param list<string> $requires
     * @param list<string> $conflicts
     */
    private static function descriptor(
        string $moduleId,
        array $requires = [],
        array $conflicts = [],
    ): ModuleDescriptor {
        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: self::composerName($moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'conflicts' => self::sortedUniqueStrings($conflicts),
                'requires' => self::sortedUniqueStrings($requires),
            ],
        );
    }

    private static function manifestReader(ModuleManifest|\Throwable $result): ManifestReaderInterface
    {
        return new class($result) implements ManifestReaderInterface {
            public int $reads = 0;

            public function __construct(
                private ModuleManifest|\Throwable $result,
            ) {
            }

            public function read(): ModuleManifest
            {
                ++$this->reads;

                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }

                return $this->result;
            }
        };
    }

    private static function meter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            /**
             * @var list<array{name: string, delta: int, labels: array<string, string|int|bool>}>
             */
            public array $increments = [];

            /**
             * @var list<array{name: string, value: int, labels: array<string, string|int|bool>}>
             */
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
     * @param list<string> $expectedOutcomes
     */
    private static function assertMetricOutcomes(MeterPortInterface $meter, array $expectedOutcomes): void
    {
        self::assertObjectHasProperty('increments', $meter);
        self::assertObjectHasProperty('observations', $meter);

        $increments = $meter->increments;
        $observations = $meter->observations;

        self::assertCount(\count($expectedOutcomes), $increments);
        self::assertCount(\count($expectedOutcomes), $observations);

        foreach ($expectedOutcomes as $index => $outcome) {
            self::assertSame('kernel.modules_resolve_total', $increments[$index]['name']);
            self::assertSame(1, $increments[$index]['delta']);
            self::assertSame(
                [
                    'operation' => 'resolve',
                    'outcome' => $outcome,
                ],
                $increments[$index]['labels'],
            );

            self::assertSame('kernel.modules_resolve_duration_ms', $observations[$index]['name']);
            self::assertIsInt($observations[$index]['value']);
            self::assertSame(
                [
                    'operation' => 'resolve',
                    'outcome' => $outcome,
                ],
                $observations[$index]['labels'],
            );
        }
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

    private static function composerName(string $moduleId): string
    {
        return 'coretsia/' . \str_replace('.', '-', $moduleId);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sortedUniqueStrings(array $values): array
    {
        $values = \array_values(\array_unique($values));

        \usort($values, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $values;
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
            . '/coretsia-module-plan-resolver-bootstrap-preset-'
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
