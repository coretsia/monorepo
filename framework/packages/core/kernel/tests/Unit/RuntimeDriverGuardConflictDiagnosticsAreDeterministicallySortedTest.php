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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverGuardConflictDiagnosticsAreDeterministicallySortedTest extends TestCase
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
        $exception = self::detectConflict([
            'kernel.runtime.frankenphp.enabled' => true,
            'kernel.runtime.swoole.enabled' => true,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => false,
            'worker.task_type' => 'queue',
        ]);

        self::assertSame(
            RuntimeDriverConflictException::ERROR_CODE,
            $exception->errorCode(),
        );
        self::assertSame(
            RuntimeDriverConflictException::REASON_MULTIPLE_HTTP_DRIVERS,
            $exception->reason(),
        );

        self::assertOnlyCanonicalDriverIds($exception->activeDriverIds());
        self::assertOnlyCanonicalDriverIds($exception->conflictingDriverIds());
    }

    public function testConflictDiagnosticsForbidShortenedAliases(): void
    {
        $exception = self::detectConflict([
            'kernel.runtime.frankenphp.enabled' => true,
            'kernel.runtime.swoole.enabled' => true,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => false,
            'worker.task_type' => 'queue',
        ]);

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
        $exception = self::detectConflict([
            'kernel.runtime.frankenphp.enabled' => true,
            'kernel.runtime.swoole.enabled' => true,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => false,
            'worker.task_type' => 'queue',
        ]);

        self::assertSame(
            [
                'http.frankenphp',
                'http.roadrunner',
                'http.swoole',
            ],
            $exception->activeDriverIds(),
        );

        self::assertSame(
            [
                'http.frankenphp',
                'http.roadrunner',
                'http.swoole',
            ],
            $exception->conflictingDriverIds(),
        );

        self::assertSortedByStrcmp($exception->activeDriverIds());
        self::assertSortedByStrcmp($exception->conflictingDriverIds());
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function detectConflict(array $values): RuntimeDriverConflictException
    {
        try {
            new RuntimeDriverGuard()->detect(self::config($values));
        } catch (RuntimeDriverConflictException $exception) {
            return $exception;
        }

        self::fail('RuntimeDriverGuard must reject conflicting HTTP runtime drivers.');
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

    /**
     * @param array<string,mixed> $values
     */
    private static function config(array $values): ConfigRepositoryInterface
    {
        return new class($values) implements ConfigRepositoryInterface {
            /**
             * @param array<string,mixed> $values
             */
            public function __construct(
                private readonly array $values,
            ) {
            }

            public function has(string $keyPath): bool
            {
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                if (!\array_key_exists($keyPath, $this->values)) {
                    return $default;
                }

                return $this->values[$keyPath];
            }

            /**
             * @return array<string,mixed>
             */
            public function all(): array
            {
                throw new RuntimeException('runtime-driver-guard-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('runtime-driver-guard-test-config-source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('runtime-driver-guard-test-config-explain-forbidden');
            }
        };
    }
}
