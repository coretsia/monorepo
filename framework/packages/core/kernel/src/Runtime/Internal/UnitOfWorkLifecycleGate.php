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

namespace Coretsia\Kernel\Runtime\Internal;

use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;

/**
 * Per-KernelRuntime single-active-UnitOfWork lifecycle gate.
 *
 * @internal
 */
final class UnitOfWorkLifecycleGate
{
    private bool $active = false;

    public function acquire(): void
    {
        if ($this->active) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
            );
        }

        $this->active = true;
    }

    public function release(): void
    {
        $this->active = false;
    }
}
