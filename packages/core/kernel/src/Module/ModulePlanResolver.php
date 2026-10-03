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
 * Pure Phase B coordinator for one installed manifest
 * and effective module selection.
 *
 * @internal
 */
final readonly class ModulePlanResolver
{
    public function __construct(private ModuleGraphResolver $graphResolver)
    {
    }

    public function resolve(
        string $app,
        ModuleManifest $manifest,
        ModuleSelection $selection,
    ): ModulePlan {
        return $this->graphResolver->resolve(app: $app, installed: $manifest, selection: $selection);
    }
}
