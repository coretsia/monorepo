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

namespace Coretsia\Platform\Cli\Output;

/**
 * Immutable terminal capabilities captured once at the CLI entrypoint boundary.
 *
 * Interactivity describes stdout only. ANSI support describes both stdout and
 * stderr, so a redirected diagnostic stream cannot receive automatic color.
 *
 * @internal
 */
final readonly class TerminalCapabilities
{
    public function __construct(
        private bool $interactive,
        private bool $ansiSupported,
    ) {
    }

    public function interactive(): bool
    {
        return $this->interactive;
    }

    public function ansiSupported(): bool
    {
        return $this->ansiSupported;
    }
}
