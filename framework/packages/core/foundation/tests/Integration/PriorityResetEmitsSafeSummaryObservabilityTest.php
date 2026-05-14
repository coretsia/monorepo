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

final class PriorityResetEmitsSafeSummaryObservabilityTest extends TestCase
{
    public function testPriorityResetEmitsOnlySafeSummaryObservabilityOnSuccess(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetEmitsSafeSummaryObservabilityRecorder();

        $services = [
            'service.alpha.secret_payload_holder' => new PriorityResetEmitsSafeSummaryObservabilityOkService(
                'service.alpha.secret_payload_holder',
                $recorder,
            ),
            'service.beta.authorization_holder' => new PriorityResetEmitsSafeSummaryObservabilityOkService(
                'service.beta.authorization_holder',
                $recorder,
            ),
            'service.gamma.raw_sql_holder' => new PriorityResetEmitsSafeSummaryObservabilityOkService(
                'service.gamma.raw_sql_holder',
                $recorder,
            ),
        ];

        /*
         * Service ids and unknown meta values intentionally contain unsafe
         * tokens. Summary observability MUST expose only counts/outcome and
         * MUST NOT leak service ids, meta payloads, or raw service internals.
         */
        $tagRegistry->add(
            $effectiveResetTag,
            'service.gamma.raw_sql_holder',
            0,
            [
                'priority' => 10,
                'group' => 'cache',
                'debug' => 'SELECT * FROM users WHERE token = secret',
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.beta.authorization_holder',
            0,
            [
                'priority' => 20,
                'group' => 'context',
                'x' => ['Authorization' => 'Bearer secret-token'],
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.alpha.secret_payload_holder',
            0,
            [
                'priority' => 20,
                'group' => 'context',
                'payload' => 'raw-request-body',
            ],
        );

        $tracer = new PriorityResetEmitsSafeSummaryObservabilityFakeTracer();
        $meter = new PriorityResetEmitsSafeSummaryObservabilityFakeMeter();
        $logger = new PriorityResetEmitsSafeSummaryObservabilityFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetEmitsSafeSummaryObservabilityContainer($services),
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

        $orchestrator->resetAll();

        self::assertSame(
            [
                'service.alpha.secret_payload_holder',
                'service.beta.authorization_holder',
                'service.gamma.raw_sql_holder',
            ],
            $recorder->ids(),
        );

        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(
            [
                'services_count' => 3,
                'groups_count' => 2,
                'outcome' => 'ok',
            ],
            $span->attributes(),
        );
        self::assertSame([], $span->recordedExceptions());
        self::assertTrue($span->ended());

        self::assertNoUnsafePayloadInMap($span->attributes());

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

        foreach ($meter->increments() as $increment) {
            self::assertNoUnsafePayloadInMap($increment['labels']);
            self::assertSame(['outcome'], \array_keys($increment['labels']));
        }

        foreach ($meter->observations() as $observation) {
            self::assertNoUnsafePayloadInMap($observation['labels']);
            self::assertSame(['outcome'], \array_keys($observation['labels']));
        }

        self::assertCount(1, $logger->records());
        self::assertSame('foundation.reset', $logger->records()[0]['message']);
        self::assertSame(
            [
                'services_count' => 3,
                'groups_count' => 2,
                'outcome' => 'ok',
            ],
            $logger->records()[0]['context'],
        );
        self::assertNoUnsafePayloadInMap($logger->records()[0]['context']);
    }

    public function testPriorityResetEmitsOnlySafeSummaryObservabilityOnFailure(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $tagRegistry = new TagRegistry();
        $recorder = new PriorityResetEmitsSafeSummaryObservabilityRecorder();

        $first = new PriorityResetEmitsSafeSummaryObservabilityOkService('service.first', $recorder);
        $throwing = new PriorityResetEmitsSafeSummaryObservabilityThrowingService(
            'service.throwing.secret_payload_holder',
            $recorder,
        );
        $later = new PriorityResetEmitsSafeSummaryObservabilityOkService('service.later', $recorder);

        $services = [
            'service.first' => $first,
            'service.throwing.secret_payload_holder' => $throwing,
            'service.later' => $later,
        ];

        $tagRegistry->add(
            $effectiveResetTag,
            'service.later',
            0,
            [
                'priority' => 10,
                'group' => 'gamma',
                'debug' => 'must-not-run',
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.throwing.secret_payload_holder',
            0,
            [
                'priority' => 20,
                'group' => 'beta',
                'payload' => 'Authorization: Bearer secret-token',
            ],
        );
        $tagRegistry->add(
            $effectiveResetTag,
            'service.first',
            0,
            [
                'priority' => 30,
                'group' => 'alpha',
                'x' => 'raw-request-body',
            ],
        );

        $tracer = new PriorityResetEmitsSafeSummaryObservabilityFakeTracer();
        $meter = new PriorityResetEmitsSafeSummaryObservabilityFakeMeter();
        $logger = new PriorityResetEmitsSafeSummaryObservabilityFakeLogger();

        $orchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetEmitsSafeSummaryObservabilityContainer($services),
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

        try {
            $orchestrator->resetAll();
            self::fail('Throwing reset service must fail deterministically.');
        } catch (ResetException $exception) {
            self::assertSame(ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED, $exception->code());
            self::assertSame('reset-service-failed', $exception->getMessage());
            self::assertSame(0, $exception->getCode());

            self::assertNoUnsafePayloadInScalar($exception->getMessage());
        }

        self::assertSame(
            [
                'service.first',
                'service.throwing.secret_payload_holder',
            ],
            $recorder->ids(),
        );
        self::assertFalse($later->wasReset());

        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(
            [
                'services_count' => 3,
                'groups_count' => 3,
                'outcome' => 'failed',
            ],
            $span->attributes(),
        );
        self::assertTrue($span->ended());
        self::assertNoUnsafePayloadInMap($span->attributes());

        self::assertCount(1, $span->recordedExceptions());
        self::assertInstanceOf(ResetException::class, $span->recordedExceptions()[0]['throwable']);
        self::assertSame(
            ['outcome' => 'failed'],
            $span->recordedExceptions()[0]['attributes'],
        );
        self::assertNoUnsafePayloadInMap($span->recordedExceptions()[0]['attributes']);

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

        foreach ($meter->increments() as $increment) {
            self::assertNoUnsafePayloadInMap($increment['labels']);
            self::assertSame(['outcome'], \array_keys($increment['labels']));
        }

        foreach ($meter->observations() as $observation) {
            self::assertNoUnsafePayloadInMap($observation['labels']);
            self::assertSame(['outcome'], \array_keys($observation['labels']));
        }

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
        self::assertNoUnsafePayloadInMap($logger->records()[0]['context']);
    }

    /**
     * @param array<string,mixed> $map
     */
    private static function assertNoUnsafePayloadInMap(array $map): void
    {
        foreach ($map as $key => $value) {
            self::assertIsString($key);
            self::assertNoUnsafePayloadInScalar($key);

            if (\is_array($value)) {
                self::assertNoUnsafePayloadInMap($value);
                continue;
            }

            if (\is_scalar($value) || $value === null) {
                self::assertNoUnsafePayloadInScalar((string)$value);
                continue;
            }

            self::fail(
                'Summary observability must not emit objects/resources/closures as attributes, labels, or log context.'
            );
        }
    }

    private static function assertNoUnsafePayloadInScalar(string $value): void
    {
        foreach (self::forbiddenObservabilityFragments() as $fragment) {
            self::assertStringNotContainsString($fragment, $value);
        }
    }

    /**
     * @return list<string>
     */
    private static function forbiddenObservabilityFragments(): array
    {
        return [
            'service.alpha.secret_payload_holder',
            'service.beta.authorization_holder',
            'service.gamma.raw_sql_holder',
            'service.throwing.secret_payload_holder',
            'service.first',
            'service.later',
            'secret_payload_holder',
            'Authorization',
            'Bearer',
            'secret-token',
            'token',
            'raw-request-body',
            'SELECT * FROM users',
            'raw SQL',
            'raw-sql',
            'debug',
            'payload',
            'meta',
            'x',
            'reset failure contains raw payload',
            '/tmp/coretsia-secret',
        ];
    }
}

final class PriorityResetEmitsSafeSummaryObservabilityRecorder
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

final class PriorityResetEmitsSafeSummaryObservabilityOkService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $id,
        private readonly PriorityResetEmitsSafeSummaryObservabilityRecorder $recorder,
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

final class PriorityResetEmitsSafeSummaryObservabilityThrowingService implements ResetInterface
{
    public function __construct(
        private readonly string $id,
        private readonly PriorityResetEmitsSafeSummaryObservabilityRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->record($this->id);

        throw new \RuntimeException(
            'reset failure contains raw payload: Authorization Bearer secret-token /tmp/coretsia-secret',
        );
    }
}

final readonly class PriorityResetEmitsSafeSummaryObservabilityContainer implements ContainerInterface
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

final class PriorityResetEmitsSafeSummaryObservabilityFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetEmitsSafeSummaryObservabilityFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetEmitsSafeSummaryObservabilityFakeSpan($name, $attributes);
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
     * @return list<PriorityResetEmitsSafeSummaryObservabilityFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetEmitsSafeSummaryObservabilityFakeSpan implements SpanInterface
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

final class PriorityResetEmitsSafeSummaryObservabilityFakeMeter implements MeterPortInterface
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

final class PriorityResetEmitsSafeSummaryObservabilityFakeLogger extends AbstractLogger
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
