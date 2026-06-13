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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\ContainerCompiler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompiledContainerPreservesLaterBindingOverridesTest extends TestCase
{
    public function testLaterServiceParameterAndAliasBindingsOverrideEarlierBindings(): void
    {
        $graph = self::compiler()->compile([
            [
                'kind' => 'parameter',
                'name' => 'override.value',
                'value' => 'first',
            ],
            [
                'kind' => 'service.class',
                'id' => 'test.override.service',
                'class' => CompiledContainerPreservesLaterBindingOverridesFirstService::class,
                'shared' => true,
                'arguments' => [
                    [
                        'name' => 'override.value',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'alias',
                'alias' => 'test.override.alias',
                'serviceId' => 'test.override.first',
            ],
            [
                'kind' => 'parameter',
                'name' => 'override.value',
                'value' => 'second',
            ],
            [
                'kind' => 'service.class',
                'id' => 'test.override.service',
                'class' => CompiledContainerPreservesLaterBindingOverridesSecondService::class,
                'shared' => false,
                'arguments' => [
                    [
                        'name' => 'override.value',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'alias',
                'alias' => 'test.override.alias',
                'serviceId' => 'test.override.service',
            ],
        ]);

        $payload = $graph->toArray();

        self::assertSame(
            [
                'override.value' => 'second',
            ],
            $payload['parameters'],
            'Later parameter binding must override earlier parameter binding for the same name.',
        );

        self::assertArrayHasKey('test.override.service', $payload['services']);

        $service = $payload['services']['test.override.service'];

        self::assertSame(
            CompiledContainerPreservesLaterBindingOverridesSecondService::class,
            $service['construction']['class'] ?? null,
            'Later service binding must override earlier service binding for the same id.',
        );
        self::assertFalse($service['shared']);
        self::assertSame(
            [
                [
                    'name' => 'override.value',
                    'type' => 'parameter',
                ],
            ],
            $service['arguments'],
        );

        self::assertSame(
            [
                'test.override.alias' => 'test.override.service',
            ],
            $payload['aliases'],
            'Later alias binding must override earlier alias binding for the same alias.',
        );
    }

    public function testExportedGraphRemainsDeterministicallySortedAfterOverrides(): void
    {
        $graph = self::compiler()->compile([
            [
                'kind' => 'service.class',
                'id' => 'z.service',
                'class' => CompiledContainerPreservesLaterBindingOverridesFirstService::class,
            ],
            [
                'kind' => 'service.class',
                'id' => 'a.service',
                'class' => CompiledContainerPreservesLaterBindingOverridesSecondService::class,
            ],
            [
                'kind' => 'alias',
                'alias' => 'z.alias',
                'serviceId' => 'z.service',
            ],
            [
                'kind' => 'alias',
                'alias' => 'a.alias',
                'serviceId' => 'a.service',
            ],
            [
                'kind' => 'parameter',
                'name' => 'z.parameter',
                'value' => 'z',
            ],
            [
                'kind' => 'parameter',
                'name' => 'a.parameter',
                'value' => 'a',
            ],
        ]);

        $payload = $graph->toArray();

        self::assertSame(['a.service', 'z.service'], \array_keys($payload['services']));
        self::assertSame(['a.alias', 'z.alias'], \array_keys($payload['aliases']));
        self::assertSame(['a.parameter', 'z.parameter'], \array_keys($payload['parameters']));
    }

    private static function compiler(): ContainerCompiler
    {
        return new ContainerCompiler(
            tracer: self::tracer(),
            meter: self::meter(),
            logger: new NullLogger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function tracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return CompiledContainerPreservesLaterBindingOverridesTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = CompiledContainerPreservesLaterBindingOverridesTest::span($name);

                try {
                    return $callback($span);
                } finally {
                    $span->end();
                }
            }

            public function currentSpan(): ?SpanInterface
            {
                return null;
            }
        };
    }

    public static function span(string $name = 'kernel.test'): SpanInterface
    {
        return new class($name) implements SpanInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function setAttribute(string $key, mixed $value): void
            {
            }

            public function setAttributes(array $attributes): void
            {
            }

            public function addEvent(string $name, array $attributes = []): void
            {
            }

            public function recordException(\Throwable $throwable, array $attributes = []): void
            {
            }

            public function end(): void
            {
            }
        };
    }

    private static function meter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
    }
}

final class CompiledContainerPreservesLaterBindingOverridesFirstService
{
}

final class CompiledContainerPreservesLaterBindingOverridesSecondService
{
}
