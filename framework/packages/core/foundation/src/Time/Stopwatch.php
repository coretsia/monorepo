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

namespace Coretsia\Foundation\Time;

use Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException;

/**
 * Canonical Foundation float-free stopwatch.
 *
 * Stopwatch measures elapsed time using monotonic hrtime tokens and returns
 * durations as integer milliseconds.
 *
 * It MUST NOT use microtime(true), DateTime differences, floats, locale-aware
 * formatting, wall-clock timezone data, stdout, stderr, logs, traces, or
 * metrics.
 */
final class Stopwatch
{
    /**
     * Starts a duration measurement.
     *
     * The returned token is an integer nanosecond timestamp from hrtime(true).
     * Consumers MUST treat it as an opaque Stopwatch token and MUST NOT export
     * it to logs, metrics, traces, diagnostics, or artifacts.
     */
    public function start(): int
    {
        return \hrtime(true);
    }

    /**
     * Stops a duration measurement and returns integer milliseconds.
     *
     * The input MUST be a positive token previously returned by start().
     *
     * The returned duration is deterministic-format and non-negative. If the
     * elapsed monotonic delta is negative for any reason, the value is clamped
     * to zero.
     */
    public function stop(int $startedAt): int
    {
        if ($startedAt <= 0) {
            throw new StopwatchInvalidStateException(
                StopwatchInvalidStateException::REASON_START_TOKEN_INVALID,
            );
        }

        $durationNs = \hrtime(true) - $startedAt;

        if ($durationNs <= 0) {
            return 0;
        }

        return \intdiv($durationNs, 1_000_000);
    }
}
