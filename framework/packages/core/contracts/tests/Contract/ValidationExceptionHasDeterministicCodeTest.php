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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Validation\ValidationException;
use Coretsia\Contracts\Validation\ValidationResult;
use Coretsia\Contracts\Validation\Violation;
use PHPUnit\Framework\TestCase;

final class ValidationExceptionHasDeterministicCodeTest extends TestCase
{
    public function testValidationExceptionExistsAndExtendsRuntimeException(): void
    {
        self::assertTrue(class_exists(ValidationException::class));
        self::assertTrue(is_subclass_of(ValidationException::class, \RuntimeException::class));
    }

    public function testValidationExceptionExposesDeterministicStringErrorCode(): void
    {
        $result = ValidationResult::failure([
            new Violation('email', 'VALIDATION_REQUIRED'),
        ]);

        $exception = new ValidationException($result);

        self::assertSame('CORETSIA_VALIDATION_FAILED', ValidationException::CODE);
        self::assertSame('CORETSIA_VALIDATION_FAILED', $exception->errorCode());

        /**
         * The canonical validation code is the stable string returned by
         * errorCode(). PHP native Throwable::getCode() remains intentionally
         * numeric and non-canonical.
         */
        self::assertSame(0, $exception->getCode());
    }

    public function testValidationExceptionCarriesFailedValidationResult(): void
    {
        $result = ValidationResult::failure([
            new Violation('email', 'VALIDATION_REQUIRED'),
        ]);

        $exception = new ValidationException($result);

        self::assertSame($result, $exception->result());
        self::assertTrue($exception->result()->isFailure());
    }

    public function testValidationExceptionRejectsSuccessfulValidationResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation exception requires a failed validation result.');

        new ValidationException(ValidationResult::success());
    }

    public function testValidationExceptionMessageIsSafeAndGeneric(): void
    {
        $exception = new ValidationException(
            ValidationResult::failure([
                new Violation('email', 'VALIDATION_REQUIRED'),
            ]),
        );

        self::assertSame('Validation failed.', ValidationException::MESSAGE);
        self::assertSame('Validation failed.', $exception->getMessage());
        self::assertStringNotContainsString("\n", $exception->getMessage());
        self::assertStringNotContainsString("\r", $exception->getMessage());
        self::assertStringNotContainsString('/tmp', $exception->getMessage());
        self::assertStringNotContainsString('password', strtolower($exception->getMessage()));
        self::assertStringNotContainsString('token', strtolower($exception->getMessage()));
    }

    public function testValidationExceptionPreservesPreviousThrowable(): void
    {
        $previous = new \RuntimeException('Previous safe failure.');

        $exception = new ValidationException(
            ValidationResult::failure([
                new Violation('email', 'VALIDATION_REQUIRED'),
            ]),
            $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testValidationExceptionDoesNotExposeErrorDescriptorBehavior(): void
    {
        $reflection = new \ReflectionClass(ValidationException::class);

        self::assertFalse($reflection->hasMethod('toErrorDescriptor'));
        self::assertFalse($reflection->hasMethod('errorDescriptor'));
        self::assertFalse($reflection->hasMethod('mapToErrorDescriptor'));
        self::assertFalse($reflection->hasMethod('httpStatus'));

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof \ReflectionNamedType) {
                self::assertNotSame(ErrorDescriptor::class, $returnType->getName());
            }
        }

        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();

            if ($type instanceof \ReflectionNamedType) {
                self::assertNotSame(ErrorDescriptor::class, $type->getName());
            }
        }
    }
}
