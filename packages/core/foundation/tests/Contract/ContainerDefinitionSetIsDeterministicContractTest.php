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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionSetIsDeterministicContractTest extends TestCase
{
    public function testEquivalentDefinitionsProduceIdenticalCanonicalState(): void
    {
        $first = self::firstEquivalentSet();
        $second = self::secondEquivalentSet();

        self::assertSame(
            $first->toDescriptorStream(),
            $second->toDescriptorStream(),
        );
        self::assertSame(
            $first->requiredServiceIds(),
            $second->requiredServiceIds(),
        );

        self::assertSame(
            [
                [
                    'kind' => 'parameter',
                    'name' => 'application.options',
                    'value' => [
                        'Alpha' => [
                            'a' => 1,
                            'b' => 2,
                        ],
                        'alpha' => [
                            'kept',
                            'in-order',
                        ],
                        'zeta' => true,
                    ],
                ],
                [
                    'arguments' => [
                        [
                            ContainerDefinitionDeterministicStaticFactory::class,
                            'create',
                        ],
                        [
                            'a' => 1,
                            'z' => 2,
                        ],
                        [
                            'id' => 'dependency.service',
                            'type' => 'service',
                        ],
                        [
                            'name' => 'application.options',
                            'type' => 'parameter',
                        ],
                        [
                            'class' => ContainerDefinitionDeterministicSubject::class,
                            'type' => 'class',
                        ],
                    ],
                    'class' => ContainerDefinitionDeterministicSubject::class,
                    'id' => 'definition.subject',
                    'kind' => 'service.class',
                    'shared' => true,
                ],
                [
                    'alias' => 'definition.subject.alias',
                    'kind' => 'alias',
                    'serviceId' => 'definition.subject',
                ],
                [
                    'kind' => 'tag',
                    'meta' => [
                        'Alpha' => [
                            'a' => 1,
                            'b' => 2,
                        ],
                        'zeta' => true,
                    ],
                    'priority' => 25,
                    'serviceId' => 'definition.subject',
                    'tag' => 'test.definition',
                ],
            ],
            $first->toDescriptorStream(),
        );

        self::assertSame(
            [
                'dependency.alpha',
                'dependency.zeta',
            ],
            $first->requiredServiceIds(),
        );
    }

    public function testMergePreservesSetAndOperationOrderAndCanonicalizesRequiredIds(): void
    {
        $first = new ContainerDefinitionBuilder()
            ->parameter('binding.value', 'first')
            ->tag(
                tag: 'test.definition',
                serviceId: 'service.first',
                priority: 10,
            )
            ->requireService('dependency.zeta')
            ->build();

        $second = new ContainerDefinitionBuilder()
            ->classService(
                id: 'service.second',
                class: ContainerDefinitionDeterministicSubject::class,
            )
            ->alias(
                alias: 'service.second.alias',
                serviceId: 'service.second',
            )
            ->requireService('dependency.alpha')
            ->requireService('dependency.zeta')
            ->build();

        $merged = ContainerDefinitionSet::merge($first, $second);

        self::assertSame(
            [
                ...$first->toDescriptorStream(),
                ...$second->toDescriptorStream(),
            ],
            $merged->toDescriptorStream(),
        );
        self::assertSame(
            [
                'dependency.alpha',
                'dependency.zeta',
            ],
            $merged->requiredServiceIds(),
        );
    }

    private static function firstEquivalentSet(): ContainerDefinitionSet
    {
        return new ContainerDefinitionBuilder()
            ->parameter(
                'application.options',
                [
                    'zeta' => true,
                    'alpha' => [
                        'kept',
                        'in-order',
                    ],
                    'Alpha' => [
                        'b' => 2,
                        'a' => 1,
                    ],
                ],
            )
            ->classService(
                id: 'definition.subject',
                class: ContainerDefinitionDeterministicSubject::class,
                arguments: [
                    [
                        ContainerDefinitionDeterministicStaticFactory::class,
                        'create',
                    ],
                    [
                        'z' => 2,
                        'a' => 1,
                    ],
                    ContainerValueReference::service('dependency.service'),
                    ContainerValueReference::parameter('application.options'),
                    ContainerValueReference::class(
                        ContainerDefinitionDeterministicSubject::class,
                    ),
                ],
            )
            ->alias(
                alias: 'definition.subject.alias',
                serviceId: 'definition.subject',
            )
            ->tag(
                tag: 'test.definition',
                serviceId: 'definition.subject',
                priority: 25,
                meta: [
                    'zeta' => true,
                    'Alpha' => [
                        'b' => 2,
                        'a' => 1,
                    ],
                ],
            )
            ->requireService('dependency.zeta')
            ->requireService('dependency.alpha')
            ->requireService('dependency.zeta')
            ->build();
    }

    private static function secondEquivalentSet(): ContainerDefinitionSet
    {
        return new ContainerDefinitionBuilder()
            ->parameter(
                'application.options',
                [
                    'Alpha' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'alpha' => [
                        'kept',
                        'in-order',
                    ],
                    'zeta' => true,
                ],
            )
            ->classService(
                id: 'definition.subject',
                class: ContainerDefinitionDeterministicSubject::class,
                arguments: [
                    [
                        ContainerDefinitionDeterministicStaticFactory::class,
                        'create',
                    ],
                    [
                        'a' => 1,
                        'z' => 2,
                    ],
                    ContainerValueReference::service('dependency.service'),
                    ContainerValueReference::parameter('application.options'),
                    ContainerValueReference::class(
                        ContainerDefinitionDeterministicSubject::class,
                    ),
                ],
            )
            ->alias(
                alias: 'definition.subject.alias',
                serviceId: 'definition.subject',
            )
            ->tag(
                tag: 'test.definition',
                serviceId: 'definition.subject',
                priority: 25,
                meta: [
                    'Alpha' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'zeta' => true,
                ],
            )
            ->requireService('dependency.alpha')
            ->requireService('dependency.zeta')
            ->build();
    }
}

final class ContainerDefinitionDeterministicSubject
{
}

final class ContainerDefinitionDeterministicStaticFactory
{
    public static function create(): ContainerDefinitionDeterministicSubject
    {
        return new ContainerDefinitionDeterministicSubject();
    }
}
