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
 * Source of real worker tasks.
 *
 * receive() MUST use transport-native blocking, event-loop waiting, or an
 * equivalent transport-owned wait mechanism. Synthetic no-op tasks,
 * sleep/usleep idle loops, and busy polling are forbidden.
 */
interface WorkerTaskSourceInterface
{
    public function taskType(): WorkerTaskType;

    /**
     * Verifies transport, consumer, handler, emitter, and source readiness
     * before the child publishes its readiness frame.
     *
     * This method MUST NOT acquire, acknowledge, execute, or consume a task.
     */
    public function assertReady(
        WorkerTaskSourceContextInterface $context,
    ): void;

    /**
     * Waits for one real task.
     *
     * A null result is valid only after cooperative cancellation has been
     * requested. Implementations MUST remain cooperatively interruptible.
     */
    public function receive(
        WorkerTaskSourceContextInterface $context,
    ): ?WorkerTaskInterface;
}
