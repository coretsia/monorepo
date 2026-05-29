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

namespace Coretsia\Contracts\Runtime;

/**
 * Format-neutral external kernel runtime boundary.
 *
 * This contract is consumed by platform/runtime adapters such as HTTP, CLI,
 * workers, schedulers, queue consumers, and custom runtime bridges.
 *
 * It intentionally uses only scalar values, callables, throwables, and
 * json-like arrays so adapters can integrate with KernelRuntime without
 * depending on the concrete core/kernel package, Foundation internals, PSR-7,
 * PSR-15, platform packages, integration packages, or transport-specific
 * request/response/message types.
 *
 * KernelRuntime owns unit-of-work lifecycle orchestration:
 *
 * - begin context creation;
 * - base context key writes;
 * - before-unit-of-work hook invocation;
 * - external body execution for the high-level API;
 * - after-unit-of-work hook invocation;
 * - reset orchestration;
 * - safe lifecycle result export.
 *
 * The preferred adapter API is {@see runUnitOfWork()} because it lets the
 * runtime enforce after/reset behavior with try/finally semantics.
 *
 * The low-level {@see beginUnitOfWork()} and {@see afterUnitOfWork()} methods
 * exist for adapters that must integrate around an existing framework
 * lifecycle or event loop and therefore cannot delegate the whole body
 * execution to KernelRuntime directly.
 */
interface KernelRuntimeInterface
{
    /**
     * Runs an external unit-of-work body inside KernelRuntime lifecycle
     * orchestration.
     *
     * Implementations MUST begin a unit of work before invoking the body.
     * Implementations MUST enforce after-unit-of-work and reset behavior after
     * the body has been invoked, including failure paths.
     *
     * On success, this method MUST return the external body return value.
     * It MUST NOT return the exported unit-of-work result array.
     *
     * If the body succeeds but the after/reset phase fails, implementations
     * MUST surface the after/reset failure instead of returning the body value.
     *
     * @param array<string, mixed> $attributes Format-neutral adapter-provided
     *                                         attributes for the unit of work.
     *
     * @return mixed The external body return value.
     */
    public function runUnitOfWork(
        string $type,
        callable $body,
        array $attributes = [],
    ): mixed;

    /**
     * Begins a unit of work and returns the normalized exported context array.
     *
     * Implementations MUST create the unit-of-work context, write base context
     * keys, invoke before-unit-of-work hooks, and return the normalized exported
     * context array.
     *
     * If this method returns successfully, before-unit-of-work hooks have
     * already completed successfully.
     *
     * Low-level adapters MUST execute the external body only after successful
     * completion of this method.
     *
     * @param array<string, mixed> $attributes Format-neutral adapter-provided
     *                                         attributes for the unit of work.
     *
     * @return array<string, mixed> Normalized exported unit-of-work context.
     */
    public function beginUnitOfWork(
        string $type,
        array $attributes = [],
    ): array;

    /**
     * Completes a previously begun unit of work and returns the normalized
     * exported result array.
     *
     * Exported unit-of-work context/result arrays are lifecycle hook payloads.
     * Low-level adapters that need the exported result array MUST use this
     * method directly.
     *
     * Implementations MUST invoke after-unit-of-work hooks and reset
     * orchestration according to runtime lifecycle policy.
     *
     * @param array<string, mixed> $context Normalized exported context array
     *                                      previously returned by
     *                                      {@see beginUnitOfWork()}.
     * @param array<string, mixed> $extensions Additional format-neutral result
     *                                         extensions.
     *
     * @return array<string, mixed> Normalized exported unit-of-work result.
     */
    public function afterUnitOfWork(
        array $context,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array;
}
