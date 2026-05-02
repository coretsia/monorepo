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

use Coretsia\Contracts\Observability\Tracing\SpanExporterInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class SpanExporterInterfaceShapeContractTest extends TestCase
{
    public function test_span_exporter_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(SpanExporterInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('export'));

        $method = $reflection->getMethod('export');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('spans', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame('iterable', $parameters[0]->getType()->getName());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_span_exporter_accepts_iterable_of_span_interfaces(): void
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
            public function recordException(\Throwable $throwable, array $attributes = []): void
            {
                $this->recordedExceptions++;
                $this->attributes['exception.recorded'] = true;
            }

            public function end(): void
            {
                $this->ended = true;
            }
        };

        $exporter = new class() implements SpanExporterInterface {
            /**
             * @var list<SpanInterface>
             */
            public array $exported = [];

            /**
             * @param iterable<SpanInterface> $spans
             */
            public function export(iterable $spans): void
            {
                foreach ($spans as $span) {
                    $this->exported[] = $span;
                }
            }
        };

        $exporter->export([$span]);

        self::assertSame([$span], $exporter->exported);
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
