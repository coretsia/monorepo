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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Provider\Tags as FoundationTags;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class KernelRuntimeEmitsPolicyCompliantObservabilityTest extends TestCase
{
    public function testRunUnitOfWorkEmitsPolicyCompliantLifecycleObservability(): void
    {
        $logger = new KernelRuntimeEmitsPolicyCompliantObservabilityLogger();
        $tracer = new KernelRuntimeEmitsPolicyCompliantObservabilityTracer();
        $meter = new KernelRuntimeEmitsPolicyCompliantObservabilityMeter();

        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertInstanceOf(TracerPortInterface::class, $tracer);
        self::assertInstanceOf(MeterPortInterface::class, $meter);

        $runtime = self::runtime(
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
        );

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static fn (): string => 'body-value',
        );

        self::assertSame('body-value', $result);

        self::assertCount(1, $tracer->spans);
        self::assertSame('kernel.uow', $tracer->spans[0]->name);
        self::assertTrue($tracer->spans[0]->ended);

        self::assertSame(
            [
                'operation' => UnitOfWorkType::HTTP,
                'outcome' => Outcome::SUCCESS,
            ],
            $tracer->spans[0]->attributes,
        );

        self::assertSame(
            [
                'operation',
                'outcome',
            ],
            \array_keys($tracer->spans[0]->attributes),
        );

        self::assertCount(1, $meter->increments);
        self::assertSame('kernel.uow_total', $meter->increments[0]['name']);
        self::assertSame(1, $meter->increments[0]['delta']);
        self::assertSame(
            [
                'operation' => UnitOfWorkType::HTTP,
                'outcome' => Outcome::SUCCESS,
            ],
            $meter->increments[0]['labels'],
        );

        self::assertSame(
            [
                'operation',
                'outcome',
            ],
            \array_keys($meter->increments[0]['labels']),
        );

        self::assertCount(1, $meter->observations);
        self::assertSame('kernel.uow_duration_ms', $meter->observations[0]['name']);
        self::assertIsInt($meter->observations[0]['value']);
        self::assertGreaterThanOrEqual(0, $meter->observations[0]['value']);
        self::assertSame(
            [
                'operation' => UnitOfWorkType::HTTP,
                'outcome' => Outcome::SUCCESS,
            ],
            $meter->observations[0]['labels'],
        );

        self::assertSame(
            [
                'operation',
                'outcome',
            ],
            \array_keys($meter->observations[0]['labels']),
        );

        self::assertCount(1, $logger->records);
        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('kernel.uow', $logger->records[0]['message']);

        self::assertSame(
            [
                'duration_ms',
                'operation',
                'outcome',
            ],
            \array_keys($logger->records[0]['context']),
        );

        self::assertSame(UnitOfWorkType::HTTP, $logger->records[0]['context']['operation']);
        self::assertSame(Outcome::SUCCESS, $logger->records[0]['context']['outcome']);
        self::assertIsInt($logger->records[0]['context']['duration_ms']);
        self::assertGreaterThanOrEqual(0, $logger->records[0]['context']['duration_ms']);

        self::assertSafeSummaryPayload($tracer->spans[0]->attributes);
        self::assertSafeSummaryPayload($meter->increments[0]['labels']);
        self::assertSafeSummaryPayload($meter->observations[0]['labels']);
        self::assertSafeSummaryPayload($logger->records[0]['context']);
    }

    public function testObservabilityPortFailuresDoNotReplacePrimaryKernelRuntimeLifecycleFailures(): void
    {
        $logger = new KernelRuntimeEmitsPolicyCompliantObservabilityLogger(
            failure: new \RuntimeException(
                'logger unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $tracer = new KernelRuntimeEmitsPolicyCompliantObservabilityTracer(
            failure: new \RuntimeException(
                'tracer unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $meter = new KernelRuntimeEmitsPolicyCompliantObservabilityMeter(
            failure: new \RuntimeException(
                'meter unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $primaryFailure = new \RuntimeException('primary-body-failure');

        $runtime = self::runtime(
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($primaryFailure): never {
                    throw $primaryFailure;
                },
            );

            self::fail('Expected KernelRuntime to keep the body throwable primary.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($primaryFailure, $throwable);
        }
    }

    public function testObservabilityPortFailuresDoNotReplaceResetFailureWhenNoEarlierFailureExists(): void
    {
        $logger = new KernelRuntimeEmitsPolicyCompliantObservabilityLogger(
            failure: new \RuntimeException('logger-failure'),
        );

        $tracer = new KernelRuntimeEmitsPolicyCompliantObservabilityTracer(
            failure: new \RuntimeException('tracer-failure'),
        );

        $meter = new KernelRuntimeEmitsPolicyCompliantObservabilityMeter(
            failure: new \RuntimeException('meter-failure'),
        );

        $runtime = self::runtime(
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
            resetFailure: new \RuntimeException(
                'reset unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'body-value',
            );

            self::fail('Expected KernelRuntime to surface reset failure.');
        } catch (KernelRuntimeException $exception) {
            self::assertSame(KernelRuntimeException::ERROR_CODE, $exception->errorCode());
            self::assertSame(KernelRuntimeException::REASON_RESET_FAILED, $exception->reason());
            self::assertSame(
                'CORETSIA_KERNEL_RUNTIME_ERROR: kernel-runtime-reset-failed',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('unsafe-token', $exception->getMessage());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Cookie', $exception->getMessage());
            self::assertStringNotContainsString('session_id', $exception->getMessage());
            self::assertStringNotContainsString('SELECT * FROM users', $exception->getMessage());
            self::assertStringNotContainsString('/tmp/', $exception->getMessage());
        }
    }

    private static function runtime(
        KernelRuntimeEmitsPolicyCompliantObservabilityLogger $logger,
        KernelRuntimeEmitsPolicyCompliantObservabilityTracer $tracer,
        KernelRuntimeEmitsPolicyCompliantObservabilityMeter $meter,
        ?\Throwable $resetFailure = null,
    ): KernelRuntime {
        $contextStore = new ContextStore();

        $container = new KernelRuntimeEmitsPolicyCompliantObservabilityContainer([
            KernelRuntimeEmitsPolicyCompliantObservabilityResetService::class => new KernelRuntimeEmitsPolicyCompliantObservabilityResetService(
                contextStore: $contextStore,
                failure: $resetFailure,
            ),
        ]);

        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            FoundationTags::KERNEL_RESET,
            KernelRuntimeEmitsPolicyCompliantObservabilityResetService::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeEmitsPolicyCompliantObservabilityIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeEmitsPolicyCompliantObservabilityCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: new KernelRuntimeEmitsPolicyCompliantObservabilityContainer([]),
                tags: new TagRegistry(),
            ),
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertSafeSummaryPayload(array $payload): void
    {
        $encoded = \json_encode($payload, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('01ARZ3NDEKTSV4RRFFQ69G5FAV', $encoded);
        self::assertStringNotContainsString('01B7X3NDEKTSV4RRFFQ69G5FAV', $encoded);
        self::assertStringNotContainsString('uowId', $encoded);
        self::assertStringNotContainsString('correlationId', $encoded);
        self::assertStringNotContainsString('context', $encoded);
        self::assertStringNotContainsString('attributes', $encoded);
        self::assertStringNotContainsString('hook', $encoded);
        self::assertStringNotContainsString('payload', $encoded);
        self::assertStringNotContainsString('Throwable', $encoded);
        self::assertStringNotContainsString('RuntimeException', $encoded);
        self::assertStringNotContainsString('primary-body-failure', $encoded);
        self::assertStringNotContainsString('unsafe-token', $encoded);
        self::assertStringNotContainsString('Authorization', $encoded);
        self::assertStringNotContainsString('Cookie', $encoded);
        self::assertStringNotContainsString('Set-Cookie', $encoded);
        self::assertStringNotContainsString('headers', $encoded);
        self::assertStringNotContainsString('session_id', $encoded);
        self::assertStringNotContainsString('SELECT * FROM users', $encoded);
        self::assertStringNotContainsString('/tmp/', $encoded);
        self::assertStringNotContainsString(__DIR__, $encoded);
        self::assertStringNotContainsString("\n#", $encoded);
    }
}

final class KernelRuntimeEmitsPolicyCompliantObservabilityLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function __construct(
        private readonly ?\Throwable $failure = null,
    ) {
    }

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->records[] = [
            'level' => (string)$level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}

final class KernelRuntimeEmitsPolicyCompliantObservabilityTracer implements TracerPortInterface
{
    /**
     * @var list<KernelRuntimeEmitsPolicyCompliantObservabilitySpan>
     */
    public array $spans = [];

    public function __construct(
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $span = new KernelRuntimeEmitsPolicyCompliantObservabilitySpan(
            name: $name,
            attributes: $attributes,
        );

        $this->spans[] = $span;

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
}

final class KernelRuntimeEmitsPolicyCompliantObservabilitySpan implements SpanInterface
{
    public bool $ended = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $name,
        public array $attributes,
    ) {
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
        $this->attributes = [
            ...$this->attributes,
            ...$attributes,
        ];
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
        $this->ended = true;
    }
}

final class KernelRuntimeEmitsPolicyCompliantObservabilityMeter implements MeterPortInterface
{
    /**
     * @var list<array{name: string, delta: int, labels: array<string, string|int|bool>}>
     */
    public array $increments = [];

    /**
     * @var list<array{name: string, value: int, labels: array<string, string|int|bool>}>
     */
    public array $observations = [];

    public function __construct(
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->increments[] = [
            'name' => $name,
            'delta' => $delta,
            'labels' => $labels,
        ];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->observations[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
    }
}

final readonly class KernelRuntimeEmitsPolicyCompliantObservabilityResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private ?\Throwable $failure = null,
    ) {
    }

    public function reset(): void
    {
        $this->contextStore->reset();

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final readonly class KernelRuntimeEmitsPolicyCompliantObservabilityIdGenerator implements IdGeneratorInterface
{
    /**
     * @param non-empty-string $id
     */
    public function __construct(
        private string $id,
    ) {
    }

    public function generate(): string
    {
        return $this->id;
    }
}

final readonly class KernelRuntimeEmitsPolicyCompliantObservabilityCorrelationIdProvider implements CorrelationIdProviderInterface
{
    /**
     * @param non-empty-string|null $correlationId
     */
    public function __construct(
        private ?string $correlationId,
    ) {
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}

final readonly class KernelRuntimeEmitsPolicyCompliantObservabilityContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \RuntimeException('test-container-service-not-found');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
