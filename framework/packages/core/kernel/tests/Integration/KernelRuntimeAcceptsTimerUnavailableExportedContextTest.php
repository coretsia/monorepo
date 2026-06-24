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
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeAcceptsTimerUnavailableExportedContextTest extends TestCase
{
    public function testAfterUnitOfWorkAcceptsTimerUnavailableStartedAt(): void
    {
        $recorder = new KernelRuntimeAcceptsTimerUnavailableExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $result = $runtime->afterUnitOfWork(
            context: [
                'attributes' => [],
                'correlationId' => 'correlation-id',
                'startedAt' => 0,
                'type' => UnitOfWorkType::HTTP,
                'uowId' => 'uow-id',
            ],
            outcome: Outcome::SUCCESS,
            extensions: [],
        );

        self::assertSame(1, $recorder->resetCount);

        self::assertArrayNotHasKey('attributes', $result);
        self::assertSame([], $result['extensions']);

        self::assertSame('correlation-id', $result['correlationId']);
        self::assertSame(0, $result['startedAt']);
        self::assertSame(UnitOfWorkType::HTTP, $result['type']);
        self::assertSame('uow-id', $result['uowId']);

        self::assertSame(Outcome::SUCCESS, $result['outcome']);
        self::assertSame(0, $result['durationMs']);

        self::assertArrayHasKey('finishedAt', $result);
        self::assertIsInt($result['finishedAt']);
        self::assertGreaterThanOrEqual(0, $result['finishedAt']);
    }

    private static function runtime(
        KernelRuntimeAcceptsTimerUnavailableExportedContextRecorder $recorder,
    ): KernelRuntime {
        $contextStore = new ContextStore();

        $container = new KernelRuntimeAcceptsTimerUnavailableExportedContextContainer([
            KernelRuntimeAcceptsTimerUnavailableExportedContextResetService::class => new KernelRuntimeAcceptsTimerUnavailableExportedContextResetService(
                contextStore: $contextStore,
                recorder: $recorder,
            ),
        ]);

        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            ReservedTags::KERNEL_RESET,
            KernelRuntimeAcceptsTimerUnavailableExportedContextResetService::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeAcceptsTimerUnavailableExportedContextIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeAcceptsTimerUnavailableExportedContextCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: new KernelRuntimeAcceptsTimerUnavailableExportedContextContainer([]),
                tags: new TagRegistry(),
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
        );
    }
}

final class KernelRuntimeAcceptsTimerUnavailableExportedContextRecorder
{
    public int $resetCount = 0;
}

final readonly class KernelRuntimeAcceptsTimerUnavailableExportedContextResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private KernelRuntimeAcceptsTimerUnavailableExportedContextRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        ++$this->recorder->resetCount;

        $this->contextStore->reset();
    }
}

final readonly class KernelRuntimeAcceptsTimerUnavailableExportedContextIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeAcceptsTimerUnavailableExportedContextCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeAcceptsTimerUnavailableExportedContextContainer implements ContainerInterface
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
