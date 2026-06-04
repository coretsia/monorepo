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
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModulePlanResolverFailurePrecedenceTest extends TestCase
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

    public function testPresetNotFoundHappensBeforeComposerManifestReading(): void
    {
        $manifestReader = self::manifestReader(
            ModuleManifestInvalidException::installedMetadataInvalid(),
        );

        $resolver = self::resolver(
            packageRoot: $this->tempRoot . '/package',
            manifestReader: $manifestReader,
            meter: self::meter(),
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $this->tempRoot . '/skeleton',
                    preset: 'missing',
                ),
            );

            self::fail('Expected preset-not-found to happen before manifest reading.');
        } catch (ModePresetNotFoundException $exception) {
            self::assertSame(0, $manifestReader->reads);
            self::assertSame(ModePresetNotFoundException::REASON_PRESET_NOT_FOUND, $exception->reason());
            self::assertSame(['preset' => 'missing'], $exception->context());
        }
    }

    public function testPresetInvalidHappensBeforeComposerManifestReading(): void
    {
        $packageRoot = $this->tempRoot . '/package';

        self::writeFile(
            $packageRoot . '/resources/modes/micro.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn 'not-an-array';\n",
        );

        $manifestReader = self::manifestReader(
            ModuleManifestInvalidException::installedMetadataInvalid(),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: $manifestReader,
            meter: self::meter(),
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $this->tempRoot . '/skeleton',
                    preset: 'micro',
                ),
            );

            self::fail('Expected preset-invalid to happen before manifest reading.');
        } catch (ModePresetInvalidException $exception) {
            self::assertSame(0, $manifestReader->reads);
            self::assertSame(['preset' => 'micro'], $exception->context());
        }
    }

    public function testManifestInvalidHappensBeforeGraphPolicyFailures(): void
    {
        $packageRoot = $this->tempRoot . '/package';

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
                ModuleManifestInvalidException::installedMetadataInvalid(),
            ),
            meter: self::meter(),
        );

        $this->expectException(ModuleManifestInvalidException::class);

        $resolver->resolve(
            self::bootstrapConfig(
                skeletonRoot: $this->tempRoot . '/skeleton',
                preset: 'micro',
            ),
        );
    }

    public function testConflictFailurePrecedesRequiredMissingFailure(): void
    {
        $packageRoot = $this->tempRoot . '/package';

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'core.kernel',
                    'platform.http',
                    'platform.metrics',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader(
                self::manifest([
                    self::descriptor(
                        'core.kernel',
                        conflicts: [
                            'platform.http',
                        ],
                    ),
                    self::descriptor('platform.http'),
                ]),
            ),
            meter: self::meter(),
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $this->tempRoot . '/skeleton',
                    preset: 'micro',
                ),
            );

            self::fail('Expected conflict to precede required-missing failure.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(ModuleConflictException::REASON_MODULE_CONFLICT, $exception->reason());
            self::assertSame(
                [
                    'higherModuleId' => 'platform.http',
                    'lowerModuleId' => 'core.kernel',
                ],
                $exception->context(),
            );
        }
    }

    public function testRequiredMissingFailurePrecedesCycleDetection(): void
    {
        $packageRoot = $this->tempRoot . '/package';

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'platform.alpha',
                    'platform.gamma',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader(
                self::manifest([
                    self::descriptor(
                        'platform.alpha',
                        requires: [
                            'platform.beta',
                        ],
                    ),
                    self::descriptor(
                        'platform.beta',
                        requires: [
                            'platform.alpha',
                        ],
                    ),
                ]),
            ),
            meter: self::meter(),
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $this->tempRoot . '/skeleton',
                    preset: 'micro',
                ),
            );

            self::fail('Expected required-missing to precede cycle detection.');
        } catch (ModuleRequiredMissingException $exception) {
            self::assertSame(
                ModuleRequiredMissingException::REASON_PRESET_REQUIRED_MODULE_MISSING,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'missingModuleId' => 'platform.gamma',
                    'preset' => 'micro',
                ],
                $exception->context(),
            );
        }
    }

    public function testCycleDetectionIsReportedAfterEarlierGraphFailuresAreAbsent(): void
    {
        $packageRoot = $this->tempRoot . '/package';

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: self::presetPayload(
                name: 'micro',
                required: [
                    'platform.alpha',
                    'platform.beta',
                ],
            ),
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: self::manifestReader(
                self::manifest([
                    self::descriptor(
                        'platform.alpha',
                        requires: [
                            'platform.beta',
                        ],
                    ),
                    self::descriptor(
                        'platform.beta',
                        requires: [
                            'platform.alpha',
                        ],
                    ),
                ]),
            ),
            meter: self::meter(),
        );

        try {
            $resolver->resolve(
                self::bootstrapConfig(
                    skeletonRoot: $this->tempRoot . '/skeleton',
                    preset: 'micro',
                ),
            );

            self::fail('Expected cycle detection after earlier failures are absent.');
        } catch (ModuleCycleDetectedException $exception) {
            self::assertSame(ModuleCycleDetectedException::REASON_CYCLE_DETECTED, $exception->reason());
            self::assertSame(
                [
                    'moduleIds' => [
                        'platform.alpha',
                        'platform.beta',
                    ],
                ],
                $exception->context(),
            );
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
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
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
     * @param list<string> $required
     * @param list<string> $optional
     * @param list<string> $disabled
     *
     * @return array<string, mixed>
     */
    private static function presetPayload(
        string $name,
        array $required,
        array $optional = [],
        array $disabled = [],
    ): array {
        return [
            'schemaVersion' => 1,
            'name' => $name,
            'description' => \ucfirst($name) . ' test mode.',
            'required' => $required,
            'optional' => $optional,
            'disabled' => $disabled,
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
            . '/coretsia-module-plan-resolver-failure-precedence-'
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
