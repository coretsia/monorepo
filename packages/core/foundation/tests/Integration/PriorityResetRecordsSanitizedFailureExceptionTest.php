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
use Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator;
use Coretsia\Foundation\Runtime\Reset\ResetErrorCodes;
use Coretsia\Foundation\Runtime\Reset\ResetException;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class PriorityResetRecordsSanitizedFailureExceptionTest extends TestCase
{
    public function testResetFailureRecordedIntoSpanUsesSanitizedResetExceptionWithoutPrevious(): void
    {
        $effectiveResetTag = 'kernel.reset';

        $unsafeFailureMessage = \implode(' ', [
            'raw-reset-payload',
            'Authorization: Bearer raw-token-value',
            'Cookie: session_id=raw-cookie-value',
            'credential=raw-credential-value',
            'password=raw-password-value',
            'SELECT * FROM users WHERE token = raw-token-value',
            'object(stdClass)#123',
            '/home/user/project/.env',
            __DIR__,
        ]);

        $service = new PriorityResetRecordsSanitizedFailureExceptionThrowingService($unsafeFailureMessage);

        $container = new PriorityResetRecordsSanitizedFailureExceptionContainer([
            'service.reset.throwing' => $service,
        ]);

        $tagRegistry = new TagRegistry();
        $tagRegistry->add($effectiveResetTag, 'service.reset.throwing');

        $tracer = new PriorityResetRecordsSanitizedFailureExceptionFakeTracer();
        $meter = new PriorityResetRecordsSanitizedFailureExceptionFakeMeter();
        $logger = new PriorityResetRecordsSanitizedFailureExceptionFakeLogger();

        $orchestrator = new PriorityResetOrchestrator(
            container: $container,
            tagRegistry: $tagRegistry,
            defaultGroup: 'default',
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        );

        $surfacedException = null;

        try {
            $orchestrator->resetAll($effectiveResetTag);
        } catch (ResetException $exception) {
            $surfacedException = $exception;
        }

        self::assertInstanceOf(ResetException::class, $surfacedException);
        self::assertTrue($service->wasReset());

        self::assertSurfacedFailurePreservesSafeShapeAndRawPrevious(
            exception: $surfacedException,
            unsafeFailureMessage: $unsafeFailureMessage,
        );

        self::assertSpanRecordedSanitizedFailureException(
            tracer: $tracer,
            surfacedException: $surfacedException,
        );

        self::assertResetMetricsAndLogsRemainSummaryOnly(
            meter: $meter,
            logger: $logger,
        );
    }

    private static function assertSurfacedFailurePreservesSafeShapeAndRawPrevious(
        ResetException $exception,
        string $unsafeFailureMessage,
    ): void {
        self::assertSame(ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED, $exception->code());
        self::assertSame($exception->code(), $exception->errorCode());
        self::assertSame('reset-service-failed', $exception->reason());
        self::assertSame('reset-service-failed', $exception->getMessage());
        self::assertSame(0, $exception->getCode());

        $previous = $exception->getPrevious();

        self::assertInstanceOf(\RuntimeException::class, $previous);
        self::assertSame($unsafeFailureMessage, $previous->getMessage());

        self::assertNoUnsafeDiagnosticsInString($exception->getMessage());
    }

    private static function assertSpanRecordedSanitizedFailureException(
        PriorityResetRecordsSanitizedFailureExceptionFakeTracer $tracer,
        ResetException $surfacedException,
    ): void {
        self::assertCount(1, $tracer->startedSpans());

        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(
            [
                'services_count' => 1,
                'groups_count' => 1,
                'outcome' => 'failed',
            ],
            $span->attributes(),
        );
        self::assertTrue($span->ended());

        self::assertCount(1, $span->recordedExceptions());

        $recordedException = $span->recordedExceptions()[0];
        $recordedThrowable = $recordedException['throwable'];

        self::assertInstanceOf(ResetException::class, $recordedThrowable);
        self::assertNotSame($surfacedException, $recordedThrowable);

        self::assertSame($surfacedException->code(), $recordedThrowable->code());
        self::assertSame($surfacedException->errorCode(), $recordedThrowable->errorCode());
        self::assertSame($recordedThrowable->code(), $recordedThrowable->errorCode());
        self::assertSame($surfacedException->reason(), $recordedThrowable->reason());
        self::assertSame('reset-service-failed', $recordedThrowable->reason());
        self::assertSame($surfacedException->getMessage(), $recordedThrowable->getMessage());
        self::assertSame(0, $recordedThrowable->getCode());

        self::assertNull(
            $recordedThrowable->getPrevious(),
            'Span recorded exception must be sanitized and must not preserve raw previous throwable chains.',
        );

        self::assertSame(
            [
                'outcome' => 'failed',
            ],
            $recordedException['attributes'],
        );

        self::assertNoUnsafeDiagnosticsInString($recordedThrowable->getMessage());
        self::assertNoUnsafeDiagnosticsInMap($recordedException['attributes']);
        self::assertNoUnsafeDiagnosticsInMap($span->attributes());
    }

    private static function assertResetMetricsAndLogsRemainSummaryOnly(
        PriorityResetRecordsSanitizedFailureExceptionFakeMeter $meter,
        PriorityResetRecordsSanitizedFailureExceptionFakeLogger $logger,
    ): void {
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
                'services_count' => 1,
                'groups_count' => 1,
                'outcome' => 'failed',
            ],
            $logger->records()[0]['context'],
        );

        foreach ($meter->increments() as $increment) {
            self::assertNoUnsafeDiagnosticsInMap($increment['labels']);
        }

        foreach ($meter->observations() as $observation) {
            self::assertNoUnsafeDiagnosticsInMap($observation['labels']);
        }

        foreach ($logger->records() as $record) {
            self::assertNoUnsafeDiagnosticsInString($record['message']);
            self::assertNoUnsafeDiagnosticsInMap($record['context']);
        }
    }

    private static function assertNoUnsafeDiagnosticsInString(string $value): void
    {
        foreach (self::unsafeDiagnosticsNeedles() as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $value,
                'Reset observability diagnostics must not leak unsafe runtime values.',
            );
        }
    }

    /**
     * @param array<string,mixed> $map
     */
    private static function assertNoUnsafeDiagnosticsInMap(array $map): void
    {
        $encoded = \json_encode($map, \JSON_THROW_ON_ERROR);

        self::assertIsString($encoded);
        self::assertNoUnsafeDiagnosticsInString($encoded);
    }

    /**
     * @return list<string>
     */
    private static function unsafeDiagnosticsNeedles(): array
    {
        return [
            'service.reset.throwing',
            'raw-reset-payload',
            'Authorization',
            'Bearer',
            'raw-token-value',
            'Cookie',
            'session_id',
            'raw-cookie-value',
            'credential',
            'raw-credential-value',
            'password',
            'raw-password-value',
            'SELECT',
            'users',
            'object(stdClass)',
            '/home/user/project/.env',
            __DIR__,
        ];
    }
}

final class PriorityResetRecordsSanitizedFailureExceptionThrowingService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $unsafeFailureMessage,
    ) {
    }

    public function reset(): void
    {
        $this->wasReset = true;

        throw new \RuntimeException($this->unsafeFailureMessage);
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final readonly class PriorityResetRecordsSanitizedFailureExceptionContainer implements ContainerInterface
{
    /**
     * @param array<string,object> $services
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

final class PriorityResetRecordsSanitizedFailureExceptionFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetRecordsSanitizedFailureExceptionFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetRecordsSanitizedFailureExceptionFakeSpan($name, $attributes);
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
     * @return list<PriorityResetRecordsSanitizedFailureExceptionFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetRecordsSanitizedFailureExceptionFakeSpan implements SpanInterface
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

final class PriorityResetRecordsSanitizedFailureExceptionFakeMeter implements MeterPortInterface
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

final class PriorityResetRecordsSanitizedFailureExceptionFakeLogger extends AbstractLogger
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
