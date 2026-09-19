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
 * This provider is a lightweight package wiring surface.
 * No mutable state is allowed here (no caches/buffers).
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
     * Optional wiring map.
     *
     * Intentionally empty until package wiring requires explicit factories.
     *
     * @return array<string, callable>
     */
    public static function factories(): array
    {
        return [];
    }
}
