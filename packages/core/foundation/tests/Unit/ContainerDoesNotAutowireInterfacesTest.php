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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class ContainerDoesNotAutowireInterfacesTest extends TestCase
{
    public function testCanAutowireReturnsFalseForInterfaces(): void
    {
        $container = new Container(config: self::validConfig());

        self::assertFalse($container->canAutowire(ContainerAutowireInterfaceFixtureInterface::class));
    }

    public function testHasReturnsFalseForUnboundInterfaces(): void
    {
        $container = new Container(config: self::validConfig());

        self::assertFalse($container->has(ContainerAutowireInterfaceFixtureInterface::class));
    }

    public function testGetDoesNotAutowireInterfaces(): void
    {
        $container = new Container(config: self::validConfig());

        try {
            $container->get(ContainerAutowireInterfaceFixtureInterface::class);
        } catch (NotFoundException $exception) {
            self::assertSame('container-service-not-found', $exception->getMessage());
            self::assertSame(ContainerAutowireInterfaceFixtureInterface::class, $exception->serviceId());

            return;
        }

        self::fail('Expected container to reject unbound interface autowire.');
    }

    public function testAbstractClassesAreNotAutowired(): void
    {
        $container = new Container(config: self::validConfig());

        self::assertFalse($container->canAutowire(ContainerAutowireAbstractFixture::class));
        self::assertFalse($container->has(ContainerAutowireAbstractFixture::class));

        try {
            $container->get(ContainerAutowireAbstractFixture::class);
        } catch (NotFoundException $exception) {
            self::assertSame('container-service-not-found', $exception->getMessage());
            self::assertSame(ContainerAutowireAbstractFixture::class, $exception->serviceId());

            return;
        }

        self::fail('Expected container to reject abstract-class autowire.');
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

interface ContainerAutowireInterfaceFixtureInterface
{
}

abstract class ContainerAutowireAbstractFixture
{
}
