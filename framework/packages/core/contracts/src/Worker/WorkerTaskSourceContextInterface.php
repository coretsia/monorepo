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

namespace Coretsia\Contracts\Worker;

/**
 * Safe child-process context exposed to worker task sources.
 *
 * Implementations MUST NOT expose runtime paths, control endpoints,
 * configuration trees, environment values, lifecycle locks, process handles,
 * child tables, or transport credentials.
 */
interface WorkerTaskSourceContextInterface
{
    /**
     * Returns the stable zero-based worker slot index.
     */
    public function workerIndex(): int;

    /**
     * Returns the configured worker pool size.
     */
    public function workerCount(): int;

    /**
     * Indicates that cooperative shutdown has been requested.
     */
    public function cancellationRequested(): bool;

    /**
     * Maximum continuous transport blocking interval before the source MUST
     * regain control and re-check cooperative cancellation.
     *
     * Transport-native cancellation MAY wake the wait earlier, but MUST NOT
     * extend the continuous blocking interval beyond this bound.
     *
     * @return positive-int
     */
    public function maxBlockingWaitMs(): int;
}
