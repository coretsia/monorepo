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

namespace Coretsia\Contracts\Kernel\Ops;

/**
 * Transport-neutral port for Kernel-owned operational capabilities.
 *
 * Implementations own Kernel orchestration and return deterministic safe
 * results. This contract performs no stdout/stderr I/O and is intentionally
 * narrower than a generic command bus.
 *
 * Worker lifecycle, migrations, database, queue, storage, integration, and
 * other package-owned operations MUST remain outside this port.
 */
interface KernelOpsInterface
{
    public function validateConfig(KernelOpsRequest $request): OpsResult;

    public function debugConfig(KernelOpsRequest $request): OpsResult;

    public function compileConfig(KernelOpsRequest $request): OpsResult;

    public function hashConfig(KernelOpsRequest $request): OpsResult;

    public function verifyCache(KernelOpsRequest $request): OpsResult;

    public function debugModules(KernelOpsRequest $request): OpsResult;
}
