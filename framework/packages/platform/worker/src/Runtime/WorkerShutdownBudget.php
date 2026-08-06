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

namespace Coretsia\Platform\Worker\Runtime;

/**
 * Canonical wall-clock budgets for one active-supervisor shutdown request.
 *
 * @internal
 */
final class WorkerShutdownBudget
{
    public const int CLEANUP_TIMEOUT_MS = 2_000;

    private const int MAX_TIMEOUT_MS = 86_400_000;

    private function __construct()
    {
    }

    public static function stopRequestTimeoutMs(
        int $stopTimeoutMs,
        int $forceKillTimeoutMs,
    ): int {
        self::assertTimeout($stopTimeoutMs);
        self::assertTimeout($forceKillTimeoutMs);

        return $stopTimeoutMs
            + (2 * $forceKillTimeoutMs)
            + self::CLEANUP_TIMEOUT_MS;
    }

    private static function assertTimeout(int $timeoutMs): void
    {
        if ($timeoutMs < 1 || $timeoutMs > self::MAX_TIMEOUT_MS) {
            throw new \InvalidArgumentException('worker-shutdown-budget-invalid');
        }
    }
}
