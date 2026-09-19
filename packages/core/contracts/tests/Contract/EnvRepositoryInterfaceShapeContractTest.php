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

use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class EnvRepositoryInterfaceShapeContractTest extends TestCase
{
    public function testEnvRepositoryInterfaceExposesCanonicalMethodsOnly(): void
    {
        $interface = new ReflectionClass(EnvRepositoryInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['has', 'get', 'all', 'sourceOf'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testHasMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(EnvRepositoryInterface::class, 'has');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), 'bool', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('Present empty string MUST return true.', $docComment);
    }

    public function testGetMethodReturnsEnvValueNotNullableString(): void
    {
        $method = new \ReflectionMethod(EnvRepositoryInterface::class, 'get');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), EnvValue::class, false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString(
            'Missing and present-empty-string must remain distinct through EnvValue.',
            $docComment
        );
    }

    public function testAllMethodShapeIsStableButDocumentedAsRawRuntimeAccess(): void
    {
        $method = new \ReflectionMethod(EnvRepositoryInterface::class, 'all');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        self::assertNamedType($method->getReturnType(), 'array', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return array<string,string>', $docComment);
        self::assertStringContainsString('MUST NOT print this', $docComment);
    }

    public function testSourceOfMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(EnvRepositoryInterface::class, 'sourceOf');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($method->getReturnType(), ConfigValueSource::class, true);
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
