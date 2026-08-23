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
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ModulePlanResolverEmitsPolicyCompliantSpanTest extends TestCase
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

    public function testSuccessfulResolutionIsWrappedInCanonicalModulesResolveSpan(): void
    {
        $packageRoot = $this->tempRoot . '/package-root-with-sensitive-name';
        $skeletonRoot = $this->tempRoot . '/skeleton-root-with-sensitive-name';
        $tracer = new ModulePlanResolverSpanProbe();
        $meter = self::recordingMeter();
        $spanNameObservedDuringManifestRead = null;

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

        $manifestReader = self::manifestReader(
            self::manifest([
                self::descriptor('core.kernel'),
            ]),
            static function () use (
                $tracer,
                &$spanNameObservedDuringManifestRead,
            ): void {
                $spanNameObservedDuringManifestRead =
                    $tracer->currentSpan()?->name();
            },
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: $manifestReader,
            tracer: $tracer,
            meter: $meter,
        );

        $plan = $resolver->resolve(
            self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                preset: 'micro',
            ),
        );

        self::assertTrue($plan->hasEnabledModule('core.kernel'));
        self::assertSame(
            'kernel.modules_resolve',
            $spanNameObservedDuringManifestRead,
        );
        self::assertSame(1, $tracer->startCalls);
        self::assertSame(1, $tracer->setAttributesCalls);
        self::assertSame(1, $tracer->endCalls);
        self::assertSame(0, $tracer->recordExceptionCalls);
        self::assertSame(
            'kernel.modules_resolve',
            $tracer->startedSpanName,
        );
        self::assertSame(
            [
                'operation' => 'resolve',
            ],
            $tracer->startAttributes,
        );
        self::assertSame(
            [
                'operation' => 'resolve',
                'outcome' => 'success',
            ],
            $tracer->finalAttributes,
        );
        self::assertSame(
            [
                'operation',
                'outcome',
            ],
            \array_keys($tracer->finalAttributes),
        );
        self::assertNull($tracer->currentSpan());

        $serializedSpanAttributes = \json_encode(
            [
                'start' => $tracer->startAttributes,
                'final' => $tracer->finalAttributes,
            ],
            \JSON_THROW_ON_ERROR,
        );

        foreach (
            [
                $this->tempRoot,
                $packageRoot,
                $skeletonRoot,
                'resources/modes',
                'config/modes',
                'micro',
                'core.kernel',
                'coretsia/core-kernel',
                '/',
                '\\',
                '://',
                '..',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $serializedSpanAttributes,
            );
        }
    }

    public function testUnsupportedDiscoverySourceIsWrappedInCanonicalSpan(): void
    {
        $packageRoot = $this->tempRoot . '/package-root-with-sensitive-name';
        $skeletonRoot = $this->tempRoot . '/skeleton-root-with-sensitive-name';
        $tracer = new ModulePlanResolverSpanProbe();
        $meter = self::recordingMeter();
        $manifestReadCount = 0;

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

        $manifestReader = self::manifestReader(
            self::manifest([
                self::descriptor('core.kernel'),
            ]),
            static function () use (
                &$manifestReadCount,
            ): void {
                ++$manifestReadCount;
            },
        );

        $resolver = self::resolver(
            packageRoot: $packageRoot,
            manifestReader: $manifestReader,
            tracer: $tracer,
            meter: $meter,
            modulesConfig: [
                'discovery' => [
                    'source' => 'filesystem',
                    'allowed_sources' => [
                        'composer',
                    ],
                ],
            ],
        );

        try {
            $resolver->resolveResolution(
                self::bootstrapConfig(
                    skeletonRoot: $skeletonRoot,
                    preset: 'micro',
                ),
            );

            self::fail('Expected unsupported discovery source failure.');
        } catch (ModuleDiscoverySourceUnsupportedException) {
            self::assertSame(0, $manifestReadCount);
            self::assertSame(1, $tracer->startCalls);
            self::assertSame(1, $tracer->setAttributesCalls);
            self::assertSame(1, $tracer->endCalls);
            self::assertSame(0, $tracer->recordExceptionCalls);
            self::assertSame(
                'kernel.modules_resolve',
                $tracer->startedSpanName,
            );
            self::assertSame(
                [
                    'operation' => 'resolve',
                    'outcome' => 'discovery_source_unsupported',
                ],
                $tracer->finalAttributes,
            );
            self::assertNull($tracer->currentSpan());
        }
    }

    public function testUnexpectedThrowableEndsSpanWithUnexpectedFailureAndIsRethrownUnchanged(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $tracer = new ModulePlanResolverSpanProbe();
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
            tracer: $tracer,
            meter: $meter,
        );

        try {
            $resolver->resolveResolution(
                self::bootstrapConfig(
                    skeletonRoot: $skeletonRoot,
                    preset: 'micro',
                ),
            );

            self::fail('Expected unexpected module resolution throwable.');
        } catch (\LogicException $caught) {
            self::assertSame($unexpected, $caught);
            self::assertSame(1, $tracer->startCalls);
            self::assertSame(1, $tracer->setAttributesCalls);
            self::assertSame(1, $tracer->endCalls);
            self::assertSame(0, $tracer->recordExceptionCalls);
            self::assertSame(
                'kernel.modules_resolve',
                $tracer->startedSpanName,
            );
            self::assertSame(
                [
                    'operation' => 'resolve',
                    'outcome' => 'unexpected_failure',
                ],
                $tracer->finalAttributes,
            );
            self::assertNull($tracer->currentSpan());
        }
    }

    public function testTracerStartFailureDoesNotChangeSuccessfulResolution(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $tracer = new ModulePlanResolverSpanProbe(
            throwOnStart: true,
        );
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
            tracer: $tracer,
            meter: $meter,
        );

        $resolution = $resolver->resolveResolution(
            self::bootstrapConfig(
                skeletonRoot: $skeletonRoot,
                preset: 'micro',
            ),
        );

        self::assertTrue(
            $resolution->plan()->hasEnabledModule('core.kernel'),
        );
        self::assertSame(1, $tracer->startCalls);
        self::assertSame(0, $tracer->setAttributesCalls);
        self::assertSame(0, $tracer->endCalls);
        self::assertSame(0, $tracer->recordExceptionCalls);
        self::assertNull($tracer->currentSpan());

        self::assertSame(
            [
                [
                    'name' => 'kernel.modules_resolve_total',
                    'delta' => 1,
                    'labels' => [
                        'operation' => 'resolve',
                        'outcome' => 'success',
                    ],
                ],
            ],
            $meter->increments,
        );
        self::assertCount(1, $meter->observations);
        self::assertSame(
            'kernel.modules_resolve_duration_ms',
            $meter->observations[0]['name'],
        );
        self::assertIsInt($meter->observations[0]['value']);
        self::assertGreaterThanOrEqual(
            0,
            $meter->observations[0]['value'],
        );
        self::assertSame(
            [
                'operation' => 'resolve',
                'outcome' => 'success',
            ],
            $meter->observations[0]['labels'],
        );
    }

    public function testSpanAttributeFailureStillAttemptsEndAndPreservesPrimaryFailure(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $tracer = new ModulePlanResolverSpanProbe(
            throwOnSetAttributes: true,
            throwOnEnd: true,
        );
        $meter = self::recordingMeter();
        $primary = ModuleRequiredMissingException::presetRequiredModuleMissing(
            presetName: 'micro',
            missingModuleId: ModuleId::fromString('platform.http'),
        );

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
            manifestReader: self::manifestReader($primary),
            tracer: $tracer,
            meter: $meter,
        );

        try {
            $resolver->resolveResolution(
                self::bootstrapConfig(
                    skeletonRoot: $skeletonRoot,
                    preset: 'micro',
                ),
            );

            self::fail('Expected required missing failure.');
        } catch (ModuleRequiredMissingException $caught) {
            self::assertSame($primary, $caught);
            self::assertSame(
                ModuleRequiredMissingException::REASON_PRESET_REQUIRED_MODULE_MISSING,
                $caught->reason(),
            );
            self::assertSame(
                [
                    'missingModuleId' => 'platform.http',
                    'preset' => 'micro',
                ],
                $caught->context(),
            );
        }

        self::assertSame(1, $tracer->startCalls);
        self::assertSame(1, $tracer->setAttributesCalls);
        self::assertSame(1, $tracer->endCalls);
        self::assertSame(0, $tracer->recordExceptionCalls);

        self::assertSame(
            [
                [
                    'name' => 'kernel.modules_resolve_total',
                    'delta' => 1,
                    'labels' => [
                        'operation' => 'resolve',
                        'outcome' => 'required_missing',
                    ],
                ],
            ],
            $meter->increments,
        );

        self::assertCount(
            1,
            $meter->observations,
        );

        self::assertSame(
            'kernel.modules_resolve_duration_ms',
            $meter->observations[0]['name'],
        );

        self::assertSame(
            [
                'operation' => 'resolve',
                'outcome' => 'required_missing',
            ],
            $meter->observations[0]['labels'],
        );
    }

    /**
     * @param array<string, mixed> $modulesConfig
     */
    private static function resolver(
        string $packageRoot,
        ManifestReaderInterface $manifestReader,
        TracerPortInterface $tracer,
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
            graphResolver: new ModuleGraphResolver(
                new TopologicalSorter(),
            ),
            tracer: $tracer,
            meter: $meter,
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: $modulesConfig,
        );
    }

    private static function bootstrapConfig(
        string $skeletonRoot,
        string $preset,
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: $preset,
            debug: false,
            artifactsCacheDir: 'var/cache',
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

    private static function manifestReader(
        ModuleManifest|\Throwable $result,
        ?\Closure $onRead = null,
    ): ManifestReaderInterface {
        return new class($result, $onRead, ) implements ManifestReaderInterface {
            public function __construct(
                private ModuleManifest|\Throwable $result,
                private ?\Closure $onRead,
            ) {
            }

            public function read(): ModuleManifest
            {
                if ($this->onRead !== null) {
                    ($this->onRead)();
                }

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
             * @var list<array{
             *     name: string,
             *     delta: int,
             *     labels: array<string, string|int|bool>
             * }>
             */
            public array $increments = [];

            /**
             * @var list<array{
             *     name: string,
             *     value: int,
             *     labels: array<string, string|int|bool>
             * }>
             */
            public array $observations = [];

            public function increment(
                string $name,
                int $delta = 1,
                array $labels = [],
            ): void {
                $this->increments[] = [
                    'name' => $name,
                    'delta' => $delta,
                    'labels' => $labels,
                ];
            }

            public function observe(
                string $name,
                int $value,
                array $labels = [],
            ): void {
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
    private static function presetPayload(
        string $name,
        array $required,
    ): array {
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
    private static function writePresetFile(
        string $directory,
        string $name,
        array $payload,
    ): void {
        self::writeFile(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($payload, true) . ";\n",
        );
    }

    private static function writeFile(
        string $file,
        string $contents,
    ): void {
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
            . '/coretsia-module-plan-observability-span-'
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

final class ModulePlanResolverSpanProbe implements TracerPortInterface, SpanInterface
{
    public int $startCalls = 0;
    public int $setAttributesCalls = 0;
    public int $endCalls = 0;
    public int $recordExceptionCalls = 0;

    public ?string $startedSpanName = null;

    /**
     * @var array<string, mixed>
     */
    public array $startAttributes = [];

    /**
     * @var array<string, mixed>
     */
    public array $finalAttributes = [];

    private bool $active = false;

    public function __construct(
        public bool $throwOnStart = false,
        public bool $throwOnSetAttributes = false,
        public bool $throwOnEnd = false,
    ) {
    }

    public function startSpan(
        string $name,
        array $attributes = [],
    ): SpanInterface {
        ++$this->startCalls;

        if ($this->throwOnStart) {
            throw new \RuntimeException('test-tracer-start-failure');
        }

        $this->startedSpanName = $name;
        $this->startAttributes = $attributes;
        $this->finalAttributes = $attributes;
        $this->active = true;

        return $this;
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        $span = $this->startSpan(
            $name,
            $attributes,
        );

        try {
            return $callback($span);
        } finally {
            $span->end();
        }
    }

    public function currentSpan(): ?SpanInterface
    {
        return $this->active
            ? $this
            : null;
    }

    public function name(): string
    {
        return $this->startedSpanName ?? throw new \LogicException('test-span-not-started');
    }

    public function setAttribute(
        string $key,
        mixed $value,
    ): void {
        $this->finalAttributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        ++$this->setAttributesCalls;

        if ($this->throwOnSetAttributes) {
            throw new \RuntimeException('test-span-set-attributes-failure');
        }

        foreach ($attributes as $key => $value) {
            $this->finalAttributes[$key] = $value;
        }
    }

    public function addEvent(
        string $name,
        array $attributes = [],
    ): void {
        unset($name, $attributes);
    }

    public function recordException(
        \Throwable $throwable,
        array $attributes = [],
    ): void {
        ++$this->recordExceptionCalls;

        unset($throwable, $attributes);
    }

    public function end(): void
    {
        ++$this->endCalls;

        if ($this->throwOnEnd) {
            throw new \RuntimeException('test-span-end-failure');
        }

        $this->active = false;
    }
}
