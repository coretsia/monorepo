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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Foundation\Clock\FrozenClock;
use Coretsia\Foundation\Clock\SystemClock;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class FoundationClockAndStopwatchBindingsTest extends TestCase
{
    public function testClockInterfaceAndSystemClockResolveToSameRuntimeClockInstance(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(ClockInterface::class));
        self::assertTrue($container->has(SystemClock::class));

        $clock = $container->get(ClockInterface::class);
        $systemClock = $container->get(SystemClock::class);

        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertInstanceOf(SystemClock::class, $clock);
        self::assertInstanceOf(SystemClock::class, $systemClock);
        self::assertSame($systemClock, $clock);

        self::assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    public function testStopwatchResolvesAsExplicitFoundationRuntimeService(): void
    {
        $container = self::foundationContainer();

        self::assertTrue($container->has(Stopwatch::class));

        $stopwatch = $container->get(Stopwatch::class);

        self::assertInstanceOf(Stopwatch::class, $stopwatch);

        $durationMs = $stopwatch->stop($stopwatch->start());

        self::assertIsInt($durationMs);
        self::assertGreaterThanOrEqual(0, $durationMs);
    }

    public function testFrozenClockIsNotRegisteredAsDefaultRuntimeClockBinding(): void
    {
        $container = self::foundationContainer();

        self::assertFalse($container->has(FrozenClock::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
    }

    private static function foundationContainer(): Container
    {
        $builder = new ContainerBuilder([
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => 'ulid',
                ],
                'reset' => [
                    'tag' => 'kernel.reset',
                ],
            ],
        ]);

        $builder->register(new FoundationServiceProvider());

        return $builder->build();
    }
}
