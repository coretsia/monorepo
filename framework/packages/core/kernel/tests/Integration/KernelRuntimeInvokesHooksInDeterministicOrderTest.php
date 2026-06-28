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
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
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

final class KernelRuntimeInvokesHooksInDeterministicOrderTest extends TestCase
{
    public function testRunUnitOfWorkInvokesHooksAroundBodyInExactTagRegistryOrder(): void
    {
        $contextStore = new ContextStore();
        $recorder = new KernelRuntimeInvokesHooksRecorder();

        $sharedBeforeHook = new KernelRuntimeInvokesHooksBeforeHook(
            'before:shared-object',
            $recorder,
        );

        $sharedAfterHook = new KernelRuntimeInvokesHooksAfterHook(
            'after:shared-object',
            $recorder,
        );

        $hookContainer = new KernelRuntimeInvokesHooksContainer([
            'hook.before.beta' => new KernelRuntimeInvokesHooksBeforeHook('before:hook.before.beta', $recorder),
            'hook.before.alpha' => new KernelRuntimeInvokesHooksBeforeHook('before:hook.before.alpha', $recorder),
            'hook.before.gamma' => new KernelRuntimeInvokesHooksBeforeHook('before:hook.before.gamma', $recorder),
            'hook.before.shared.one' => $sharedBeforeHook,
            'hook.before.shared.two' => $sharedBeforeHook,
            'hook.after.beta' => new KernelRuntimeInvokesHooksAfterHook('after:hook.after.beta', $recorder),
            'hook.after.alpha' => new KernelRuntimeInvokesHooksAfterHook('after:hook.after.alpha', $recorder),
            'hook.after.gamma' => new KernelRuntimeInvokesHooksAfterHook('after:hook.after.gamma', $recorder),
            'hook.after.shared.one' => $sharedAfterHook,
            'hook.after.shared.two' => $sharedAfterHook,
        ]);

        $tags = new TagRegistry();

        $tags->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, 'hook.before.gamma', priority: 5);
        $tags->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, 'hook.before.beta', priority: 10);
        $tags->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, 'hook.before.alpha', priority: 10);
        $tags->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, 'hook.before.shared.two', priority: 1);
        $tags->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, 'hook.before.shared.one', priority: 1);

        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'hook.after.gamma', priority: 5);
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'hook.after.beta', priority: 10);
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'hook.after.alpha', priority: 10);
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'hook.after.shared.two', priority: 1);
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'hook.after.shared.one', priority: 1);

        $expectedBeforeServiceOrder = \array_map(
            static fn ($service): string => $service->id(),
            $tags->all(ReservedTags::KERNEL_HOOK_BEFORE_UOW),
        );

        $expectedAfterServiceOrder = \array_map(
            static fn ($service): string => $service->id(),
            $tags->all(ReservedTags::KERNEL_HOOK_AFTER_UOW),
        );

        self::assertSame(
            [
                'hook.before.alpha',
                'hook.before.beta',
                'hook.before.gamma',
                'hook.before.shared.one',
                'hook.before.shared.two',
            ],
            $expectedBeforeServiceOrder,
        );

        self::assertSame(
            [
                'hook.after.alpha',
                'hook.after.beta',
                'hook.after.gamma',
                'hook.after.shared.one',
                'hook.after.shared.two',
            ],
            $expectedAfterServiceOrder,
        );

        $runtime = self::runtime(
            contextStore: $contextStore,
            hooks: new HookInvoker(
                container: $hookContainer,
                tags: $tags,
            ),
        );

        $bodyArguments = null;

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            function (...$arguments) use ($recorder, &$bodyArguments): string {
                $bodyArguments = $arguments;
                $recorder->events[] = 'body';

                return 'body-value';
            },
            [
                'zeta' => [
                    'second' => 2,
                    'first' => 1,
                ],
                'alpha' => [
                    'b',
                    'a',
                ],
            ],
        );

        self::assertSame('body-value', $result);
        self::assertSame([], $bodyArguments);

        self::assertSame(
            [
                'before:hook.before.alpha',
                'before:hook.before.beta',
                'before:hook.before.gamma',
                'before:shared-object',
                'before:shared-object',
                'body',
                'after:hook.after.alpha',
                'after:hook.after.beta',
                'after:hook.after.gamma',
                'after:shared-object',
                'after:shared-object',
            ],
            $recorder->events,
        );

        self::assertCount(5, $recorder->beforeContexts);
        self::assertCount(5, $recorder->afterContexts);
        self::assertCount(5, $recorder->afterResults);

        $firstBeforeContext = $recorder->beforeContexts[0];

        self::assertSame(
            [
                'attributes',
                'correlationId',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($firstBeforeContext),
        );

        self::assertSame(
            [
                'alpha' => [
                    'b',
                    'a',
                ],
                'zeta' => [
                    'first' => 1,
                    'second' => 2,
                ],
            ],
            $firstBeforeContext['attributes'],
        );

        self::assertSame('01B7X3NDEKTSV4RRFFQ69G5FAV', $firstBeforeContext['correlationId']);
        self::assertSame(UnitOfWorkType::HTTP, $firstBeforeContext['type']);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $firstBeforeContext['uowId']);
        self::assertIsInt($firstBeforeContext['startedAt']);
        self::assertGreaterThan(0, $firstBeforeContext['startedAt']);

        foreach ($recorder->beforeContexts as $context) {
            self::assertSame($firstBeforeContext, $context);
            self::assertJsonLikePayload($context);
        }

        foreach ($recorder->afterContexts as $context) {
            self::assertSame($firstBeforeContext, $context);
            self::assertJsonLikePayload($context);
        }

        $firstAfterResult = $recorder->afterResults[0];

        self::assertSame(
            [
                'correlationId',
                'durationMs',
                'extensions',
                'finishedAt',
                'outcome',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($firstAfterResult),
        );

        self::assertSame('01B7X3NDEKTSV4RRFFQ69G5FAV', $firstAfterResult['correlationId']);
        self::assertSame([], $firstAfterResult['extensions']);
        self::assertSame(Outcome::SUCCESS, $firstAfterResult['outcome']);
        self::assertSame(UnitOfWorkType::HTTP, $firstAfterResult['type']);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $firstAfterResult['uowId']);
        self::assertIsInt($firstAfterResult['durationMs']);
        self::assertGreaterThanOrEqual(0, $firstAfterResult['durationMs']);
        self::assertIsInt($firstAfterResult['finishedAt']);
        self::assertGreaterThan(0, $firstAfterResult['finishedAt']);
        self::assertSame($firstBeforeContext['startedAt'], $firstAfterResult['startedAt']);

        foreach ($recorder->afterResults as $resultPayload) {
            self::assertSame($firstAfterResult, $resultPayload);
            self::assertJsonLikePayload($resultPayload);
        }

        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    public function testLowLevelAdaptersCanUseBeginAndAfterToAccessExportedContextAndResult(): void
    {
        $contextStore = new ContextStore();

        $runtime = self::runtime(
            contextStore: $contextStore,
            hooks: new HookInvoker(
                container: new KernelRuntimeInvokesHooksContainer([]),
                tags: new TagRegistry(),
            ),
        );

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        self::assertIsArray($context);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $context['uowId']);
        self::assertSame('01B7X3NDEKTSV4RRFFQ69G5FAV', $context['correlationId']);
        self::assertSame(UnitOfWorkType::HTTP, $context['type']);

        $result = $runtime->afterUnitOfWork(
            context: $context,
            outcome: Outcome::SUCCESS,
            extensions: [
                'adapter' => 'low-level',
            ],
        );

        self::assertIsArray($result);
        self::assertSame($context['uowId'], $result['uowId']);
        self::assertSame($context['correlationId'], $result['correlationId']);
        self::assertSame(Outcome::SUCCESS, $result['outcome']);
        self::assertSame(
            [
                'adapter' => 'low-level',
            ],
            $result['extensions'],
        );

        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    private static function runtime(
        ContextStore $contextStore,
        HookInvoker $hooks,
    ): KernelRuntime {
        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: self::resetOrchestrator($contextStore),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeInvokesHooksIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeInvokesHooksCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: $hooks,
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
            container: new KernelRuntimeInvokesHooksContainer([
                ContextStore::class => $contextStore,
            ]),
            tagRegistry: $tagRegistry,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertJsonLikePayload(array $payload): void
    {
        foreach ($payload as $value) {
            self::assertJsonLikeValue($value);
        }
    }

    private static function assertJsonLikeValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        self::assertIsArray($value);

        foreach ($value as $nestedValue) {
            self::assertJsonLikeValue($nestedValue);
        }
    }
}

final class KernelRuntimeInvokesHooksRecorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $beforeContexts = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $afterContexts = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $afterResults = [];
}

final readonly class KernelRuntimeInvokesHooksBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private string $event,
        private KernelRuntimeInvokesHooksRecorder $recorder,
    ) {
    }

    public function beforeUow(array $context): void
    {
        $this->recorder->events[] = $this->event;
        $this->recorder->beforeContexts[] = $context;
    }
}

final readonly class KernelRuntimeInvokesHooksAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private string $event,
        private KernelRuntimeInvokesHooksRecorder $recorder,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        $this->recorder->events[] = $this->event;
        $this->recorder->afterContexts[] = $context;
        $this->recorder->afterResults[] = $result;
    }
}

final readonly class KernelRuntimeInvokesHooksIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeInvokesHooksCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeInvokesHooksContainer implements ContainerInterface
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
