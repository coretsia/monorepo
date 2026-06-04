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
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModulePlanResolverRejectsUnsupportedDiscoverySourceTest extends TestCase
{
    public function testUnsupportedDiscoverySourceFailsBeforePresetLoadingAndComposerMetadataReading(): void
    {
        $manifestReader = new class() implements ManifestReaderInterface {
            public int $reads = 0;

            public function read(): ModuleManifest
            {
                ++$this->reads;

                throw new \RuntimeException('manifest-reader-must-not-be-called');
            }
        };

        $meter = self::meter();

        $resolver = new ModulePlanResolver(
            presetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: \sys_get_temp_dir() . '/coretsia-module-plan-resolver-unsupported-source-package',
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
                    'source' => 'filesystem',
                    'allowed_sources' => [
                        'composer',
                    ],
                ],
            ],
            logger: null,
        );

        try {
            $resolver->resolve(
                new BootstrapConfig(
                    appEnv: 'local',
                    preset: 'micro',
                    debug: false,
                    envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
                    appTarget: AppTarget::from('api'),
                    skeletonRoot: \sys_get_temp_dir() . '/coretsia-module-plan-resolver-unsupported-source-skeleton',
                ),
            );

            self::fail('Expected unsupported discovery source to fail deterministically.');
        } catch (ModuleDiscoverySourceUnsupportedException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED,
                $exception->errorCode(),
            );

            self::assertSame(
                ModuleDiscoverySourceUnsupportedException::REASON_DISCOVERY_SOURCE_UNSUPPORTED,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'allowedSources' => [
                        'composer',
                    ],
                    'source' => 'filesystem',
                ],
                $exception->context(),
            );

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED
                . ': '
                . ModuleDiscoverySourceUnsupportedException::REASON_DISCOVERY_SOURCE_UNSUPPORTED,
                $exception->getMessage(),
            );

            self::assertSame(0, $manifestReader->reads);
            self::assertDiagnosticsDoNotExposePathsOrConfigPayload($exception->context());
            self::assertMetricOutcomes($meter, ['discovery_source_unsupported']);
        }
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
     * @param array<string, mixed> $context
     */
    private static function assertDiagnosticsDoNotExposePathsOrConfigPayload(array $context): void
    {
        $serialized = \json_encode($context, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('/tmp', $serialized);
        self::assertStringNotContainsString('/var', $serialized);
        self::assertStringNotContainsString('\\', $serialized);
        self::assertStringNotContainsString('resources/modes', $serialized);
        self::assertStringNotContainsString('config/modes', $serialized);
        self::assertStringNotContainsString('kernel.modules', $serialized);
        self::assertStringNotContainsString('discovery.source', $serialized);
        self::assertStringNotContainsString('skeleton', $serialized);
        self::assertStringNotContainsString('package', $serialized);
        self::assertStringNotContainsString('://', $serialized);
        self::assertStringNotContainsString('..', $serialized);
    }
}
