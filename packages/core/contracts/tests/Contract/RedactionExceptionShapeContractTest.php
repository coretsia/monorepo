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

use Coretsia\Contracts\Security\Exception\RedactionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;

final class RedactionExceptionShapeContractTest extends TestCase
{
    public function testExceptionIdentityAndPublicTypedConstantsAreExact(): void
    {
        $reflection = new ReflectionClass(RedactionException::class);

        self::assertSame(
            'Coretsia\\Contracts\\Security\\Exception\\RedactionException',
            $reflection->getName(),
        );
        self::assertTrue($reflection->isFinal());
        self::assertSame(\RuntimeException::class, $reflection->getParentClass()?->getName());
        self::assertSame('CORETSIA_REDACTION_FAILED', RedactionException::ERROR_CODE);

        $expected = [
            'ERROR_CODE' => 'CORETSIA_REDACTION_FAILED',
            'REASON_INPUT_INVALID' => 'input-invalid',
            'REASON_INPUT_LIMIT_EXCEEDED' => 'input-limit-exceeded',
            'REASON_SENSITIVE_MAP_KEY' => 'sensitive-map-key',
            'REASON_OUTPUT_INVALID' => 'output-invalid',
            'REASON_INTERNAL_FAILURE' => 'internal-failure',
        ];

        $actual = [];

        foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $type = $constant->getType();

            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertSame('string', $type->getName());
            self::assertFalse($type->allowsNull());

            $actual[$constant->getName()] = $constant->getValue();
        }

        self::assertSame($expected, $actual);
    }

    public function testOnlyFiveNamedConstructorsCanCreateExceptions(): void
    {
        $reflection = new ReflectionClass(RedactionException::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());
        self::assertSame('reason', $constructor->getParameters()[0]->getName());

        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== RedactionException::class) {
                continue;
            }

            $methods[] = $method->getName();

            if (\in_array($method->getName(), \array_keys(self::namedConstructors()), true)) {
                self::assertTrue($method->isStatic());
                self::assertSame(0, $method->getNumberOfParameters());

                $returnType = $method->getReturnType();

                self::assertInstanceOf(ReflectionNamedType::class, $returnType);
                self::assertSame('self', $returnType->getName());
                self::assertFalse($returnType->allowsNull());
            }
        }

        \sort($methods, \SORT_STRING);

        self::assertSame(
            [
                'errorCode',
                'inputInvalid',
                'inputLimitExceeded',
                'internalFailure',
                'outputInvalid',
                'reason',
                'sensitiveMapKey',
            ],
            $methods,
        );

        foreach (['errorCode', 'reason'] as $methodName) {
            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();

            self::assertFalse($method->isStatic());
            self::assertSame(0, $method->getNumberOfParameters());
            self::assertInstanceOf(ReflectionNamedType::class, $returnType);
            self::assertSame('string', $returnType->getName());
            self::assertFalse($returnType->allowsNull());
        }
    }

    #[DataProvider('reasonFactoryProvider')]
    public function testNamedConstructorsHaveExactSafeDiagnostics(
        string $factory,
        string $expectedReason,
    ): void {
        $exception = RedactionException::$factory();
        $rawFixture = 'synthetic-sensitive-fixture-value';

        self::assertSame($expectedReason, $exception->reason());
        self::assertSame('CORETSIA_REDACTION_FAILED', $exception->errorCode());
        self::assertSame(
            'CORETSIA_REDACTION_FAILED: ' . $expectedReason,
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertStringNotContainsString($rawFixture, $exception->getMessage());
        self::assertStringNotContainsString('Coretsia\\Platform\\', $exception->getMessage());
    }

    public function testUnknownReasonIsRejectedEvenThroughReflection(): void
    {
        $reflection = new ReflectionClass(RedactionException::class);
        $exception = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        try {
            $constructor->invoke($exception, 'synthetic-invalid-reason');
            self::fail('An unknown redaction failure reason must be rejected.');
        } catch (\InvalidArgumentException $caught) {
            self::assertSame('redaction-exception-reason-invalid', $caught->getMessage());
            self::assertStringNotContainsString('synthetic-invalid-reason', $caught->getMessage());
        }
    }

    public function testExceptionRetainsOnlyItsOwnSafeReasonState(): void
    {
        $reflection = new ReflectionClass(RedactionException::class);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === RedactionException::class) {
                $properties[] = $property->getName();
                self::assertTrue($property->isPrivate());
            }
        }

        self::assertSame(['reason'], $properties);

        foreach (self::namedConstructors() as $factory => $reason) {
            $exception = RedactionException::$factory();

            self::assertSame($reason, $exception->reason());
            self::assertNull($exception->getPrevious());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function reasonFactoryProvider(): array
    {
        $cases = [];

        foreach (self::namedConstructors() as $factory => $reason) {
            $cases[$factory] = [$factory, $reason];
        }

        return $cases;
    }

    /**
     * @return array<string, string>
     */
    private static function namedConstructors(): array
    {
        return [
            'inputInvalid' => 'input-invalid',
            'inputLimitExceeded' => 'input-limit-exceeded',
            'sensitiveMapKey' => 'sensitive-map-key',
            'outputInvalid' => 'output-invalid',
            'internalFailure' => 'internal-failure',
        ];
    }
}
