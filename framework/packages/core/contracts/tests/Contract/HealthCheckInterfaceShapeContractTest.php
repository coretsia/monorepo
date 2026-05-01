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
                'name',
            ],
            $methodNames,
        );

        $name = $reflection->getMethod('name');

        self::assertTrue($name->isPublic());
        self::assertSame(0, $name->getNumberOfParameters());
        self::assertSame(0, $name->getNumberOfRequiredParameters());
        self::assertMethodReturnType($name, 'string', false);

        $check = $reflection->getMethod('check');

        self::assertTrue($check->isPublic());
        self::assertSame(0, $check->getNumberOfParameters());
        self::assertSame(0, $check->getNumberOfRequiredParameters());
        self::assertMethodReturnType($check, HealthStatus::class, false);
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

    public function test_health_check_implementations_can_return_health_status(): void
    {
        $check = new class() implements HealthCheckInterface {
            public function name(): string
            {
                return 'core.health';
            }

            public function check(): HealthStatus
            {
                return HealthStatus::Pass;
            }
        };

        self::assertSame('core.health', $check->name());
        self::assertSame(HealthStatus::Pass, $check->check());
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
