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
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\Preset\PresetNamespaceResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ModuleResolutionOrchestratorTest extends TestCase
{
    public function testPreservesSingleManifestDiscoverySnapshotAndPassesOnlyResolvedPlanToRuntime(): void
    {
        $package = ModeInfrastructureTestSupport::root();
        $app = ModeInfrastructureTestSupport::root();
        try {
            ModeInfrastructureTestSupport::writePreset(
                $package . '/resources/modes/micro.php',
                'micro',
                ['core.kernel'],
                ['platform.worker'],
            );
            $manifest = ModeInfrastructureTestSupport::manifest([
                ModeInfrastructureTestSupport::descriptor('core.foundation'),
                ModeInfrastructureTestSupport::descriptor('core.kernel', ['core.foundation']),
                ModeInfrastructureTestSupport::descriptor('platform.worker', ['core.kernel']),
            ]);
            $reader = new class($manifest) implements ManifestReaderInterface {
                public int $reads = 0;

                public function __construct(private ModuleManifest $manifest)
                {
                }

                public function read(): ModuleManifest
                {
                    ++$this->reads;
                    return $this->manifest;
                }
            };
            $orchestrator = self::orchestrator(
                $package,
                $reader,
            );
            $result = $orchestrator->resolve(ModeInfrastructureTestSupport::bootstrap($app));
            self::assertSame(1, $reader->reads);
            self::assertSame($manifest, $result->manifest());
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.worker'],
                ModeInfrastructureTestSupport::values($result->plan()->enabled()),
            );
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.worker'],
                ModeInfrastructureTestSupport::values(
                    $result->plan()->topologicalOrder(),
                ),
            );
        } finally {
            ModeInfrastructureTestSupport::remove($package);
            ModeInfrastructureTestSupport::remove($app);
        }
    }

    public function testResolveSelectionUsesPhaseAPathWithoutReadingManifest(): void
    {
        $package = ModeInfrastructureTestSupport::root();
        $app = ModeInfrastructureTestSupport::root();

        try {
            ModeInfrastructureTestSupport::writePreset(
                $package . '/resources/modes/micro.php',
                'micro',
                ['core.kernel'],
                ['platform.worker'],
            );

            $manifest = ModeInfrastructureTestSupport::manifest([]);
            $reader = new class($manifest) implements ManifestReaderInterface {
                public int $reads = 0;

                public function __construct(private ModuleManifest $manifest)
                {
                }

                public function read(): ModuleManifest
                {
                    ++$this->reads;

                    return $this->manifest;
                }
            };

            $orchestrator = self::orchestrator(
                $package,
                $reader,
            );

            $selection = $orchestrator->resolveSelection(
                ModeInfrastructureTestSupport::bootstrap($app),
            );

            self::assertSame(0, $reader->reads);
            self::assertSame(
                ['core.kernel', 'platform.worker'],
                ModeInfrastructureTestSupport::values($selection->roots()),
            );
            self::assertSame(
                [],
                ModeInfrastructureTestSupport::values($selection->excluded()),
            );
        } finally {
            ModeInfrastructureTestSupport::remove($package);
            ModeInfrastructureTestSupport::remove($app);
        }
    }

    public function testResolveSelectionRejectsUnsupportedDiscoveryBeforePresetLoading(): void
    {
        $package = ModeInfrastructureTestSupport::root();
        $app = ModeInfrastructureTestSupport::root();

        try {
            $reader = new class() implements ManifestReaderInterface {
                public function read(): ModuleManifest
                {
                    throw new \LogicException('manifest must not be read');
                }
            };

            $orchestrator = self::orchestrator(
                $package,
                $reader,
                'filesystem',
                ['composer'],
            );

            $this->expectException(ModuleDiscoverySourceUnsupportedException::class);
            $orchestrator->resolveSelection(
                ModeInfrastructureTestSupport::bootstrap($app),
            );
        } finally {
            ModeInfrastructureTestSupport::remove($package);
            ModeInfrastructureTestSupport::remove($app);
        }
    }

    public function testResolveUsesExtractedSelectionPathExactlyOnce(): void
    {
        $path = new \ReflectionClass(ModuleResolutionOrchestrator::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertSame(
            1,
            \substr_count(
                $source,
                '$this->resolveSelection($bootstrapConfig)',
            ),
        );

        $start = \strpos(
            $source,
            'public function resolveSelection(',
        );
        $end = \strpos(
            $source,
            'private function assertSupportedDiscoverySource(',
        );

        self::assertIsInt($start);
        self::assertIsInt($end);

        $selectionSource = \substr(
            $source,
            $start,
            $end - $start,
        );

        self::assertStringNotContainsString(
            '$this->manifestReader',
            $selectionSource,
        );
        self::assertStringNotContainsString(
            '$this->modulePlanResolver',
            $selectionSource,
        );
    }

    /**
     * @param list<string> $allowedSources
     */
    private static function orchestrator(
        string $package,
        ManifestReaderInterface $reader,
        string $source = 'composer',
        array $allowedSources = ['composer'],
    ): ModuleResolutionOrchestrator {
        return new ModuleResolutionOrchestrator(
            presetLoaderFactory: new ModePresetLoaderFactory(
                $package,
                [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                new ModePresetSchemaValidator(),
            ),
            presetNamespaceResolver: new PresetNamespaceResolver(),
            moduleSelectionFactory: new ModuleSelectionFactory(
                new ModuleIdSetNormalizer(),
            ),
            manifestReader: $reader,
            modulePlanResolver: new ModulePlanResolver(
                new ModuleGraphResolver(new TopologicalSorter()),
            ),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: [
                'discovery' => [
                    'source' => $source,
                    'allowed_sources' => $allowedSources,
                ],
            ],
        );
    }
}
