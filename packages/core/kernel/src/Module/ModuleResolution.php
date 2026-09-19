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

namespace Coretsia\Kernel\Module;

use Coretsia\Contracts\Module\ModuleManifest;

/**
 * Immutable result of one Kernel module-resolution run.
 *
 * The installed manifest and resolved ModulePlan belong to the same discovery
 * snapshot. This value is compile-time orchestration state only and must not be
 * exported into generated artifacts or retained by runtime services.
 *
 * @internal Kernel compile-time module-resolution value.
 */
final readonly class ModuleResolution
{
    public function __construct(
        private ModuleManifest $manifest,
        private ModulePlan $plan,
    ) {
    }

    public function manifest(): ModuleManifest
    {
        return $this->manifest;
    }

    public function plan(): ModulePlan
    {
        return $this->plan;
    }
}
