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

use Coretsia\Contracts\Runtime\ResetInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ResetInterfaceIsMinimalContractTest extends TestCase
{
    public function testResetInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(ResetInterface::class));

        $reflection = new ReflectionClass(ResetInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testResetInterfaceExposesOnlyResetVoidWithoutParameters(): void
    {
        $reflection = new ReflectionClass(ResetInterface::class);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertCount(1, $methods);

        $method = $methods[0];

        self::assertSame(ResetInterface::class, $method->getDeclaringClass()->getName());
        self::assertSame('reset', $method->getName());
        self::assertSame([], $method->getParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());
    }
}
