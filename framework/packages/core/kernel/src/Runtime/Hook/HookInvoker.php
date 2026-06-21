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

namespace Coretsia\Kernel\Runtime\Hook;

use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Psr\Container\ContainerInterface;

/**
 * Invokes Kernel lifecycle hooks discovered through the canonical TagRegistry.
 *
 * Hook discovery order is owned by TagRegistry. This invoker must preserve the
 * exact order returned by TagRegistry::all(); it must not re-sort, dedupe, or
 * apply custom priority rules.
 *
 * Tagged services are resolved through PSR-11 by TaggedService::id().
 *
 * Hook service resolution failures and hook service type mismatches are wrapped
 * in KernelRuntimeException with stable safe diagnostics. Exceptions thrown by
 * valid hook implementations are not wrapped here; they propagate as-is so
 * KernelRuntime can apply deterministic lifecycle failure precedence.
 *
 * @internal Kernel-owned hook invocation service.
 */
final readonly class HookInvoker
{
    public function __construct(
        private ContainerInterface $container,
        private TagRegistry $tags,
    ) {
    }

    /**
     * Invokes before-unit-of-work hooks in exact TagRegistry order.
     *
     * @param array<string, mixed> $context Normalized exported UnitOfWork
     *                                      context payload.
     */
    public function invokeBeforeHooks(array $context): void
    {
        foreach ($this->tags->all(ReservedTags::KERNEL_HOOK_BEFORE_UOW) as $taggedService) {
            $hook = $this->resolveBeforeHook($taggedService);

            $hook->beforeUow($context);
        }
    }

    /**
     * Invokes after-unit-of-work hooks in exact TagRegistry order.
     *
     * @param array<string, mixed> $context Normalized exported UnitOfWork
     *                                      context payload.
     * @param array<string, mixed> $result Normalized exported UnitOfWork
     *                                      result payload.
     */
    public function invokeAfterHooks(array $context, array $result): void
    {
        foreach ($this->tags->all(ReservedTags::KERNEL_HOOK_AFTER_UOW) as $taggedService) {
            $hook = $this->resolveAfterHook($taggedService);

            $hook->afterUow($context, $result);
        }
    }

    private function resolveBeforeHook(TaggedService $taggedService): BeforeUowHookInterface
    {
        $service = $this->resolveService($taggedService);

        if (!$service instanceof BeforeUowHookInterface) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_HOOK_SERVICE_INVALID,
            );
        }

        return $service;
    }

    private function resolveAfterHook(TaggedService $taggedService): AfterUowHookInterface
    {
        $service = $this->resolveService($taggedService);

        if (!$service instanceof AfterUowHookInterface) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_HOOK_SERVICE_INVALID,
            );
        }

        return $service;
    }

    private function resolveService(TaggedService $taggedService): mixed
    {
        $serviceId = $taggedService->id();

        try {
            if (!$this->container->has($serviceId)) {
                throw KernelRuntimeException::withReason(
                    KernelRuntimeException::REASON_HOOK_SERVICE_NOT_FOUND,
                );
            }

            return $this->container->get($serviceId);
        } catch (KernelRuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_HOOK_SERVICE_NOT_FOUND,
                $throwable,
            );
        }
    }
}
