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
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverGuardRejectsWorkerHttpWithAnyConfiguredHttpDriverTest extends TestCase
{
    /**
     * @param non-empty-string $configuredHttpDriver
     * @param list<non-empty-string> $expectedDriverIds
     */
    #[DataProvider('workerHttpConflictProvider')]
    public function testResolveRejectsWorkerHttpWithAnyNonClassicConfiguredHttpDriver(
        string $configuredHttpDriver,
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
            httpDrivers: [HttpDriver::WORKER],
            backgroundDrivers: [],
        );

        try {
            new RuntimeDriverGuard()->resolve($cfg, $contributions);
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );
            self::assertSame($expectedDriverIds, $exception->activeDriverIds());
            self::assertSame($expectedDriverIds, $exception->conflictingDriverIds());

            return;
        }

        self::fail('RuntimeDriverGuard::resolve() must reject http.worker with a non-classic configured HTTP runtime driver.');
    }

    /**
     * @return iterable<string, array{0: non-empty-string, 1: list<non-empty-string>}>
     */
    public static function workerHttpConflictProvider(): iterable
    {
        yield 'frankenphp + worker http' => [
            'http.frankenphp',
            [
                'http.frankenphp',
                'http.worker',
            ],
        ];

        yield 'roadrunner + worker http' => [
            'http.roadrunner',
            [
                'http.roadrunner',
                'http.worker',
            ],
        ];

        yield 'swoole + worker http' => [
            'http.swoole',
            [
                'http.swoole',
                'http.worker',
            ],
        ];
    }
}
