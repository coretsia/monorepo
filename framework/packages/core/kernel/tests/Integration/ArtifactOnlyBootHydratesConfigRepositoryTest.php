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
use Coretsia\Kernel\Config\ArrayConfigRepository;
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
                skeletonRoot: $root,
                config: $compiledConfig,
            );

            $configPayload = ArtifactPipelineTestSupport::configPayloadFromArtifact(
                $root,
            );
            $expectedConfig = $configPayload['config'] ?? null;

            self::assertIsArray($expectedConfig);

            ArtifactPipelineTestSupport::writeRootConfig(
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(
                    'changed-after-publication',
                ),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                skeletonRoot: $root,
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
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
