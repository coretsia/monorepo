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

use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootResolvesResetOrchestratorTest extends TestCase
{
    public function testArtifactOnlyBootResolvesResetOrchestratorFromCompiledContainerArtifact(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-only-reset-orchestrator');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    FoundationServiceProvider::class,
                    ArtifactOnlyBootResolvesResetOrchestratorProvider::class,
                ]),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

            self::assertTrue($container->has(ResetOrchestrator::class));
            self::assertTrue($container->has(TagRegistry::class));
            self::assertTrue($container->has(ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class));

            $orchestrator = $container->get(ResetOrchestrator::class);

            self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
            self::assertSame(ReservedTags::KERNEL_RESET, $orchestrator->effectiveResetTag());
            self::assertFalse($orchestrator->priorityEnabled());

            $resetSpy = $container->get(ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class);

            self::assertInstanceOf(ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class, $resetSpy);
            self::assertSame(0, $resetSpy->resetCount());

            $orchestrator->resetAll();

            self::assertSame(
                1,
                $resetSpy->resetCount(),
                'ResetOrchestrator must execute reset services discovered through the compiled TagRegistry.',
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}

final class ArtifactOnlyBootResolvesResetOrchestratorProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->classService(
                ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class,
                ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class,
            )
            ->tag(
                tag: ReservedTags::KERNEL_RESET,
                serviceId: ArtifactOnlyBootResolvesResetOrchestratorResetSpy::class,
                priority: 100,
            );
    }
}

final class ArtifactOnlyBootResolvesResetOrchestratorResetSpy implements ResetInterface
{
    private int $resetCount = 0;

    public function reset(): void
    {
        ++$this->resetCount;
    }

    public function resetCount(): int
    {
        return $this->resetCount;
    }
}
