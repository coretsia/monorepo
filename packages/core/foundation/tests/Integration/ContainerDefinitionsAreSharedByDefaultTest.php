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

use Coretsia\Foundation\Container\Container;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionsAreSharedByDefaultTest extends TestCase
{
    public function testExplicitDefinitionsAreSharedByDefault(): void
    {
        $container = new Container(
            definitions: [
                'service' => static fn (Container $_container): object => new \stdClass(),
            ],
            config: self::foundationConfig(),
        );

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertSame($first, $second);
    }

    public function testExplicitDefinitionsCanBeMarkedNonShared(): void
    {
        $container = new Container(
            definitions: [
                'service' => static fn (Container $_container): object => new \stdClass(),
            ],
            config: self::foundationConfig(),
            definitionShared: [
                'service' => false,
            ],
        );

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertNotSame($first, $second);
    }

    public function testUnregisteredConcreteAutowireIsNotCachedAsResolvedService(): void
    {
        $container = new Container(
            config: self::foundationConfig(),
        );

        $first = $container->get(ContainerAutowireTransientSubject::class);
        $second = $container->get(ContainerAutowireTransientSubject::class);

        self::assertInstanceOf(ContainerAutowireTransientSubject::class, $first);
        self::assertInstanceOf(ContainerAutowireTransientSubject::class, $second);
        self::assertNotSame(
            $first,
            $second,
            'Unregistered concrete-class autowire must not be cached as a resolved service.',
        );

        self::assertNotContains(
            ContainerAutowireTransientSubject::class,
            $container->serviceIds(),
            'Unregistered concrete-class autowire must not grow the known service id list.',
        );
    }

    public function testUnregisteredConcreteAutowireResolvesConstructorDependenciesThroughContainer(): void
    {
        $container = new Container(
            config: self::foundationConfig(),
        );

        $subject = $container->get(ContainerAutowireSubjectWithDependency::class);

        self::assertInstanceOf(ContainerAutowireSubjectWithDependency::class, $subject);
        self::assertInstanceOf(ContainerAutowireDependency::class, $subject->dependency);
        self::assertNotContains(
            ContainerAutowireSubjectWithDependency::class,
            $container->serviceIds(),
            'Unregistered constructor-autowired subjects must not grow the known service id list.',
        );
        self::assertNotContains(
            ContainerAutowireDependency::class,
            $container->serviceIds(),
            'Unregistered constructor-autowired dependencies must not grow the known service id list.',
        );
    }

    public function testExplicitClassStringDefinitionsRemainSharedByDefault(): void
    {
        $container = new Container(
            definitions: [
                'service' => ContainerAutowireTransientSubject::class,
            ],
            config: self::foundationConfig(),
        );

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertInstanceOf(ContainerAutowireTransientSubject::class, $first);
        self::assertSame(
            $first,
            $second,
            'Explicit class-string definitions remain shared by service id by default.',
        );

        self::assertContains('service', $container->serviceIds());
    }

    /**
     * @return array<string, mixed>
     */
    private static function foundationConfig(): array
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

final class ContainerAutowireTransientSubject
{
}

final class ContainerAutowireDependency
{
}

final class ContainerAutowireSubjectWithDependency
{
    public function __construct(
        public readonly ContainerAutowireDependency $dependency,
    ) {
    }
}
