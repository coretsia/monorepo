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
 * It intentionally uses only scalar values, callables, throwables, json-like
 * arrays, and contracts-owned opaque lifecycle handles so adapters can integrate
 * with KernelRuntime without depending on the concrete core/kernel package,
 * Foundation internals, PSR-7, PSR-15, platform packages, integration packages,
 * or transport-specific request/response/message types.
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
 *
 * Low-level lifecycle methods are a sharp-edge adapter API. Adapters MUST
 * prefer runUnitOfWork() unless they need a begin/after lifecycle handle or must
 * integrate with an existing external lifecycle.
 *
 * If beginUnitOfWork() returns successfully, the adapter owns completion
 * responsibility and MUST pass the exact returned UnitOfWorkHandle to exactly one
 * afterUnitOfWork() call before the next unit of work can start on the same
 * runtime boundary.
 *
 * Low-level adapters MUST wrap external body execution so that
 * {@see afterUnitOfWork()} is attempted on both success and failure paths.
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
     * Begins a unit of work and returns an opaque lifecycle handle.
     *
     * Implementations MUST create the unit-of-work context, write base context
     * keys, invoke before-unit-of-work hooks, and return a handle that exposes the
     * normalized exported context through UnitOfWorkHandle::context().
     *
     * If this method returns successfully, before-unit-of-work hooks have already
     * completed successfully.
     *
     * Low-level adapters MUST execute the external body only after successful
     * completion of this method.
     *
     * If this method returns successfully, the caller MUST treat the returned
     * handle as an open lifecycle handle and MUST attempt exactly one matching
     * afterUnitOfWork() call.
     *
     * @param array<string, mixed> $attributes Format-neutral adapter-provided
     *                                         attributes for the unit of work.
     */
    public function beginUnitOfWork(
        string $type,
        array $attributes = [],
    ): UnitOfWorkHandle;

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
     * Low-level adapters SHOULD call this method from a finally-equivalent
     * completion path after successful {@see beginUnitOfWork()}.
     *
     * @param UnitOfWorkHandle $handle Lifecycle handle previously returned by
     *                                 beginUnitOfWork().
     * @param array<string, mixed> $extensions Additional format-neutral result
     *                                         extensions.
     *
     * @return array<string, mixed> Normalized exported unit-of-work result.
     */
    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array;
}
