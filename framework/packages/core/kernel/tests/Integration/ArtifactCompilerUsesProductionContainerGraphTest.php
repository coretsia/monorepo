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

use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionFixtureService;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;

final class ArtifactCompilerUsesProductionContainerGraphTest extends TestCase
{
    public function testWritesProviderProducedGraphWithoutRawDescriptorInput(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-compiler-production-container-graph');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $envelope = ArtifactPipelineTestSupport::artifactEnvelope(
                $root,
                'container.php',
            );
            $payload = $envelope['payload'] ?? null;

            self::assertIsArray($payload);
            self::assertArrayHasKey(
                ContainerDefinitionFixtureService::class,
                $payload['services'],
            );
            self::assertSame(
                ContainerDefinitionProviderFixture::PARAMETER_VALUE,
                $payload['parameters'][ContainerDefinitionProviderFixture::PARAMETER_NAME] ?? null,
            );
            self::assertSame(
                ContainerDefinitionFixtureService::class,
                $payload['aliases'][ContainerDefinitionProviderFixture::SERVICE_ALIAS] ?? null,
            );
            self::assertSame(
                [
                    [
                        'id' => ContainerDefinitionFixtureService::class,
                        'meta' => [],
                        'priority' => 25,
                    ],
                ],
                $payload['tags'][ContainerDefinitionProviderFixture::SERVICE_TAG] ?? null,
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
