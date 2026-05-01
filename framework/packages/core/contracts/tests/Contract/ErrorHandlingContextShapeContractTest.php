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

use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final class ErrorHandlingContextShapeContractTest extends TestCase
{
    public function test_constructor_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ErrorHandlingContext::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(3, $parameters);

        self::assertSame('operation', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', true);
        self::assertTrue($parameters[0]->isDefaultValueAvailable());
        self::assertNull($parameters[0]->getDefaultValue());

        self::assertSame('correlationId', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', true);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertSame('metadata', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], 'array', false);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertSame([], $parameters[2]->getDefaultValue());
    }

    public function test_getters_and_public_array_shape_are_stable(): void
    {
        $context = new ErrorHandlingContext(
            operation: 'contract-test',
            correlationId: 'corr-1',
            metadata: [
                'z' => 'last',
                'a' => 'first',
            ],
        );

        self::assertSame('contract-test', $context->operation());
        self::assertSame('corr-1', $context->correlationId());
        self::assertSame(
            [
                'a' => 'first',
                'z' => 'last',
            ],
            $context->metadata(),
        );

        self::assertSame(
            [
                'correlationId' => 'corr-1',
                'metadata' => [
                    'a' => 'first',
                    'z' => 'last',
                ],
                'operation' => 'contract-test',
            ],
            $context->toArray(),
        );

        self::assertSame(
            [
                'correlationId',
                'metadata',
                'operation',
            ],
            array_keys($context->toArray()),
        );
    }

    public function test_default_context_is_empty_and_format_neutral(): void
    {
        $context = new ErrorHandlingContext();

        self::assertNull($context->operation());
        self::assertNull($context->correlationId());
        self::assertSame([], $context->metadata());

        self::assertSame(
            [
                'correlationId' => null,
                'metadata' => [],
                'operation' => null,
            ],
            $context->toArray(),
        );
    }

    public function test_context_rejects_empty_operation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorHandlingContext(operation: '');
    }

    public function test_context_rejects_empty_correlation_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorHandlingContext(correlationId: '');
    }

    public function test_context_rejects_multiline_operation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorHandlingContext(operation: "operation\nunsafe");
    }

    public function test_context_rejects_multiline_correlation_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorHandlingContext(correlationId: "corr\nunsafe");
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
