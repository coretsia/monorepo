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

    public function pollExit(
        WorkerChildProcess $child,
    ): ?WorkerProcessExit;

    public function terminate(
        WorkerChildProcess $child,
    ): void;

    public function kill(
        WorkerChildProcess $child,
    ): void;

    public function close(
        WorkerChildProcess $child,
    ): void;

    /**
     * Releases driver-owned process infrastructure after every child has been
     * closed.
     */
    public function shutdown(): void;
}
