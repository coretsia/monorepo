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
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Provider\Tags;
use Coretsia\Foundation\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

final class ContextStoreIsTaggedKernelStatefulTest extends TestCase
{
    public function testContextStoreIsTaggedKernelStateful(): void
    {
        $container = self::foundationContainer();
        $tagRegistry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);

        $taggedServices = $tagRegistry->all(Tags::KERNEL_STATEFUL);

        self::assertCount(1, $taggedServices);
        self::assertSame(ContextStore::class, $taggedServices[0]->id());
        self::assertSame(0, $taggedServices[0]->priority());
        self::assertSame([], $taggedServices[0]->meta());
    }

    public function testContextStoreIsNotTaggedThroughContextAccessorInterface(): void
    {
        $container = self::foundationContainer();
        $tagRegistry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);

        $taggedIds = \array_map(
            static fn ($taggedService): string => $taggedService->id(),
            $tagRegistry->all(Tags::KERNEL_STATEFUL),
        );

        self::assertSame([ContextStore::class], $taggedIds);
    }

    private static function foundationContainer(): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
            ],
        ]);

        $builder->register(new FoundationServiceProvider());

        return $builder->build();
    }
}
