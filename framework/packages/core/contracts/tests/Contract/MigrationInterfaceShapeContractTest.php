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

use Coretsia\Contracts\Database\ConnectionInterface;
use Coretsia\Contracts\Migrations\MigrationInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class MigrationInterfaceShapeContractTest extends TestCase
{
    public function testMigrationInterfaceExistsInCanonicalNamespace(): void
    {
        self::assertTrue(interface_exists(MigrationInterface::class));

        $interface = new ReflectionClass(MigrationInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame('Coretsia\\Contracts\\Migrations', $interface->getNamespaceName());
        self::assertSame('MigrationInterface', $interface->getShortName());
    }

    public function testMigrationInterfaceHasExactPublicSurface(): void
    {
        $interface = new ReflectionClass(MigrationInterface::class);

        self::assertSame(['down', 'up'], $this->publicMethodNames($interface));
    }

    public function testUpAcceptsOnlyConnectionInterfaceAndReturnsVoid(): void
    {
        $method = new ReflectionMethod(MigrationInterface::class, 'up');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('connection', $parameters[0]->getName());
        $this->assertNamedParameterType($parameters[0], ConnectionInterface::class);
        $this->assertNamedReturnType($method, 'void');
    }

    public function testDownAcceptsOnlyConnectionInterfaceAndReturnsVoid(): void
    {
        $method = new ReflectionMethod(MigrationInterface::class, 'down');
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('connection', $parameters[0]->getName());
        $this->assertNamedParameterType($parameters[0], ConnectionInterface::class);
        $this->assertNamedReturnType($method, 'void');
    }

    public function testMigrationInterfaceDoesNotExposeMetadataDiscoveryOrderingOrRunnerMethods(): void
    {
        $interface = new ReflectionClass(MigrationInterface::class);

        foreach (
            [
                'id',
                'name',
                'version',
                'timestamp',
                'description',
                'dependencies',
                'tags',
                'checksum',
                'batch',
                'connectionName',
                'transactional',
                'isTransactional',
                'createdAt',
                'author',
                'moduleId',
                'packageId',
                'discover',
                'order',
                'run',
                'execute',
                'handle',
            ] as $forbiddenMethodName
        ) {
            self::assertFalse(
                $interface->hasMethod($forbiddenMethodName),
                sprintf('MigrationInterface must not expose %s().', $forbiddenMethodName),
            );
        }
    }

    public function testMigrationInterfaceDependsOnlyOnConnectionInterfaceAsObjectType(): void
    {
        $interface = new ReflectionClass(MigrationInterface::class);

        $objectTypeNames = [];

        foreach ($interface->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($this->methodTypeNames($method) as $typeName) {
                if ($this->isBuiltinTypeName($typeName)) {
                    continue;
                }

                $objectTypeNames[] = $typeName;
            }
        }

        $objectTypeNames = array_values(array_unique($objectTypeNames));
        sort($objectTypeNames);

        self::assertSame([ConnectionInterface::class], $objectTypeNames);
    }

    public function testMigrationInterfaceDoesNotExposeVendorPlatformIntegrationOrPsrTypes(): void
    {
        $interface = new ReflectionClass(MigrationInterface::class);

        foreach ($interface->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($this->methodTypeNames($method) as $typeName) {
                $this->assertNotForbiddenVendorOrRuntimeType($typeName);
            }
        }
    }

    public function testMigrationInterfaceDeclaresNoTagConfigOrArtifactConstants(): void
    {
        $interface = new ReflectionClass(MigrationInterface::class);

        self::assertSame([], $interface->getConstants());
    }

    /**
     * @return list<string>
     */
    private function publicMethodNames(ReflectionClass $class): array
    {
        $names = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        sort($names);

        return array_values($names);
    }

    private function assertNamedReturnType(
        ReflectionMethod $method,
        string $expectedName,
        bool $allowsNull = false,
    ): void {
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }

    private function assertNamedParameterType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $allowsNull = false,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }

    /**
     * @return list<string>
     */
    private function methodTypeNames(ReflectionMethod $method): array
    {
        $typeNames = [];

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            $typeNames = array_merge($typeNames, $this->reflectionTypeNames($returnType));
        }

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type !== null) {
                $typeNames = array_merge($typeNames, $this->reflectionTypeNames($type));
            }
        }

        return array_values(array_unique($typeNames));
    }

    /**
     * @return list<string>
     */
    private function reflectionTypeNames(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_values(
                array_merge(
                    ...array_map(
                        fn (ReflectionType $innerType): array => $this->reflectionTypeNames($innerType),
                        $type->getTypes(),
                    ),
                )
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            return array_values(
                array_merge(
                    ...array_map(
                        fn (ReflectionType $innerType): array => $this->reflectionTypeNames($innerType),
                        $type->getTypes(),
                    ),
                )
            );
        }

        self::fail('Unsupported reflection type encountered.');
    }

    private function isBuiltinTypeName(string $typeName): bool
    {
        return in_array(
            $typeName,
            [
                'array',
                'bool',
                'callable',
                'false',
                'float',
                'int',
                'iterable',
                'mixed',
                'never',
                'null',
                'object',
                'string',
                'true',
                'void',
            ],
            true,
        );
    }

    private function assertNotForbiddenVendorOrRuntimeType(string $typeName): void
    {
        $forbiddenExact = [
            'PDO',
            'PDOStatement',
            'PDOException',
            'mysqli',
            'SQLite3',
            'DateTimeInterface',
            'DateTimeImmutable',
            'DateTime',
        ];

        self::assertNotContains($typeName, $forbiddenExact);

        foreach (
            [
                'Psr\\Http\\Message\\',
                'Coretsia\\Platform\\',
                'Coretsia\\Integrations\\',
                'Doctrine\\',
                'Illuminate\\',
                'Cycle\\',
                'Laminas\\',
                'PgSql\\',
            ] as $forbiddenPrefix
        ) {
            self::assertStringStartsNotWith($forbiddenPrefix, $typeName);
        }
    }
}
