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
use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

final class ContextStoreIsTaggedWithEffectiveResetTagTest extends TestCase
{
    public function testContextStoreIsTaggedWithDefaultKernelResetTag(): void
    {
        $container = self::foundationContainer();
        $tagRegistry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);

        $taggedServices = $tagRegistry->all(ReservedTags::KERNEL_RESET);

        self::assertCount(1, $taggedServices);
        self::assertSame(ContextStore::class, $taggedServices[0]->id());
        self::assertSame(0, $taggedServices[0]->priority());
        self::assertSame([], $taggedServices[0]->meta());
    }

    public function testResetOrchestratorUsesDefaultKernelResetTagAndResetsContextStore(): void
    {
        $container = self::foundationContainer();

        $store = $container->get(ContextStore::class);
        $orchestrator = $container->get(ResetOrchestrator::class);

        self::assertInstanceOf(ContextStore::class, $store);
        self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
        self::assertSame(ReservedTags::KERNEL_RESET, $orchestrator->effectiveResetTag());

        $store->set(ContextKeys::CORRELATION_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');

        self::assertTrue($store->has(ContextKeys::CORRELATION_ID));

        $orchestrator->resetAll();

        self::assertFalse($store->has(ContextKeys::CORRELATION_ID));
        self::assertSame([], $store->all());
    }

    public function testContextStoreIsTaggedWithCustomEffectiveResetTag(): void
    {
        $customResetTag = 'runtime.reset';
        $container = self::foundationContainer([
            'reset' => [
                'tag' => $customResetTag,
            ],
        ]);

        $tagRegistry = $container->get(TagRegistry::class);
        $orchestrator = $container->get(ResetOrchestrator::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);
        self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
        self::assertSame($customResetTag, $orchestrator->effectiveResetTag());

        $customTaggedServices = $tagRegistry->all($customResetTag);
        $defaultTaggedServices = $tagRegistry->all(ReservedTags::KERNEL_RESET);

        self::assertCount(1, $customTaggedServices);
        self::assertSame(ContextStore::class, $customTaggedServices[0]->id());
        self::assertSame([], $defaultTaggedServices);
    }

    public function testResetOrchestratorUsesCustomEffectiveResetTagAndResetsContextStore(): void
    {
        $container = self::foundationContainer([
            'reset' => [
                'tag' => 'runtime.reset',
            ],
        ]);

        $store = $container->get(ContextStore::class);
        $orchestrator = $container->get(ResetOrchestrator::class);

        self::assertInstanceOf(ContextStore::class, $store);
        self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
        self::assertSame('runtime.reset', $orchestrator->effectiveResetTag());

        $store->set(ContextKeys::UOW_ID, 'uow-custom-reset');

        self::assertTrue($store->has(ContextKeys::UOW_ID));

        $orchestrator->resetAll();

        self::assertFalse($store->has(ContextKeys::UOW_ID));
        self::assertSame([], $store->all());
    }

    /**
     * @param array<string,mixed> $foundationOverrides
     */
    private static function foundationContainer(array $foundationOverrides = []): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => \array_replace_recursive(
                [
                    'container' => [
                        'autowire_concrete' => false,
                        'allow_reflection_for_concrete' => false,
                    ],
                ],
                $foundationOverrides,
            ),
        ]);

        $builder->register(new FoundationServiceProvider());

        return $builder->build();
    }
}
