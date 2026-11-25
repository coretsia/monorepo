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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use PHPUnit\Framework\TestCase;

final class FoundationResolvesContextStoreBindingsTest extends TestCase
{
    public function testFoundationProviderResolvesContextStoreAndContextPortsToSameInstance(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(ContextStore::class));
        self::assertTrue($container->has(ContextAccessorInterface::class));
        self::assertTrue($container->has(ContextWriterInterface::class));

        $store = $container->get(ContextStore::class);
        $accessor = $container->get(ContextAccessorInterface::class);
        $writer = $container->get(ContextWriterInterface::class);

        self::assertInstanceOf(ContextStore::class, $store);
        self::assertInstanceOf(ContextAccessorInterface::class, $accessor);
        self::assertInstanceOf(ContextWriterInterface::class, $writer);
        self::assertSame($store, $accessor);
        self::assertSame($store, $writer);

        $writer->set(ContextKeys::UOW_TYPE, 'http');

        self::assertTrue($accessor->has(ContextKeys::UOW_TYPE));
        self::assertSame('http', $accessor->get(ContextKeys::UOW_TYPE));

        $writer->remove(ContextKeys::UOW_TYPE);

        self::assertFalse($accessor->has(ContextKeys::UOW_TYPE));
        self::assertNull($accessor->get(ContextKeys::UOW_TYPE));

        $writer->set(ContextKeys::UOW_TYPE, 'http');
        self::assertSame('http', $accessor->get(ContextKeys::UOW_TYPE));

        $reset = $container->get(ResetOrchestrator::class);
        self::assertInstanceOf(ResetOrchestrator::class, $reset);
        $reset->resetAll();

        self::assertFalse($accessor->has(ContextKeys::UOW_TYPE));
        self::assertNull($accessor->get(ContextKeys::UOW_TYPE));
    }

    public function testFoundationProviderResolvesCorrelationProviderBindingsToSameInstance(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(CorrelationIdProvider::class));
        self::assertTrue($container->has(CorrelationIdProviderInterface::class));

        $provider = $container->get(CorrelationIdProvider::class);
        $providerPort = $container->get(CorrelationIdProviderInterface::class);

        self::assertInstanceOf(CorrelationIdProvider::class, $provider);
        self::assertInstanceOf(CorrelationIdProviderInterface::class, $providerPort);
        self::assertSame($provider, $providerPort);
    }

    public function testFoundationProviderResolvesUlidAndCorrelationIdGenerators(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(UlidGenerator::class));
        self::assertTrue($container->has(CorrelationIdGenerator::class));

        $ulids = $container->get(UlidGenerator::class);
        $correlationIds = $container->get(CorrelationIdGenerator::class);

        self::assertInstanceOf(UlidGenerator::class, $ulids);
        self::assertInstanceOf(CorrelationIdGenerator::class, $correlationIds);

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $ulids->generate(),
        );

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $correlationIds->generate(),
        );
    }

    public function testFoundationContextAndCorrelationServicesResolveWithConcreteAutowireDisabled(): void
    {
        $container = self::foundationContainer();

        self::assertInstanceOf(ContextStore::class, $container->get(ContextStore::class));
        self::assertInstanceOf(ContextAccessorInterface::class, $container->get(ContextAccessorInterface::class));
        self::assertInstanceOf(ContextWriterInterface::class, $container->get(ContextWriterInterface::class));
        self::assertInstanceOf(UlidGenerator::class, $container->get(UlidGenerator::class));
        self::assertInstanceOf(CorrelationIdGenerator::class, $container->get(CorrelationIdGenerator::class));
        self::assertInstanceOf(CorrelationIdProvider::class, $container->get(CorrelationIdProvider::class));
        self::assertInstanceOf(
            CorrelationIdProviderInterface::class,
            $container->get(CorrelationIdProviderInterface::class),
        );
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
