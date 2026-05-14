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

final class ResetOrderingIsLocaleIndependentTest extends TestCase
{
    public function testEnhancedResetOrderingUsesByteOrderStringComparisonAfterLocaleAttempt(): void
    {
        $originalLocale = \setlocale(\LC_ALL, '0');

        try {
            $effectiveResetTag = 'kernel.reset';
            $expectedOrder = [
                'service.alpha',
                'service.beta',
                'service.delta',
                'service.gamma',
                'service.zeta',
                'service.epsilon',
            ];

            $beforeLocaleRecorder = new ResetOrderingIsLocaleIndependentRecorder();
            $beforeLocaleTracer = new ResetOrderingIsLocaleIndependentFakeTracer();
            $beforeLocaleMeter = new ResetOrderingIsLocaleIndependentFakeMeter();
            $beforeLocaleLogger = new ResetOrderingIsLocaleIndependentFakeLogger();

            $beforeLocaleOrchestrator = self::createOrchestrator(
                effectiveResetTag: $effectiveResetTag,
                recorder: $beforeLocaleRecorder,
                tracer: $beforeLocaleTracer,
                meter: $beforeLocaleMeter,
                logger: $beforeLocaleLogger,
            );

            $beforeLocaleOrchestrator->resetAll();

            self::assertSame(
                $expectedOrder,
                $beforeLocaleRecorder->ids(),
                'Sanity check: enhanced reset order must use byte-order strcmp before locale mutation.',
            );

            $attemptedLocale = self::trySetNonTrivialLocale();

            self::assertTrue(
                $attemptedLocale === null || \is_string($attemptedLocale),
                'Locale attempt must either set a locale or explicitly report that no non-trivial locale is available.',
            );

            $afterLocaleRecorder = new ResetOrderingIsLocaleIndependentRecorder();
            $afterLocaleTracer = new ResetOrderingIsLocaleIndependentFakeTracer();
            $afterLocaleMeter = new ResetOrderingIsLocaleIndependentFakeMeter();
            $afterLocaleLogger = new ResetOrderingIsLocaleIndependentFakeLogger();

            $afterLocaleOrchestrator = self::createOrchestrator(
                effectiveResetTag: $effectiveResetTag,
                recorder: $afterLocaleRecorder,
                tracer: $afterLocaleTracer,
                meter: $afterLocaleMeter,
                logger: $afterLocaleLogger,
            );

            $afterLocaleOrchestrator->resetAll();

            self::assertSame(
                $expectedOrder,
                $afterLocaleRecorder->ids(),
                'Enhanced reset order must remain byte-order strcmp after non-trivial locale attempt.',
            );

            self::assertSame(
                $beforeLocaleRecorder->ids(),
                $afterLocaleRecorder->ids(),
                'Planned order must be identical before and after locale attempt.',
            );

            self::assertResetObservabilityIsSummaryOnly($beforeLocaleTracer, $beforeLocaleMeter, $beforeLocaleLogger);
            self::assertResetObservabilityIsSummaryOnly($afterLocaleTracer, $afterLocaleMeter, $afterLocaleLogger);
        } finally {
            if (\is_string($originalLocale) && $originalLocale !== '') {
                \setlocale(\LC_ALL, $originalLocale);
            }
        }
    }

    private static function createOrchestrator(
        string $effectiveResetTag,
        ResetOrderingIsLocaleIndependentRecorder $recorder,
        ResetOrderingIsLocaleIndependentFakeTracer $tracer,
        ResetOrderingIsLocaleIndependentFakeMeter $meter,
        ResetOrderingIsLocaleIndependentFakeLogger $logger,
    ): mixed {
        $tagRegistry = new TagRegistry();

        $services = [
            'service.alpha' => new ResetOrderingIsLocaleIndependentService('service.alpha', $recorder),
            'service.beta' => new ResetOrderingIsLocaleIndependentService('service.beta', $recorder),
            'service.delta' => new ResetOrderingIsLocaleIndependentService('service.delta', $recorder),
            'service.epsilon' => new ResetOrderingIsLocaleIndependentService('service.epsilon', $recorder),
            'service.gamma' => new ResetOrderingIsLocaleIndependentService('service.gamma', $recorder),
            'service.zeta' => new ResetOrderingIsLocaleIndependentService('service.zeta', $recorder),
        ];

        /*
         * All priorities are equal, so group and serviceId ordering are isolated.
         *
         * The group values are valid normalized ids, but their byte-order order
         * is intentionally punctuation/digit/underscore sensitive:
         *
         * a-rail  < a.rail < a0rail < a_rail < aarail
         *
         * This MUST remain true regardless of the process locale because the
         * enhanced reset planner must use strcmp, not locale collation.
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.zeta',
            0,
            ['priority' => 10, 'group' => 'a_rail'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            0,
            ['priority' => 10, 'group' => 'a-rail'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.epsilon',
            0,
            ['priority' => 10, 'group' => 'aarail'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.gamma',
            0,
            ['priority' => 10, 'group' => 'a0rail'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.delta',
            0,
            ['priority' => 10, 'group' => 'a.rail'],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            0,
            ['priority' => 10, 'group' => 'a-rail'],
        );

        return FoundationServiceFactory::resetOrchestrator(
            container: new ResetOrderingIsLocaleIndependentContainer($services),
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
    }

    private static function trySetNonTrivialLocale(): ?string
    {
        $candidates = [
            'uk_UA.UTF-8',
            'uk_UA.utf8',
            'en_US.UTF-8',
            'en_US.utf8',
            'de_DE.UTF-8',
            'de_DE.utf8',
            'tr_TR.UTF-8',
            'tr_TR.utf8',
            'ja_JP.UTF-8',
            'ja_JP.utf8',
        ];

        foreach ($candidates as $candidate) {
            $result = \setlocale(\LC_ALL, $candidate);

            if (\is_string($result) && $result !== '' && $result !== 'C' && $result !== 'POSIX') {
                return $result;
            }
        }

        return null;
    }

    private static function assertResetObservabilityIsSummaryOnly(
        ResetOrderingIsLocaleIndependentFakeTracer $tracer,
        ResetOrderingIsLocaleIndependentFakeMeter $meter,
        ResetOrderingIsLocaleIndependentFakeLogger $logger,
    ): void {
        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(6, $span->attributes()['services_count'] ?? null);
        self::assertSame(5, $span->attributes()['groups_count'] ?? null);
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

final class ResetOrderingIsLocaleIndependentRecorder
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

final readonly class ResetOrderingIsLocaleIndependentService implements ResetInterface
{
    public function __construct(
        private string $id,
        private ResetOrderingIsLocaleIndependentRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);
    }
}

final readonly class ResetOrderingIsLocaleIndependentContainer implements ContainerInterface
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

final class ResetOrderingIsLocaleIndependentFakeTracer implements TracerPortInterface
{
    /**
     * @var list<ResetOrderingIsLocaleIndependentFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new ResetOrderingIsLocaleIndependentFakeSpan($name, $attributes);
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
     * @return list<ResetOrderingIsLocaleIndependentFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class ResetOrderingIsLocaleIndependentFakeSpan implements SpanInterface
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

final class ResetOrderingIsLocaleIndependentFakeMeter implements MeterPortInterface
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

final class ResetOrderingIsLocaleIndependentFakeLogger extends AbstractLogger
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
