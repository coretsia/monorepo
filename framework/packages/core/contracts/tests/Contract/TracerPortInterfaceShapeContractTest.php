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
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class TracerPortInterfaceShapeContractTest extends TestCase
{
    public function test_tracer_port_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(TracerPortInterface::class);

        self::assertTrue($reflection->isInterface());

        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        sort($methodNames, \SORT_STRING);

        self::assertSame(
            [
                'currentSpan',
                'inSpan',
                'startSpan',
            ],
            $methodNames,
        );

        $startSpan = $reflection->getMethod('startSpan');

        self::assertTrue($startSpan->isPublic());
        self::assertSame(2, $startSpan->getNumberOfParameters());
        self::assertSame(1, $startSpan->getNumberOfRequiredParameters());
        self::assertMethodReturnType($startSpan, SpanInterface::class, false);

        $startParameters = $startSpan->getParameters();

        self::assertSame('name', $startParameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $startParameters[0]->getType());
        self::assertSame('string', $startParameters[0]->getType()->getName());

        self::assertSame('attributes', $startParameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $startParameters[1]->getType());
        self::assertSame('array', $startParameters[1]->getType()->getName());
        self::assertTrue($startParameters[1]->isDefaultValueAvailable());
        self::assertSame([], $startParameters[1]->getDefaultValue());

        $inSpan = $reflection->getMethod('inSpan');

        self::assertTrue($inSpan->isPublic());
        self::assertSame(3, $inSpan->getNumberOfParameters());
        self::assertSame(2, $inSpan->getNumberOfRequiredParameters());
        self::assertMethodReturnType($inSpan, 'mixed', true);

        $inSpanParameters = $inSpan->getParameters();

        self::assertSame('name', $inSpanParameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $inSpanParameters[0]->getType());
        self::assertSame('string', $inSpanParameters[0]->getType()->getName());

        self::assertSame('callback', $inSpanParameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $inSpanParameters[1]->getType());
        self::assertSame('callable', $inSpanParameters[1]->getType()->getName());

        self::assertSame('attributes', $inSpanParameters[2]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $inSpanParameters[2]->getType());
        self::assertSame('array', $inSpanParameters[2]->getType()->getName());
        self::assertTrue($inSpanParameters[2]->isDefaultValueAvailable());
        self::assertSame([], $inSpanParameters[2]->getDefaultValue());

        $currentSpan = $reflection->getMethod('currentSpan');

        self::assertTrue($currentSpan->isPublic());
        self::assertSame(0, $currentSpan->getNumberOfParameters());
        self::assertSame(0, $currentSpan->getNumberOfRequiredParameters());
        self::assertMethodReturnType($currentSpan, SpanInterface::class, true);
    }

    public function test_tracer_implementation_can_run_callback_inside_span(): void
    {
        $tracer = new class() implements TracerPortInterface {
            private ?SpanInterface $current = null;

            /**
             * @param array<string,mixed> $attributes
             */
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                $this->current = new class($name, $attributes) implements SpanInterface {
                    /**
                     * @param array<string,mixed> $attributes
                     */
                    public function __construct(
                        private readonly string $name,
                        public array $attributes = [],
                    ) {
                    }

                    public bool $ended = false;

                    public int $recordedExceptions = 0;

                    public function name(): string
                    {
                        return $this->name;
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
                        $this->attributes['event.' . $name] = $attributes;
                    }

                    /**
                     * @param array<string,mixed> $attributes
                     */
                    public function recordException(\Throwable $throwable, array $attributes = []): void
                    {
                        $this->recordedExceptions++;
                    }

                    public function end(): void
                    {
                        $this->ended = true;
                    }
                };

                return $this->current;
            }

            /**
             * @param array<string,mixed> $attributes
             */
            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = $this->startSpan($name, $attributes);

                try {
                    return $callback($span);
                } catch (\Throwable $throwable) {
                    $span->recordException($throwable);

                    throw $throwable;
                } finally {
                    $span->end();
                }
            }

            public function currentSpan(): ?SpanInterface
            {
                return $this->current;
            }
        };

        $result = $tracer->inSpan(
            'core.operation',
            static fn (SpanInterface $span): string => $span->name(),
            ['operation' => 'test'],
        );

        self::assertSame('core.operation', $result);
        self::assertInstanceOf(SpanInterface::class, $tracer->currentSpan());
        self::assertSame('core.operation', $tracer->currentSpan()?->name());
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
