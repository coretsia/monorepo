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

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;

final class RedactionContextShapeContractTest extends TestCase
{
    public function testContextHasExactReadonlyStateConstructorAndAccessors(): void
    {
        $reflection = new ReflectionClass(RedactionContext::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $properties[] = $property->getName();
            self::assertTrue($property->isPrivate());
        }

        self::assertSame(['scope', 'mode'], $properties);

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertSame(2, $constructor->getNumberOfParameters());
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());

        [$scope, $mode] = $constructor->getParameters();

        self::assertSame('scope', $scope->getName());
        $scopeType = $scope->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $scopeType);
        self::assertSame('string', $scopeType->getName());
        self::assertFalse($scopeType->allowsNull());
        self::assertFalse($scope->isDefaultValueAvailable());
        self::assertSame('mode', $mode->getName());
        $modeType = $mode->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $modeType);
        self::assertSame(RedactionMode::class, $modeType->getName());
        self::assertFalse($modeType->allowsNull());
        self::assertTrue($mode->isDefaultValueAvailable());
        self::assertSame(RedactionMode::Placeholder, $mode->getDefaultValue());

        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methods[$method->getName()] = $method;
        }

        self::assertSame(['__construct', 'schemaVersion', 'scope', 'mode'], \array_keys($methods));

        foreach (
            [
                'schemaVersion' => 'int',
                'scope' => 'string',
                'mode' => RedactionMode::class,
            ] as $name => $typeName
        ) {
            $method = $methods[$name];
            $type = $method->getReturnType();

            self::assertSame(0, $method->getNumberOfParameters());
            self::assertFalse($method->isStatic());
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertSame($typeName, $type->getName());
            self::assertFalse($type->allowsNull());
        }

        $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

        self::assertCount(1, $constants);
        self::assertSame('SCHEMA_VERSION', $constants[0]->getName());
        $constantType = $constants[0]->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $constantType);
        self::assertSame('int', $constantType->getName());
        self::assertSame(1, $constants[0]->getValue());
    }

    #[DataProvider('validScopeProvider')]
    public function testValidBoundedScopesPreserveTheirExactBytes(string $scope): void
    {
        $context = new RedactionContext($scope);

        self::assertSame($scope, $context->scope());
        self::assertSame(1, $context->schemaVersion());
        self::assertSame(RedactionMode::Placeholder, $context->mode());
        self::assertSame($scope, new RedactionContext($scope, RedactionMode::Length)->scope());
        self::assertSame(
            RedactionMode::Length,
            new RedactionContext($scope, RedactionMode::Length)->mode(),
        );
    }

    #[DataProvider('invalidScopeProvider')]
    public function testInvalidScopeHasOneSafeDeterministicError(string $scope): void
    {
        try {
            new RedactionContext($scope);
            self::fail('Invalid scope must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('redaction-context-scope-invalid', $exception->getMessage());
            self::assertStringNotContainsString('synthetic', $exception->getMessage());
        }
    }

    public function testSemanticHighCardinalityPolicyIsDocumentedButNotInferred(): void
    {
        $reflection = new ReflectionClass(RedactionContext::class);
        $doc = $reflection->getDocComment();

        self::assertIsString($doc);
        self::assertStringContainsString('Callers MUST NOT', $doc);
        self::assertStringContainsString('user or tenant ids', $doc);
        self::assertStringContainsString('request ids', $doc);
        self::assertStringContainsString('correlation ids', $doc);
        self::assertStringContainsString('Semantic high-cardinality policy remains caller-owned', $doc);

        // Lexical validity alone cannot establish whether a caller-owned scope is semantically safe.
        // This synthetic identifier passes structural validation; callers must still enforce policy.
        self::assertSame('request.123', new RedactionContext('request.123')->scope());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validScopeProvider(): array
    {
        return [
            'single-letter' => ['a'],
            'lowercase-and-digits' => ['core25'],
            'cli-boundary' => ['cli.output'],
            'logging-boundary' => ['logging.record'],
            'http-boundary' => ['http.problem-detail'],
            'underscore-separator' => ['redaction_scope'],
            'colon-separator' => ['app:output'],
            'maximum-128-bytes' => ['a' . \str_repeat('b', 127)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidScopeProvider(): array
    {
        $cases = [
            'empty' => [''],
            'starts-with-digit' => ['1scope'],
            'uppercase' => ['Cli.Output'],
            'leading-separator' => ['.scope'],
            'trailing-separator' => ['scope.'],
            'adjacent-separators' => ['scope..value'],
            'space' => ['scope value'],
            'leading-space' => [' scope'],
            'trailing-space' => ['scope '],
            'tab' => ["scope\tvalue"],
            'newline' => ["scope\nsynthetic"],
            'carriage-return' => ["scope\rsynthetic"],
            'multiline' => ["scope\r\nsynthetic"],
            'nul' => ["scope\0synthetic"],
            'esc' => ["scope\x1Bsynthetic"],
            'unit-separator' => ["scope\x1Fsynthetic"],
            'del' => ["scope\x7Fsynthetic"],
            'non-ascii' => ['scópé'],
            'over-128-bytes' => ['a' . \str_repeat('b', 128)],
        ];

        for ($byte = 0; $byte < 0x20; $byte++) {
            $cases[\sprintf('c0-control-%02X', $byte)] = ['scope' . \chr($byte) . 'synthetic'];
        }

        return $cases;
    }
}
