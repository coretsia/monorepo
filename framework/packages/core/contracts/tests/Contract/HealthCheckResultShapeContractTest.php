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

use Coretsia\Contracts\Observability\Health\HealthCheckResult;
use Coretsia\Contracts\Observability\Health\HealthStatus;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

final class HealthCheckResultShapeContractTest extends TestCase
{
    public function test_constructor_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(HealthCheckResult::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(3, $parameters);
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());

        self::assertSame('status', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], HealthStatus::class, false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertSame('message', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', true);
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());

        self::assertSame('details', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], 'array', false);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertSame([], $parameters[2]->getDefaultValue());
    }

    public function test_getters_and_array_shape_are_stable(): void
    {
        $result = new HealthCheckResult(
            status: HealthStatus::Warn,
            message: 'Degraded.',
            details: [
                'z' => [
                    'b' => false,
                    'a' => true,
                ],
                'latencyMs' => 42,
            ],
        );

        self::assertSame(1, $result->schemaVersion());
        self::assertSame(HealthStatus::Warn, $result->status());
        self::assertSame('Degraded.', $result->message());
        self::assertSame(
            [
                'latencyMs' => 42,
                'z' => [
                    'a' => true,
                    'b' => false,
                ],
            ],
            $result->details(),
        );

        self::assertSame(
            [
                'details' => [
                    'latencyMs' => 42,
                    'z' => [
                        'a' => true,
                        'b' => false,
                    ],
                ],
                'message' => 'Degraded.',
                'schemaVersion' => 1,
                'status' => 'warn',
            ],
            $result->toArray(),
        );

        self::assertSame(
            [
                'details',
                'message',
                'schemaVersion',
                'status',
            ],
            array_keys($result->toArray()),
        );
    }

    public function test_empty_message_normalizes_to_null(): void
    {
        $result = new HealthCheckResult(
            status: HealthStatus::Pass,
            message: '',
        );

        self::assertNull($result->message());
        self::assertNull($result->toArray()['message']);
    }

    public function test_message_rejects_outer_whitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HealthCheckResult(
            status: HealthStatus::Pass,
            message: ' ',
        );
    }

    public function test_details_preserve_list_order_and_sort_maps(): void
    {
        $result = new HealthCheckResult(
            status: HealthStatus::Pass,
            details: [
                'items' => [
                    'z',
                    'a',
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'items' => [
                    'z',
                    'a',
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
            ],
            $result->details(),
        );
    }

    public function test_details_reject_non_empty_root_lists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HealthCheckResult(
            status: HealthStatus::Pass,
            details: ['root-list-value'],
        );
    }

    public function test_details_reject_floats_objects_closures_and_invalid_keys(): void
    {
        $invalidCases = [
            'float' => [
                'value' => 1.5,
            ],
            'nan' => [
                'value' => \NAN,
            ],
            'inf' => [
                'value' => \INF,
            ],
            'object' => [
                'value' => new stdClass(),
            ],
            'closure' => [
                'value' => static fn (): string => 'invalid',
            ],
            'empty-key' => [
                '' => 'value',
            ],
        ];

        foreach ($invalidCases as $label => $details) {
            try {
                new HealthCheckResult(
                    status: HealthStatus::Pass,
                    details: $details,
                );

                self::fail(sprintf('Expected invalid health details case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_message_rejects_multiline_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HealthCheckResult(
            status: HealthStatus::Fail,
            message: "line\nbreak",
        );
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
