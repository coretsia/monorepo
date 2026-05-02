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

use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

final class SpanInterfaceShapeContractTest extends TestCase
{
    public function test_span_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(SpanInterface::class);

        self::assertTrue($reflection->isInterface());

        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        sort($methodNames, \SORT_STRING);

        self::assertSame(
            [
                'addEvent',
                'end',
                'name',
                'recordException',
                'setAttribute',
                'setAttributes',
            ],
            $methodNames,
        );
    }

    public function test_name_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'name');

        self::assertTrue($method->isPublic());
        self::assertSame(0, $method->getNumberOfParameters());
        self::assertSame(0, $method->getNumberOfRequiredParameters());
        self::assertMethodReturnType($method, 'string', false);
    }

    public function test_set_attribute_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'setAttribute');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(2, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('key', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame('string', $parameters[0]->getType()->getName());

        self::assertSame('value', $parameters[1]->getName());

        $valueType = $parameters[1]->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $valueType);
        self::assertSame('mixed', $valueType->getName());
        self::assertTrue($valueType->allowsNull());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_set_attributes_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'setAttributes');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('attributes', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame('array', $parameters[0]->getType()->getName());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_add_event_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'addEvent');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('name', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame('string', $parameters[0]->getType()->getName());

        self::assertSame('attributes', $parameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame('array', $parameters[1]->getType()->getName());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame([], $parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_record_exception_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'recordException');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('throwable', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(Throwable::class, $parameters[0]->getType()->getName());

        self::assertSame('attributes', $parameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame('array', $parameters[1]->getType()->getName());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame([], $parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_end_shape_is_stable(): void
    {
        $method = new ReflectionMethod(SpanInterface::class, 'end');

        self::assertTrue($method->isPublic());
        self::assertSame(0, $method->getNumberOfParameters());
        self::assertSame(0, $method->getNumberOfRequiredParameters());
        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_span_implementation_can_record_attributes_events_exception_and_end(): void
    {
        $span = new class() implements SpanInterface {
            /**
             * @var array<string,mixed>
             */
            public array $attributes = [];

            /**
             * @var list<array{name: string, attributes: array<string,mixed>}>
             */
            public array $events = [];

            public int $recordedExceptions = 0;

            public bool $ended = false;

            public function name(): string
            {
                return 'core.test';
            }

            public function setAttribute(string $key, mixed $value): void
            {
                $this->attributes[$key] = $value;
            }

            /**
             * @param array<string,mixed> $attributes
             */
            public function setAttributes(array $attributes): void
            {
                foreach ($attributes as $key => $value) {
                    $this->attributes[$key] = $value;
                }
            }

            /**
             * @param array<string,mixed> $attributes
             */
            public function addEvent(string $name, array $attributes = []): void
            {
                $this->events[] = [
                    'name' => $name,
                    'attributes' => $attributes,
                ];
            }

            /**
             * @param array<string,mixed> $attributes
             */
            public function recordException(Throwable $throwable, array $attributes = []): void
            {
                $this->recordedExceptions++;
                $this->attributes['exception.type'] = $throwable::class;
            }

            public function end(): void
            {
                $this->ended = true;
            }
        };

        $span->setAttribute('operation', 'test');
        $span->setAttributes(['attempt' => 1]);
        $span->addEvent('event.test', ['ok' => true]);
        $span->recordException(new RuntimeException('private'));
        $span->end();

        self::assertSame('core.test', $span->name());
        self::assertSame('test', $span->attributes['operation']);
        self::assertSame(1, $span->attributes['attempt']);
        self::assertSame(RuntimeException::class, $span->attributes['exception.type']);
        self::assertSame(1, $span->recordedExceptions);
        self::assertSame(
            [
                [
                    'name' => 'event.test',
                    'attributes' => [
                        'ok' => true,
                    ],
                ],
            ],
            $span->events,
        );
        self::assertTrue($span->ended);
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
