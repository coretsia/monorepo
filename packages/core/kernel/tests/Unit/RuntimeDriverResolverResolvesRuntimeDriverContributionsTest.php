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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverResolverResolvesRuntimeDriverContributionsTest extends TestCase
{
    public function testResolveKeepsClassicHttpWithWorkerQueueContribution(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.classic',
                ],
            ],
        ]);

        $contributions = RuntimeDriverContributions::fromDrivers(
            httpDrivers: [],
            backgroundDrivers: [BackgroundDriver::WORKER_QUEUE],
        );

        $drivers = new RuntimeDriverResolver()->resolve($cfg, $contributions);

        self::assertSame(HttpDriver::CLASSIC, $drivers->httpDriver());
        self::assertSame('http.classic', $drivers->httpDriverId());

        self::assertSame([BackgroundDriver::WORKER_QUEUE], $drivers->backgroundDrivers());
        self::assertSame(['bg.worker_queue'], $drivers->backgroundDriverIds());
        self::assertSame(['bg.worker_queue', 'http.classic'], $drivers->driverIds());
    }

    public function testResolveUsesWorkerHttpContributionWithoutModulePlanOrPlatformHttp(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.classic',
                ],
            ],
        ]);

        $contributions = RuntimeDriverContributions::fromDrivers(
            httpDrivers: [HttpDriver::WORKER],
            backgroundDrivers: [],
        );

        $drivers = new RuntimeDriverResolver()->resolve($cfg, $contributions);

        self::assertSame(HttpDriver::WORKER, $drivers->httpDriver());
        self::assertSame('http.worker', $drivers->httpDriverId());

        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame([], $drivers->backgroundDriverIds());
        self::assertSame(['http.worker'], $drivers->driverIds());
    }

    public function testResolveRejectsWorkerHttpContributionWithNonClassicHttpDriver(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.roadrunner',
                ],
            ],
        ]);

        $contributions = RuntimeDriverContributions::fromDrivers(
            httpDrivers: [HttpDriver::WORKER],
            backgroundDrivers: [],
        );

        $this->expectException(RuntimeDriverConflictException::class);

        new RuntimeDriverResolver()->resolve($cfg, $contributions);
    }

    /**
     * @param non-empty-string $configuredHttpDriver
     * @param HttpDriver $expectedHttpDriver
     * @param non-empty-string $expectedHttpDriverId
     * @param list<non-empty-string> $expectedDriverIds
     */
    #[DataProvider('nonClassicHttpPlusWorkerQueueProvider')]
    public function testResolveKeepsConfiguredHttpDriverWithWorkerQueueContribution(
        string $configuredHttpDriver,
        HttpDriver $expectedHttpDriver,
        string $expectedHttpDriverId,
        array $expectedDriverIds,
    ): void {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => $configuredHttpDriver,
                ],
            ],
        ]);

        $contributions = RuntimeDriverContributions::fromDrivers(
            httpDrivers: [],
            backgroundDrivers: [BackgroundDriver::WORKER_QUEUE],
        );

        $drivers = new RuntimeDriverResolver()->resolve($cfg, $contributions);

        self::assertSame($expectedHttpDriver, $drivers->httpDriver());
        self::assertSame($expectedHttpDriverId, $drivers->httpDriverId());
        self::assertSame([BackgroundDriver::WORKER_QUEUE], $drivers->backgroundDrivers());
        self::assertSame(['bg.worker_queue'], $drivers->backgroundDriverIds());
        self::assertSame($expectedDriverIds, $drivers->driverIds());
    }

    /**
     * @return iterable<string,array{
     *     0: non-empty-string,
     *     1: HttpDriver,
     *     2: non-empty-string,
     *     3: list<non-empty-string>
     * }>
     */
    public static function nonClassicHttpPlusWorkerQueueProvider(): iterable
    {
        yield 'frankenphp + worker queue' => [
            'http.frankenphp',
            HttpDriver::FRANKENPHP,
            'http.frankenphp',
            ['bg.worker_queue', 'http.frankenphp'],
        ];

        yield 'roadrunner + worker queue' => [
            'http.roadrunner',
            HttpDriver::ROADRUNNER,
            'http.roadrunner',
            ['bg.worker_queue', 'http.roadrunner'],
        ];

        yield 'swoole + worker queue' => [
            'http.swoole',
            HttpDriver::SWOOLE,
            'http.swoole',
            ['bg.worker_queue', 'http.swoole'],
        ];
    }
}
