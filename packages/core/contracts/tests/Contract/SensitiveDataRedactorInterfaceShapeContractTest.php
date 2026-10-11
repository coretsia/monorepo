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

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class SensitiveDataRedactorInterfaceShapeContractTest extends TestCase
{
    public function testInterfaceExposesExactlyTwoPublicMethods(): void
    {
        $reflection = new ReflectionClass(SensitiveDataRedactorInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame(SensitiveDataRedactorInterface::class, $reflection->getName());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getReflectionConstants());

        $methods = \array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        \sort($methods, \SORT_STRING);

        self::assertSame(['redactJsonLike', 'redactValue'], $methods);
    }

    public function testDirectStringRedactionSignatureIsExact(): void
    {
        self::assertMethodShape(
            'redactValue',
            [
                ['value', 'string'],
                ['kind', RedactionKind::class],
                ['context', RedactionContext::class],
            ],
            RedactedValue::class,
        );
    }

    public function testRecursiveRedactionSignatureIsExact(): void
    {
        self::assertMethodShape(
            'redactJsonLike',
            [
                ['value', 'mixed'],
                ['context', RedactionContext::class],
            ],
            'mixed',
        );

        $doc = new ReflectionMethod(SensitiveDataRedactorInterface::class, 'redactJsonLike')->getDocComment();

        self::assertIsString($doc);
        self::assertStringContainsString('@return null|bool|int|string|array<int|string, mixed>', $doc);
    }

    public function testBothMethodsDeclareContractsLevelRedactionExceptionOnly(): void
    {
        foreach (['redactValue', 'redactJsonLike'] as $methodName) {
            $method = new ReflectionMethod(SensitiveDataRedactorInterface::class, $methodName);
            $doc = $method->getDocComment();

            self::assertIsString($doc);
            self::assertSame(
                1,
                \substr_count(
                    $doc,
                    '@throws \\Coretsia\\Contracts\\Security\\Exception\\RedactionException',
                ),
            );
            self::assertSame(1, \substr_count($doc, '@throws'));
            self::assertStringNotContainsString('Coretsia\\Platform\\', $doc);
        }
    }

    public function testInterfaceSourceHasNoImplementationDependency(): void
    {
        $file = new ReflectionClass(SensitiveDataRedactorInterface::class)->getFileName();

        self::assertIsString($file);

        $source = \file_get_contents($file);

        self::assertIsString($source);
        self::assertStringNotContainsString('Coretsia\\Platform\\', $source);
        self::assertStringNotContainsString('Coretsia\\Integrations\\', $source);
        self::assertStringNotContainsString('DefaultSensitiveDataRedactor', $source);
        self::assertStringNotContainsString('SensitiveKeyClassifier', $source);
        self::assertStringNotContainsString('SensitiveValueClassifier', $source);
        self::assertStringNotContainsString('StableRedactionHasher', $source);
    }

    /**
     * @param list<array{string, string}> $expectedParameters
     */
    private static function assertMethodShape(
        string $methodName,
        array $expectedParameters,
        string $expectedReturnType,
    ): void {
        $method = new ReflectionMethod(SensitiveDataRedactorInterface::class, $methodName);

        self::assertTrue($method->isPublic());
        self::assertTrue($method->isAbstract());
        self::assertFalse($method->isStatic());
        self::assertSame(\count($expectedParameters), $method->getNumberOfParameters());
        self::assertSame(\count($expectedParameters), $method->getNumberOfRequiredParameters());

        foreach ($method->getParameters() as $index => $parameter) {
            [$name, $typeName] = $expectedParameters[$index];

            self::assertSame($name, $parameter->getName());
            self::assertFalse($parameter->isVariadic());
            self::assertFalse($parameter->isPassedByReference());
            self::assertFalse($parameter->isDefaultValueAvailable());

            $type = $parameter->getType();

            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertSame($typeName, $type->getName());
            self::assertSame($typeName === 'mixed', $type->allowsNull());
        }

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame($expectedReturnType, $returnType->getName());
        self::assertSame($expectedReturnType === 'mixed', $returnType->allowsNull());
    }
}
