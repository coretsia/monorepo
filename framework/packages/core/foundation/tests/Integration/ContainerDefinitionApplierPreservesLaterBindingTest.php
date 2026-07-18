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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionApplierPreservesLaterBindingTest extends TestCase
{
    public function testLaterServiceDefinitionOverridesEarlierDefinition(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classService(
                id: 'binding.service',
                class: ContainerDefinitionEarlierBindingSubject::class,
            )
            ->classService(
                id: 'binding.service',
                class: ContainerDefinitionLaterBindingSubject::class,
            )
            ->build();

        $container = new ContainerBuilder(config: [])
            ->applyDefinitions($definitions)
            ->build();

        self::assertInstanceOf(
            ContainerDefinitionLaterBindingSubject::class,
            $container->get('binding.service'),
        );
    }

    public function testParameterReferenceUsesFinalLaterBindingValue(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classService(
                id: 'binding.parameter_consumer',
                class: ContainerDefinitionParameterConsumer::class,
                arguments: [
                    ContainerValueReference::parameter('binding.value'),
                ],
            )
            ->parameter('binding.value', 'first')
            ->parameter('binding.value', 'second')
            ->build();

        $container = new ContainerBuilder(config: [])
            ->applyDefinitions($definitions)
            ->build();

        $consumer = $container->get('binding.parameter_consumer');

        self::assertInstanceOf(
            ContainerDefinitionParameterConsumer::class,
            $consumer,
        );
        self::assertSame('second', $consumer->value);
    }

    public function testLaterAliasDefinitionOverridesEarlierAliasDefinition(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classService(
                id: 'binding.target.first',
                class: ContainerDefinitionEarlierBindingSubject::class,
            )
            ->classService(
                id: 'binding.target.second',
                class: ContainerDefinitionLaterBindingSubject::class,
            )
            ->alias(
                alias: 'binding.alias',
                serviceId: 'binding.target.first',
            )
            ->alias(
                alias: 'binding.alias',
                serviceId: 'binding.target.second',
            )
            ->build();

        $container = new ContainerBuilder(config: [])
            ->applyDefinitions($definitions)
            ->build();

        self::assertInstanceOf(
            ContainerDefinitionLaterBindingSubject::class,
            $container->get('binding.alias'),
        );
    }

    public function testOneContainerBuilderRejectsASecondDefinitionSetApplication(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classService(
                id: 'binding.service',
                class: ContainerDefinitionLaterBindingSubject::class,
            )
            ->build();

        $builder = new ContainerBuilder(config: []);
        $builder->applyDefinitions($definitions);

        try {
            $builder->applyDefinitions($definitions);
        } catch (ContainerException $exception) {
            self::assertSame(
                'container-definition-set-already-applied',
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'Expected ContainerBuilder to reject a second declarative definition-set application.',
        );
    }
}

final class ContainerDefinitionEarlierBindingSubject
{
}

final class ContainerDefinitionLaterBindingSubject
{
}

final readonly class ContainerDefinitionParameterConsumer
{
    public function __construct(
        public string $value,
    ) {
    }
}
