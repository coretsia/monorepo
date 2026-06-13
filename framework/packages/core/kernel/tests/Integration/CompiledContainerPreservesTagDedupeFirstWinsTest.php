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

final class CompiledContainerPreservesTagDedupeFirstWinsTest extends TestCase
{
    public function testDuplicateTagRegistrationUsesFirstWinsSemantics(): void
    {
        $graph = self::compiler()->compile([
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.beta',
                'priority' => 10,
                'meta' => [],
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.beta',
                'priority' => 999,
                'meta' => [],
            ],
        ]);

        $payload = $graph->toArray();

        self::assertSame(
            [
                'kernel.reset' => [
                    [
                        'id' => 'test.reset.beta',
                        'priority' => 10,
                    ],
                ],
            ],
            $payload['tags'],
            'Later duplicate tag registration for the same tag/service id must be ignored.',
        );
    }

    public function testTagExportUsesPriorityDescendingThenServiceIdAscendingOrder(): void
    {
        $graph = self::compiler()->compile([
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.beta',
                'priority' => 10,
                'meta' => [],
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.gamma',
                'priority' => 50,
                'meta' => [],
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.alpha',
                'priority' => 10,
                'meta' => [],
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.gamma',
                'priority' => -100,
                'meta' => [],
            ],
        ]);

        $payload = $graph->toArray();

        self::assertSame(['kernel.reset'], \array_keys($payload['tags']));

        self::assertSame(
            [
                [
                    'id' => 'test.reset.gamma',
                    'priority' => 50,
                ],
                [
                    'id' => 'test.reset.alpha',
                    'priority' => 10,
                ],
                [
                    'id' => 'test.reset.beta',
                    'priority' => 10,
                ],
            ],
            $payload['tags']['kernel.reset'],
            'Tag export must preserve Foundation ordering: priority DESC, id ASC, with first-wins dedupe.',
        );
    }

    public function testTagMetadataIsAcceptedAtCompileInputButNotEmittedInCompiledPayload(): void
    {
        $graph = self::compiler()->compile([
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'test.reset.alpha',
                'priority' => 1,
                'meta' => [
                    'safe_flag' => true,
                ],
            ],
        ]);

        $payload = $graph->toArray();

        self::assertSame(
            [
                'kernel.reset' => [
                    [
                        'id' => 'test.reset.alpha',
                        'priority' => 1,
                    ],
                ],
            ],
            $payload['tags'],
        );

        self::assertArrayNotHasKey('meta', $payload['tags']['kernel.reset'][0]);
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
                return CompiledContainerPreservesTagDedupeFirstWinsTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = CompiledContainerPreservesTagDedupeFirstWinsTest::span($name);

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
