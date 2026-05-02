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
        'Psr\\Http\\Message\\',
        'Coretsia\\Platform\\',
        'Coretsia\\Integrations\\',
    ];

    public function testBeforeUowHookInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(BeforeUowHookInterface::class));

        $reflection = new ReflectionClass(BeforeUowHookInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testBeforeUowHookInterfaceExposesOnlyBeforeUowVoidWithoutParameters(): void
    {
        $this->assertHookInterfaceShape(
            BeforeUowHookInterface::class,
            'beforeUow',
        );
    }

    public function testAfterUowHookInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(AfterUowHookInterface::class));

        $reflection = new ReflectionClass(AfterUowHookInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testAfterUowHookInterfaceExposesOnlyAfterUowVoidWithoutParameters(): void
    {
        $this->assertHookInterfaceShape(
            AfterUowHookInterface::class,
            'afterUow',
        );
    }

    /**
     * @param class-string $interface
     */
    private function assertHookInterfaceShape(string $interface, string $methodName): void
    {
        $reflection = new ReflectionClass($interface);

        self::assertTrue($reflection->isInterface());

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertCount(1, $methods);

        $method = $methods[0];

        self::assertSame($interface, $method->getDeclaringClass()->getName());
        self::assertSame($methodName, $method->getName());
        self::assertSame([], $method->getParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());

        $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
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
                    ltrim($typeName, '\\'),
                    sprintf(
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
            sprintf(
                'Unsupported reflection type "%s".',
                $type::class,
            )
        );
    }
}
