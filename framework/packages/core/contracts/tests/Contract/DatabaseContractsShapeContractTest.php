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
use Coretsia\Contracts\Database\SqlQueryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class DatabaseContractsShapeContractTest extends TestCase
{
    public function testDatabaseDriverInterfaceHasCanonicalSurface(): void
    {
        $interface = new ReflectionClass(DatabaseDriverInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(['connect', 'id'], $this->publicMethodNames($interface));

        $id = $interface->getMethod('id');
        self::assertSame([], $id->getParameters());
        $this->assertNamedReturnType($id, 'string');

        $connect = $interface->getMethod('connect');
        $parameters = $connect->getParameters();

        self::assertCount(3, $parameters);

        self::assertSame('connectionName', $parameters[0]->getName());
        $this->assertNamedParameterType($parameters[0], 'string');

        self::assertSame('config', $parameters[1]->getName());
        $this->assertNamedParameterType($parameters[1], 'array');

        self::assertSame('tuning', $parameters[2]->getName());
        $this->assertNamedParameterType($parameters[2], 'array');

        $this->assertNamedReturnType($connect, ConnectionInterface::class);
    }

    public function testConnectionInterfaceHasCanonicalSurface(): void
    {
        $interface = new ReflectionClass(ConnectionInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(
            [
                'beginTransaction',
                'commit',
                'dialect',
                'driverId',
                'execute',
                'name',
                'rollBack',
            ],
            $this->publicMethodNames($interface),
        );

        $name = $interface->getMethod('name');
        self::assertSame([], $name->getParameters());
        $this->assertNamedReturnType($name, 'string');

        $driverId = $interface->getMethod('driverId');
        self::assertSame([], $driverId->getParameters());
        $this->assertNamedReturnType($driverId, 'string');

        $execute = $interface->getMethod('execute');
        $executeParameters = $execute->getParameters();

        self::assertCount(1, $executeParameters);
        self::assertSame('query', $executeParameters[0]->getName());
        $this->assertNamedParameterType($executeParameters[0], SqlQueryInterface::class);
        $this->assertNamedReturnType($execute, QueryResultInterface::class);

        foreach (['beginTransaction', 'commit', 'rollBack'] as $methodName) {
            $method = $interface->getMethod($methodName);

            self::assertSame([], $method->getParameters());
            $this->assertNamedReturnType($method, 'void');
        }

        $dialect = $interface->getMethod('dialect');
        self::assertSame([], $dialect->getParameters());
        $this->assertNamedReturnType($dialect, SqlDialectInterface::class);
    }

    public function testQueryResultInterfaceHasCanonicalSurface(): void
    {
        $interface = new ReflectionClass(QueryResultInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(['affectedRows', 'rows'], $this->publicMethodNames($interface));

        $rows = $interface->getMethod('rows');
        self::assertSame([], $rows->getParameters());
        $this->assertNamedReturnType($rows, 'array');

        $this->assertMethodDocblockContainsLine(
            $rows,
            '@return list<array<string,int|string|bool|null>>',
        );

        $affectedRows = $interface->getMethod('affectedRows');
        self::assertSame([], $affectedRows->getParameters());
        $this->assertNamedReturnType($affectedRows, 'int');
    }

    public function testSqlDialectInterfaceHasCanonicalSurface(): void
    {
        $interface = new ReflectionClass(SqlDialectInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(
            [
                'applyLimitOffset',
                'booleanLiteral',
                'id',
                'quoteIdentifier',
                'supportsIdentityColumns',
                'supportsReturning',
                'supportsTransactionalDdl',
            ],
            $this->publicMethodNames($interface),
        );

        $id = $interface->getMethod('id');
        self::assertSame([], $id->getParameters());
        $this->assertNamedReturnType($id, 'string');

        $quoteIdentifier = $interface->getMethod('quoteIdentifier');
        $quoteIdentifierParameters = $quoteIdentifier->getParameters();

        self::assertCount(1, $quoteIdentifierParameters);
        self::assertSame('identifier', $quoteIdentifierParameters[0]->getName());
        $this->assertNamedParameterType($quoteIdentifierParameters[0], 'string');
        $this->assertNamedReturnType($quoteIdentifier, 'string');

        $booleanLiteral = $interface->getMethod('booleanLiteral');
        $booleanLiteralParameters = $booleanLiteral->getParameters();

        self::assertCount(1, $booleanLiteralParameters);
        self::assertSame('value', $booleanLiteralParameters[0]->getName());
        $this->assertNamedParameterType($booleanLiteralParameters[0], 'bool');
        $this->assertNamedReturnType($booleanLiteral, 'string');

        $applyLimitOffset = $interface->getMethod('applyLimitOffset');
        $applyLimitOffsetParameters = $applyLimitOffset->getParameters();

        self::assertCount(3, $applyLimitOffsetParameters);

        self::assertSame('query', $applyLimitOffsetParameters[0]->getName());
        $this->assertNamedParameterType($applyLimitOffsetParameters[0], SqlQueryInterface::class);
        self::assertFalse($applyLimitOffsetParameters[0]->isOptional());

        self::assertSame('limit', $applyLimitOffsetParameters[1]->getName());
        $this->assertNamedParameterType($applyLimitOffsetParameters[1], 'int', true);
        self::assertTrue($applyLimitOffsetParameters[1]->isOptional());
        self::assertTrue($applyLimitOffsetParameters[1]->isDefaultValueAvailable());
        self::assertNull($applyLimitOffsetParameters[1]->getDefaultValue());

        self::assertSame('offset', $applyLimitOffsetParameters[2]->getName());
        $this->assertNamedParameterType($applyLimitOffsetParameters[2], 'int', true);
        self::assertTrue($applyLimitOffsetParameters[2]->isOptional());
        self::assertTrue($applyLimitOffsetParameters[2]->isDefaultValueAvailable());
        self::assertNull($applyLimitOffsetParameters[2]->getDefaultValue());

        $this->assertNamedReturnType($applyLimitOffset, SqlQueryInterface::class);

        foreach (
            [
                'supportsReturning',
                'supportsIdentityColumns',
                'supportsTransactionalDdl',
            ] as $methodName
        ) {
            $method = $interface->getMethod($methodName);

            self::assertSame([], $method->getParameters());
            $this->assertNamedReturnType($method, 'bool');
        }
    }

    public function testDatabaseContractsDeclareNoTagConfigOrArtifactConstants(): void
    {
        foreach ($this->databaseContractInterfaces() as $interfaceName) {
            $interface = new ReflectionClass($interfaceName);

            self::assertSame([], $interface->getConstants());
        }
    }

    public function testPublicMethodSignaturesDoNotExposeVendorPlatformIntegrationOrPsrTypes(): void
    {
        foreach ($this->databaseContractInterfaces() as $interfaceName) {
            $interface = new ReflectionClass($interfaceName);

            foreach ($interface->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($this->methodTypeNames($method) as $typeName) {
                    $this->assertNotForbiddenVendorOrRuntimeType($typeName);
                }
            }
        }
    }

    public function testMinimalConnectionFixtureCanExposeNonEmptyConnectionNameAndRegexValidDriverId(): void
    {
        $dialect = new class() implements SqlDialectInterface {
            public function id(): string
            {
                return 'test-memory';
            }

            public function quoteIdentifier(string $identifier): string
            {
                return '"' . str_replace('"', '""', $identifier) . '"';
            }

            public function booleanLiteral(bool $value): string
            {
                return $value ? 'TRUE' : 'FALSE';
            }

            public function applyLimitOffset(
                SqlQueryInterface $query,
                ?int $limit = null,
                ?int $offset = null,
            ): SqlQueryInterface {
                return $query;
            }

            public function supportsReturning(): bool
            {
                return false;
            }

            public function supportsIdentityColumns(): bool
            {
                return true;
            }

            public function supportsTransactionalDdl(): bool
            {
                return true;
            }
        };

        $result = new class() implements QueryResultInterface {
            public function rows(): array
            {
                return [];
            }

            public function affectedRows(): int
            {
                return 0;
            }
        };

        $connection = new class($dialect, $result) implements ConnectionInterface {
            public function __construct(
                private readonly SqlDialectInterface $dialect,
                private readonly QueryResultInterface $result,
            ) {
            }

            public function name(): string
            {
                return 'main';
            }

            public function driverId(): string
            {
                return 'test-memory';
            }

            public function execute(SqlQueryInterface $query): QueryResultInterface
            {
                return $this->result;
            }

            public function beginTransaction(): void
            {
            }

            public function commit(): void
            {
            }

            public function rollBack(): void
            {
            }

            public function dialect(): SqlDialectInterface
            {
                return $this->dialect;
            }
        };

        self::assertSame('main', $connection->name());
        self::assertNotSame('', $connection->name());

        self::assertSame('test-memory', $connection->driverId());
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9_-]*$/', $connection->driverId());

        self::assertSame($dialect, $connection->dialect());
    }

    public function testRuntimeDriverImplementationsRemainResponsibleForProducedConnectionInvariants(): void
    {
        $driverInterface = new ReflectionClass(DatabaseDriverInterface::class);
        $connectionInterface = new ReflectionClass(ConnectionInterface::class);

        self::assertTrue($driverInterface->isInterface());
        self::assertTrue($connectionInterface->isInterface());

        $this->assertNamedReturnType($driverInterface->getMethod('id'), 'string');
        $this->assertNamedReturnType($connectionInterface->getMethod('name'), 'string');
        $this->assertNamedReturnType($connectionInterface->getMethod('driverId'), 'string');

        self::assertSame(
            ConnectionInterface::class,
            $driverInterface->getMethod('connect')->getReturnType()?->getName(),
        );
    }

    /**
     * @return list<class-string>
     */
    private function databaseContractInterfaces(): array
    {
        return [
            DatabaseDriverInterface::class,
            ConnectionInterface::class,
            QueryResultInterface::class,
            SqlDialectInterface::class,
        ];
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
