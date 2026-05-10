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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;

final class StopwatchDurationIsNonNegativeTest extends TestCase
{
    public function testStartReturnsIntegerNanosecondToken(): void
    {
        $stopwatch = new Stopwatch();

        $startedAt = $stopwatch->start();

        self::assertIsInt($startedAt);
        self::assertGreaterThan(0, $startedAt);
    }

    public function testStopReturnsIntegerMillisecondsGreaterThanOrEqualToZero(): void
    {
        $stopwatch = new Stopwatch();

        $startedAt = $stopwatch->start();
        $durationMs = $stopwatch->stop($startedAt);

        self::assertIsInt($durationMs);
        self::assertGreaterThanOrEqual(0, $durationMs);
    }

    public function testStopReturnsZeroForPositiveFutureToken(): void
    {
        $stopwatch = new Stopwatch();

        $durationMs = $stopwatch->stop(\PHP_INT_MAX);

        self::assertSame(0, $durationMs);
    }

    public function testStopRejectsZeroTokenWithSafeDeterministicMessage(): void
    {
        $stopwatch = new Stopwatch();

        try {
            $stopwatch->stop(0);
            self::fail('Expected StopwatchInvalidStateException.');
        } catch (StopwatchInvalidStateException $exception) {
            self::assertSame(
                StopwatchInvalidStateException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame(
                'stopwatch-start-token-invalid',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString('0', $exception->getMessage());
        }
    }

    public function testStopRejectsNegativeTokenWithSafeDeterministicMessage(): void
    {
        $stopwatch = new Stopwatch();

        try {
            $stopwatch->stop(-1);
            self::fail('Expected StopwatchInvalidStateException.');
        } catch (StopwatchInvalidStateException $exception) {
            self::assertSame(
                StopwatchInvalidStateException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame(
                'stopwatch-start-token-invalid',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString('-1', $exception->getMessage());
        }
    }
}
