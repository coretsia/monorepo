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

use Coretsia\Contracts\Secrets\SecretsResolverInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class SecretsResolverInterfaceShapeContractTest extends TestCase
{
    public function testSecretsResolverInterfaceShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(SecretsResolverInterface::class);

        self::assertTrue($reflection->isInterface());

        self::assertSame(['resolve'], self::publicMethodNames($reflection));
        self::assertSame([], self::publicConstantNames($reflection));
        self::assertSame([], $reflection->getAttributes());

        self::assertParameterShape(SecretsResolverInterface::class, 'resolve', 0, 'ref', 'string');
        self::assertReturnType(SecretsResolverInterface::class, 'resolve', 'string');
        self::assertMethodDocContains(
            SecretsResolverInterface::class,
            'resolve',
            '@param non-empty-string $ref',
            '@return string',
            'The returned value is sensitive by default',
            'MUST NOT be logged',
            'A returned empty string represents an explicitly resolved empty secret'
        );

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testSecretsResolverInterfaceDoesNotExposeRuntimeOrBackendSurface(): void
    {
        $source = self::sourceOf(SecretsResolverInterface::class);

        self::assertSourceDoesNotContain(
            $source,
            'public function name(',
            'public function supports(',
            'public function tryResolve(',
            'public function resolveOrNull(',
            'public function resolveOptional(',
            'public function backend(',
            'public function driver(',
            'public function client(',
            'public function config(',
            'public function health(',
            'public function debug(',
            'SecretNotFoundException',
            'SecretException',
            'RuntimeException',
            '@throws',
            '?string',
            'float'
        );
    }

    public function testSecretsResolverInterfaceDoesNotDependOnForbiddenPackagesOrTypes(): void
    {
        $source = self::sourceOf(SecretsResolverInterface::class);

        self::assertSourceDoesNotContain(
            $source,
            "\nuse ",
            'Psr\\Http\\Message\\',
            'Psr\\Http\\Message\\RequestInterface',
            'Psr\\Http\\Message\\ResponseInterface',
            'Coretsia\\Platform\\',
            'Coretsia\\Integrations\\',
            'Vault',
            'Aws\\',
            'Google\\Cloud\\',
            'Azure\\',
            'Symfony\\',
            'Dotenv\\',
            'PDO',
            'Redis',
            'CacheInterface',
            'LockInterface',
            'LoggerInterface',
            'Tracer',
            'Metric',
            'ClientInterface',
            'StreamInterface',
            'resource',
            'Closure',
            'callable',
            'iterable',
            'Iterator',
            'Generator'
        );
    }

    public function testSecretsResolverInterfaceDoesNotOptIntoDtoPolicyOrDeclareDiscoveryConstants(): void
    {
        $reflection = new ReflectionClass(SecretsResolverInterface::class);
        $source = self::sourceOf(SecretsResolverInterface::class);

        self::assertSame([], $reflection->getAttributes());
        self::assertSame([], self::publicConstantNames($reflection));

        self::assertSourceDoesNotContain(
            $source,
            '#[Coretsia\\Dto\\Attribute\\Dto]',
            '#[\\Coretsia\\Dto\\Attribute\\Dto]',
            'Coretsia\\Dto\\Attribute\\Dto',
            'TAG_',
            'CONFIG_',
            'ARTIFACT_',
            'SCHEMA_VERSION'
        );
    }

    /**
     * @return list<string>
     */
    private static function publicMethodNames(ReflectionClass $reflection): array
    {
        $names = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
        );

        sort($names, \SORT_STRING);

        return array_values($names);
    }

    /**
     * @return list<string>
     */
    private static function publicConstantNames(ReflectionClass $reflection): array
    {
        $names = array_map(
            static fn (ReflectionClassConstant $constant): string => $constant->getName(),
            $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC)
        );

        sort($names, \SORT_STRING);

        return array_values($names);
    }

    /**
     * @param class-string $className
     */
    private static function assertParameterShape(
        string $className,
        string $methodName,
        int $position,
        string $parameterName,
        string $typeName,
        bool $hasDefaultValue = false,
        mixed $defaultValue = null,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $parameters = $method->getParameters();

        self::assertArrayHasKey($position, $parameters);

        $parameter = $parameters[$position];

        self::assertSame($parameterName, $parameter->getName());
        self::assertParameterType($parameter, $typeName);
        self::assertSame($hasDefaultValue, $parameter->isDefaultValueAvailable());

        if ($hasDefaultValue) {
            self::assertSame($defaultValue, $parameter->getDefaultValue());
        }
    }

    private static function assertParameterType(ReflectionParameter $parameter, string $typeName): void
    {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertFalse($type->allowsNull());
        self::assertSame($typeName, $type->getName());
    }

    /**
     * @param class-string $className
     */
    private static function assertReturnType(string $className, string $methodName, string $typeName): void
    {
        $method = new ReflectionMethod($className, $methodName);
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertFalse($type->allowsNull());
        self::assertSame($typeName, $type->getName());
    }

    /**
     * @param class-string $className
     */
    private static function assertMethodDocContains(
        string $className,
        string $methodName,
        string ...$expectedSnippets,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $docComment = $method->getDocComment();

        self::assertIsString($docComment);

        foreach ($expectedSnippets as $expectedSnippet) {
            self::assertStringContainsString($expectedSnippet, $docComment);
        }
    }

    private static function assertForbiddenPublicMethodsAreAbsent(ReflectionClass $reflection): void
    {
        foreach (
            [
                'name',
                'supports',
                'tryResolve',
                'resolveOrNull',
                'resolveOptional',
                'backend',
                'driver',
                'client',
                'config',
                'health',
                'debug',
                'toArray',
            ] as $methodName
        ) {
            self::assertFalse(
                $reflection->hasMethod($methodName),
                'Forbidden public method must not exist: ' . $methodName
            );
        }
    }

    /**
     * @param class-string $className
     */
    private static function sourceOf(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $fileName = $reflection->getFileName();

        self::assertIsString($fileName);
        self::assertFileExists($fileName);

        $source = file_get_contents($fileName);

        self::assertIsString($source);

        return $source;
    }

    private static function assertSourceDoesNotContain(string $source, string ...$forbiddenSnippets): void
    {
        foreach ($forbiddenSnippets as $forbiddenSnippet) {
            self::assertStringNotContainsString($forbiddenSnippet, $source);
        }
    }
}
