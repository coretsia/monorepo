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
use Coretsia\Contracts\Database\DatabaseDriverInterface;
use Coretsia\Contracts\Database\QueryResultInterface;
use Coretsia\Contracts\Database\SqlDialectInterface;
use Coretsia\Contracts\Database\SqlQuery;
use Coretsia\Contracts\Database\SqlQueryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class DatabaseContractsNeverExposeFloatTypeContractTest extends TestCase
{
    public function testSqlQueryRejectsFloatBindings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query bindings must contain only int, string, bool, or null values.');

        new SqlQuery('SELECT * FROM prices WHERE amount = ?', [19.95]);
    }

    public function testPublicMethodSignaturesNeverDeclareFloat(): void
    {
        foreach ($this->databaseContractTypes() as $className) {
            $class = new ReflectionClass($className);

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($this->methodTypeNames($method) as $typeName) {
                    self::assertNotSame(
                        'float',
                        $typeName,
                        sprintf(
                            '%s::%s() must not expose float in its public signature.',
                            $className,
                            $method->getName()
                        ),
                    );
                }
            }
        }
    }

    public function testSqlQueryBindingPhpDocUsesCanonicalDbValueUnionOnly(): void
    {
        $constructor = new ReflectionMethod(SqlQuery::class, '__construct');
        $bindings = new ReflectionMethod(SqlQuery::class, 'bindings');
        $interfaceBindings = new ReflectionMethod(SqlQueryInterface::class, 'bindings');

        self::assertSame(
            'list<int|string|bool|null>',
            $this->phpDocParamType($constructor, 'bindings'),
        );

        self::assertSame(
            'list<int|string|bool|null>',
            $this->phpDocReturnType($bindings),
        );

        self::assertSame(
            'list<int|string|bool|null>',
            $this->phpDocReturnType($interfaceBindings),
        );
    }

    public function testQueryResultRowsPhpDocUsesCanonicalDbRowsShapeWithoutFloat(): void
    {
        $rows = new ReflectionMethod(QueryResultInterface::class, 'rows');

        self::assertSame(
            'list<array<string,int|string|bool|null>>',
            $this->phpDocReturnType($rows),
        );
    }

    public function testDatabaseDriverConfigAndTuningMayRemainMixedMapsAtContractSurface(): void
    {
        $connect = new ReflectionMethod(DatabaseDriverInterface::class, 'connect');

        self::assertSame(
            'array<string,mixed>',
            $this->phpDocParamType($connect, 'config'),
        );

        self::assertSame(
            'array<string,mixed>',
            $this->phpDocParamType($connect, 'tuning'),
        );

        foreach ($connect->getParameters() as $parameter) {
            if ($parameter->getName() === 'config' || $parameter->getName() === 'tuning') {
                $type = $parameter->getType();

                self::assertInstanceOf(ReflectionNamedType::class, $type);
                self::assertSame('array', $type->getName());
            }
        }
    }

    public function testAllowedDbValuePhpDocUnionsDoNotIncludeFloat(): void
    {
        $allowedDbValueAnnotations = [
            $this->phpDocParamType(new ReflectionMethod(SqlQuery::class, '__construct'), 'bindings'),
            $this->phpDocReturnType(new ReflectionMethod(SqlQuery::class, 'bindings')),
            $this->phpDocReturnType(new ReflectionMethod(SqlQueryInterface::class, 'bindings')),
            $this->phpDocReturnType(new ReflectionMethod(QueryResultInterface::class, 'rows')),
        ];

        foreach ($allowedDbValueAnnotations as $annotation) {
            self::assertStringNotContainsString('float', $annotation);
            self::assertStringContainsString('int', $annotation);
            self::assertStringContainsString('string', $annotation);
            self::assertStringContainsString('bool', $annotation);
            self::assertStringContainsString('null', $annotation);
        }
    }

    /**
     * @return list<class-string>
     */
    private function databaseContractTypes(): array
    {
        return [
            SqlQueryInterface::class,
            SqlQuery::class,
            DatabaseDriverInterface::class,
            ConnectionInterface::class,
            QueryResultInterface::class,
            SqlDialectInterface::class,
        ];
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

    private function phpDocReturnType(ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertMatchesRegularExpression('/@return\s+([^\r\n]+)/', $docComment);

        preg_match('/@return\s+([^\r\n]+)/', $docComment, $matches);

        return trim($matches[1]);
    }

    private function phpDocParamType(ReflectionMethod $method, string $parameterName): string
    {
        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertMatchesRegularExpression(
            '/@param\s+([^\s]+)\s+\$' . preg_quote($parameterName, '/') . '\b/',
            $docComment,
        );

        preg_match(
            '/@param\s+([^\s]+)\s+\$' . preg_quote($parameterName, '/') . '\b/',
            $docComment,
            $matches,
        );

        return trim($matches[1]);
    }
}
