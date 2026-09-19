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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModePresetLoaderInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ModePresetLoaderInterfaceShapeContractTest extends TestCase
{
    public function testModePresetLoaderInterfaceShapeIsStable(): void
    {
        $interface = new ReflectionClass(ModePresetLoaderInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['listNames', 'has', 'load', 'tryLoad'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testListNamesShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ModePresetLoaderInterface::class, 'listNames');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        self::assertNamedType($method->getReturnType(), 'array', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return list<non-empty-string>', $docComment);
    }

    public function testHasShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ModePresetLoaderInterface::class, 'has');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), 'bool', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@param non-empty-string $name', $docComment);
    }

    public function testLoadShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ModePresetLoaderInterface::class, 'load');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), ModePresetInterface::class, false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@param non-empty-string $name', $docComment);
    }

    public function testTryLoadShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ModePresetLoaderInterface::class, 'tryLoad');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), ModePresetInterface::class, true);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@param non-empty-string $name', $docComment);
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
