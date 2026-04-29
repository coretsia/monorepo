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

namespace Coretsia\Contracts\Module;

/**
 * Port for loading mode presets by canonical preset name.
 *
 * The input is a lowercase canonical preset name such as "micro", "express",
 * "hybrid", or "enterprise". The implementation source is intentionally hidden.
 */
interface ModePresetLoaderInterface
{
    public function load(string $name): ModePresetInterface;
}
