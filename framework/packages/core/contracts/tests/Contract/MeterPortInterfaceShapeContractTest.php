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

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class MeterPortInterfaceShapeContractTest extends TestCase
{
    public function test_meter_port_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(MeterPortInterface::class);

        self::assertTrue($reflection->isInterface());

        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        sort($methodNames, \SORT_STRING);

        self::assertSame(
            [
                'increment',
                'observe',
            ],
            $methodNames,
        );

        $increment = $reflection->getMethod('increment');

        self::assertTrue($increment->isPublic());
        self::assertSame(3, $increment->getNumberOfParameters());
        self::assertSame(1, $increment->getNumberOfRequiredParameters());

        $incrementParameters = $increment->getParameters();

        self::assertParameterNamedType($incrementParameters[0], 'name', 'string', false);
        self::assertFalse($incrementParameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($incrementParameters[1], 'delta', 'int', false);
        self::assertTrue($incrementParameters[1]->isDefaultValueAvailable());
        self::assertSame(1, $incrementParameters[1]->getDefaultValue());

        self::assertParameterNamedType($incrementParameters[2], 'labels', 'array', false);
        self::assertTrue($incrementParameters[2]->isDefaultValueAvailable());
        self::assertSame([], $incrementParameters[2]->getDefaultValue());

        self::assertMethodReturnType($increment, 'void', false);

        $observe = $reflection->getMethod('observe');

        self::assertTrue($observe->isPublic());
        self::assertSame(3, $observe->getNumberOfParameters());
        self::assertSame(2, $observe->getNumberOfRequiredParameters());

        $observeParameters = $observe->getParameters();

        self::assertParameterNamedType($observeParameters[0], 'name', 'string', false);
        self::assertFalse($observeParameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($observeParameters[1], 'value', 'int', false);
        self::assertFalse($observeParameters[1]->isDefaultValueAvailable());

        self::assertParameterNamedType($observeParameters[2], 'labels', 'array', false);
        self::assertTrue($observeParameters[2]->isDefaultValueAvailable());
        self::assertSame([], $observeParameters[2]->getDefaultValue());

        self::assertMethodReturnType($observe, 'void', false);
    }

    public function test_meter_port_accepts_safe_bounded_scalar_label_values_without_null_labels(): void
    {
        $meter = new class() implements MeterPortInterface {
            /**
             * @var list<array{name: string, delta: int, labels: array<string,string|int|bool>}>
             */
            public array $increments = [];

            /**
             * @var list<array{name: string, value: int, labels: array<string,string|int|bool>}>
             */
            public array $observations = [];

            /**
             * @param array<string,string|int|bool> $labels
             */
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
                $this->assertLabelsDoNotContainNull($labels);

                $this->increments[] = [
                    'name' => $name,
                    'delta' => $delta,
                    'labels' => $labels,
                ];
            }

            /**
             * @param array<string,string|int|bool> $labels
             */
            public function observe(string $name, int $value, array $labels = []): void
            {
                $this->assertLabelsDoNotContainNull($labels);

                $this->observations[] = [
                    'name' => $name,
                    'value' => $value,
                    'labels' => $labels,
                ];
            }

            /**
             * @param array<string,string|int|bool> $labels
             */
            private function assertLabelsDoNotContainNull(array $labels): void
            {
                foreach ($labels as $value) {
                    if ($value === null) {
                        throw new \InvalidArgumentException(
                            'Metric labels must omit absent labels instead of using null.'
                        );
                    }
                }
            }
        };

        $labels = [
            'method' => 'GET',
            'status' => 200,
            'outcome' => true,
        ];

        $meter->increment('core.requests_total', labels: $labels);
        $meter->observe('core.request_duration_ms', 12, $labels);

        self::assertSame(
            [
                [
                    'name' => 'core.requests_total',
                    'delta' => 1,
                    'labels' => $labels,
                ],
            ],
            $meter->increments,
        );

        self::assertSame(
            [
                [
                    'name' => 'core.request_duration_ms',
                    'value' => 12,
                    'labels' => $labels,
                ],
            ],
            $meter->observations,
        );
    }

    public function test_meter_label_phpdoc_documents_non_null_bounded_scalar_labels(): void
    {
        $reflection = new ReflectionClass(MeterPortInterface::class);

        foreach (['increment', 'observe'] as $methodName) {
            $method = $reflection->getMethod($methodName);
            $docComment = $method->getDocComment();

            self::assertIsString($docComment);
            self::assertStringContainsString(
                '@param array<string,string|int|bool> $labels',
                $docComment,
            );
            self::assertStringNotContainsString(
                'string|int|bool|null',
                $docComment,
            );
        }
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
