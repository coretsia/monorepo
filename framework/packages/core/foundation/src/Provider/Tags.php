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

namespace Coretsia\Foundation\Provider;

/**
 * Foundation-owned canonical DI tag constants.
 *
 * `kernel.reset` is the reserved default reset-discovery tag name used by
 * `foundation.reset.tag`.
 *
 * `kernel.stateful` is a fixed enforcement marker for CI/static-analysis rails.
 * Runtime reset execution must not depend on this marker.
 */
final class Tags
{
    public const string KERNEL_RESET = 'kernel.reset';

    public const string KERNEL_STATEFUL = 'kernel.stateful';

    private function __construct()
    {
    }
}
