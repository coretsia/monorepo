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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ConfigRepositoryInterfaceShapeContractTest extends TestCase
{
    public function testConfigRepositoryInterfaceExposesCanonicalMethodsOnly(): void
    {
        $interface = new ReflectionClass(ConfigRepositoryInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['has', 'get', 'all', 'sourceOf', 'explain'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testHasMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ConfigRepositoryInterface::class, 'has');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('keyPath', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), 'bool', false);
    }

    public function testGetMethodShapeIsDefaultAwareAndStable(): void
    {
        $method = new \ReflectionMethod(ConfigRepositoryInterface::class, 'get');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('keyPath', $parameters[0]->getName());
        self::assertSame('default', $parameters[1]->getName());

        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertSame('mixed', (string)$parameters[1]->getType());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertSame('mixed', (string)$method->getReturnType());
    }

    public function testAllMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ConfigRepositoryInterface::class, 'all');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        self::assertNamedType($method->getReturnType(), 'array', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return array<string,mixed>', $docComment);
    }

    public function testSourceOfMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ConfigRepositoryInterface::class, 'sourceOf');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('keyPath', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), ConfigValueSource::class, true);
    }

    public function testExplainMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(ConfigRepositoryInterface::class, 'explain');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        self::assertNamedType($method->getReturnType(), 'array', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return list<ConfigValueSource>', $docComment);
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
