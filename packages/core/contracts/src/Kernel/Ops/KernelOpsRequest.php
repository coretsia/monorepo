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
 * Explicit transport-neutral input for one Kernel operation.
 *
 * The request carries only the caller-selected application target. It performs
 * structural boundary validation only and intentionally does not know the
 * Kernel-owned canonical target allowlist.
 *
 * Mode, preset, filesystem paths, config values, artifact state, runtime
 * services, and transport-specific input are outside this contract.
 */
final readonly class KernelOpsRequest
{
    private string $appTarget;

    public function __construct(string $appTarget)
    {
        if ($appTarget === '') {
            throw new \InvalidArgumentException('Kernel Ops app target must be non-empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $appTarget) === 1) {
            throw new \InvalidArgumentException('Invalid Kernel Ops app target.');
        }

        $this->appTarget = $appTarget;
    }

    /**
     * Returns the exact structurally valid caller-supplied application target.
     *
     * Canonical Kernel target validation remains Kernel-owned.
     *
     * @return non-empty-string
     */
    public function appTarget(): string
    {
        return $this->appTarget;
    }
}
