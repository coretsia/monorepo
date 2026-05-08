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

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use PHPUnit\Framework\TestCase;

final class CorrelationIdProviderReadsContextStoreTest extends TestCase
{
    public function testProviderReturnsNullWhenCorrelationIdIsAbsent(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        self::assertNull($provider->correlationId());
    }

    public function testProviderReturnsCorrelationIdFromContextStore(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $provider->correlationId());
    }

    public function testProviderReturnsNullWhenCorrelationIdIsEmptyString(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, '');

        self::assertNull($provider->correlationId());
    }

    public function testProviderReturnsNullWhenCorrelationIdIsNotString(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, 123);

        self::assertNull($provider->correlationId());
    }

    public function testProviderDoesNotGenerateCorrelationIdAsReadSideEffect(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        self::assertNull($provider->correlationId());
        self::assertFalse($store->has(ContextKeys::CORRELATION_ID));
        self::assertSame([], $store->all());
    }

    public function testContainerResolvedProviderReadsTheSameContextStoreInstance(): void
    {
        $container = self::foundationContainer();

        $store = $container->get(ContextStore::class);
        $provider = $container->get(CorrelationIdProviderInterface::class);

        self::assertInstanceOf(ContextStore::class, $store);
        self::assertInstanceOf(CorrelationIdProviderInterface::class, $provider);
        self::assertNull($provider->correlationId());

        $store->set(ContextKeys::CORRELATION_ID, '01BX5ZZKBKACTAV9WEVGEMMVRZ');

        self::assertSame('01BX5ZZKBKACTAV9WEVGEMMVRZ', $provider->correlationId());
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
