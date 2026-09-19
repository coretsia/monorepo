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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Config\ConfigLoaderInterface;
use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ConfigLoaderInterfaceShapeContractTest extends TestCase
{
    public function testConfigLoaderInterfaceExposesLoadOnly(): void
    {
        $interface = new ReflectionClass(ConfigLoaderInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['load'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testLoadReturnsConfigRepositoryInterfaceNotRawArray(): void
    {
        $method = new \ReflectionMethod(ConfigLoaderInterface::class, 'load');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        self::assertNamedType($method->getReturnType(), ConfigRepositoryInterface::class, false);
    }

    private static function assertNamedType(
        ?\ReflectionType $type,
        string $expectedName,
        bool $allowsNull,
    ): void {
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }
}
