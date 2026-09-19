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

use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Definition\ServiceDefinition;
use PHPUnit\Framework\TestCase;

final class ContainerGraphChangesFingerprintTest extends TestCase
{
    public function testEverySupportedSemanticGraphChangeChangesFingerprint(): void
    {
        foreach (self::semanticGraphChanges() as $case => [$before, $after]) {
            self::assertNotSame(
                ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                    testCase: $this,
                    containerGraph: $before,
                ),
                ArtifactPipelineTestSupport::fingerprintForContainerGraph(
                    testCase: $this,
                    containerGraph: $after,
                ),
                $case . ' must change the container-graph fingerprint.',
            );
        }
    }

    /**
     * @return array<string, array{DefinitionGraph, DefinitionGraph}>
     */
    private static function semanticGraphChanges(): array
    {
        $serviceReferenceBase = DefinitionGraph::empty()
            ->withService(
                ServiceDefinition::class(
                    id: ContainerGraphFingerprintDependencyA::class,
                    class: ContainerGraphFingerprintDependencyA::class,
                ),
            )
            ->withService(
                ServiceDefinition::class(
                    id: ContainerGraphFingerprintDependencyB::class,
                    class: ContainerGraphFingerprintDependencyB::class,
                ),
            );

        $aliasBase = DefinitionGraph::empty()
            ->withService(
                ServiceDefinition::class(
                    id: ContainerGraphFingerprintDependencyA::class,
                    class: ContainerGraphFingerprintDependencyA::class,
                ),
            )
            ->withService(
                ServiceDefinition::class(
                    id: ContainerGraphFingerprintDependencyB::class,
                    class: ContainerGraphFingerprintDependencyB::class,
                ),
            );

        $tagBase = DefinitionGraph::empty()
            ->withService(
                ServiceDefinition::class(
                    id: ContainerGraphFingerprintServiceA::class,
                    class: ContainerGraphFingerprintServiceA::class,
                ),
            );

        return [
            'class change' => [
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.service',
                        class: ContainerGraphFingerprintServiceA::class,
                    ),
                ),
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.service',
                        class: ContainerGraphFingerprintServiceB::class,
                    ),
                ),
            ],
            'factory class change' => [
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::factoryClassMethod(
                        id: 'kernel.test.graph.service',
                        factoryClass: ContainerGraphFingerprintFactoryA::class,
                        method: 'create',
                    ),
                ),
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::factoryClassMethod(
                        id: 'kernel.test.graph.service',
                        factoryClass: ContainerGraphFingerprintFactoryB::class,
                        method: 'create',
                    ),
                ),
            ],
            'factory method change' => [
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::factoryClassMethod(
                        id: 'kernel.test.graph.service',
                        factoryClass: ContainerGraphFingerprintFactoryA::class,
                        method: 'createAlpha',
                    ),
                ),
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::factoryClassMethod(
                        id: 'kernel.test.graph.service',
                        factoryClass: ContainerGraphFingerprintFactoryA::class,
                        method: 'createBeta',
                    ),
                ),
            ],
            'service reference change' => [
                $serviceReferenceBase->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.consumer',
                        class: ContainerGraphFingerprintConsumer::class,
                        arguments: [
                            ServiceDefinition::serviceReference(
                                ContainerGraphFingerprintDependencyA::class,
                            ),
                        ],
                    ),
                ),
                $serviceReferenceBase->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.consumer',
                        class: ContainerGraphFingerprintConsumer::class,
                        arguments: [
                            ServiceDefinition::serviceReference(
                                ContainerGraphFingerprintDependencyB::class,
                            ),
                        ],
                    ),
                ),
            ],
            'parameter change' => [
                DefinitionGraph::empty()->withParameter(
                    name: 'kernel.test.graph.parameter',
                    value: 'alpha',
                ),
                DefinitionGraph::empty()->withParameter(
                    name: 'kernel.test.graph.parameter',
                    value: 'beta',
                ),
            ],
            'alias target change' => [
                $aliasBase->withAlias(
                    alias: 'kernel.test.graph.alias',
                    serviceId: ContainerGraphFingerprintDependencyA::class,
                ),
                $aliasBase->withAlias(
                    alias: 'kernel.test.graph.alias',
                    serviceId: ContainerGraphFingerprintDependencyB::class,
                ),
            ],
            'tag priority change' => [
                $tagBase->withTag(
                    tag: 'kernel.test.graph',
                    serviceId: ContainerGraphFingerprintServiceA::class,
                    priority: 10,
                ),
                $tagBase->withTag(
                    tag: 'kernel.test.graph',
                    serviceId: ContainerGraphFingerprintServiceA::class,
                    priority: 20,
                ),
            ],
            'tag metadata change' => [
                $tagBase->withTag(
                    tag: 'kernel.test.graph',
                    serviceId: ContainerGraphFingerprintServiceA::class,
                    meta: [
                        'mode' => 'alpha',
                    ],
                ),
                $tagBase->withTag(
                    tag: 'kernel.test.graph',
                    serviceId: ContainerGraphFingerprintServiceA::class,
                    meta: [
                        'mode' => 'beta',
                    ],
                ),
            ],
            'shared flag change' => [
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.service',
                        class: ContainerGraphFingerprintServiceA::class,
                        shared: true,
                    ),
                ),
                DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'kernel.test.graph.service',
                        class: ContainerGraphFingerprintServiceA::class,
                        shared: false,
                    ),
                ),
            ],
        ];
    }
}

final class ContainerGraphFingerprintServiceA
{
}

final class ContainerGraphFingerprintServiceB
{
}

final class ContainerGraphFingerprintDependencyA
{
}

final class ContainerGraphFingerprintDependencyB
{
}

final class ContainerGraphFingerprintConsumer
{
}

final class ContainerGraphFingerprintFactoryA
{
}

final class ContainerGraphFingerprintFactoryB
{
}
