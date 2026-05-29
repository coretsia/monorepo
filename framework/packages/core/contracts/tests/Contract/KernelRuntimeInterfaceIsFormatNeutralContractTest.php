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

use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class KernelRuntimeInterfaceIsFormatNeutralContractTest extends TestCase
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

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_SOURCE_TOKENS = [
        'Coretsia\\Kernel\\',
        'Coretsia\\Foundation\\',
        'Coretsia\\Platform\\',
        'Coretsia\\Integrations\\',
        'Psr\\Http\\Message\\',
        'Psr\\Http\\Server\\',
    ];

    public function testKernelRuntimeInterfaceExistsAndIsAnInterface(): void
    {
        self::assertTrue(interface_exists(KernelRuntimeInterface::class));

        $reflection = new ReflectionClass(KernelRuntimeInterface::class);

        self::assertTrue($reflection->isInterface());
    }

    public function testKernelRuntimeInterfaceExposesCanonicalMethodsOnly(): void
    {
        $reflection = new ReflectionClass(KernelRuntimeInterface::class);

        self::assertSame(
            [
                'afterUnitOfWork',
                'beginUnitOfWork',
                'runUnitOfWork',
            ],
            $this->publicMethodNames($reflection),
        );
    }

    public function testRunUnitOfWorkSignatureIsFormatNeutral(): void
    {
        $method = new ReflectionMethod(KernelRuntimeInterface::class, 'runUnitOfWork');

        self::assertSame(3, $method->getNumberOfParameters());
        self::assertSame(2, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        $this->assertParameter($parameters[0], 'type', 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        $this->assertParameter($parameters[1], 'body', 'callable', false);
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        $this->assertParameter($parameters[2], 'attributes', 'array', false);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertSame([], $parameters[2]->getDefaultValue());

        $this->assertReturnType($method, 'mixed', true);
        $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
    }

    public function testBeginUnitOfWorkSignatureIsFormatNeutral(): void
    {
        $method = new ReflectionMethod(KernelRuntimeInterface::class, 'beginUnitOfWork');

        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        $this->assertParameter($parameters[0], 'type', 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        $this->assertParameter($parameters[1], 'attributes', 'array', false);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame([], $parameters[1]->getDefaultValue());

        $this->assertReturnType($method, 'array', false);
        $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
    }

    public function testAfterUnitOfWorkSignatureIsFormatNeutral(): void
    {
        $method = new ReflectionMethod(KernelRuntimeInterface::class, 'afterUnitOfWork');

        self::assertSame(4, $method->getNumberOfParameters());
        self::assertSame(2, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        $this->assertParameter($parameters[0], 'context', 'array', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        $this->assertParameter($parameters[1], 'outcome', 'string', false);
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        $this->assertParameter($parameters[2], 'error', 'Throwable', true);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertNull($parameters[2]->getDefaultValue());

        $this->assertParameter($parameters[3], 'extensions', 'array', false);
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertSame([], $parameters[3]->getDefaultValue());

        $this->assertReturnType($method, 'array', false);
        $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
    }

    public function testKernelRuntimeInterfaceDoesNotReferenceRuntimeOrTransportNamespaces(): void
    {
        $reflection = new ReflectionClass(KernelRuntimeInterface::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
        }

        $fileName = $reflection->getFileName();

        self::assertIsString($fileName);

        $source = \file_get_contents($fileName);

        self::assertIsString($source);

        foreach (self::FORBIDDEN_SOURCE_TOKENS as $forbiddenToken) {
            self::assertStringNotContainsString(
                $forbiddenToken,
                $source,
                \sprintf(
                    'KernelRuntimeInterface source must not reference forbidden token "%s".',
                    $forbiddenToken,
                ),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function publicMethodNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $method->getName();
        }

        \sort($names, \SORT_STRING);

        return $names;
    }

    private function assertParameter(
        ReflectionParameter $parameter,
        string $expectedName,
        string $expectedTypeName,
        bool $expectedAllowsNull,
    ): void {
        self::assertSame($expectedName, $parameter->getName());

        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedTypeName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }

    private function assertReturnType(
        ReflectionMethod $method,
        string $expectedTypeName,
        bool $expectedAllowsNull,
    ): void {
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedTypeName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }

    private function assertMethodSignatureDoesNotReferenceForbiddenTypes(ReflectionMethod $method): void
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type !== null) {
                $this->assertTypeDoesNotReferenceForbiddenTypes($type);
            }
        }

        $returnType = $method->getReturnType();

        if ($returnType !== null) {
            $this->assertTypeDoesNotReferenceForbiddenTypes($returnType);
        }
    }

    private function assertTypeDoesNotReferenceForbiddenTypes(ReflectionType $type): void
    {
        foreach ($this->typeNames($type) as $typeName) {
            foreach (self::FORBIDDEN_TYPE_PREFIXES as $forbiddenPrefix) {
                self::assertStringStartsNotWith(
                    $forbiddenPrefix,
                    \ltrim($typeName, '\\'),
                    \sprintf(
                        'KernelRuntimeInterface signature must not reference forbidden type "%s".',
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
