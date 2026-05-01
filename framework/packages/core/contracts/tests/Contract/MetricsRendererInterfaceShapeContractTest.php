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

use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class MetricsRendererInterfaceShapeContractTest extends TestCase
{
    public function test_metrics_renderer_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(MetricsRendererInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('render'));

        $method = $reflection->getMethod('render');

        self::assertTrue($method->isPublic());
        self::assertSame(0, $method->getNumberOfParameters());
        self::assertSame(0, $method->getNumberOfRequiredParameters());

        self::assertMethodReturnType($method, 'string', false);
    }

    public function test_metrics_renderer_returns_string_without_vendor_api_requirement(): void
    {
        $renderer = new class() implements MetricsRendererInterface {
            public function render(): string
            {
                return "core_metric_total 1\n";
            }
        };

        self::assertSame("core_metric_total 1\n", $renderer->render());
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
