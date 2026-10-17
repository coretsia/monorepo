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

use Coretsia\Platform\Cli\Exception\CliOutputFormatException;

/**
 * Immutable already-formatted stream payloads, excluding final newlines.
 *
 * @internal
 */
final readonly class FormattedOutput
{
    public function __construct(
        private string $stdoutBytes,
        private string $stderrBytes,
    ) {
        if (
            \str_ends_with($stdoutBytes, "\n")
            || \str_ends_with($stdoutBytes, "\r")
            || \str_ends_with($stderrBytes, "\n")
            || \str_ends_with($stderrBytes, "\r")
        ) {
            throw CliOutputFormatException::failed();
        }
    }

    public function stdoutBytes(): string
    {
        return $this->stdoutBytes;
    }

    public function stderrBytes(): string
    {
        return $this->stderrBytes;
    }
}
