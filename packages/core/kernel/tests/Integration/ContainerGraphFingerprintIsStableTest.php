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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Kernel\Artifacts\Fingerprint\ContainerGraphFingerprintBucketBuilder;
use PHPUnit\Framework\TestCase;

final class ContainerGraphFingerprintIsStableTest extends TestCase
{
    public function testRepeatedProductionGraphCompilationProducesSameFingerprint(): void
    {
        $moduleResolution = ArtifactPipelineTestSupport::moduleResolution([
            StableContainerGraphProviderA::class,
        ]);
        $compiler = ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this);
        $compiledConfig = ArtifactPipelineTestSupport::defaultConfig();

        $firstGraph = $compiler->compile(
            moduleResolution: $moduleResolution,
            compiledConfig: $compiledConfig,
        );
        $secondGraph = $compiler->compile(
            moduleResolution: $moduleResolution,
            compiledConfig: $compiledConfig,
        );

        $bucketBuilder = new ContainerGraphFingerprintBucketBuilder();

        self::assertSame($firstGraph->toArray(), $secondGraph->toArray());
        self::assertSame(
            $bucketBuilder->build($firstGraph),
            $bucketBuilder->build($secondGraph),
        );
        self::assertSame(
            ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                testCase: $this,
                containerGraph: $firstGraph,
            ),
            ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                testCase: $this,
                containerGraph: $secondGraph,
            ),
        );
    }

    public function testProviderClassNameIsNotSeparateGraphFingerprintIdentity(): void
    {
        $compiler = ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this);
        $compiledConfig = ArtifactPipelineTestSupport::defaultConfig();

        $firstGraph = $compiler->compile(
            moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                StableContainerGraphProviderA::class,
            ]),
            compiledConfig: $compiledConfig,
        );
        $secondGraph = $compiler->compile(
            moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                StableContainerGraphProviderB::class,
            ]),
            compiledConfig: $compiledConfig,
        );

        $bucketBuilder = new ContainerGraphFingerprintBucketBuilder();

        self::assertSame($firstGraph->toArray(), $secondGraph->toArray());
        self::assertSame(
            $bucketBuilder->build($firstGraph),
            $bucketBuilder->build($secondGraph),
        );
        self::assertSame(
            ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                testCase: $this,
                containerGraph: $firstGraph,
            ),
            ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                testCase: $this,
                containerGraph: $secondGraph,
            ),
        );
    }
}

final class StableContainerGraphProviderA implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        self::defineGraph($definitions);
    }

    private static function defineGraph(
        ContainerDefinitionBuilder $definitions,
    ): void {
        $definitions
            ->parameter(
                name: 'kernel.test.stable.parameter',
                value: 'stable-value',
            )
            ->classService(
                id: StableContainerGraphService::class,
                class: StableContainerGraphService::class,
            )
            ->alias(
                alias: 'kernel.test.stable.alias',
                serviceId: StableContainerGraphService::class,
            )
            ->tag(
                tag: 'kernel.test.stable',
                serviceId: StableContainerGraphService::class,
                priority: 25,
            );
    }
}

final class StableContainerGraphProviderB implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->parameter(
                name: 'kernel.test.stable.parameter',
                value: 'stable-value',
            )
            ->classService(
                id: StableContainerGraphService::class,
                class: StableContainerGraphService::class,
            )
            ->alias(
                alias: 'kernel.test.stable.alias',
                serviceId: StableContainerGraphService::class,
            )
            ->tag(
                tag: 'kernel.test.stable',
                serviceId: StableContainerGraphService::class,
                priority: 25,
            );
    }
}

final class StableContainerGraphService
{
}
