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

use Coretsia\Contracts\Filesystem\DiskInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class FilesystemDiskInterfaceShapeContractTest extends TestCase
{
    public function testDiskInterfaceExistsAndIsInterface(): void
    {
        self::assertTrue(interface_exists(DiskInterface::class));

        $reflection = new ReflectionClass(DiskInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame('Coretsia\Contracts\Filesystem', $reflection->getNamespaceName());
        self::assertSame('DiskInterface', $reflection->getShortName());
    }

    public function testDiskInterfaceExposesExactlyCanonicalPublicMethodSurface(): void
    {
        $reflection = new ReflectionClass(DiskInterface::class);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertSame(
            [
                'exists',
                'read',
                'write',
                'delete',
                'listPaths',
            ],
            array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $methods,
            ),
        );

        $this->assertMethodShape(
            $reflection,
            'exists',
            [
                ['path', 'string', false, false],
            ],
            'bool',
        );

        $this->assertMethodShape(
            $reflection,
            'read',
            [
                ['path', 'string', false, false],
            ],
            '?string',
        );

        $this->assertMethodShape(
            $reflection,
            'write',
            [
                ['path', 'string', false, false],
                ['contents', 'string', false, false],
            ],
            'void',
        );

        $this->assertMethodShape(
            $reflection,
            'delete',
            [
                ['path', 'string', false, false],
            ],
            'void',
        );

        $this->assertMethodShape(
            $reflection,
            'listPaths',
            [
                ['prefix', 'string', true, true],
            ],
            'array',
        );
    }

    public function testDiskInterfacePublicTypesDoNotUseForbiddenDependencies(): void
    {
        $reflection = new ReflectionClass(DiskInterface::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertTypeIsAllowed($method->getReturnType(), $method->getName() . ' return type');

            foreach ($method->getParameters() as $parameter) {
                $this->assertTypeIsAllowed(
                    $parameter->getType(),
                    $method->getName() . '::$' . $parameter->getName(),
                );
            }
        }
    }

    public function testDiskInterfaceDoesNotDeclareConstants(): void
    {
        $reflection = new ReflectionClass(DiskInterface::class);

        self::assertSame([], $reflection->getConstants());
    }

    public function testDiskInterfaceDoesNotDeclareConfigArtifactOrTagConcepts(): void
    {
        $reflection = new ReflectionClass(DiskInterface::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            self::assertStringNotContainsString('config', strtolower($methodName));
            self::assertStringNotContainsString('artifact', strtolower($methodName));
            self::assertStringNotContainsString('tag', strtolower($methodName));

            foreach ($method->getParameters() as $parameter) {
                $parameterName = $parameter->getName();

                self::assertStringNotContainsString('config', strtolower($parameterName));
                self::assertStringNotContainsString('artifact', strtolower($parameterName));
                self::assertStringNotContainsString('tag', strtolower($parameterName));
            }
        }
    }

    /**
     * @param list<array{0:string,1:string,2:bool,3:bool}> $expectedParameters
     */
    private function assertMethodShape(
        ReflectionClass $reflection,
        string $methodName,
        array $expectedParameters,
        string $expectedReturnType,
    ): void {
        self::assertTrue($reflection->hasMethod($methodName));

        $method = $reflection->getMethod($methodName);

        self::assertSame(DiskInterface::class, $method->getDeclaringClass()->getName());
        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertFalse($method->isFinal());

        self::assertSame($expectedReturnType, $this->typeToString($method->getReturnType()));

        $parameters = $method->getParameters();

        self::assertCount(count($expectedParameters), $parameters);

        foreach ($expectedParameters as $index => [$name, $type, $hasDefaultValue, $isDefaultValueAvailable]) {
            self::assertArrayHasKey($index, $parameters);

            $parameter = $parameters[$index];

            self::assertSame($name, $parameter->getName());
            self::assertSame($type, $this->typeToString($parameter->getType()));
            self::assertSame($hasDefaultValue, $parameter->isDefaultValueAvailable());
            self::assertSame($isDefaultValueAvailable, $parameter->isDefaultValueAvailable());

            if ($parameter->isDefaultValueAvailable()) {
                self::assertSame('', $parameter->getDefaultValue());
            }
        }
    }

    private function assertTypeIsAllowed(?ReflectionType $type, string $context): void
    {
        self::assertNotNull($type, $context . ' must declare an explicit type.');

        foreach ($this->flattenTypeNames($type) as $typeName) {
            self::assertNotSame('callable', $typeName, $context . ' must not expose callable.');
            self::assertNotSame('object', $typeName, $context . ' must not expose object.');
            self::assertNotSame('iterable', $typeName, $context . ' must not expose iterable.');
            self::assertNotSame('mixed', $typeName, $context . ' must not expose mixed.');

            foreach ($this->forbiddenTypePrefixes() as $forbiddenPrefix) {
                self::assertFalse(
                    str_starts_with($typeName, $forbiddenPrefix),
                    $context . ' must not use forbidden type ' . $typeName . '.',
                );
            }

            foreach ($this->forbiddenExactTypes() as $forbiddenType) {
                self::assertNotSame(
                    $forbiddenType,
                    $typeName,
                    $context . ' must not use forbidden type ' . $typeName . '.',
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function flattenTypeNames(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];

            foreach ($type->getTypes() as $innerType) {
                foreach ($this->flattenTypeNames($innerType) as $name) {
                    $names[] = $name;
                }
            }

            sort($names, SORT_STRING);

            return $names;
        }

        self::fail('Unsupported reflection type: ' . $type::class);
    }

    private function typeToString(?ReflectionType $type): string
    {
        self::assertNotNull($type);

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $names = $this->flattenTypeNames($type);

            if (in_array('null', $names, true) && count($names) === 2) {
                $nonNullNames = array_values(
                    array_filter(
                        $names,
                        static fn (string $name): bool => $name !== 'null',
                    )
                );

                return '?' . $nonNullNames[0];
            }

            return implode('|', $names);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', $this->flattenTypeNames($type));
        }

        self::fail('Unsupported reflection type: ' . $type::class);
    }

    /**
     * @return list<string>
     */
    private function forbiddenTypePrefixes(): array
    {
        return [
            'Coretsia\\Platform\\',
            'Coretsia\\Integrations\\',
            'Psr\\Http\\Message\\',
            'League\\Flysystem\\',
            'Symfony\\Component\\Filesystem\\',
        ];
    }

    /**
     * @return list<string>
     */
    private function forbiddenExactTypes(): array
    {
        return [
            'Closure',
            'DirectoryIterator',
            'FilesystemIterator',
            'RecursiveDirectoryIterator',
            'SplFileInfo',
        ];
    }
}
