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
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use PHPUnit\Framework\TestCase;

final class DefaultIdGeneratorResolvesFromConfigTest extends TestCase
{
    public function testDefaultIdGeneratorResolvesToUlidGeneratorWhenConfiguredAsUlid(): void
    {
        $container = self::foundationContainer('ulid');

        self::assertTrue($container->has(IdGeneratorInterface::class));
        self::assertTrue($container->has(UlidGenerator::class));
        self::assertTrue($container->has(UuidGenerator::class));

        $defaultIds = $container->get(IdGeneratorInterface::class);
        $ulids = $container->get(UlidGenerator::class);
        $uuids = $container->get(UuidGenerator::class);

        self::assertInstanceOf(IdGeneratorInterface::class, $defaultIds);
        self::assertInstanceOf(UlidGenerator::class, $defaultIds);
        self::assertInstanceOf(UlidGenerator::class, $ulids);
        self::assertInstanceOf(UuidGenerator::class, $uuids);

        self::assertSame($ulids, $defaultIds);
        self::assertNotSame($uuids, $defaultIds);

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $defaultIds->generate(),
        );
    }

    public function testDefaultIdGeneratorResolvesToUuidGeneratorWhenConfiguredAsUuid(): void
    {
        $container = self::foundationContainer('uuid');

        self::assertTrue($container->has(IdGeneratorInterface::class));
        self::assertTrue($container->has(UlidGenerator::class));
        self::assertTrue($container->has(UuidGenerator::class));

        $defaultIds = $container->get(IdGeneratorInterface::class);
        $ulids = $container->get(UlidGenerator::class);
        $uuids = $container->get(UuidGenerator::class);

        self::assertInstanceOf(IdGeneratorInterface::class, $defaultIds);
        self::assertInstanceOf(UuidGenerator::class, $defaultIds);
        self::assertInstanceOf(UlidGenerator::class, $ulids);
        self::assertInstanceOf(UuidGenerator::class, $uuids);

        self::assertSame($uuids, $defaultIds);
        self::assertNotSame($ulids, $defaultIds);

        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            $defaultIds->generate(),
        );
    }

    public function testUuidDefaultDoesNotMakeCorrelationIdGeneratorUseUuid(): void
    {
        $container = self::foundationContainer('uuid');

        $defaultIds = $container->get(IdGeneratorInterface::class);
        $correlationIds = $container->get(CorrelationIdGenerator::class);

        self::assertInstanceOf(UuidGenerator::class, $defaultIds);
        self::assertInstanceOf(CorrelationIdGenerator::class, $correlationIds);
        self::assertNotSame($defaultIds, $correlationIds);

        self::assertMatchesRegularExpression(
            '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            $correlationIds->generate(),
        );
    }

    private static function foundationContainer(string $defaultId): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => $defaultId,
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
