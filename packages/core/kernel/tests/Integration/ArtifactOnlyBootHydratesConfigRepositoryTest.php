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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootHydratesConfigRepositoryTest extends TestCase
{
    public function testHydratesExactConfigRepositoryFromSelectedGeneration(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-only-hydrates-config-repository',
        );
        $compiledConfig = ArtifactPipelineTestSupport::defaultConfig(
            'from-config-artifact',
        );
        $compiledConfig['custom']['artifact_runtime'] = [
            'enabled' => true,
            'name' => 'selected-generation',
        ];

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                applicationRoot: $root,
                config: $compiledConfig,
            );

            $configPayload = ArtifactPipelineTestSupport::configPayloadFromArtifact(
                $root,
            );
            $expectedConfig = $configPayload['config'] ?? null;

            self::assertIsArray($expectedConfig);

            ArtifactPipelineTestSupport::writeRootConfig(
                applicationRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(
                    'changed-after-publication',
                ),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                applicationRoot: $root,
            );
            $repository = $container->get(ConfigRepositoryInterface::class);

            self::assertInstanceOf(
                ArrayConfigRepository::class,
                $repository,
            );
            self::assertSame(
                $expectedConfig,
                $repository->all(),
            );
            self::assertTrue(
                $repository->has('custom.artifact_runtime.enabled'),
            );
            self::assertSame(
                true,
                $repository->get('custom.artifact_runtime.enabled'),
            );
            self::assertSame(
                'selected-generation',
                $repository->get('custom.artifact_runtime.name'),
            );
            self::assertSame(
                'from-config-artifact',
                $repository->get('custom.feature.value'),
            );
            self::assertNotSame(
                'changed-after-publication',
                $repository->get('custom.feature.value'),
            );

            $artifactSources = $configPayload['sources'];

            self::assertIsArray($artifactSources);
            self::assertNotEmpty($artifactSources);

            $runtimeSources = \array_map(
                static fn (ConfigValueSource $source): array => \array_filter(
                    $source->toArray(),
                    static fn (mixed $value): bool => $value !== null && $value !== [],
                ),
                $repository->explain(),
            );

            self::assertCount(\count($artifactSources), $runtimeSources);

            foreach ($artifactSources as $source) {
                self::assertContains($source, $runtimeSources);
            }

            $source = $repository->sourceOf('custom.feature.value');

            self::assertInstanceOf(ConfigValueSource::class, $source);
            self::assertSame(ConfigSourceType::ApplicationConfig, $source->type());
            self::assertNull($repository->sourceOf('custom.missing'));
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testHydratedEffectiveSourcesMatchBootedSourceHost(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-only-config-source-parity',
        );

        try {
            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/config/app.php',
                [
                    'appEnv' => 'local',
                    'moduleOverrides' => [
                        'console' => [
                            'include' => [],
                            'exclude' => [
                                'platform.http',
                                'platform.http-app',
                                'platform.problem-details',
                            ],
                        ],
                    ],
                ],
            );
            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/config/logging.php',
                ['driver' => 'null'],
            );
            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/config/kernel.php',
                [
                    'uow' => [
                        'attributes' => [
                            'max_depth' => 12,
                            'max_keys' => 150,
                        ],
                    ],
                ],
            );
            self::assertNotFalse(
                \file_put_contents(
                    $root . '/.env',
                    "KERNEL_UOW_ATTRIBUTES_MAX_KEYS=123\n",
                ),
            );

            $sourceContainer = new KernelOpsHostBooter()->boot(
                new KernelOpsHostInput($root),
            );
            $sourceRepository = $sourceContainer->get(ConfigRepositoryInterface::class);
            $ops = $sourceContainer->get(KernelOpsInterface::class);

            self::assertInstanceOf(ConfigRepositoryInterface::class, $sourceRepository);
            self::assertInstanceOf(KernelOpsInterface::class, $ops);
            self::assertSame(
                'success',
                $ops->compileConfig(new KernelOpsRequest('console'))->outcome(),
            );

            $artifactContainer = new ArtifactRuntimeBooter()->boot(
                new ArtifactRuntimeInput(
                    applicationRoot: $root,
                    artifactRoot: $root . '/var/cache/console',
                ),
            );
            $repository = $artifactContainer->get(ConfigRepositoryInterface::class);

            self::assertInstanceOf(ArrayConfigRepository::class, $repository);
            self::assertEquals($sourceRepository->all(), $repository->all());
            self::assertNotEmpty($repository->explain());
            self::assertSame(
                \array_map(
                    static fn (ConfigValueSource $source): array => $source->toArray(),
                    $sourceRepository->explain(),
                ),
                \array_map(
                    static fn (ConfigValueSource $source): array => $source->toArray(),
                    $repository->explain(),
                ),
            );

            foreach (
                [
                    'kernel.runtime.http_driver' => [
                        'http.classic',
                        ConfigSourceType::PackageDefault,
                    ],
                    'kernel.uow.attributes.max_depth' => [
                        12,
                        ConfigSourceType::ApplicationConfig,
                    ],
                    'kernel.uow.attributes.max_keys' => [
                        123,
                        ConfigSourceType::Env,
                    ],
                ] as $path => [$value, $type]
            ) {
                self::assertSame($value, $sourceRepository->get($path));
                self::assertSame($value, $repository->get($path));

                $source = $sourceRepository->sourceOf($path);
                $hydratedSource = $repository->sourceOf($path);

                self::assertInstanceOf(ConfigValueSource::class, $source);
                self::assertInstanceOf(ConfigValueSource::class, $hydratedSource);
                self::assertSame($type, $hydratedSource->type());
                self::assertSame($source->toArray(), $hydratedSource->toArray());
            }

            self::assertNull($sourceRepository->sourceOf('kernel.missing'));
            self::assertNull($repository->sourceOf('kernel.missing'));
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
