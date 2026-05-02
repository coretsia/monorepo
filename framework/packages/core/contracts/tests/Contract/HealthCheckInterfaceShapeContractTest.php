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

use Coretsia\Contracts\Observability\Health\HealthCheckInterface;
use Coretsia\Contracts\Observability\Health\HealthCheckResult;
use Coretsia\Contracts\Observability\Health\HealthStatus;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class HealthCheckInterfaceShapeContractTest extends TestCase
{
    public function test_health_check_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(HealthCheckInterface::class);

        self::assertTrue($reflection->isInterface());

        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        sort($methodNames, \SORT_STRING);

        self::assertSame(
            [
                'check',
                'id',
            ],
            $methodNames,
        );

        $id = $reflection->getMethod('id');

        self::assertTrue($id->isPublic());
        self::assertSame(0, $id->getNumberOfParameters());
        self::assertSame(0, $id->getNumberOfRequiredParameters());
        self::assertMethodReturnType($id, 'string', false);

        $check = $reflection->getMethod('check');

        self::assertTrue($check->isPublic());
        self::assertSame(0, $check->getNumberOfParameters());
        self::assertSame(0, $check->getNumberOfRequiredParameters());
        self::assertMethodReturnType($check, HealthCheckResult::class, false);
    }

    public function test_health_status_cases_are_stable(): void
    {
        self::assertSame(
            [
                'pass',
                'warn',
                'fail',
            ],
            array_map(
                static fn (HealthStatus $status): string => $status->value,
                HealthStatus::cases(),
            ),
        );

        self::assertSame(
            [
                'pass',
                'warn',
                'fail',
            ],
            HealthStatus::values(),
        );
    }

    public function test_health_check_implementations_can_return_health_result(): void
    {
        $check = new class() implements HealthCheckInterface {
            public function id(): string
            {
                return 'core.health';
            }

            public function check(): HealthCheckResult
            {
                return new HealthCheckResult(
                    status: HealthStatus::Pass,
                    message: 'Healthy.',
                    details: [
                        'component' => 'core',
                    ],
                );
            }
        };

        self::assertSame('core.health', $check->id());

        $result = $check->check();

        self::assertSame(HealthStatus::Pass, $result->status());
        self::assertSame('Healthy.', $result->message());
        self::assertSame(['component' => 'core'], $result->details());
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
