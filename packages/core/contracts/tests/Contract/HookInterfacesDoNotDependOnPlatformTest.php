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

use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class HookInterfacesDoNotDependOnPlatformTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_TYPE_PREFIXES = [
        'Coretsia\\Kernel\\',
        'Coretsia\\Foundation\\',
        'Coretsia\\Platform\\',
        'Coretsia\\Integrations\\',
        'Psr\\Http\\Message\\',
        'Psr\\Http\\Server\\',
    ];

    public function testBeforeUowHookInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(BeforeUowHookInterface::class));

        $reflection = new ReflectionClass(BeforeUowHookInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testBeforeUowHookInterfaceExposesOnlyBeforeUowVoidWithContextArrayPayload(): void
    {
        $this->assertHookInterfaceShape(
            BeforeUowHookInterface::class,
            'beforeUow',
            ['context'],
        );
    }

    public function testAfterUowHookInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(AfterUowHookInterface::class));

        $reflection = new ReflectionClass(AfterUowHookInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testAfterUowHookInterfaceExposesOnlyAfterUowVoidWithContextAndResultArrayPayloads(): void
    {
        $this->assertHookInterfaceShape(
            AfterUowHookInterface::class,
            'afterUow',
            ['context', 'result'],
        );
    }

    /**
     * @param class-string $interface
     * @param list<non-empty-string> $expectedParameterNames
     */
    private function assertHookInterfaceShape(
        string $interface,
        string $methodName,
        array $expectedParameterNames,
    ): void {
        $reflection = new ReflectionClass($interface);

        self::assertTrue($reflection->isInterface());

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertCount(1, $methods);

        $method = $methods[0];

        self::assertSame($interface, $method->getDeclaringClass()->getName());
        self::assertSame($methodName, $method->getName());

        $parameters = $method->getParameters();

        self::assertCount(\count($expectedParameterNames), $parameters);

        foreach ($expectedParameterNames as $index => $expectedParameterName) {
            $this->assertArrayParameter($parameters[$index], $expectedParameterName);
        }

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());

        $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
    }

    private function assertArrayParameter(
        ReflectionParameter $parameter,
        string $expectedName,
    ): void {
        self::assertSame($expectedName, $parameter->getName());
        self::assertFalse($parameter->isDefaultValueAvailable());

        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame('array', $type->getName());
        self::assertFalse($type->allowsNull());
    }

    private function assertMethodSignatureDoesNotReferenceForbiddenTypes(ReflectionMethod $method): void
    {
        foreach ($method->getParameters() as $parameter) {
            $this->assertParameterSignatureDoesNotReferenceForbiddenTypes($parameter);
        }

        $returnType = $method->getReturnType();

        if ($returnType !== null) {
            $this->assertTypeDoesNotReferenceForbiddenTypes($returnType);
        }
    }

    private function assertParameterSignatureDoesNotReferenceForbiddenTypes(ReflectionParameter $parameter): void
    {
        $type = $parameter->getType();

        if ($type === null) {
            return;
        }

        $this->assertTypeDoesNotReferenceForbiddenTypes($type);
    }

    private function assertTypeDoesNotReferenceForbiddenTypes(ReflectionType $type): void
    {
        foreach ($this->typeNames($type) as $typeName) {
            foreach (self::FORBIDDEN_TYPE_PREFIXES as $forbiddenPrefix) {
                self::assertStringStartsNotWith(
                    $forbiddenPrefix,
                    \ltrim($typeName, '\\'),
                    \sprintf(
                        'Hook contract signature must not reference forbidden type "%s".',
                        $typeName,
                    ),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function typeNames(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];

            foreach ($type->getTypes() as $nestedType) {
                $names = [
                    ...$names,
                    ...$this->typeNames($nestedType),
                ];
            }

            return $names;
        }

        self::fail(
            \sprintf(
                'Unsupported reflection type "%s".',
                $type::class,
            ),
        );
    }
}
