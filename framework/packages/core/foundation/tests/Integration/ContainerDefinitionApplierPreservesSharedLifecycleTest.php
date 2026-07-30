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
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionApplierPreservesSharedLifecycleTest extends TestCase
{
    public function testClassServiceIsSharedByDefault(): void
    {
        $container = new ContainerBuilder(config: [])
            ->applyDefinitions(
                new ContainerDefinitionBuilder()
                    ->classService(
                        id: 'lifecycle.shared',
                        class: ContainerDefinitionLifecycleSubject::class,
                    )
                    ->build(),
            )
            ->build();

        $first = $container->get('lifecycle.shared');
        $second = $container->get('lifecycle.shared');

        self::assertInstanceOf(ContainerDefinitionLifecycleSubject::class, $first);
        self::assertSame($first, $second);
    }

    public function testClassServiceCanBeNonShared(): void
    {
        $container = new ContainerBuilder(config: [])
            ->applyDefinitions(
                new ContainerDefinitionBuilder()
                    ->classService(
                        id: 'lifecycle.non_shared',
                        class: ContainerDefinitionLifecycleSubject::class,
                        shared: false,
                    )
                    ->build(),
            )
            ->build();

        $first = $container->get('lifecycle.non_shared');
        $second = $container->get('lifecycle.non_shared');

        self::assertInstanceOf(ContainerDefinitionLifecycleSubject::class, $first);
        self::assertInstanceOf(ContainerDefinitionLifecycleSubject::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testClassAndServiceMethodFactoriesPreserveNonSharedLifecycle(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classMethodFactory(
                id: 'lifecycle.class_method',
                factoryClass: ContainerDefinitionLifecycleStaticFactory::class,
                method: 'create',
                shared: false,
            )
            ->classService(
                id: 'lifecycle.factory_service',
                class: ContainerDefinitionLifecycleServiceFactory::class,
            )
            ->serviceMethodFactory(
                id: 'lifecycle.service_method',
                factoryServiceId: 'lifecycle.factory_service',
                method: 'create',
                shared: false,
            )
            ->build();

        $container = new ContainerBuilder(config: [])
            ->applyDefinitions($definitions)
            ->build();

        self::assertNotSame(
            $container->get('lifecycle.class_method'),
            $container->get('lifecycle.class_method'),
        );
        self::assertNotSame(
            $container->get('lifecycle.service_method'),
            $container->get('lifecycle.service_method'),
        );
    }

    public function testAliasPreservesSharedAndNonSharedTargetLifecycle(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->classService(
                id: 'lifecycle.shared_target',
                class: ContainerDefinitionLifecycleSubject::class,
            )
            ->alias(
                alias: 'lifecycle.shared_alias',
                serviceId: 'lifecycle.shared_target',
            )
            ->classService(
                id: 'lifecycle.non_shared_target',
                class: ContainerDefinitionLifecycleSubject::class,
                shared: false,
            )
            ->alias(
                alias: 'lifecycle.non_shared_alias',
                serviceId: 'lifecycle.non_shared_target',
            )
            ->build();

        $container = new ContainerBuilder(config: [])
            ->applyDefinitions($definitions)
            ->build();

        self::assertSame(
            $container->get('lifecycle.shared_target'),
            $container->get('lifecycle.shared_alias'),
        );

        $firstAliasResult = $container->get('lifecycle.non_shared_alias');
        $secondAliasResult = $container->get('lifecycle.non_shared_alias');

        self::assertInstanceOf(
            ContainerDefinitionLifecycleSubject::class,
            $firstAliasResult,
        );
        self::assertInstanceOf(
            ContainerDefinitionLifecycleSubject::class,
            $secondAliasResult,
        );
        self::assertNotSame($firstAliasResult, $secondAliasResult);
    }
}

final class ContainerDefinitionLifecycleSubject
{
}

final class ContainerDefinitionLifecycleStaticFactory
{
    public static function create(): ContainerDefinitionLifecycleSubject
    {
        return new ContainerDefinitionLifecycleSubject();
    }
}

final class ContainerDefinitionLifecycleServiceFactory
{
    public function create(): ContainerDefinitionLifecycleSubject
    {
        return new ContainerDefinitionLifecycleSubject();
    }
}
