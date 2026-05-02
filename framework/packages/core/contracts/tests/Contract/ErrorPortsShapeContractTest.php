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
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

final class ErrorPortsShapeContractTest extends TestCase
{
    public function test_exception_mapper_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ExceptionMapperInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertInterfaceMethods($reflection, ['map']);

        $method = $reflection->getMethod('map');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertParameterNamedType($parameters[0], 'throwable', Throwable::class, false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[1], 'context', ErrorHandlingContext::class, true);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, ErrorDescriptor::class, true);
    }

    public function test_error_reporter_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ErrorReporterPortInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertInterfaceMethods($reflection, ['report']);

        $method = $reflection->getMethod('report');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertParameterNamedType($parameters[0], 'descriptor', ErrorDescriptor::class, false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[1], 'context', ErrorHandlingContext::class, true);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_error_handler_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ErrorHandlerInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertInterfaceMethods($reflection, ['handle']);

        $method = $reflection->getMethod('handle');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertParameterNamedType($parameters[0], 'throwable', Throwable::class, false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[1], 'context', ErrorHandlingContext::class, true);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, ErrorDescriptor::class, false);
    }

    public function test_error_ports_can_compose_through_format_neutral_contracts(): void
    {
        $mapper = new class() implements ExceptionMapperInterface {
            public function map(
                Throwable $throwable,
                ?ErrorHandlingContext $context = null,
            ): ?ErrorDescriptor {
                return new ErrorDescriptor(
                    code: 'core.runtime_error',
                    message: 'Runtime error.',
                    severity: ErrorSeverity::Error,
                    httpStatus: null,
                    extensions: [
                        'throwableClass' => $throwable::class,
                    ],
                );
            }
        };

        $reporter = new class() implements ErrorReporterPortInterface {
            public ?ErrorDescriptor $descriptor = null;

            public ?ErrorHandlingContext $context = null;

            public function report(
                ErrorDescriptor $descriptor,
                ?ErrorHandlingContext $context = null,
            ): void {
                $this->descriptor = $descriptor;
                $this->context = $context;
            }
        };

        $handler = new class($mapper, $reporter) implements ErrorHandlerInterface {
            public function __construct(
                private readonly ExceptionMapperInterface $mapper,
                private readonly ErrorReporterPortInterface $reporter,
            ) {
            }

            public function handle(
                Throwable $throwable,
                ?ErrorHandlingContext $context = null,
            ): ErrorDescriptor {
                $descriptor = $this->mapper->map($throwable, $context)
                    ?? new ErrorDescriptor(
                        code: 'core.unmapped_error',
                        message: 'Unhandled error.',
                    );

                $this->reporter->report($descriptor, $context);

                return $descriptor;
            }
        };

        $context = new ErrorHandlingContext(
            operation: 'contract-test',
            correlationId: 'corr-1',
            metadata: [
                'attempt' => 1,
            ],
        );

        $descriptor = $handler->handle(
            new RuntimeException('private internal exception message'),
            $context,
        );

        self::assertSame('core.runtime_error', $descriptor->code());
        self::assertSame('Runtime error.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertNull($descriptor->httpStatus());
        self::assertSame(
            [
                'throwableClass' => RuntimeException::class,
            ],
            $descriptor->extensions(),
        );

        self::assertSame($descriptor, $reporter->descriptor);
        self::assertSame($context, $reporter->context);
    }

    /**
     * @param list<string> $expectedMethodNames
     */
    private static function assertInterfaceMethods(
        ReflectionClass $reflection,
        array $expectedMethodNames,
    ): void {
        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        sort($methodNames, \SORT_STRING);
        sort($expectedMethodNames, \SORT_STRING);

        self::assertSame($expectedMethodNames, $methodNames);
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedParameterName,
        string $expectedTypeName,
        bool $expectedAllowsNull,
    ): void {
        self::assertSame($expectedParameterName, $parameter->getName());

        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedTypeName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }

    private static function assertMethodReturnType(
        ReflectionMethod $method,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
