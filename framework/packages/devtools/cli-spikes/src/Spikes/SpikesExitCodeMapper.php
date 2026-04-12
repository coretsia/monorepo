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

namespace Coretsia\Devtools\CliSpikes\Spikes;

/**
 * Phase 0 exit code policy for tool-spike dispatch (single-choice):
 * - 0 success
 * - 1 any failure
 */
final class SpikesExitCodeMapper
{
    public const int SUCCESS = 0;
    public const int FAILURE = 1;

    private function __construct()
    {
    }

    public static function success(): int
    {
        return self::SUCCESS;
    }

    public static function failure(): int
    {
        return self::FAILURE;
    }

    public static function fromSuccessFlag(bool $ok): int
    {
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
