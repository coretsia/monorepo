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

use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootHydratesModulePlanTest extends TestCase
{
    public function testHydratesCanonicalModulePlanFromSelectedGeneration(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-only-hydrates-module-plan',
        );
        $moduleResolution = ArtifactPipelineTestSupport::moduleResolution();
        $expectedPlan = $moduleResolution->plan();

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: $moduleResolution,
            );

            self::assertTrue(
                \unlink($root . '/config/roots.php'),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                skeletonRoot: $root,
            );
            $hydratedPlan = $container->get(ModulePlan::class);
            $artifactPayload = ArtifactPipelineTestSupport::moduleManifestPayloadFromArtifact(
                $root,
            );

            self::assertInstanceOf(ModulePlan::class, $hydratedPlan);
            self::assertNotSame($expectedPlan, $hydratedPlan);
            self::assertSame(
                $expectedPlan->toArray(),
                $hydratedPlan->toArray(),
            );
            self::assertSame(
                $artifactPayload,
                $hydratedPlan->toArray(),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
