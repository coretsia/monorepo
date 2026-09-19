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
use Coretsia\Foundation\Container\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;

final class ContainerBuilderLaterBindingOverridesEarlierBindingTest extends TestCase
{
    public function testLaterProviderBindingOverridesEarlierProviderBindingDeterministically(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new FirstContainerBuilderOverrideProvider(),
            new SecondContainerBuilderOverrideProvider(),
        );

        $container = $builder->build();

        self::assertSame('second', $container->get('service.override.scalar'));
    }

    public function testLaterInterfaceBindingOverridesEarlierInterfaceBindingDeterministically(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new FirstContainerBuilderOverrideProvider(),
            new SecondContainerBuilderOverrideProvider(),
        );

        $container = $builder->build();
        $service = $container->get(ContainerBuilderOverrideContract::class);

        self::assertInstanceOf(SecondContainerBuilderOverrideImplementation::class, $service);
        self::assertSame('second', $service->value());
    }

    public function testLaterInstanceOverridesEarlierDefinitionDeterministically(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new FirstContainerBuilderOverrideProvider(),
            new InstanceContainerBuilderOverrideProvider(),
        );

        $container = $builder->build();
        $service = $container->get(ContainerBuilderOverrideContract::class);

        self::assertInstanceOf(InstanceContainerBuilderOverrideImplementation::class, $service);
        self::assertSame('instance', $service->value());
    }

    public function testLaterDefinitionOverridesEarlierInstanceDeterministically(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new InstanceContainerBuilderOverrideProvider(),
            new SecondContainerBuilderOverrideProvider(),
        );

        $container = $builder->build();
        $service = $container->get(ContainerBuilderOverrideContract::class);

        self::assertInstanceOf(SecondContainerBuilderOverrideImplementation::class, $service);
        self::assertSame('second', $service->value());
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}

interface ContainerBuilderOverrideContract
{
    public function value(): string;
}

final class FirstContainerBuilderOverrideImplementation implements ContainerBuilderOverrideContract
{
    public function value(): string
    {
        return 'first';
    }
}

final class SecondContainerBuilderOverrideImplementation implements ContainerBuilderOverrideContract
{
    public function value(): string
    {
        return 'second';
    }
}

final class InstanceContainerBuilderOverrideImplementation implements ContainerBuilderOverrideContract
{
    public function value(): string
    {
        return 'instance';
    }
}

final class FirstContainerBuilderOverrideProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->bind('service.override.scalar', 'first');

        $builder->bind(
            ContainerBuilderOverrideContract::class,
            FirstContainerBuilderOverrideImplementation::class,
        );
    }
}

final class SecondContainerBuilderOverrideProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->bind('service.override.scalar', 'second');

        $builder->bind(
            ContainerBuilderOverrideContract::class,
            SecondContainerBuilderOverrideImplementation::class,
        );
    }
}

final class InstanceContainerBuilderOverrideProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->instance(
            ContainerBuilderOverrideContract::class,
            new InstanceContainerBuilderOverrideImplementation(),
        );
    }
}
