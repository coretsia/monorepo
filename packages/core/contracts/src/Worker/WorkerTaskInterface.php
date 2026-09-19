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
 * One real unit of work acquired from a worker task source.
 *
 * The task owns transport-specific success and failure settlement.
 */
interface WorkerTaskInterface
{
    /**
     * Executes the application-level task body.
     */
    public function execute(): mixed;

    /**
     * Performs success-side transport settlement after the Kernel unit of work
     * completes successfully.
     */
    public function complete(mixed $result): void;

    /**
     * Performs failure-side transport settlement only after application or
     * Kernel unit-of-work execution fails.
     */
    public function fail(\Throwable $failure): void;
}
