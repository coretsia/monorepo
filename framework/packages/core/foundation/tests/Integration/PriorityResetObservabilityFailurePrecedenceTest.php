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
use Psr\Log\LoggerInterface;

final class PriorityResetObservabilityFailurePrecedenceTest extends TestCase
{
    private const string EFFECTIVE_RESET_TAG = 'kernel.reset';
    private const string SERVICE_ID = 'service.reset.target';
    private const string UNSAFE_RESET_FAILURE_MESSAGE = 'raw-reset-payload Authorization Bearer reset-token Cookie session_id=reset-cookie credential=reset-credential password=reset-password SELECT * FROM users /home/user/project/.env';
    private const string UNSAFE_OBSERVABILITY_FAILURE_MESSAGE = 'raw-observability-payload Authorization Bearer observability-token Cookie session_id=observability-cookie credential=observability-credential password=observability-password SELECT * FROM observability /tmp/coretsia-observability-secret';

    public function testSpanEndFailureAfterSuccessfulResetIsSwallowed(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceSuccessfulService();

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer(
            spanEndFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter();
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        self::orchestrator(
            service: $service,
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        )->resetAll(self::EFFECTIVE_RESET_TAG);

        self::assertTrue($service->wasReset());

        self::assertCount(1, $tracer->startedSpans());
        self::assertSame('foundation.reset', $tracer->startedSpans()[0]->name());
        self::assertTrue($tracer->startedSpans()[0]->ended());
        self::assertSame(
            [
                'services_count' => 1,
                'groups_count' => 1,
                'outcome' => 'ok',
            ],
            $tracer->startedSpans()[0]->attributes(),
        );

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
        self::assertSame(['outcome' => 'ok'], $meter->observations()[0]['labels']);

        self::assertSame(
            [
                [
                    'level' => 'info',
                    'message' => 'foundation.reset',
                    'context' => [
                        'services_count' => 1,
                        'groups_count' => 1,
                        'outcome' => 'ok',
                    ],
                ],
            ],
            $logger->records(),
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    public function testMeterIncrementFailureAfterSuccessfulResetIsSwallowed(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceSuccessfulService();

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer();
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter(
            incrementFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        self::orchestrator(
            service: $service,
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        )->resetAll(self::EFFECTIVE_RESET_TAG);

        self::assertTrue($service->wasReset());

        self::assertCount(1, $tracer->startedSpans());
        self::assertTrue($tracer->startedSpans()[0]->ended());

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

        self::assertSame([], $meter->observations());

        self::assertSame(
            [
                [
                    'level' => 'info',
                    'message' => 'foundation.reset',
                    'context' => [
                        'services_count' => 1,
                        'groups_count' => 1,
                        'outcome' => 'ok',
                    ],
                ],
            ],
            $logger->records(),
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    public function testMeterObserveFailureAfterSuccessfulResetIsSwallowed(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceSuccessfulService();

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer();
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter(
            observeFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        self::orchestrator(
            service: $service,
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        )->resetAll(self::EFFECTIVE_RESET_TAG);

        self::assertTrue($service->wasReset());

        self::assertCount(1, $tracer->startedSpans());
        self::assertTrue($tracer->startedSpans()[0]->ended());

        self::assertCount(1, $meter->increments());
        self::assertCount(1, $meter->observations());
        self::assertSame('foundation.reset_duration_ms', $meter->observations()[0]['name']);
        self::assertSame(['outcome' => 'ok'], $meter->observations()[0]['labels']);

        self::assertSame(
            [
                [
                    'level' => 'info',
                    'message' => 'foundation.reset',
                    'context' => [
                        'services_count' => 1,
                        'groups_count' => 1,
                        'outcome' => 'ok',
                    ],
                ],
            ],
            $logger->records(),
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    public function testLoggerFailureAfterSuccessfulResetIsSwallowed(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceSuccessfulService();

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer();
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter();
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger(
            failureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );

        self::orchestrator(
            service: $service,
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        )->resetAll(self::EFFECTIVE_RESET_TAG);

        self::assertTrue($service->wasReset());

        self::assertCount(1, $tracer->startedSpans());
        self::assertTrue($tracer->startedSpans()[0]->ended());

        self::assertCount(1, $meter->increments());
        self::assertCount(1, $meter->observations());
        self::assertCount(1, $logger->records());

        self::assertSame('foundation.reset', $logger->records()[0]['message']);
        self::assertSame(
            [
                'services_count' => 1,
                'groups_count' => 1,
                'outcome' => 'ok',
            ],
            $logger->records()[0]['context'],
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    public function testResetServiceFailureRemainsPrimaryWhenSpanRecordExceptionThrows(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceThrowingService(
            self::UNSAFE_RESET_FAILURE_MESSAGE,
        );

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer(
            spanRecordExceptionFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter();
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        $exception = self::catchResetException(
            self::orchestrator(
                service: $service,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        self::assertTrue($service->wasReset());
        self::assertServiceFailedException($exception);
        self::assertPrimaryResetFailureWasNotReplaced($exception);

        self::assertCount(1, $tracer->startedSpans());
        self::assertCount(1, $tracer->startedSpans()[0]->recordedExceptions());

        $recordedThrowable = $tracer->startedSpans()[0]->recordedExceptions()[0]['throwable'];

        self::assertInstanceOf(ResetException::class, $recordedThrowable);
        self::assertSame('reset-service-failed', $recordedThrowable->getMessage());
        self::assertNull($recordedThrowable->getPrevious());

        self::assertFailedResetSummaryWasEmitted($meter, $logger);
    }

    public function testResetServiceFailureRemainsPrimaryWhenSpanEndThrows(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceThrowingService(
            self::UNSAFE_RESET_FAILURE_MESSAGE,
        );

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer(
            spanEndFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter();
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        $exception = self::catchResetException(
            self::orchestrator(
                service: $service,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        self::assertTrue($service->wasReset());
        self::assertServiceFailedException($exception);
        self::assertPrimaryResetFailureWasNotReplaced($exception);

        self::assertCount(1, $tracer->startedSpans());
        self::assertCount(1, $tracer->startedSpans()[0]->recordedExceptions());

        $recordedThrowable = $tracer->startedSpans()[0]->recordedExceptions()[0]['throwable'];

        self::assertInstanceOf(ResetException::class, $recordedThrowable);
        self::assertSame('reset-service-failed', $recordedThrowable->getMessage());
        self::assertNull($recordedThrowable->getPrevious());

        self::assertFailedResetSummaryWasEmitted($meter, $logger);
    }

    public function testResetServiceFailureRemainsPrimaryWhenMeterEmissionThrows(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceThrowingService(
            self::UNSAFE_RESET_FAILURE_MESSAGE,
        );

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer();
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter(
            incrementFailureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger();

        $exception = self::catchResetException(
            self::orchestrator(
                service: $service,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        self::assertTrue($service->wasReset());
        self::assertServiceFailedException($exception);
        self::assertPrimaryResetFailureWasNotReplaced($exception);

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

        self::assertSame([], $meter->observations());

        self::assertSame(
            [
                [
                    'level' => 'info',
                    'message' => 'foundation.reset',
                    'context' => [
                        'services_count' => 1,
                        'groups_count' => 1,
                        'outcome' => 'failed',
                    ],
                ],
            ],
            $logger->records(),
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    public function testResetServiceFailureRemainsPrimaryWhenLoggerEmissionThrows(): void
    {
        $service = new PriorityResetObservabilityFailurePrecedenceThrowingService(
            self::UNSAFE_RESET_FAILURE_MESSAGE,
        );

        $tracer = new PriorityResetObservabilityFailurePrecedenceFakeTracer();
        $meter = new PriorityResetObservabilityFailurePrecedenceFakeMeter();
        $logger = new PriorityResetObservabilityFailurePrecedenceFakeLogger(
            failureMessage: self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
        );

        $exception = self::catchResetException(
            self::orchestrator(
                service: $service,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        self::assertTrue($service->wasReset());
        self::assertServiceFailedException($exception);
        self::assertPrimaryResetFailureWasNotReplaced($exception);

        self::assertCount(1, $meter->increments());
        self::assertCount(1, $meter->observations());
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

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    private static function orchestrator(
        ResetInterface $service,
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
    ): PriorityResetOrchestrator {
        $tagRegistry = new TagRegistry();
        $tagRegistry->add(self::EFFECTIVE_RESET_TAG, self::SERVICE_ID);

        return new PriorityResetOrchestrator(
            container: new PriorityResetObservabilityFailurePrecedenceContainer([
                self::SERVICE_ID => $service,
            ]),
            tagRegistry: $tagRegistry,
            defaultGroup: 'default',
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        );
    }

    private static function catchResetException(PriorityResetOrchestrator $orchestrator): ResetException
    {
        try {
            $orchestrator->resetAll(self::EFFECTIVE_RESET_TAG);
        } catch (ResetException $exception) {
            return $exception;
        }

        self::fail('Expected ResetException was not thrown.');
    }

    private static function assertFailedResetSummaryWasEmitted(
        PriorityResetObservabilityFailurePrecedenceFakeMeter $meter,
        PriorityResetObservabilityFailurePrecedenceFakeLogger $logger,
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
        self::assertSame(['outcome' => 'failed'], $meter->observations()[0]['labels']);

        self::assertSame(
            [
                [
                    'level' => 'info',
                    'message' => 'foundation.reset',
                    'context' => [
                        'services_count' => 1,
                        'groups_count' => 1,
                        'outcome' => 'failed',
                    ],
                ],
            ],
            $logger->records(),
        );

        self::assertNoUnsafeDiagnosticsInMetricRecords($meter);
        self::assertNoUnsafeDiagnosticsInLogRecords($logger);
    }

    private static function assertServiceFailedException(ResetException $exception): void
    {
        self::assertSame(ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED, $exception->code());
        self::assertSame($exception->code(), $exception->errorCode());
        self::assertSame('reset-service-failed', $exception->reason());
        self::assertSame('reset-service-failed', $exception->getMessage());
        self::assertSame(0, $exception->getCode());

        $previous = $exception->getPrevious();

        self::assertInstanceOf(\RuntimeException::class, $previous);
        self::assertSame(self::UNSAFE_RESET_FAILURE_MESSAGE, $previous->getMessage());

        self::assertSafeExceptionMessage($exception);
    }

    private static function assertPrimaryResetFailureWasNotReplaced(ResetException $exception): void
    {
        self::assertSame(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED,
            $exception->code(),
            'Observability failure must not replace the primary reset failure.',
        );

        self::assertSame(
            'reset-service-failed',
            $exception->getMessage(),
            'Primary reset failure message must remain stable and safe.',
        );

        self::assertStringNotContainsString(
            self::UNSAFE_OBSERVABILITY_FAILURE_MESSAGE,
            $exception->getMessage(),
        );

        foreach (self::unsafeObservabilityNeedles() as $needle) {
            self::assertStringNotContainsString($needle, $exception->getMessage());
        }
    }

    private static function assertSafeExceptionMessage(ResetException $exception): void
    {
        foreach (self::unsafeDiagnosticsNeedles() as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $exception->getMessage(),
                'ResetException::getMessage() must remain a stable safe reason token.',
            );
        }
    }

    private static function assertNoUnsafeDiagnosticsInMetricRecords(
        PriorityResetObservabilityFailurePrecedenceFakeMeter $meter,
    ): void {
        foreach ($meter->increments() as $increment) {
            self::assertNoUnsafeDiagnosticsInMap($increment['labels']);
        }

        foreach ($meter->observations() as $observation) {
            self::assertNoUnsafeDiagnosticsInMap($observation['labels']);
        }
    }

    private static function assertNoUnsafeDiagnosticsInLogRecords(
        PriorityResetObservabilityFailurePrecedenceFakeLogger $logger,
    ): void {
        foreach ($logger->records() as $record) {
            self::assertNoUnsafeDiagnosticsInString($record['message']);
            self::assertNoUnsafeDiagnosticsInMap($record['context']);
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
     * @return list<string>
     */
    private static function unsafeDiagnosticsNeedles(): array
    {
        return [
            self::SERVICE_ID,
            'raw-reset-payload',
            'raw-observability-payload',
            'Authorization',
            'Bearer',
            'reset-token',
            'observability-token',
            'Cookie',
            'session_id',
            'reset-cookie',
            'observability-cookie',
            'credential',
            'reset-credential',
            'observability-credential',
            'password',
            'reset-password',
            'observability-password',
            'SELECT',
            'users',
            '/home/user/project/.env',
            '/tmp/coretsia-observability-secret',
        ];
    }

    /**
     * @return list<string>
     */
    private static function unsafeObservabilityNeedles(): array
    {
        return [
            'raw-observability-payload',
            'observability-token',
            'observability-cookie',
            'observability-credential',
            'observability-password',
            '/tmp/coretsia-observability-secret',
        ];
    }
}

final class PriorityResetObservabilityFailurePrecedenceSuccessfulService implements ResetInterface
{
    private bool $wasReset = false;

    public function reset(): void
    {
        $this->wasReset = true;
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final class PriorityResetObservabilityFailurePrecedenceThrowingService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $failureMessage,
    ) {
    }

    public function reset(): void
    {
        $this->wasReset = true;

        throw new \RuntimeException($this->failureMessage);
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final readonly class PriorityResetObservabilityFailurePrecedenceContainer implements ContainerInterface
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

final class PriorityResetObservabilityFailurePrecedenceFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetObservabilityFailurePrecedenceFakeSpan>
     */
    private array $startedSpans = [];

    public function __construct(
        private readonly ?string $spanRecordExceptionFailureMessage = null,
        private readonly ?string $spanEndFailureMessage = null,
    ) {
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetObservabilityFailurePrecedenceFakeSpan(
            name: $name,
            attributes: $attributes,
            recordExceptionFailureMessage: $this->spanRecordExceptionFailureMessage,
            endFailureMessage: $this->spanEndFailureMessage,
        );

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
     * @return list<PriorityResetObservabilityFailurePrecedenceFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetObservabilityFailurePrecedenceFakeSpan implements SpanInterface
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
        array $attributes,
        private readonly ?string $recordExceptionFailureMessage = null,
        private readonly ?string $endFailureMessage = null,
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

        if ($this->recordExceptionFailureMessage !== null) {
            throw new \RuntimeException($this->recordExceptionFailureMessage);
        }
    }

    public function end(): void
    {
        $this->ended = true;

        if ($this->endFailureMessage !== null) {
            throw new \RuntimeException($this->endFailureMessage);
        }
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

final class PriorityResetObservabilityFailurePrecedenceFakeMeter implements MeterPortInterface
{
    /**
     * @var list<array{name:string,delta:int,labels:array<string,string|int|bool>}>
     */
    private array $increments = [];

    /**
     * @var list<array{name:string,value:int,labels:array<string,string|int|bool>}>
     */
    private array $observations = [];

    public function __construct(
        private readonly ?string $incrementFailureMessage = null,
        private readonly ?string $observeFailureMessage = null,
    ) {
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = [
            'name' => $name,
            'delta' => $delta,
            'labels' => $labels,
        ];

        if ($this->incrementFailureMessage !== null) {
            throw new \RuntimeException($this->incrementFailureMessage);
        }
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];

        if ($this->observeFailureMessage !== null) {
            throw new \RuntimeException($this->observeFailureMessage);
        }
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

final class PriorityResetObservabilityFailurePrecedenceFakeLogger extends AbstractLogger
{
    /**
     * @var list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    private array $records = [];

    public function __construct(
        private readonly ?string $failureMessage = null,
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];

        if ($this->failureMessage !== null) {
            throw new \RuntimeException($this->failureMessage);
        }
    }

    /**
     * @return list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }
}
