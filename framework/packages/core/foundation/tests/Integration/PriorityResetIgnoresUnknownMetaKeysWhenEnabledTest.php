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

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledTest extends TestCase
{
    public function testPriorityEnabledIgnoresUnknownMetaKeysAndOrdersOnlyByPriorityGroupAndServiceId(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetIgnoresUnknownMetaKeysWhenEnabledRecorder();

        $services = [
            'service.alpha' => new PriorityResetIgnoresUnknownMetaKeysWhenEnabledService('service.alpha', $recorder),
            'service.beta' => new PriorityResetIgnoresUnknownMetaKeysWhenEnabledService('service.beta', $recorder),
            'service.delta' => new PriorityResetIgnoresUnknownMetaKeysWhenEnabledService('service.delta', $recorder),
            'service.epsilon' => new PriorityResetIgnoresUnknownMetaKeysWhenEnabledService(
                'service.epsilon',
                $recorder
            ),
            'service.gamma' => new PriorityResetIgnoresUnknownMetaKeysWhenEnabledService('service.gamma', $recorder),
        ];

        /*
         * Unknown keys are intentionally mixed and nested.
         *
         * Enhanced mode MUST read and validate only:
         * - priority
         * - group
         *
         * Ordering MUST be computed only from:
         * 1) priority DESC
         * 2) group ASC by strcmp()
         * 3) serviceId ASC by strcmp()
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            0,
            [
                'priority' => 10,
                'group' => 'default',
                'x' => 'would-be-first-if-read',
                'debug' => ['priority' => 999, 'group' => 'aaa'],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.gamma',
            999,
            [
                'priority' => 20,
                'group' => 'alpha',
                'x' => ['nested' => ['ignored' => true]],
                'debug' => ['serviceId' => 'service.aaa'],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.epsilon',
            -999,
            [
                'priority' => '10',
                'group' => 'default',
                'x' => new \stdClass(),
                'debug' => ['priority' => -999, 'group' => 'zzz'],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            0,
            [
                'priority' => 20,
                'group' => 'omega',
                'x' => null,
                'debug' => ['a' => 1],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.delta',
            0,
            [
                'priority' => 10,
                'group' => 'default',
                'x' => false,
                'debug' => ['raw' => ['ignored']],
            ],
        );

        $tracer = new PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeTracer();
        $meter = new PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeMeter();
        $logger = new PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetIgnoresUnknownMetaKeysWhenEnabledContainer($services),
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
            [
                'service.gamma',
                'service.beta',
                'service.alpha',
                'service.delta',
                'service.epsilon',
            ],
            $recorder->ids(),
            'Enhanced reset must ignore unknown meta keys and order only by priority, group, and serviceId.',
        );

        self::assertResetObservabilityIsSummaryOnly($tracer, $meter, $logger);
    }

    private static function assertResetObservabilityIsSummaryOnly(
        PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeTracer $tracer,
        PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeMeter $meter,
        PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeLogger $logger,
    ): void {
        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(5, $span->attributes()['services_count'] ?? null);
        self::assertSame(3, $span->attributes()['groups_count'] ?? null);
        self::assertSame('ok', $span->attributes()['outcome'] ?? null);
        self::assertTrue($span->ended());

        self::assertSame(
            [
                [
                    'name' => 'foundation.reset_total',
                    'delta' => 1,
                    'labels' => ['outcome' => 'ok'],
                ],
            ],
            $meter->increments(),
        );

        self::assertCount(1, $meter->observations());
        self::assertSame('foundation.reset_duration_ms', $meter->observations()[0]['name']);
        self::assertGreaterThanOrEqual(0, $meter->observations()[0]['value']);
        self::assertSame(['outcome' => 'ok'], $meter->observations()[0]['labels']);

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
                    'priority',
                    'group',
                    'x',
                    'debug',
                ]);
                self::assertTrue(
                    \is_scalar($value) || $value === null,
                    'Summary-only reset log context must stay scalar/null.',
                );
            }
        }
    }
}

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledRecorder
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
}

final readonly class PriorityResetIgnoresUnknownMetaKeysWhenEnabledService implements ResetInterface
{
    public function __construct(
        private string $id,
        private PriorityResetIgnoresUnknownMetaKeysWhenEnabledRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);
    }
}

final readonly class PriorityResetIgnoresUnknownMetaKeysWhenEnabledContainer implements ContainerInterface
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

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeSpan($name, $attributes);
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
     * @return list<PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeSpan implements SpanInterface
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

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeMeter implements MeterPortInterface
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

final class PriorityResetIgnoresUnknownMetaKeysWhenEnabledFakeLogger extends AbstractLogger
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
