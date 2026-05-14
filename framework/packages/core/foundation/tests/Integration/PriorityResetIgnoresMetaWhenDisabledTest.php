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

final class PriorityResetIgnoresMetaWhenDisabledTest extends TestCase
{
    public function testPriorityDisabledIgnoresInvalidMetaAndPreservesLegacyOrder(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetIgnoresMetaWhenDisabledRecorder();

        $services = [
            'service.alpha' => new PriorityResetIgnoresMetaWhenDisabledService('service.alpha', $recorder),
            'service.beta' => new PriorityResetIgnoresMetaWhenDisabledService('service.beta', $recorder),
            'service.gamma' => new PriorityResetIgnoresMetaWhenDisabledService('service.gamma', $recorder),
            'service.delta' => new PriorityResetIgnoresMetaWhenDisabledService('service.delta', $recorder),
        ];

        /*
         * These meta payloads are intentionally invalid for enhanced mode.
         *
         * Disabled legacy/base mode MUST ignore all meta completely:
         * - no priority meta parsing;
         * - no group meta parsing;
         * - no unknown-key interpretation;
         * - exact TagRegistry::all(...) order only.
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.gamma',
            0,
            [
                'priority' => '+100',
                'group' => 'Bad',
                'x' => 'ignored',
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.delta',
            -5,
            [
                'priority' => null,
                'group' => ' cache ',
                'debug' => ['unsafe-shape-for-planning' => true],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            10,
            [
                'priority' => '1.0',
                'group' => 'cache/group',
                'unknown' => ['nested' => ['value']],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            10,
            [
                'priority' => [],
                'group' => true,
                'extra' => new \stdClass(),
            ],
        );

        $expectedRegistryOrder = [];
        foreach ($tagRegistry->all($effectiveResetTag) as $taggedService) {
            $expectedRegistryOrder[] = $taggedService->id();
        }

        self::assertSame(
            [
                'service.alpha',
                'service.beta',
                'service.gamma',
                'service.delta',
            ],
            $expectedRegistryOrder,
            'Sanity check: TagRegistry::all() must expose legacy priority DESC/id ASC order.',
        );

        $tracer = new PriorityResetIgnoresMetaWhenDisabledFakeTracer();
        $meter = new PriorityResetIgnoresMetaWhenDisabledFakeMeter();
        $logger = new PriorityResetIgnoresMetaWhenDisabledFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetIgnoresMetaWhenDisabledContainer($services),
            tagRegistry: $tagRegistry,
            foundationConfig: [
                'reset' => [
                    'tag' => $effectiveResetTag,
                    'priority' => [
                        'enabled' => false,
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

        self::assertFalse($orchestrator->priorityEnabled());
        self::assertSame($effectiveResetTag, $orchestrator->effectiveResetTag());

        $orchestrator->resetAll();

        self::assertSame(
            $expectedRegistryOrder,
            $recorder->ids(),
            'Disabled mode must ignore invalid tag meta and execute exact TagRegistry::all($effectiveResetTag) order.',
        );

        self::assertSame([], $tracer->startedSpans());
        self::assertSame([], $meter->increments());
        self::assertSame([], $meter->observations());
        self::assertSame([], $logger->records());
    }
}

final class PriorityResetIgnoresMetaWhenDisabledRecorder
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

final readonly class PriorityResetIgnoresMetaWhenDisabledService implements ResetInterface
{
    public function __construct(
        private string $id,
        private PriorityResetIgnoresMetaWhenDisabledRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);
    }
}

final readonly class PriorityResetIgnoresMetaWhenDisabledContainer implements ContainerInterface
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

final class PriorityResetIgnoresMetaWhenDisabledFakeTracer implements TracerPortInterface
{
    /**
     * @var list<array{name:string,attributes:array<string,mixed>}>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $this->startedSpans[] = [
            'name' => $name,
            'attributes' => $attributes,
        ];

        return new PriorityResetIgnoresMetaWhenDisabledFakeSpan($name);
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
     * @return list<array{name:string,attributes:array<string,mixed>}>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetIgnoresMetaWhenDisabledFakeSpan implements SpanInterface
{
    private bool $ended = false;

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
        unset($key, $value);
    }

    public function setAttributes(array $attributes): void
    {
        unset($attributes);
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

    public function ended(): bool
    {
        return $this->ended;
    }
}

final class PriorityResetIgnoresMetaWhenDisabledFakeMeter implements MeterPortInterface
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

final class PriorityResetIgnoresMetaWhenDisabledFakeLogger extends AbstractLogger
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
