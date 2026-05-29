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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Provider\Tags;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class HookInvokerDeterministicOrderTest extends TestCase
{
    public function testBeforeHooksAreInvokedInExactTagRegistryOrderAfterContainerResolution(): void
    {
        $recorder = new HookInvocationRecorder();

        $sharedHook = new RecordingBeforeHook('shared-object', $recorder);

        $container = new HookInvokerTestContainer([
            'hook.beta' => new RecordingBeforeHook('hook.beta', $recorder),
            'hook.alpha' => new RecordingBeforeHook('hook.alpha', $recorder),
            'hook.gamma' => new RecordingBeforeHook('hook.gamma', $recorder),
            'hook.shared.one' => $sharedHook,
            'hook.shared.two' => $sharedHook,
        ]);

        $tags = new TagRegistry();

        $tags->add(Tags::KERNEL_HOOK_BEFORE_UOW, 'hook.gamma', priority: 5);
        $tags->add(Tags::KERNEL_HOOK_BEFORE_UOW, 'hook.beta', priority: 10);
        $tags->add(Tags::KERNEL_HOOK_BEFORE_UOW, 'hook.alpha', priority: 10);
        $tags->add(Tags::KERNEL_HOOK_BEFORE_UOW, 'hook.shared.two', priority: 1);
        $tags->add(Tags::KERNEL_HOOK_BEFORE_UOW, 'hook.shared.one', priority: 1);

        $expectedOrder = \array_map(
            static fn (TaggedService $service): string => $service->id(),
            $tags->all(Tags::KERNEL_HOOK_BEFORE_UOW),
        );

        self::assertSame(
            [
                'hook.alpha',
                'hook.beta',
                'hook.gamma',
                'hook.shared.one',
                'hook.shared.two',
            ],
            $expectedOrder,
        );

        $context = [
            'type' => 'http.request',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ];

        new HookInvoker($container, $tags)->invokeBeforeHooks($context);

        self::assertSame(
            [
                ['before', 'hook.alpha', $context],
                ['before', 'hook.beta', $context],
                ['before', 'hook.gamma', $context],
                ['before', 'shared-object', $context],
                ['before', 'shared-object', $context],
            ],
            $recorder->calls(),
        );
    }

    public function testAfterHooksAreInvokedInExactTagRegistryOrderAfterContainerResolution(): void
    {
        $recorder = new HookInvocationRecorder();

        $sharedHook = new RecordingAfterHook('shared-object', $recorder);

        $container = new HookInvokerTestContainer([
            'hook.beta' => new RecordingAfterHook('hook.beta', $recorder),
            'hook.alpha' => new RecordingAfterHook('hook.alpha', $recorder),
            'hook.gamma' => new RecordingAfterHook('hook.gamma', $recorder),
            'hook.shared.one' => $sharedHook,
            'hook.shared.two' => $sharedHook,
        ]);

        $tags = new TagRegistry();

        $tags->add(Tags::KERNEL_HOOK_AFTER_UOW, 'hook.gamma', priority: 5);
        $tags->add(Tags::KERNEL_HOOK_AFTER_UOW, 'hook.beta', priority: 10);
        $tags->add(Tags::KERNEL_HOOK_AFTER_UOW, 'hook.alpha', priority: 10);
        $tags->add(Tags::KERNEL_HOOK_AFTER_UOW, 'hook.shared.two', priority: 1);
        $tags->add(Tags::KERNEL_HOOK_AFTER_UOW, 'hook.shared.one', priority: 1);

        $expectedOrder = \array_map(
            static fn (TaggedService $service): string => $service->id(),
            $tags->all(Tags::KERNEL_HOOK_AFTER_UOW),
        );

        self::assertSame(
            [
                'hook.alpha',
                'hook.beta',
                'hook.gamma',
                'hook.shared.one',
                'hook.shared.two',
            ],
            $expectedOrder,
        );

        $context = [
            'type' => 'http.request',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ];

        $result = [
            'outcome' => 'success',
            'durationMs' => 12,
        ];

        new HookInvoker($container, $tags)->invokeAfterHooks($context, $result);

        self::assertSame(
            [
                ['after', 'hook.alpha', $context, $result],
                ['after', 'hook.beta', $context, $result],
                ['after', 'hook.gamma', $context, $result],
                ['after', 'shared-object', $context, $result],
                ['after', 'shared-object', $context, $result],
            ],
            $recorder->calls(),
        );
    }

    public function testEmptyHookTagsAreNoop(): void
    {
        $container = new HookInvokerTestContainer([]);
        $tags = new TagRegistry();
        $invoker = new HookInvoker($container, $tags);

        $invoker->invokeBeforeHooks(['type' => 'http.request']);
        $invoker->invokeAfterHooks(['type' => 'http.request'], ['outcome' => 'success']);

        self::addToAssertionCount(1);
    }
}

final readonly class HookInvokerTestContainer implements ContainerInterface
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

final class HookInvocationRecorder
{
    /**
     * @var list<array<int, mixed>>
     */
    private array $calls = [];

    /**
     * @param array<string, mixed> $context
     */
    public function recordBefore(string $id, array $context): void
    {
        $this->calls[] = ['before', $id, $context];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $result
     */
    public function recordAfter(string $id, array $context, array $result): void
    {
        $this->calls[] = ['after', $id, $context, $result];
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}

final readonly class RecordingBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private string $id,
        private HookInvocationRecorder $recorder,
    ) {
    }

    public function beforeUow(array $context): void
    {
        $this->recorder->recordBefore($this->id, $context);
    }
}

final readonly class RecordingAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private string $id,
        private HookInvocationRecorder $recorder,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        $this->recorder->recordAfter($this->id, $context, $result);
    }
}
