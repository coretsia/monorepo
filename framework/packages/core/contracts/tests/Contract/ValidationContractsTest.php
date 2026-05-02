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

use Coretsia\Contracts\Validation\ValidationException;
use Coretsia\Contracts\Validation\ValidationResult;
use Coretsia\Contracts\Validation\ValidatorInterface;
use Coretsia\Contracts\Validation\Violation;
use PHPUnit\Framework\TestCase;

final class ValidationContractsTest extends TestCase
{
    public function testValidatorInterfaceExistsAndHasCanonicalValidateMethodOnly(): void
    {
        $reflection = new \ReflectionClass(ValidatorInterface::class);

        self::assertTrue($reflection->isInterface());

        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        self::assertCount(1, $methods);
        self::assertSame('validate', $methods[0]->getName());

        $method = $reflection->getMethod('validate');

        self::assertSame(3, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('input', $parameters[0]->getName());
        self::assertSame('mixed', self::namedTypeName($parameters[0]->getType()));
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertSame('rules', $parameters[1]->getName());
        self::assertSame('array', self::namedTypeName($parameters[1]->getType()));
        self::assertSame([], $parameters[1]->getDefaultValue());

        self::assertSame('context', $parameters[2]->getName());
        self::assertSame('array', self::namedTypeName($parameters[2]->getType()));
        self::assertSame([], $parameters[2]->getDefaultValue());

        self::assertSame(ValidationResult::class, self::namedTypeName($method->getReturnType()));
    }

    public function testValidatorInterfaceSignatureDoesNotUseForbiddenRuntimeTypes(): void
    {
        $method = new \ReflectionMethod(ValidatorInterface::class, 'validate');

        $types = [];

        foreach ($method->getParameters() as $parameter) {
            $typeName = self::namedTypeName($parameter->getType());

            if ($typeName !== null) {
                $types[] = $typeName;
            }
        }

        $returnTypeName = self::namedTypeName($method->getReturnType());

        if ($returnTypeName !== null) {
            $types[] = $returnTypeName;
        }

        foreach ($types as $type) {
            self::assertStringDoesNotStartWith('Psr\\Http\\Message\\', $type);
            self::assertStringDoesNotStartWith('Coretsia\\Platform\\', $type);
            self::assertStringDoesNotStartWith('Coretsia\\Integrations\\', $type);
        }
    }

    public function testValidationResultHasStableSurface(): void
    {
        $reflection = new \ReflectionClass(ValidationResult::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        self::assertSame(1, ValidationResult::SCHEMA_VERSION);

        self::assertSame(
            [
                'failure',
                'isFailure',
                'isSuccess',
                'schemaVersion',
                'success',
                'toArray',
                'violations',
            ],
            self::declaredPublicMethodNames($reflection),
        );
    }

    public function testValidationResultSuccessShapeIsStable(): void
    {
        $result = ValidationResult::success();

        self::assertSame(1, $result->schemaVersion());
        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame([], $result->violations());

        self::assertSame(
            [
                'schemaVersion',
                'success',
                'violations',
            ],
            array_keys($result->toArray()),
        );

        self::assertSame(
            [
                'schemaVersion' => 1,
                'success' => true,
                'violations' => [],
            ],
            $result->toArray(),
        );
    }

    public function testValidationResultFailureShapePreservesOrderedViolationList(): void
    {
        $first = new Violation('email', 'VALIDATION_REQUIRED', index: 1);
        $second = new Violation('profile.name', 'VALIDATION_TOO_SHORT', index: 0);

        $result = ValidationResult::failure([
            $first,
            $second,
        ]);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isFailure());
        self::assertSame([$first, $second], $result->violations());

        self::assertSame(
            [
                $first->toArray(),
                $second->toArray(),
            ],
            $result->toArray()['violations'],
        );

        self::assertSame('email', $result->toArray()['violations'][0]['path']);
        self::assertSame('profile.name', $result->toArray()['violations'][1]['path']);
    }

    public function testValidationResultRejectsEmptyFailure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed validation result must contain violations.');

        ValidationResult::failure([]);
    }

    public function testValidationResultRejectsNonListViolations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation result violations must be a list.');

        ValidationResult::failure([
            'email' => new Violation('email', 'VALIDATION_REQUIRED'),
        ]);
    }

    public function testValidationResultRejectsNonViolationItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation result violations must contain only Violation instances.');

        $method = new \ReflectionMethod(ValidationResult::class, 'failure');
        $method->invokeArgs(null, [
            [
                'not-a-violation',
            ],
        ]);
    }

    public function testViolationHasStableSurface(): void
    {
        $reflection = new \ReflectionClass(Violation::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        self::assertSame(1, Violation::SCHEMA_VERSION);

        self::assertSame(
            [
                '__construct',
                'code',
                'index',
                'message',
                'meta',
                'path',
                'rule',
                'schemaVersion',
                'toArray',
            ],
            self::declaredPublicMethodNames($reflection),
        );
    }

    public function testViolationExposesDeterministicSortKeys(): void
    {
        $violation = new Violation(
            path: 'items[0].sku',
            code: 'VALIDATION_REQUIRED',
            rule: 'required',
            index: 10,
        );

        self::assertSame('items[0].sku', $violation->path());
        self::assertSame('VALIDATION_REQUIRED', $violation->code());
        self::assertSame('required', $violation->rule());
        self::assertSame(10, $violation->index());
    }

    public function testValidationExceptionSurfaceIsContractsOnly(): void
    {
        $reflection = new \ReflectionClass(ValidationException::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(\RuntimeException::class));

        self::assertSame('CORETSIA_VALIDATION_FAILED', ValidationException::CODE);
        self::assertSame('Validation failed.', ValidationException::MESSAGE);

        self::assertTrue($reflection->hasMethod('errorCode'));
        self::assertTrue($reflection->hasMethod('result'));

        self::assertSame('string', self::namedTypeName($reflection->getMethod('errorCode')->getReturnType()));
        self::assertSame(
            ValidationResult::class,
            self::namedTypeName($reflection->getMethod('result')->getReturnType())
        );
    }

    public function testValidationContractsDoNotDeclareDiTagConstants(): void
    {
        foreach (
            [
                ValidatorInterface::class,
                ValidationResult::class,
                Violation::class,
                ValidationException::class,
            ] as $className
        ) {
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getReflectionConstants() as $constant) {
                self::assertStringNotContainsString('TAG', $constant->getName());
                self::assertNotSame('error.mapper', $constant->getValue());
            }
        }
    }

    public function testValidationContractsPublicTypesDoNotUseForbiddenRuntimeNamespaces(): void
    {
        foreach (
            [
                ValidatorInterface::class,
                ValidationResult::class,
                Violation::class,
                ValidationException::class,
            ] as $className
        ) {
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                self::assertTypeIsNotForbidden($method->getReturnType());

                foreach ($method->getParameters() as $parameter) {
                    self::assertTypeIsNotForbidden($parameter->getType());
                }
            }

            foreach ($reflection->getProperties() as $property) {
                self::assertTypeIsNotForbidden($property->getType());
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function declaredPublicMethodNames(\ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $method->getName();
        }

        sort($names, \SORT_STRING);

        return $names;
    }

    private static function assertTypeIsNotForbidden(?\ReflectionType $type): void
    {
        if ($type === null) {
            return;
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $innerType) {
                self::assertTypeIsNotForbidden($innerType);
            }

            return;
        }

        self::assertInstanceOf(\ReflectionNamedType::class, $type);

        $name = $type->getName();

        self::assertStringDoesNotStartWith('Psr\\Http\\Message\\', $name);
        self::assertStringDoesNotStartWith('Coretsia\\Platform\\', $name);
        self::assertStringDoesNotStartWith('Coretsia\\Integrations\\', $name);
    }

    private static function namedTypeName(?\ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        self::assertInstanceOf(\ReflectionNamedType::class, $type);

        return $type->getName();
    }

    private static function assertStringDoesNotStartWith(string $prefix, string $actual): void
    {
        self::assertFalse(
            str_starts_with($actual, $prefix),
            sprintf(
                'Failed asserting that "%s" does not start with "%s".',
                $actual,
                $prefix,
            ),
        );
    }
}
