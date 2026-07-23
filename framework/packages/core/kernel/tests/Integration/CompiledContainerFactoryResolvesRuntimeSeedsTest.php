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
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\ArtifactRuntimeSeedFactory;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;

final class CompiledContainerFactoryResolvesRuntimeSeedsTest extends TestCase
{
    public function testCompiledServicesResolveExactEntrypointOwnedRuntimeSeedInstances(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'compiled-container-runtime-seeds',
        );
        $moduleResolution = ArtifactPipelineTestSupport::moduleResolution([
            CompiledContainerFactoryRuntimeSeedsProvider::class,
        ]);

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: $moduleResolution,
            );

            $containerPath = ArtifactPipelineTestSupport::artifactPath(
                $root,
                'container.php',
            );
            $configPayload =
                ArtifactPipelineTestSupport::configPayloadFromArtifact(
                    $root,
                );
            $moduleManifestEnvelope =
                ArtifactPipelineTestSupport::artifactEnvelope(
                    $root,
                    'module-manifest.php',
                );
            $moduleManifestPayload =
                $moduleManifestEnvelope['payload'] ?? null;

            self::assertIsArray($moduleManifestPayload);

            $seeds = new ArtifactRuntimeSeedFactory()->create(
                input: new ArtifactRuntimeInput(
                    skeletonRoot: $root,
                    artifactRoot: \dirname($containerPath),
                ),
                configPayload: $configPayload,
                moduleManifestPayload: $moduleManifestPayload,
            );
            $instances = $seeds->instances();

            /** @var ConfigRepositoryInterface $config */
            $config = $instances[ConfigRepositoryInterface::class];

            /** @var ModulePlan $modulePlan */
            $modulePlan = $instances[ModulePlan::class];

            /** @var RuntimePathContext $paths */
            $paths = $instances[RuntimePathContext::class];

            self::assertInstanceOf(
                ConfigRepositoryInterface::class,
                $config,
            );
            self::assertInstanceOf(ModulePlan::class, $modulePlan);
            self::assertInstanceOf(RuntimePathContext::class, $paths);

            self::assertSame(
                $configPayload['config'],
                $config->all(),
            );
            self::assertSame(
                $moduleResolution->plan()->toArray(),
                $modulePlan->toArray(),
            );
            self::assertSame($root, $paths->skeletonRoot());
            self::assertSame(
                \dirname($containerPath),
                $paths->artifactRoot(),
            );

            $container =
                ArtifactPipelineTestSupport::compiledContainerFactory()
                    ->build(
                        containerArtifactPath: $containerPath,
                        configPayload: $configPayload,
                        seeds: $seeds,
                    );

            self::assertSame(
                $config,
                $container->get(ConfigRepositoryInterface::class),
            );
            self::assertSame(
                $modulePlan,
                $container->get(ModulePlan::class),
            );
            self::assertSame(
                $paths,
                $container->get(RuntimePathContext::class),
            );

            /** @var CompiledContainerFactoryRuntimeSeedsSubject $subject */
            $subject = $container->get(
                CompiledContainerFactoryRuntimeSeedsSubject::class,
            );

            self::assertInstanceOf(
                CompiledContainerFactoryRuntimeSeedsSubject::class,
                $subject,
            );
            self::assertSame($config, $subject->config);
            self::assertSame($modulePlan, $subject->modulePlan);
            self::assertSame($paths, $subject->paths);
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}

final class CompiledContainerFactoryRuntimeSeedsProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(ModulePlan::class)
            ->requireService(RuntimePathContext::class)
            ->classService(
                id: CompiledContainerFactoryRuntimeSeedsSubject::class,
                class: CompiledContainerFactoryRuntimeSeedsSubject::class,
                arguments: [
                    ContainerValueReference::service(
                        ConfigRepositoryInterface::class,
                    ),
                    ContainerValueReference::service(
                        ModulePlan::class,
                    ),
                    ContainerValueReference::service(
                        RuntimePathContext::class,
                    ),
                ],
            );
    }
}

final readonly class CompiledContainerFactoryRuntimeSeedsSubject
{
    public function __construct(
        public ConfigRepositoryInterface $config,
        public ModulePlan $modulePlan,
        public RuntimePathContext $paths,
    ) {
    }
}
