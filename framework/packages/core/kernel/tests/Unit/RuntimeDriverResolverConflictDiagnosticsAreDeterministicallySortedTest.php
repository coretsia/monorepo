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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverResolverConflictDiagnosticsAreDeterministicallySortedTest extends TestCase
{
    /**
     * @var array<string, true>
     */
    private const array CANONICAL_DRIVER_IDS = [
        'bg.worker_queue' => true,
        'http.classic' => true,
        'http.frankenphp' => true,
        'http.roadrunner' => true,
        'http.swoole' => true,
        'http.worker' => true,
    ];

    /**
     * @var list<string>
     */
    private const array SHORTENED_ALIASES = [
        'classic',
        'frankenphp',
        'roadrunner',
        'swoole',
        'worker',
        'worker_queue',
        'queue',
    ];

    public function testConflictDiagnosticsUseOnlyCanonicalDriverIds(): void
    {
        $exception = self::resolveConflict();

        self::assertSame(
            RuntimeDriverConflictException::ERROR_CODE,
            $exception->errorCode(),
        );
        self::assertSame(
            RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
            $exception->reason(),
        );

        self::assertOnlyCanonicalDriverIds($exception->activeDriverIds());
        self::assertOnlyCanonicalDriverIds($exception->conflictingDriverIds());
    }

    public function testConflictDiagnosticsForbidShortenedAliases(): void
    {
        $exception = self::resolveConflict();

        foreach (self::SHORTENED_ALIASES as $alias) {
            self::assertNotContains(
                $alias,
                $exception->activeDriverIds(),
                'Runtime driver diagnostics must not expose shortened aliases as active driver ids.',
            );

            self::assertNotContains(
                $alias,
                $exception->conflictingDriverIds(),
                'Runtime driver diagnostics must not expose shortened aliases as conflicting driver ids.',
            );
        }
    }

    public function testConflictDiagnosticsAreSortedByCanonicalIdUsingByteOrderStrcmp(): void
    {
        $exception = self::resolveConflict();

        self::assertSame(
            [
                'http.roadrunner',
                'http.worker',
            ],
            $exception->activeDriverIds(),
        );

        self::assertSame(
            [
                'http.roadrunner',
                'http.worker',
            ],
            $exception->conflictingDriverIds(),
        );

        self::assertSortedByStrcmp($exception->activeDriverIds());
        self::assertSortedByStrcmp($exception->conflictingDriverIds());
    }

    /**
     * @return RuntimeDriverConflictException
     */
    private static function resolveConflict(): RuntimeDriverConflictException
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

        try {
            new RuntimeDriverResolver()->resolve($cfg, $contributions);
        } catch (RuntimeDriverConflictException $exception) {
            return $exception;
        }

        self::fail('RuntimeDriverResolver must reject conflicting HTTP runtime drivers.');
    }

    /**
     * @param list<string> $driverIds
     */
    private static function assertOnlyCanonicalDriverIds(array $driverIds): void
    {
        self::assertNotSame([], $driverIds);

        foreach ($driverIds as $driverId) {
            self::assertArrayHasKey(
                $driverId,
                self::CANONICAL_DRIVER_IDS,
                \sprintf('Runtime driver diagnostics must use canonical driver id "%s".', $driverId),
            );
        }
    }

    /**
     * @param list<string> $values
     */
    private static function assertSortedByStrcmp(array $values): void
    {
        $sorted = $values;

        \usort(
            $sorted,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertSame(
            $sorted,
            $values,
            'Runtime driver diagnostics must be sorted by canonical id using byte-order strcmp.',
        );
    }
}
