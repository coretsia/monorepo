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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Provider\FoundationServiceFactory;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class PriorityResetOrderDeterministicTest extends TestCase
{
    public function testPriorityEnabledOrdersByPriorityDescGroupAscAndServiceIdAscDeterministically(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetOrderDeterministicRecorder();

        $services = [
            'service.alpha' => new PriorityResetOrderDeterministicService('service.alpha', $recorder),
            'service.beta' => new PriorityResetOrderDeterministicService('service.beta', $recorder),
            'service.gamma' => new PriorityResetOrderDeterministicService('service.gamma', $recorder),
            'service.delta' => new PriorityResetOrderDeterministicService('service.delta', $recorder),
            'service.zeta' => new PriorityResetOrderDeterministicService('service.zeta', $recorder),
        ];

        /*
         * Insertion order is intentionally scrambled.
         *
         * Enhanced mode MUST ignore insertion order and plan by:
         * 1) priority DESC
         * 2) group ASC via byte-order string comparison
         * 3) serviceId ASC via byte-order string comparison
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.gamma',
            0,
            ['priority' => 20, 'group' => 'beta'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.zeta',
            0,
            ['priority' => 10, 'group' => 'beta'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            1000,
            ['priority' => 20, 'group' => 'beta'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.delta',
            -1000,
            ['priority' => 10, 'group' => 'alpha'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            0,
            ['priority' => '20', 'group' => 'alpha'],
        );

        $expectedEnhancedOrder = [
            'service.alpha',
            'service.beta',
            'service.gamma',
            'service.delta',
            'service.zeta',
        ];

        $tracer = new PriorityResetOrderDeterministicFakeTracer();
        $meter = new PriorityResetOrderDeterministicFakeMeter();
        $logger = new PriorityResetOrderDeterministicFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetOrderDeterministicContainer($services),
            tagRegistry: $tagRegistry,
            foundationConfig: [
                'reset' => [
                    'tag' => $effectiveResetTag,
                    'priority' => [
                        'enabled' => true,
                    ],
                    'group' => [
                        'default' => 'default',
                    ],
                ],
            ],
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        );

        self::assertTrue($orchestrator->priorityEnabled());
        self::assertSame($effectiveResetTag, $orchestrator->effectiveResetTag());

        $orchestrator->resetAll();

        self::assertSame(
            $expectedEnhancedOrder,
            $recorder->ids(),
            'Enhanced mode must execute priority DESC, group ASC, serviceId ASC.',
        );

        $recorder->clear();

        $orchestrator->resetAll();

        self::assertSame(
            $expectedEnhancedOrder,
            $recorder->ids(),
            'Enhanced reset planning must be stable across repeated runs.',
        );

        self::assertCount(2, $tracer->startedSpans());
        foreach ($tracer->startedSpans() as $span) {
            self::assertSame('foundation.reset', $span->name());
            self::assertSame(5, $span->attributes()['services_count'] ?? null);
            self::assertSame(2, $span->attributes()['groups_count'] ?? null);
            self::assertSame('ok', $span->attributes()['outcome'] ?? null);
            self::assertTrue($span->ended());
        }

        self::assertCount(2, $meter->increments());
        foreach ($meter->increments() as $increment) {
            self::assertSame('foundation.reset_total', $increment['name']);
            self::assertSame(1, $increment['delta']);
            self::assertSame(['outcome' => 'ok'], $increment['labels']);
        }

        self::assertCount(2, $meter->observations());
        foreach ($meter->observations() as $observation) {
            self::assertSame('foundation.reset_duration_ms', $observation['name']);
            self::assertGreaterThanOrEqual(0, $observation['value']);
            self::assertSame(['outcome' => 'ok'], $observation['labels']);
        }

        foreach ($logger->records() as $record) {
            self::assertIsString($record['message']);
            self::assertNotSame('', $record['message']);

            foreach ($record['context'] as $key => $value) {
                self::assertIsString($key);
                self::assertNotContains($key, [
                    'path',
                    'raw_path',
                    'query',
                    'headers',
                    'cookies',
                    'body',
                    'token',
                    'password',
                    'secret',
                    'raw_sql',
                ]);
                self::assertTrue(
                    \is_scalar($value) || $value === null,
                    'Summary-only reset log context must stay scalar/null.',
                );
            }
        }
    }
}

final class PriorityResetOrderDeterministicRecorder
{
    /**
     * @var list<string>
     */
    private array $ids = [];

    public function record(string $id): void
    {
        $this->ids[] = $id;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->ids;
    }

    public function clear(): void
    {
        $this->ids = [];
    }
}

final readonly class PriorityResetOrderDeterministicService implements ResetInterface
{
    public function __construct(
        private string $id,
        private PriorityResetOrderDeterministicRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);
    }
}

final readonly class PriorityResetOrderDeterministicContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!\array_key_exists($id, $this->services)) {
            throw new \RuntimeException('test-service-not-found');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}

final class PriorityResetOrderDeterministicFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetOrderDeterministicFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetOrderDeterministicFakeSpan($name, $attributes);
        $this->startedSpans[] = $span;

        return $span;
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

    /**
     * @return list<PriorityResetOrderDeterministicFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetOrderDeterministicFakeSpan implements SpanInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $attributes;

    private bool $ended = false;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        private readonly string $name,
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (\is_string($key)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        unset($name, $attributes);
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        unset($throwable, $attributes);
    }

    public function end(): void
    {
        $this->ended = true;
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function ended(): bool
    {
        return $this->ended;
    }
}

final class PriorityResetOrderDeterministicFakeMeter implements MeterPortInterface
{
    /**
     * @var list<array{name:string,delta:int,labels:array<string,string|int|bool>}>
     */
    private array $increments = [];

    /**
     * @var list<array{name:string,value:int,labels:array<string,string|int|bool>}>
     */
    private array $observations = [];

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = [
            'name' => $name,
            'delta' => $delta,
            'labels' => $labels,
        ];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
    }

    /**
     * @return list<array{name:string,delta:int,labels:array<string,string|int|bool>}>
     */
    public function increments(): array
    {
        return $this->increments;
    }

    /**
     * @return list<array{name:string,value:int,labels:array<string,string|int|bool>}>
     */
    public function observations(): array
    {
        return $this->observations;
    }
}

final class PriorityResetOrderDeterministicFakeLogger extends AbstractLogger
{
    /**
     * @var list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }
}
