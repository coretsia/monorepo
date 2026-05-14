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

final class ResetGroupWorksTest extends TestCase
{
    public function testGroupAbsentEmptyAndTrimmedValuesAreNormalizedAndAffectOrdering(): void
    {
        $effectiveResetTag = 'kernel.reset';
        $defaultGroup = 'default';

        $tagRegistry = new TagRegistry();
        $recorder = new ResetGroupWorksRecorder();

        $services = [
            'service.alpha' => new ResetGroupWorksService('service.alpha', $recorder),
            'service.beta' => new ResetGroupWorksService('service.beta', $recorder),
            'service.cache' => new ResetGroupWorksService('service.cache', $recorder),
            'service.default_absent' => new ResetGroupWorksService('service.default_absent', $recorder),
            'service.default_empty' => new ResetGroupWorksService('service.default_empty', $recorder),
        ];

        /*
         * All registry priorities are intentionally equal.
         *
         * Enhanced mode therefore must order only by:
         * 1) normalized group ASC via strcmp
         * 2) serviceId ASC via strcmp
         *
         * Covered group cases:
         * - group absent => foundation.reset.group.default
         * - group ASCII-whitespace-only => foundation.reset.group.default
         * - group with ASCII surrounding whitespace => trimmed valid group
         * - already-valid group value remains usable as ordering key
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.default_empty',
            0,
            ['group' => " \t "],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.cache',
            0,
            ['group' => 'cache.v1'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            0,
            ['group' => 'beta'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.default_absent',
            0,
            [],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            0,
            ['group' => " \talpha\t "],
        );

        $tracer = new ResetGroupWorksFakeTracer();
        $meter = new ResetGroupWorksFakeMeter();
        $logger = new ResetGroupWorksFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new ResetGroupWorksContainer($services),
            tagRegistry: $tagRegistry,
            foundationConfig: [
                'reset' => [
                    'tag' => $effectiveResetTag,
                    'priority' => [
                        'enabled' => true,
                    ],
                    'group' => [
                        'default' => $defaultGroup,
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
                'service.alpha',
                'service.beta',
                'service.cache',
                'service.default_absent',
                'service.default_empty',
            ],
            $recorder->ids(),
            'Enhanced reset must use normalized group ASC and serviceId ASC ordering.',
        );

        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(5, $span->attributes()['services_count'] ?? null);
        self::assertSame(4, $span->attributes()['groups_count'] ?? null);
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
                ]);
                self::assertTrue(
                    \is_scalar($value) || $value === null,
                    'Summary-only reset log context must stay scalar/null.',
                );
            }
        }
    }
}

final class ResetGroupWorksRecorder
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

final readonly class ResetGroupWorksService implements ResetInterface
{
    public function __construct(
        private string $id,
        private ResetGroupWorksRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);
    }
}

final readonly class ResetGroupWorksContainer implements ContainerInterface
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

final class ResetGroupWorksFakeTracer implements TracerPortInterface
{
    /**
     * @var list<ResetGroupWorksFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new ResetGroupWorksFakeSpan($name, $attributes);
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
     * @return list<ResetGroupWorksFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class ResetGroupWorksFakeSpan implements SpanInterface
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

final class ResetGroupWorksFakeMeter implements MeterPortInterface
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

final class ResetGroupWorksFakeLogger extends AbstractLogger
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
