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

use Coretsia\Contracts\Observability\Tracing\SamplerInterface;
use Coretsia\Contracts\Observability\Tracing\SamplingDecision;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class SamplerInterfaceShapeContractTest extends TestCase
{
    public function test_sampler_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(SamplerInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('shouldSample'));

        $method = $reflection->getMethod('shouldSample');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('spanName', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame('string', $parameters[0]->getType()->getName());

        self::assertSame('attributes', $parameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame('array', $parameters[1]->getType()->getName());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame([], $parameters[1]->getDefaultValue());

        self::assertMethodReturnType($method, SamplingDecision::class, false);
    }

    public function test_sampler_implementations_can_return_sampling_decision(): void
    {
        $sampler = new class() implements SamplerInterface {
            /**
             * @var array{0: string, 1: array<string,mixed>}|null
             */
            public ?array $lastCall = null;

            /**
             * @param array<string,mixed> $attributes
             */
            public function shouldSample(string $spanName, array $attributes = []): SamplingDecision
            {
                $this->lastCall = [$spanName, $attributes];

                return SamplingDecision::Defer;
            }
        };

        self::assertSame(
            SamplingDecision::Defer,
            $sampler->shouldSample('core.operation', ['operation' => 'test']),
        );

        self::assertSame(
            [
                'core.operation',
                [
                    'operation' => 'test',
                ],
            ],
            $sampler->lastCall,
        );
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
