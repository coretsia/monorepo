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

namespace Coretsia\Platform\Cli\Provider;

/**
 * Minimal placeholder service provider for `platform/cli`.
 *
 * Phase 0 note:
 * - This package is kernel-free; provider is a wiring hook for future DI integration.
 * - No mutable state is allowed here (no caches/buffers).
 *
 * @internal
 */
final class CliServiceProvider
{
    public function id(): string
    {
        return 'platform.cli';
    }

    /**
     * Optional: wiring map for a future container/DI layer.
     *
     * Phase 0: intentionally empty.
     *
     * @return array<string, callable>
     */
    public static function factories(): array
    {
        return [];
    }
}
