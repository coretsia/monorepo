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
use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverGuardAllowsRoadrunnerPlusWorkerQueueTest extends TestCase
{
    public function testAllowsRoadrunnerHttpPlusWorkerQueueBackgroundDriver(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => true,
            'worker.task_type' => 'queue',
        ]);

        $drivers = new RuntimeDriverGuard()->detect($cfg);

        self::assertSame(HttpDriver::ROADRUNNER, $drivers->httpDriver());
        self::assertSame('http.roadrunner', $drivers->httpDriverId());
        self::assertSame([BackgroundDriver::WORKER_QUEUE], $drivers->backgroundDrivers());
        self::assertSame(['bg.worker_queue'], $drivers->backgroundDriverIds());
        self::assertSame(
            [
                'bg.worker_queue',
                'http.roadrunner',
            ],
            $drivers->driverIds(),
        );
    }

    public function testAssertCompatibleAllowsRoadrunnerHttpPlusWorkerQueue(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => true,
            'worker.task_type' => 'queue',
        ]);

        new RuntimeDriverGuard()->assertCompatible($cfg);

        self::assertTrue(true);
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
