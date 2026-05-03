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

use Coretsia\Contracts\RateLimit\RateLimitDecision;
use Coretsia\Contracts\RateLimit\RateLimitKeyHasherInterface;
use Coretsia\Contracts\RateLimit\RateLimitState;
use Coretsia\Contracts\RateLimit\RateLimitStoreInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class RateLimitContractsShapeContractTest extends TestCase
{
    public function testRateLimitStoreInterfaceShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(RateLimitStoreInterface::class);

        self::assertTrue($reflection->isInterface());

        self::assertSame(
            ['consume', 'name', 'reset', 'state'],
            self::publicMethodNames($reflection)
        );

        self::assertSame([], self::publicConstantNames($reflection));

        self::assertMethodHasNoParameters(RateLimitStoreInterface::class, 'name');
        self::assertReturnType(RateLimitStoreInterface::class, 'name', 'string');
        self::assertMethodDocContains(
            RateLimitStoreInterface::class,
            'name',
            '@return non-empty-string'
        );

        self::assertParameterShape(RateLimitStoreInterface::class, 'consume', 0, 'keyHash', 'string');
        self::assertParameterShape(RateLimitStoreInterface::class, 'consume', 1, 'limit', 'int');
        self::assertParameterShape(RateLimitStoreInterface::class, 'consume', 2, 'windowSeconds', 'int');
        self::assertParameterShape(RateLimitStoreInterface::class, 'consume', 3, 'cost', 'int', true, 1);
        self::assertReturnType(RateLimitStoreInterface::class, 'consume', RateLimitDecision::class);
        self::assertMethodDocContains(
            RateLimitStoreInterface::class,
            'consume',
            '@param non-empty-string $keyHash',
            '@param int<1,max> $limit',
            '@param int<1,max> $windowSeconds',
            '@param int<1,max> $cost'
        );

        self::assertParameterShape(RateLimitStoreInterface::class, 'state', 0, 'keyHash', 'string');
        self::assertParameterShape(RateLimitStoreInterface::class, 'state', 1, 'limit', 'int');
        self::assertParameterShape(RateLimitStoreInterface::class, 'state', 2, 'windowSeconds', 'int');
        self::assertReturnType(RateLimitStoreInterface::class, 'state', RateLimitState::class);
        self::assertMethodDocContains(
            RateLimitStoreInterface::class,
            'state',
            '@param non-empty-string $keyHash',
            '@param int<1,max> $limit',
            '@param int<1,max> $windowSeconds'
        );

        self::assertParameterShape(RateLimitStoreInterface::class, 'reset', 0, 'keyHash', 'string');
        self::assertReturnType(RateLimitStoreInterface::class, 'reset', 'void');
        self::assertMethodDocContains(
            RateLimitStoreInterface::class,
            'reset',
            '@param non-empty-string $keyHash'
        );

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testRateLimitKeyHasherInterfaceShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(RateLimitKeyHasherInterface::class);

        self::assertTrue($reflection->isInterface());

        self::assertSame(['hash'], self::publicMethodNames($reflection));
        self::assertSame([], self::publicConstantNames($reflection));

        self::assertParameterShape(RateLimitKeyHasherInterface::class, 'hash', 0, 'key', 'string');
        self::assertReturnType(RateLimitKeyHasherInterface::class, 'hash', 'string');
        self::assertMethodDocContains(
            RateLimitKeyHasherInterface::class,
            'hash',
            '@param non-empty-string $key',
            '@return non-empty-string'
        );

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testRateLimitStateShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(RateLimitState::class);

        self::assertFalse($reflection->isInterface());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        self::assertSame(
            ['__construct', 'limit', 'remaining', 'resetAfterSeconds', 'schemaVersion', 'toArray', 'windowSeconds'],
            self::publicMethodNames($reflection)
        );

        self::assertSame(['SCHEMA_VERSION'], self::publicConstantNames($reflection));
        self::assertSchemaVersionConstantIsLocked($reflection);

        self::assertParameterShape(RateLimitState::class, '__construct', 0, 'limit', 'int');
        self::assertParameterShape(RateLimitState::class, '__construct', 1, 'remaining', 'int');
        self::assertParameterShape(RateLimitState::class, '__construct', 2, 'resetAfterSeconds', 'int');
        self::assertParameterShape(RateLimitState::class, '__construct', 3, 'windowSeconds', 'int');
        self::assertMethodDocContains(
            RateLimitState::class,
            '__construct',
            '@param int<1,max> $limit',
            '@param int<0,max> $remaining',
            '@param int<0,max> $resetAfterSeconds',
            '@param int<1,max> $windowSeconds'
        );

        self::assertMethodHasNoParameters(RateLimitState::class, 'schemaVersion');
        self::assertReturnType(RateLimitState::class, 'schemaVersion', 'int');

        self::assertMethodHasNoParameters(RateLimitState::class, 'limit');
        self::assertReturnType(RateLimitState::class, 'limit', 'int');
        self::assertMethodDocContains(RateLimitState::class, 'limit', '@return int<1,max>');

        self::assertMethodHasNoParameters(RateLimitState::class, 'remaining');
        self::assertReturnType(RateLimitState::class, 'remaining', 'int');
        self::assertMethodDocContains(RateLimitState::class, 'remaining', '@return int<0,max>');

        self::assertMethodHasNoParameters(RateLimitState::class, 'resetAfterSeconds');
        self::assertReturnType(RateLimitState::class, 'resetAfterSeconds', 'int');
        self::assertMethodDocContains(RateLimitState::class, 'resetAfterSeconds', '@return int<0,max>');

        self::assertMethodHasNoParameters(RateLimitState::class, 'windowSeconds');
        self::assertReturnType(RateLimitState::class, 'windowSeconds', 'int');
        self::assertMethodDocContains(RateLimitState::class, 'windowSeconds', '@return int<1,max>');

        self::assertMethodHasNoParameters(RateLimitState::class, 'toArray');
        self::assertReturnType(RateLimitState::class, 'toArray', 'array');
        self::assertMethodDocContains(
            RateLimitState::class,
            'toArray',
            '@return array{',
            'limit: int<1,max>',
            'remaining: int<0,max>',
            'resetAfterSeconds: int<0,max>',
            'schemaVersion: int',
            'windowSeconds: int<1,max>'
        );
        self::assertMethodDocDoesNotContainTopLevelGenericArrayReturn(RateLimitState::class, 'toArray');

        $state = new RateLimitState(
            limit: 10,
            remaining: 7,
            resetAfterSeconds: 60,
            windowSeconds: 120,
        );

        self::assertSame(1, $state->schemaVersion());
        self::assertSame(10, $state->limit());
        self::assertSame(7, $state->remaining());
        self::assertSame(60, $state->resetAfterSeconds());
        self::assertSame(120, $state->windowSeconds());

        self::assertSame(
            ['limit', 'remaining', 'resetAfterSeconds', 'schemaVersion', 'windowSeconds'],
            array_keys($state->toArray())
        );

        self::assertSame(
            [
                'limit' => 10,
                'remaining' => 7,
                'resetAfterSeconds' => 60,
                'schemaVersion' => 1,
                'windowSeconds' => 120,
            ],
            $state->toArray()
        );

        self::assertInvalidArgument(static fn (): RateLimitState => new RateLimitState(0, 0, 0, 1));
        self::assertInvalidArgument(static fn (): RateLimitState => new RateLimitState(1, -1, 0, 1));
        self::assertInvalidArgument(static fn (): RateLimitState => new RateLimitState(1, 2, 0, 1));
        self::assertInvalidArgument(static fn (): RateLimitState => new RateLimitState(1, 0, -1, 1));
        self::assertInvalidArgument(static fn (): RateLimitState => new RateLimitState(1, 0, 0, 0));

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testRateLimitDecisionShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(RateLimitDecision::class);

        self::assertFalse($reflection->isInterface());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        self::assertSame(
            [
                '__construct',
                'allowed',
                'isAllowed',
                'reason',
                'retryAfterSeconds',
                'schemaVersion',
                'state',
                'toArray',
            ],
            self::publicMethodNames($reflection)
        );

        self::assertSame(['SCHEMA_VERSION'], self::publicConstantNames($reflection));
        self::assertSchemaVersionConstantIsLocked($reflection);

        self::assertParameterShape(RateLimitDecision::class, '__construct', 0, 'allowed', 'bool');
        self::assertParameterShape(RateLimitDecision::class, '__construct', 1, 'state', RateLimitState::class);
        self::assertParameterShape(
            RateLimitDecision::class,
            '__construct',
            2,
            'retryAfterSeconds',
            'int',
            true,
            null,
            true
        );
        self::assertParameterShape(RateLimitDecision::class, '__construct', 3, 'reason', 'string', true, null, true);
        self::assertMethodDocContains(
            RateLimitDecision::class,
            '__construct',
            '@param int<0,max>|null $retryAfterSeconds',
            '@param non-empty-string|null $reason'
        );

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'schemaVersion');
        self::assertReturnType(RateLimitDecision::class, 'schemaVersion', 'int');

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'allowed');
        self::assertReturnType(RateLimitDecision::class, 'allowed', 'bool');

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'isAllowed');
        self::assertReturnType(RateLimitDecision::class, 'isAllowed', 'bool');

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'retryAfterSeconds');
        self::assertReturnType(RateLimitDecision::class, 'retryAfterSeconds', 'int', true);
        self::assertMethodDocContains(RateLimitDecision::class, 'retryAfterSeconds', '@return int<0,max>|null');

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'reason');
        self::assertReturnType(RateLimitDecision::class, 'reason', 'string', true);
        self::assertMethodDocContains(RateLimitDecision::class, 'reason', '@return non-empty-string|null');

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'state');
        self::assertReturnType(RateLimitDecision::class, 'state', RateLimitState::class);

        self::assertMethodHasNoParameters(RateLimitDecision::class, 'toArray');
        self::assertReturnType(RateLimitDecision::class, 'toArray', 'array');
        self::assertMethodDocContains(
            RateLimitDecision::class,
            'toArray',
            '@return array{',
            'allowed: bool',
            'reason: non-empty-string|null',
            'retryAfterSeconds: int<0,max>|null',
            'schemaVersion: int',
            'state: array<string,mixed>'
        );
        self::assertMethodDocDoesNotContainTopLevelGenericArrayReturn(RateLimitDecision::class, 'toArray');

        $state = new RateLimitState(
            limit: 10,
            remaining: 0,
            resetAfterSeconds: 60,
            windowSeconds: 120,
        );

        $decision = new RateLimitDecision(
            allowed: false,
            state: $state,
            retryAfterSeconds: 60,
            reason: 'rate_limit:denied-v1.ok',
        );

        self::assertSame(1, $decision->schemaVersion());
        self::assertFalse($decision->allowed());
        self::assertFalse($decision->isAllowed());
        self::assertSame(60, $decision->retryAfterSeconds());
        self::assertSame('rate_limit:denied-v1.ok', $decision->reason());
        self::assertSame($state, $decision->state());

        self::assertSame(
            ['allowed', 'reason', 'retryAfterSeconds', 'schemaVersion', 'state'],
            array_keys($decision->toArray())
        );

        self::assertSame(
            [
                'allowed' => false,
                'reason' => 'rate_limit:denied-v1.ok',
                'retryAfterSeconds' => 60,
                'schemaVersion' => 1,
                'state' => $state->toArray(),
            ],
            $decision->toArray()
        );

        $allowedDecision = new RateLimitDecision(
            allowed: true,
            state: new RateLimitState(10, 9, 60, 120),
        );

        self::assertTrue($allowedDecision->allowed());
        self::assertTrue($allowedDecision->isAllowed());
        self::assertNull($allowedDecision->retryAfterSeconds());
        self::assertNull($allowedDecision->reason());

        self::assertInvalidArgument(static fn (): RateLimitDecision => new RateLimitDecision(true, $state, -1));
        self::assertInvalidArgument(static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, ''));
        self::assertInvalidArgument(static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, "\n"));
        self::assertInvalidArgument(
            static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, "bad\nreason")
        );
        self::assertInvalidArgument(
            static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, '9bad')
        );
        self::assertInvalidArgument(
            static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, 'bad reason')
        );
        self::assertInvalidArgument(
            static fn (): RateLimitDecision => new RateLimitDecision(true, $state, null, 'bad/reason')
        );

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testRateLimitContractsAreNotDtoMarked(): void
    {
        foreach (self::contractReflections() as $reflection) {
            self::assertSame(
                [],
                $reflection->getAttributes('Coretsia\Dto\Attribute\Dto'),
                $reflection->getName() . ' must not be marked as DTO.'
            );
        }
    }

    public function testRateLimitContractsPublicSurfaceDoesNotExposeForbiddenTypesOrFloats(): void
    {
        foreach (self::contractReflections() as $reflection) {
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                self::assertMethodDoesNotExposeForbiddenTypes($method);
            }

            foreach ($reflection->getProperties() as $property) {
                $type = $property->getType();

                if ($type instanceof ReflectionNamedType) {
                    self::assertNotSame(
                        'float',
                        $type->getName(),
                        $reflection->getName() . ' must not expose float properties.'
                    );
                }
            }
        }
    }

    public function testRateLimitContractsSourceDoesNotImportForbiddenDependencies(): void
    {
        foreach (self::sourceFiles() as $file) {
            self::assertFileExists($file);

            $source = (string)file_get_contents($file);
            $codeWithoutComments = self::phpCodeWithoutComments($source);

            foreach (self::forbiddenSourceTokens() as $forbiddenToken) {
                self::assertStringNotContainsString(
                    $forbiddenToken,
                    $codeWithoutComments,
                    $file . ' must not depend on ' . $forbiddenToken . '.'
                );
            }
        }
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private static function contractReflections(): array
    {
        return [
            new ReflectionClass(RateLimitStoreInterface::class),
            new ReflectionClass(RateLimitKeyHasherInterface::class),
            new ReflectionClass(RateLimitState::class),
            new ReflectionClass(RateLimitDecision::class),
        ];
    }

    /**
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            $root . '/src/RateLimit/RateLimitStoreInterface.php',
            $root . '/src/RateLimit/RateLimitKeyHasherInterface.php',
            $root . '/src/RateLimit/RateLimitState.php',
            $root . '/src/RateLimit/RateLimitDecision.php',
        ];
    }

    /**
     * @return list<string>
     */
    private static function forbiddenSourceTokens(): array
    {
        return [
            'Coretsia\\Platform\\',
            'Coretsia\\Integrations\\',
            'Psr\\Http\\Message\\',
            'Redis',
            'Predis\\',
            'Relay\\',
            'PDO',
        ];
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private static function publicMethodNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $method->getName();
        }

        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private static function publicConstantNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $names[] = $constant->getName();
        }

        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function assertSchemaVersionConstantIsLocked(ReflectionClass $reflection): void
    {
        self::assertTrue($reflection->hasConstant('SCHEMA_VERSION'));
        self::assertSame(1, $reflection->getConstant('SCHEMA_VERSION'));

        $constant = $reflection->getReflectionConstant('SCHEMA_VERSION');
        self::assertNotFalse($constant);
        self::assertTrue($constant->isPublic());

        if (method_exists($constant, 'getType')) {
            $type = $constant->getType();

            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertSame('int', $type->getName());
        }
    }

    private static function assertForbiddenPublicMethodsAreAbsent(ReflectionClass $reflection): void
    {
        $publicMethodNames = self::publicMethodNames($reflection);

        foreach (
            [
                'query',
                'raw',
                'close',
                'isOpen',
                'isConnected',
                'connection',
                'client',
                'redis',
                'ttl',
                'lock',
                'script',
                'metadata',
                'config',
                'repository',
            ] as $forbiddenMethod
        ) {
            self::assertNotContains(
                $forbiddenMethod,
                $publicMethodNames,
                $reflection->getName() . ' must not expose backend/runtime method ' . $forbiddenMethod . '().'
            );
        }
    }

    private static function assertMethodHasNoParameters(string $className, string $methodName): void
    {
        $method = new ReflectionMethod($className, $methodName);

        self::assertSame([], $method->getParameters());
    }

    private static function assertReturnType(
        string $className,
        string $methodName,
        string $expectedType,
        bool $allowsNull = false,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedType, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }

    private static function assertParameterShape(
        string $className,
        string $methodName,
        int $position,
        string $expectedName,
        string $expectedType,
        bool $hasDefault = false,
        mixed $expectedDefault = null,
        bool $allowsNull = false,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $parameters = $method->getParameters();

        self::assertArrayHasKey($position, $parameters);

        $parameter = $parameters[$position];

        self::assertInstanceOf(ReflectionParameter::class, $parameter);
        self::assertSame($expectedName, $parameter->getName());

        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedType, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());

        self::assertSame($hasDefault, $parameter->isDefaultValueAvailable());

        if ($hasDefault) {
            self::assertSame($expectedDefault, $parameter->getDefaultValue());
        }
    }

    private static function assertMethodDocContains(string $className, string $methodName, string ...$needles): void
    {
        $method = new ReflectionMethod($className, $methodName);
        $doc = $method->getDocComment();

        self::assertIsString($doc, $className . '::' . $methodName . '() must have PHPDoc.');

        foreach ($needles as $needle) {
            self::assertStringContainsString(
                $needle,
                $doc,
                $className . '::' . $methodName . '() PHPDoc must contain ' . $needle . '.'
            );
        }
    }

    private static function assertMethodDocDoesNotContainTopLevelGenericArrayReturn(
        string $className,
        string $methodName,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $doc = $method->getDocComment();

        self::assertIsString($doc, $className . '::' . $methodName . '() must have PHPDoc.');
        self::assertDoesNotMatchRegularExpression(
            '/@return\s+array<string\s*,\s*mixed>\b/',
            $doc,
            $className . '::' . $methodName . '() must use a concrete array shape, not a generic top-level array<string,mixed>.'
        );
    }

    private static function assertMethodDoesNotExposeForbiddenTypes(ReflectionMethod $method): void
    {
        $methodName = $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()';

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            self::assertTypeIsAllowedPublicSurfaceType($returnType->getName(), $methodName . ' return type');
        }

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            self::assertTypeIsAllowedPublicSurfaceType(
                $type->getName(),
                $methodName . ' parameter $' . $parameter->getName()
            );
        }
    }

    private static function assertTypeIsAllowedPublicSurfaceType(string $typeName, string $context): void
    {
        self::assertNotSame('float', $typeName, $context . ' must not expose float.');

        self::assertStringDoesNotStartWith(
            'Coretsia\\Platform\\',
            $typeName,
            $context . ' must not expose platform type.'
        );
        self::assertStringDoesNotStartWith(
            'Coretsia\\Integrations\\',
            $typeName,
            $context . ' must not expose integration type.'
        );
        self::assertStringDoesNotStartWith(
            'Psr\\Http\\Message\\',
            $typeName,
            $context . ' must not expose PSR-7 type.'
        );
        self::assertStringDoesNotStartWith(
            'Redis',
            $typeName,
            $context . ' must not expose Redis type.'
        );
        self::assertStringDoesNotStartWith(
            'Predis\\',
            $typeName,
            $context . ' must not expose Predis type.'
        );
        self::assertStringDoesNotStartWith(
            'Relay\\',
            $typeName,
            $context . ' must not expose Relay type.'
        );

        self::assertNotSame('PDO', $typeName, $context . ' must not expose PDO.');
        self::assertNotSame('Closure', $typeName, $context . ' must not expose Closure.');
        self::assertNotSame('Generator', $typeName, $context . ' must not expose Generator.');
    }

    private static function assertStringDoesNotStartWith(string $prefix, string $actual, string $message): void
    {
        self::assertFalse(
            str_starts_with($actual, $prefix),
            $message
        );
    }

    private static function phpCodeWithoutComments(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function assertInvalidArgument(callable $callback): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);

            return;
        }

        self::fail('Expected InvalidArgumentException to be thrown.');
    }
}
