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

namespace Coretsia\Kernel\Provider;

/**
 * Kernel-owned canonical DI tag constants.
 *
 * These constants are the canonical public owner constants for reserved Kernel
 * lifecycle hook discovery tags.
 *
 * Runtime code that is allowed to depend on core/kernel and needs these tags
 * should use these constants instead of raw literal tag strings.
 */
final class Tags
{
    public const string KERNEL_HOOK_BEFORE_UOW = 'kernel.hook.before_uow';

    public const string KERNEL_HOOK_AFTER_UOW = 'kernel.hook.after_uow';

    private function __construct()
    {
    }
}
