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
use Coretsia\Foundation\Runtime\Reset\ResetErrorCodes;
use Coretsia\Foundation\Runtime\Reset\ResetException;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class PriorityResetFailsFastOnFirstServiceExceptionTest extends TestCase
{
    public function testFirstThrowingResetServiceStopsSequenceAndEmitsSafeFailureSummary(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetFailsFastOnFirstServiceExceptionRecorder();

        $first = new PriorityResetFailsFastOnFirstServiceExceptionOkService('service.first', $recorder);
        $throwing = new PriorityResetFailsFastOnFirstServiceExceptionThrowingService(
            'service.throwing',
            $recorder,
        );
        $later = new PriorityResetFailsFastOnFirstServiceExceptionOkService('service.later', $recorder);

        $services = [
            'service.first' => $first,
            'service.throwing' => $throwing,
            'service.later' => $later,
        ];

        /*
         * Enhanced order is deterministic:
         *
         * 1) service.first
         * 2) service.throwing
         * 3) service.later
         *
         * The third service MUST NOT be reset after the second one throws.
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.later',
            0,
            ['priority' => 10, 'group' => 'gamma'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.throwing',
            0,
            ['priority' => 20, 'group' => 'beta'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.first',
            0,
            ['priority' => 30, 'group' => 'alpha'],
        );

        $tracer = new PriorityResetFailsFastOnFirstServiceExceptionFakeTracer();
        $meter = new PriorityResetFailsFastOnFirstServiceExceptionFakeMeter();
        $logger = new PriorityResetFailsFastOnFirstServiceExceptionFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetFailsFastOnFirstServiceExceptionContainer($services),
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

        try {
            $orchestrator->resetAll();
            self::fail('First throwing reset service must surface a deterministic ResetException.');
        } catch (ResetException $exception) {
            self::assertSame(ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED, $exception->code());
            self::assertSame('reset-service-failed', $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());

            self::assertStringNotContainsString('service.throwing', $exception->getMessage());
            self::assertStringNotContainsString('unsafe-reset-payload', $exception->getMessage());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('token', $exception->getMessage());
            self::assertStringNotContainsString(__DIR__, $exception->getMessage());
        }

        self::assertTrue($first->wasReset());
        self::assertTrue($throwing->wasReset());
        self::assertFalse($later->wasReset());

        self::assertSame(
            [
                'service.first',
                'service.throwing',
            ],
            $recorder->ids(),
            'Reset execution must stop immediately after the first throwing service.',
        );

        self::assertResetObservabilityFailureIsSummaryOnly($tracer, $meter, $logger);
    }

    private static function assertResetObservabilityFailureIsSummaryOnly(
        PriorityResetFailsFastOnFirstServiceExceptionFakeTracer $tracer,
        PriorityResetFailsFastOnFirstServiceExceptionFakeMeter $meter,
        PriorityResetFailsFastOnFirstServiceExceptionFakeLogger $logger,
    ): void {
        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(3, $span->attributes()['services_count'] ?? null);
        self::assertSame(3, $span->attributes()['groups_count'] ?? null);
        self::assertSame('failed', $span->attributes()['outcome'] ?? null);
        self::assertTrue($span->ended());

        self::assertCount(1, $span->recordedExceptions());
        self::assertInstanceOf(ResetException::class, $span->recordedExceptions()[0]['throwable']);
        self::assertSame(['outcome' => 'failed'], $span->recordedExceptions()[0]['attributes']);

        self::assertSame(
            [
                [
                    'name' => 'foundation.reset_total',
                    'delta' => 1,
                    'labels' => ['outcome' => 'failed'],
                ],
            ],
            $meter->increments(),
        );

        self::assertCount(1, $meter->observations());
        self::assertSame('foundation.reset_duration_ms', $meter->observations()[0]['name']);
        self::assertGreaterThanOrEqual(0, $meter->observations()[0]['value']);
        self::assertSame(['outcome' => 'failed'], $meter->observations()[0]['labels']);

        self::assertCount(1, $logger->records());
        self::assertSame('foundation.reset', $logger->records()[0]['message']);
        self::assertSame(
            [
                'services_count' => 3,
                'groups_count' => 3,
                'outcome' => 'failed',
            ],
            $logger->records()[0]['context'],
        );
    }
}

final class PriorityResetFailsFastOnFirstServiceExceptionRecorder
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

final class PriorityResetFailsFastOnFirstServiceExceptionOkService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $id,
        private readonly PriorityResetFailsFastOnFirstServiceExceptionRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->wasReset = true;
        $this->recorder->record($this->id);
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final class PriorityResetFailsFastOnFirstServiceExceptionThrowingService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $id,
        private readonly PriorityResetFailsFastOnFirstServiceExceptionRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->wasReset = true;
        $this->recorder->record($this->id);

        throw new \RuntimeException('unsafe-reset-payload Authorization token /tmp/coretsia-secret');
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final readonly class PriorityResetFailsFastOnFirstServiceExceptionContainer implements ContainerInterface
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

final class PriorityResetFailsFastOnFirstServiceExceptionFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetFailsFastOnFirstServiceExceptionFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetFailsFastOnFirstServiceExceptionFakeSpan($name, $attributes);
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
     * @return list<PriorityResetFailsFastOnFirstServiceExceptionFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetFailsFastOnFirstServiceExceptionFakeSpan implements SpanInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $attributes;

    /**
     * @var list<array{throwable:\Throwable,attributes:array<string,mixed>}>
     */
    private array $recordedExceptions = [];

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
        $this->recordedExceptions[] = [
            'throwable' => $throwable,
            'attributes' => $attributes,
        ];
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

    /**
     * @return list<array{throwable:\Throwable,attributes:array<string,mixed>}>
     */
    public function recordedExceptions(): array
    {
        return $this->recordedExceptions;
    }

    public function ended(): bool
    {
        return $this->ended;
    }
}

final class PriorityResetFailsFastOnFirstServiceExceptionFakeMeter implements MeterPortInterface
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

final class PriorityResetFailsFastOnFirstServiceExceptionFakeLogger extends AbstractLogger
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
