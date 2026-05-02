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
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final class ErrorDescriptorShapeContractTest extends TestCase
{
    public function test_constructor_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ErrorDescriptor::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(5, $parameters);
        self::assertSame(2, $constructor->getNumberOfRequiredParameters());

        self::assertSame('code', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertSame('message', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', false);
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        self::assertSame('severity', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], ErrorSeverity::class, false);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertSame(ErrorSeverity::Error, $parameters[2]->getDefaultValue());

        self::assertSame('httpStatus', $parameters[3]->getName());
        self::assertParameterNamedType($parameters[3], 'int', true);
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertNull($parameters[3]->getDefaultValue());

        self::assertSame('extensions', $parameters[4]->getName());
        self::assertParameterNamedType($parameters[4], 'array', false);
        self::assertTrue($parameters[4]->isDefaultValueAvailable());
        self::assertSame([], $parameters[4]->getDefaultValue());
    }

    public function test_getters_and_array_shape_are_stable(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
            httpStatus: 500,
            extensions: [
                'z' => 'last',
                'a' => 'first',
            ],
        );

        self::assertSame(1, $descriptor->schemaVersion());
        self::assertSame('core.example', $descriptor->code());
        self::assertSame('Example message.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame(500, $descriptor->httpStatus());
        self::assertSame(
            [
                'a' => 'first',
                'z' => 'last',
            ],
            $descriptor->extensions(),
        );

        self::assertSame(
            [
                'code' => 'core.example',
                'extensions' => [
                    'a' => 'first',
                    'z' => 'last',
                ],
                'httpStatus' => 500,
                'message' => 'Example message.',
                'schemaVersion' => 1,
                'severity' => 'error',
            ],
            $descriptor->toArray(),
        );

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'schemaVersion',
                'severity',
            ],
            array_keys($descriptor->toArray()),
        );
    }

    public function test_severity_defaults_to_error(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
        );

        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame('error', $descriptor->toArray()['severity']);
    }

    public function test_descriptor_rejects_empty_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('', 'Example message.');
    }

    public function test_descriptor_rejects_empty_message(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('core.example', '');
    }

    public function test_descriptor_rejects_invalid_code_shape(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('123.invalid', 'Example message.');
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
