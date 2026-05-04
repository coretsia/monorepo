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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testCrossCuttingNoopsDoNotThrow(): void
    {
        $context = self::noopContextAccessor();

        self::assertFalse($context->has('correlation_id'));
        self::assertNull($context->get('correlation_id'));

        $correlationIdProvider = self::noopCorrelationIdProvider();

        self::assertNull($correlationIdProvider->correlationId());

        $propagation = self::noopContextPropagation();

        $carrier = [
            'traceparent' => '00-00000000000000000000000000000000-0000000000000000-00',
        ];

        self::assertSame($carrier, $propagation->inject($carrier, ['operation' => 'http.request']));
        self::assertSame([], $propagation->extract($carrier));

        $span = self::noopSpan();

        self::assertSame('noop.span', $span->name());

        $span->setAttribute('operation', 'http.request');
        $span->setAttributes(['operation' => 'http.request', 'status' => 200]);
        $span->addEvent('noop.event', ['outcome' => 'ok']);
        $span->recordException(new RuntimeException('safe test exception'));
        $span->end();
        $span->end();

        $tracer = self::noopTracer();

        self::assertNull($tracer->currentSpan());

        $startedSpan = $tracer->startSpan('http.request', ['operation' => 'http.request']);

        self::assertSame('http.request', $startedSpan->name());

        $result = $tracer->inSpan(
            'http.request',
            static fn (SpanInterface $activeSpan): string => $activeSpan->name(),
            ['operation' => 'http.request'],
        );

        self::assertSame('http.request', $result);

        $meter = self::noopMeter();

        $meter->increment('http.request_total', 1, [
            'method' => 'GET',
            'status' => 200,
            'outcome' => 'ok',
        ]);

        $meter->observe('http.request_duration_ms', 12, [
            'method' => 'GET',
            'status' => 200,
            'outcome' => 'ok',
        ]);

        self::noopReset()->reset();
        self::noopBeforeUowHook()->beforeUow();
        self::noopAfterUowHook()->afterUow();

        self::addToAssertionCount(1);
    }

    public function testNoopTracerRethrowsCallbackThrowable(): void
    {
        $tracer = self::noopTracer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('callback failure');

        $tracer->inSpan(
            'http.request',
            static function (): never {
                throw new RuntimeException('callback failure');
            },
            ['operation' => 'http.request'],
        );
    }

    private static function noopContextAccessor(): ContextAccessorInterface
    {
        return new class() implements ContextAccessorInterface {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): mixed
            {
                return null;
            }
        };
    }

    private static function noopCorrelationIdProvider(): CorrelationIdProviderInterface
    {
        return new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return null;
            }
        };
    }

    private static function noopContextPropagation(): ContextPropagationInterface
    {
        return new class() implements ContextPropagationInterface {
            /**
             * @param array<string,string|list<string>> $carrier
             * @param array<string,mixed> $context
             *
             * @return array<string,string|list<string>>
             */
            public function inject(array $carrier, array $context = []): array
            {
                return $carrier;
            }

            /**
             * @param array<string,string|list<string>> $carrier
             *
             * @return array<string,mixed>
             */
            public function extract(array $carrier): array
            {
                return [];
            }
        };
    }

    private static function noopTracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return new class($name) implements SpanInterface {
                    /**
                     * @param non-empty-string $name
                     */
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

                    public function recordException(Throwable $throwable, array $attributes = []): void
                    {
                    }

                    public function end(): void
                    {
                    }
                };
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = $this->startSpan($name, $attributes);

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

    private static function noopSpan(): SpanInterface
    {
        return new class() implements SpanInterface {
            public function name(): string
            {
                return 'noop.span';
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

            public function recordException(Throwable $throwable, array $attributes = []): void
            {
            }

            public function end(): void
            {
            }
        };
    }

    private static function noopMeter(): MeterPortInterface
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

    private static function noopReset(): ResetInterface
    {
        return new class() implements ResetInterface {
            public function reset(): void
            {
            }
        };
    }

    private static function noopBeforeUowHook(): BeforeUowHookInterface
    {
        return new class() implements BeforeUowHookInterface {
            public function beforeUow(): void
            {
            }
        };
    }

    private static function noopAfterUowHook(): AfterUowHookInterface
    {
        return new class() implements AfterUowHookInterface {
            public function afterUow(): void
            {
            }
        };
    }
}
