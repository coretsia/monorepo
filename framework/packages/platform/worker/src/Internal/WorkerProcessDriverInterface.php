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

namespace Coretsia\Platform\Worker\Internal;

use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Package-internal single-child process adapter.
 *
 * @internal
 */
interface WorkerProcessDriverInterface
{
    public const string DRIVER_PCNTL = 'pcntl';
    public const string DRIVER_PROC = 'proc';

    public function name(): string;

    public function supports(WorkerPoolSpec $spec): bool;

    /**
     * Prepares driver-owned process infrastructure.
     *
     * This method is called after runtime-entrypoint validation but before the
     * supervisor acquires its lifecycle lock or opens control/readiness listeners.
     */
    public function prepare(WorkerPoolSpec $spec): void;

    public function spawn(
        WorkerPoolSpec $spec,
        int $workerIndex,
    ): WorkerChildProcess;

    /**
     * Polls one child within the caller-owned remaining phase budget.
     */
    public function pollExit(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): ?WorkerProcessExit;

    /**
     * Requests graceful termination within the remaining phase budget.
     */
    public function terminate(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void;

    /**
     * Requests forced termination within the remaining phase budget.
     */
    public function kill(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void;

    /**
     * Closes one child resource within the remaining phase budget.
     */
    public function close(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void;

    /**
     * Releases driver-owned process infrastructure after every child has been
     * closed within the caller-owned remaining cleanup budget.
     */
    public function shutdown(int $timeoutMs): void;
}
