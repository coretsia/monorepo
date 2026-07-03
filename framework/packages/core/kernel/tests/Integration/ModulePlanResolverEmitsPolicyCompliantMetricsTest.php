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
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ModulePlanResolverEmitsPolicyCompliantMetricsTest extends TestCase
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

    public function testEmitsPolicyCompliantMetricsOnSuccess(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $meter = self::recordingMeter();

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'core.kernel',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader(
                self::manifest([
                    self::descriptor('core.kernel'),
                ]),
            ),
            meter: $meter,
        );

        $resolver->resolve(
            self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                preset: 'micro',
            ),
        );

        self::assertMetricsShape(
            meter: $meter,
            expectedOutcome: 'success',
        );
    }

    public function testEmitsPolicyCompliantMetricsOnDeterministicFailure(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $meter = self::recordingMeter();

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'platform.http',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader(
                self::manifest([
                    self::descriptor('core.kernel'),
                ]),
            ),
            meter: $meter,
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $skeletonRoot,
                    preset: 'micro',
                ),
            );

            self::fail('Expected required missing failure.');
        } catch (ModuleRequiredMissingException) {
            self::assertMetricsShape(
                meter: $meter,
                expectedOutcome: 'required_missing',
            );
        }
    }

    public function testEmitsUnexpectedFailureMetricsWhenUnexpectedThrowableEscapes(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $meter = self::recordingMeter();
        $unexpected = new \LogicException('unsafe unexpected module resolution failure');

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'core.kernel',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader($unexpected),
            meter: $meter,
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $skeletonRoot,
                    preset: 'micro',
                ),
            );

            self::fail('Expected unexpected module resolution throwable.');
        } catch (\LogicException $exception) {
            self::assertSame($unexpected, $exception);

            self::assertMetricsShape(
                meter: $meter,
                expectedOutcome: 'unexpected_failure',
            );
        }
    }

    private static function assertMetricsShape(object $meter, string $expectedOutcome): void
    {
        self::assertSame(
            [
                [
                    'name' => 'kernel.modules_resolve_total',
                    'delta' => 1,
                    'labels' => [
                        'operation' => 'resolve',
                        'outcome' => $expectedOutcome,
                    ],
                ],
            ],
            $meter->increments,
        );

        self::assertCount(1, $meter->observations);
        self::assertSame('kernel.modules_resolve_duration_ms', $meter->observations[0]['name']);
        self::assertIsInt($meter->observations[0]['value']);
        self::assertGreaterThanOrEqual(0, $meter->observations[0]['value']);
        self::assertSame(
            [
                'operation' => 'resolve',
                'outcome' => $expectedOutcome,
            ],
            $meter->observations[0]['labels'],
        );

        foreach ([$meter->increments[0]['labels'], $meter->observations[0]['labels']] as $labels) {
            self::assertSame(['operation', 'outcome'], \array_keys($labels));
            self::assertSame('resolve', $labels['operation']);
            self::assertContains($labels['outcome'], [
                'success',
                'preset_not_found',
                'preset_invalid',
                'manifest_invalid',
                'discovery_source_unsupported',
                'conflict',
                'required_missing',
                'cycle',
                'unexpected_failure',
            ]);
        }
    }

    private static function resolver(
        string $packageRoot,
        ManifestReaderInterface $manifestReader,
        MeterPortInterface $meter,
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

    private static function bootstrapConfig(string $skeletonRoot, string $preset): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: $preset,
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
            appTarget: AppTarget::from('api'),
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

    private static function descriptor(string $moduleId): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: 'coretsia/' . \str_replace('.', '-', $moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'conflicts' => [],
                'requires' => [],
            ],
        );
    }

    private static function manifestReader(ModuleManifest|\Throwable $result): ManifestReaderInterface
    {
        return new class($result) implements ManifestReaderInterface {
            public function __construct(
                private ModuleManifest|\Throwable $result,
            ) {
            }

            public function read(): ModuleManifest
            {
                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }

                return $this->result;
            }
        };
    }

    private static function recordingMeter(): object
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
     * @param list<string> $required
     *
     * @return array<string, mixed>
     */
    private static function presetPayload(string $name, array $required): array
    {
        return [
            'schemaVersion' => 1,
            'name' => $name,
            'description' => \ucfirst($name) . ' test mode.',
            'required' => $required,
            'optional' => [],
            'disabled' => [],
            'featureBundles' => [],
            'metadata' => [],
        ];
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
            . '/coretsia-module-plan-observability-metrics-'
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
