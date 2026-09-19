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

use Coretsia\Foundation\Clock\FrozenClock;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class FrozenClockReturnsDeterministicNowTest extends TestCase
{
    public function testFrozenClockImplementsPsrClockInterface(): void
    {
        $clock = new FrozenClock(
            new DateTimeImmutable('2026-05-10 12:34:56.123456', new DateTimeZone('Asia/Tokyo')),
        );

        self::assertInstanceOf(ClockInterface::class, $clock);
    }

    public function testFrozenClockReturnsSameLogicalInstantOnRepeatedNowCalls(): void
    {
        $clock = new FrozenClock(
            new DateTimeImmutable('2026-05-10 12:34:56.123456', new DateTimeZone('Asia/Tokyo')),
        );

        $first = $clock->now();
        $second = $clock->now();

        self::assertEquals($first, $second);
        self::assertSame(
            $first->format('Y-m-d H:i:s.u P'),
            $second->format('Y-m-d H:i:s.u P'),
        );
    }

    public function testFrozenClockNormalizesReturnedInstantToUtc(): void
    {
        $clock = new FrozenClock(
            new DateTimeImmutable('2026-05-10 12:34:56.123456', new DateTimeZone('Asia/Tokyo')),
        );

        $now = $clock->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertSame('2026-05-10 03:34:56.123456 +00:00', $now->format('Y-m-d H:i:s.u P'));
    }
}
