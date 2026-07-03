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
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
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
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeResetHappensAfterAfterUowHooksTest extends TestCase
{
    public function testHappyPathEventOrderIsBeforeBodyAfterReset(): void
    {
        $recorder = new KernelRuntimeResetHappensAfterAfterUowHooksRecorder();

        $runtime = self::runtime($recorder);

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static function () use ($recorder): string {
                $recorder->events[] = 'body';

                return 'body-value';
            },
        );

        self::assertSame('body-value', $result);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );

        self::assertLessThan(
            self::eventIndex($recorder->events, 'reset'),
            self::eventIndex($recorder->events, 'after'),
        );
    }

    public function testAfterHookFailureStillRunsResetAfterAfterHookStartsAndFails(): void
    {
        $recorder = new KernelRuntimeResetHappensAfterAfterUowHooksRecorder();
        $afterFailure = new \RuntimeException('after-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            afterFailure: $afterFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder): string {
                    $recorder->events[] = 'body';

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to surface the after-hook throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($afterFailure, $throwable);
        }

        self::assertSame(
            [
                'before',
                'body',
                'after-start',
                'after-throws',
                'reset',
            ],
            $recorder->events,
        );

        self::assertLessThan(
            self::eventIndex($recorder->events, 'reset'),
            self::eventIndex($recorder->events, 'after-start'),
        );
        self::assertLessThan(
            self::eventIndex($recorder->events, 'reset'),
            self::eventIndex($recorder->events, 'after-throws'),
        );
    }

    private static function runtime(
        KernelRuntimeResetHappensAfterAfterUowHooksRecorder $recorder,
        ?\Throwable $afterFailure = null,
    ): KernelRuntime {
        $contextStore = new ContextStore();

        $container = new KernelRuntimeResetHappensAfterAfterUowHooksContainer([
            KernelRuntimeResetHappensAfterAfterUowHooksBeforeHook::class => new KernelRuntimeResetHappensAfterAfterUowHooksBeforeHook(
                $recorder,
            ),
            KernelRuntimeResetHappensAfterAfterUowHooksAfterHook::class => new KernelRuntimeResetHappensAfterAfterUowHooksAfterHook(
                recorder: $recorder,
                failure: $afterFailure,
            ),
            KernelRuntimeResetHappensAfterAfterUowHooksResetService::class => new KernelRuntimeResetHappensAfterAfterUowHooksResetService(
                contextStore: $contextStore,
                recorder: $recorder,
            ),
        ]);

        $hookRegistry = new TagRegistry();

        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_BEFORE_UOW,
            KernelRuntimeResetHappensAfterAfterUowHooksBeforeHook::class,
        );

        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_AFTER_UOW,
            KernelRuntimeResetHappensAfterAfterUowHooksAfterHook::class,
        );

        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            ReservedTags::KERNEL_RESET,
            KernelRuntimeResetHappensAfterAfterUowHooksResetService::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeResetHappensAfterAfterUowHooksIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeResetHappensAfterAfterUowHooksCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: $container,
                tags: $hookRegistry,
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
    }

    /**
     * @param list<string> $events
     */
    private static function eventIndex(array $events, string $event): int
    {
        $index = \array_search($event, $events, true);

        self::assertIsInt($index);

        return $index;
    }
}

final class KernelRuntimeResetHappensAfterAfterUowHooksRecorder
{
    /**
     * @var list<string>
     */
    public array $events = [];
}

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private KernelRuntimeResetHappensAfterAfterUowHooksRecorder $recorder,
    ) {
    }

    public function beforeUow(array $context): void
    {
        $this->recorder->events[] = 'before';
    }
}

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private KernelRuntimeResetHappensAfterAfterUowHooksRecorder $recorder,
        private ?\Throwable $failure = null,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        if ($this->failure !== null) {
            $this->recorder->events[] = 'after-start';
            $this->recorder->events[] = 'after-throws';

            throw $this->failure;
        }

        $this->recorder->events[] = 'after';
    }
}

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private KernelRuntimeResetHappensAfterAfterUowHooksRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->events[] = 'reset';

        $this->contextStore->reset();
    }
}

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeResetHappensAfterAfterUowHooksContainer implements ContainerInterface
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
