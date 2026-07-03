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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
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
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeWritesBaseContextKeysAtBeginUowTest extends TestCase
{
    public function testRunUnitOfWorkWritesBaseContextKeysBeforeBodyRuns(): void
    {
        $contextStore = new ContextStore();

        $runtime = self::runtime(
            contextStore: $contextStore,
            uowIds: new KernelRuntimeWritesBaseContextKeysIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeWritesBaseContextKeysCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
        );

        $seenInBody = null;
        $bodyArguments = null;

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            function (...$arguments) use ($contextStore, &$seenInBody, &$bodyArguments): string {
                $bodyArguments = $arguments;

                $seenInBody = [
                    ContextKeys::CORRELATION_ID => $contextStore->get(ContextKeys::CORRELATION_ID),
                    ContextKeys::UOW_ID => $contextStore->get(ContextKeys::UOW_ID),
                    ContextKeys::UOW_TYPE => $contextStore->get(ContextKeys::UOW_TYPE),
                ];

                return 'body-value';
            },
        );

        self::assertSame('body-value', $result);
        self::assertSame([], $bodyArguments);

        self::assertIsArray($seenInBody);
        self::assertSame(
            '01B7X3NDEKTSV4RRFFQ69G5FAV',
            $seenInBody[ContextKeys::CORRELATION_ID],
        );
        self::assertSame(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            $seenInBody[ContextKeys::UOW_ID],
        );
        self::assertSame(
            UnitOfWorkType::HTTP,
            $seenInBody[ContextKeys::UOW_TYPE],
        );

        self::assertIsString($seenInBody[ContextKeys::CORRELATION_ID]);
        self::assertIsString($seenInBody[ContextKeys::UOW_ID]);

        /*
         * ContextStore::reset() is reached through ResetOrchestrator after the
         * body/after phase, so UoW-local values must not remain dirty after
         * runUnitOfWork() completes.
         */
        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    private static function runtime(
        ContextStore $contextStore,
        IdGeneratorInterface $uowIds,
        CorrelationIdProviderInterface $correlationIdProvider,
    ): KernelRuntime {
        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: self::resetOrchestrator($contextStore),
            stopwatch: new Stopwatch(),
            uowIds: $uowIds,
            correlationIdProvider: $correlationIdProvider,
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: new KernelRuntimeWritesBaseContextKeysContainer([]),
                tags: new TagRegistry(),
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
    }

    private static function resetOrchestrator(ContextStore $contextStore): ResetOrchestrator
    {
        $tagRegistry = new TagRegistry();

        $tagRegistry->add(
            ReservedTags::KERNEL_RESET,
            ContextStore::class,
        );

        return new ResetOrchestrator(
            container: new KernelRuntimeWritesBaseContextKeysContainer([
                ContextStore::class => $contextStore,
            ]),
            tagRegistry: $tagRegistry,
        );
    }
}

final readonly class KernelRuntimeWritesBaseContextKeysIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeWritesBaseContextKeysCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeWritesBaseContextKeysContainer implements ContainerInterface
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
