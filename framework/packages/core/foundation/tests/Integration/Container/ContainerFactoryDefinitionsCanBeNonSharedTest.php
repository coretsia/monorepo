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

namespace Coretsia\Foundation\Tests\Integration\Container;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use PHPUnit\Framework\TestCase;

final class ContainerFactoryDefinitionsCanBeNonSharedTest extends TestCase
{
    public function testFactoryDefinitionCanBeNonShared(): void
    {
        $counter = 0;

        $builder = new ContainerBuilder(config: []);
        $builder->factory(
            id: 'test.non_shared_factory',
            factory: static function (Container $_container) use (
                &$counter
            ): ContainerFactoryDefinitionsCanBeNonSharedSubject {
                return new ContainerFactoryDefinitionsCanBeNonSharedSubject(++$counter);
            },
            shared: false,
        );

        $container = $builder->build();

        $first = $container->get('test.non_shared_factory');
        $second = $container->get('test.non_shared_factory');

        self::assertInstanceOf(ContainerFactoryDefinitionsCanBeNonSharedSubject::class, $first);
        self::assertInstanceOf(ContainerFactoryDefinitionsCanBeNonSharedSubject::class, $second);

        self::assertNotSame(
            $first,
            $second,
            'A factory definition registered with shared=false must produce a fresh object on every get().',
        );
        self::assertSame(1, $first->sequence);
        self::assertSame(2, $second->sequence);
    }

    public function testFactoryDefinitionIsSharedByDefaultWhenSharedTrue(): void
    {
        $counter = 0;

        $builder = new ContainerBuilder(config: []);
        $builder->factory(
            id: 'test.shared_factory',
            factory: static function (Container $_container) use (
                &$counter
            ): ContainerFactoryDefinitionsCanBeNonSharedSubject {
                return new ContainerFactoryDefinitionsCanBeNonSharedSubject(++$counter);
            },
            shared: true,
        );

        $container = $builder->build();

        $first = $container->get('test.shared_factory');
        $second = $container->get('test.shared_factory');

        self::assertInstanceOf(ContainerFactoryDefinitionsCanBeNonSharedSubject::class, $first);
        self::assertInstanceOf(ContainerFactoryDefinitionsCanBeNonSharedSubject::class, $second);

        self::assertSame(
            $first,
            $second,
            'A factory definition registered with shared=true must be cached after first resolution.',
        );
        self::assertSame(1, $first->sequence);
        self::assertSame(1, $second->sequence);
    }
}

final readonly class ContainerFactoryDefinitionsCanBeNonSharedSubject
{
    public function __construct(
        public int $sequence,
    ) {
    }
}
