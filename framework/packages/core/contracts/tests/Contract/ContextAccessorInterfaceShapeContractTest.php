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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class ContextAccessorInterfaceShapeContractTest extends TestCase
{
    public function test_context_accessor_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ContextAccessorInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame('Coretsia\Contracts\Context', $reflection->getNamespaceName());

        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $publicMethodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $publicMethods,
        );

        sort($publicMethodNames, \SORT_STRING);

        self::assertSame(
            [
                'get',
                'has',
            ],
            $publicMethodNames,
        );

        self::assertFalse($reflection->hasMethod('all'));
    }

    public function test_has_method_shape_is_stable(): void
    {
        $method = new ReflectionMethod(ContextAccessorInterface::class, 'has');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('key', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertMethodReturnType($method, 'bool', false);
    }

    public function test_get_method_shape_is_stable(): void
    {
        $method = new ReflectionMethod(ContextAccessorInterface::class, 'get');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('key', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertMethodReturnType($method, 'mixed', true);
    }

    public function test_context_accessor_can_distinguish_missing_key_from_present_null_value(): void
    {
        $accessor = new class([ 'presentNull' => null, 'operation' => 'contract-test', ]) implements ContextAccessorInterface {
            /**
             * @param array<string,mixed> $values
             */
            public function __construct(
                private readonly array $values,
            ) {
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? null;
            }
        };

        self::assertTrue($accessor->has('presentNull'));
        self::assertNull($accessor->get('presentNull'));

        self::assertTrue($accessor->has('operation'));
        self::assertSame('contract-test', $accessor->get('operation'));

        self::assertFalse($accessor->has('missing'));
        self::assertNull($accessor->get('missing'));
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }

    private static function assertMethodReturnType(
        ReflectionMethod $method,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
