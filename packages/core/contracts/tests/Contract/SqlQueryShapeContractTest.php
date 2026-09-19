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

use Coretsia\Contracts\Database\SqlQuery;
use Coretsia\Contracts\Database\SqlQueryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

final class SqlQueryShapeContractTest extends TestCase
{
    public function testSqlQueryInterfaceExistsAndHasCanonicalSurface(): void
    {
        self::assertTrue(interface_exists(SqlQueryInterface::class));

        $interface = new ReflectionClass(SqlQueryInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(
            ['bindings', 'sql'],
            $this->publicMethodNames($interface),
        );

        $sql = $interface->getMethod('sql');
        self::assertSame([], $sql->getParameters());
        $this->assertNamedReturnType($sql, 'string');

        $bindings = $interface->getMethod('bindings');
        self::assertSame([], $bindings->getParameters());
        $this->assertNamedReturnType($bindings, 'array');

        $this->assertMethodDocblockContainsLine(
            $bindings,
            '@return list<int|string|bool|null>',
        );
    }

    public function testSqlQueryClassExistsAndHasCanonicalShape(): void
    {
        self::assertTrue(class_exists(SqlQuery::class));

        $class = new ReflectionClass(SqlQuery::class);

        self::assertFalse($class->isInterface());
        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());
        self::assertTrue($class->implementsInterface(SqlQueryInterface::class));
        self::assertFalse($class->hasMethod('__toString'));

        self::assertSame(
            ['__construct', 'bindings', 'sql'],
            $this->publicMethodNames($class),
        );

        $constructor = $class->getConstructor();
        self::assertInstanceOf(ReflectionMethod::class, $constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('sql', $parameters[0]->getName());
        $this->assertNamedParameterType($parameters[0], 'string');
        self::assertFalse($parameters[0]->isOptional());

        self::assertSame('bindings', $parameters[1]->getName());
        $this->assertNamedParameterType($parameters[1], 'array');
        self::assertTrue($parameters[1]->isOptional());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame([], $parameters[1]->getDefaultValue());

        $this->assertMethodDocblockContainsLine(
            $constructor,
            '@param list<int|string|bool|null> $bindings',
        );

        $sql = $class->getMethod('sql');
        self::assertSame([], $sql->getParameters());
        $this->assertNamedReturnType($sql, 'string');

        $bindings = $class->getMethod('bindings');
        self::assertSame([], $bindings->getParameters());
        $this->assertNamedReturnType($bindings, 'array');

        $this->assertMethodDocblockContainsLine(
            $bindings,
            '@return list<int|string|bool|null>',
        );
    }

    public function testSqlQueryHasNoDtoMarkerAttribute(): void
    {
        $class = new ReflectionClass(SqlQuery::class);

        $attributeNames = array_map(
            static fn (ReflectionAttribute $attribute): string => $attribute->getName(),
            $class->getAttributes(),
        );

        self::assertNotContains('Coretsia\\Dto\\Attribute\\Dto', $attributeNames);
    }

    public function testBindingsPreserveListOrder(): void
    {
        $query = new SqlQuery(
            'SELECT * FROM users WHERE id = ? AND email = ? AND active = ? AND deleted_at IS ?',
            [10, 'user@example.com', true, null],
        );

        self::assertSame(
            [10, 'user@example.com', true, null],
            $query->bindings(),
        );
    }

    public function testAssociativeBindingsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query bindings must be a positional list.');

        new SqlQuery('SELECT * FROM users WHERE id = ?', ['id' => 10]);
    }

    #[DataProvider('invalidBindingProvider')]
    public function testInvalidBindingValuesAreRejected(mixed $binding): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query bindings must contain only int, string, bool, or null values.');

        new SqlQuery('SELECT * FROM users WHERE value = ?', [$binding]);
    }

    /**
     * @return iterable<string,array{0:mixed}>
     */
    public static function invalidBindingProvider(): iterable
    {
        yield 'float' => [1.25];
        yield 'nested-array' => [['nested']];
        yield 'object' => [new stdClass()];
        yield 'closure' => [static fn (): null => null];
    }

    public function testResourceBindingIsRejected(): void
    {
        $resource = fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('SQL query bindings must contain only int, string, bool, or null values.');

            new SqlQuery('SELECT * FROM users WHERE stream = ?', [$resource]);
        } finally {
            fclose($resource);
        }
    }

    public function testEmptySqlStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query must be a non-empty string.');

        new SqlQuery('');
    }

    public function testWhitespaceOnlySqlStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query must be a non-empty string.');

        new SqlQuery(" \t\n\r ");
    }

    public function testMultilineNonEmptySqlStringIsAccepted(): void
    {
        $sql = <<<'SQL'
SELECT
    id,
    email
FROM users
WHERE active = ?
SQL;

        $query = new SqlQuery($sql, [true]);

        self::assertSame($sql, $query->sql());
        self::assertSame([true], $query->bindings());
    }

    public function testRawSqlIsNotExposedThroughValidationExceptionMessages(): void
    {
        $rawSql = "SELECT * FROM users WHERE password = 'super-secret-password'";

        try {
            new SqlQuery($rawSql, [1.25]);

            self::fail('Expected SqlQuery to reject float bindings.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($rawSql, $exception->getMessage());
            self::assertStringNotContainsString('super-secret-password', $exception->getMessage());
        }
    }

    public function testPublicMethodSignaturesDoNotExposeVendorTypes(): void
    {
        foreach ([SqlQueryInterface::class, SqlQuery::class] as $className) {
            $class = new ReflectionClass($className);

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($this->methodTypeNames($method) as $typeName) {
                    $this->assertNotForbiddenVendorOrRuntimeType($typeName);
                }
            }
        }
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

    private function assertMethodDocblockContainsLine(ReflectionMethod $method, string $expectedLine): void
    {
        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString($expectedLine, $docComment);
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
