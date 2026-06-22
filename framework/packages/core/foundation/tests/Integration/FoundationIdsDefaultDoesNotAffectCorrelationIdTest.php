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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use PHPUnit\Framework\TestCase;

final class FoundationIdsDefaultDoesNotAffectCorrelationIdTest extends TestCase
{
    public function testUuidDefaultIdGeneratorDoesNotAffectCorrelationIdGeneratorFormat(): void
    {
        $container = self::foundationContainerWithUuidDefault();

        $defaultIds = $container->get(IdGeneratorInterface::class);
        $ulids = $container->get(UlidGenerator::class);
        $correlationIds = $container->get(CorrelationIdGenerator::class);

        self::assertInstanceOf(UuidGenerator::class, $defaultIds);
        self::assertInstanceOf(UlidGenerator::class, $ulids);
        self::assertInstanceOf(CorrelationIdGenerator::class, $correlationIds);

        self::assertNotSame($defaultIds, $ulids);
        self::assertNotSame($defaultIds, $correlationIds);

        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            $defaultIds->generate(),
        );

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $correlationIds->generate(),
        );
    }

    public function testUuidDefaultIdGeneratorDoesNotAffectCorrelationIdProviderBindingOrReadBehavior(): void
    {
        $container = self::foundationContainerWithUuidDefault();

        $defaultIds = $container->get(IdGeneratorInterface::class);
        $store = $container->get(ContextStore::class);
        $provider = $container->get(CorrelationIdProvider::class);
        $providerPort = $container->get(CorrelationIdProviderInterface::class);
        $correlationIds = $container->get(CorrelationIdGenerator::class);

        self::assertInstanceOf(UuidGenerator::class, $defaultIds);
        self::assertInstanceOf(ContextStore::class, $store);
        self::assertInstanceOf(CorrelationIdProvider::class, $provider);
        self::assertInstanceOf(CorrelationIdProviderInterface::class, $providerPort);
        self::assertSame($provider, $providerPort);

        self::assertNull($providerPort->correlationId());

        $correlationId = $correlationIds->generate();

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $correlationId,
        );

        $store->set(ContextKeys::CORRELATION_ID, $correlationId);

        self::assertSame($correlationId, $providerPort->correlationId());
    }

    private static function foundationContainerWithUuidDefault(): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => 'uuid',
                ],
                'reset' => [
                    'tag' => 'kernel.reset',
                ],
            ],
        ]);

        $builder->register(new FoundationServiceProvider());

        return $builder->build();
    }
}
