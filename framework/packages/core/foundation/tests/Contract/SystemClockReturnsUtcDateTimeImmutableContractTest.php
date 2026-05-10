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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Clock\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class SystemClockReturnsUtcDateTimeImmutableContractTest extends TestCase
{
    public function testSystemClockImplementsPsrClockInterface(): void
    {
        $clock = new SystemClock();

        self::assertInstanceOf(ClockInterface::class, $clock);
    }

    public function testNowReturnsDateTimeImmutableInUtc(): void
    {
        $clock = new SystemClock();

        $now = $clock->now();

        self::assertInstanceOf(DateTimeImmutable::class, $now);
        self::assertSame('UTC', $now->getTimezone()->getName());
    }
}
