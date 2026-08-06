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

namespace Coretsia\Platform\Worker\Process;

use Coretsia\Platform\Worker\Communication\WorkerControlServer;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Supervisor\WorkerChildTable;
use Coretsia\Platform\Worker\Supervisor\WorkerSignalController;

/**
 * Detaches all Worker-owned supervisor resources known to the package.
 *
 * The per-child readiness listener is owned by the process driver and is
 * closed by that driver before this boundary is invoked. This boundary does
 * not enumerate or close arbitrary application, integration, extension,
 * deployment, or third-party descriptors. Those descriptors are governed by
 * the repository-wide process-exec descriptor-safety contract.
 */
final readonly class WorkerForkIsolation
{
    public function __construct(
        private WorkerLifecycleLock $lifecycleLock,
        private WorkerControlServer $controlServer,
        private WorkerSignalController $signals,
        private WorkerChildTable $children,
    ) {
    }

    /**
     * Detaches all supervisor resources registered with this boundary before
     * process-image replacement.
     */
    public function prepareForkedChild(): void
    {
        $this->children->detachInForkedChild();
        $this->controlServer->detachInForkedChild();
        $this->lifecycleLock->detachInForkedChild();
        $this->signals->detachInForkedChild();
    }
}
