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

namespace Coretsia\Platform\Worker\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WorkerRuntimeDriverContributionsTest extends TestCase
{
    public function testMapsQueueTaskTypeToWorkerQueueBackgroundDriver(): void
    {
        $spec = self::workerPoolSpec('queue');

        $contributions = WorkerRuntimeDriverContributions::fromSpec($spec);

        self::assertSame([], $contributions->httpDrivers());
        self::assertSame([BackgroundDriver::WORKER_QUEUE], $contributions->backgroundDrivers());
        self::assertSame(['bg.worker_queue'], $contributions->driverIds());
    }

    public function testMapsHttpTaskTypeToWorkerHttpDriver(): void
    {
        $spec = self::workerPoolSpec('http');

        $contributions = WorkerRuntimeDriverContributions::fromSpec($spec);

        self::assertSame([HttpDriver::WORKER], $contributions->httpDrivers());
        self::assertSame([], $contributions->backgroundDrivers());
        self::assertSame(['http.worker'], $contributions->driverIds());
    }

    public function testRejectsUnknownWorkerTaskType(): void
    {
        $config = self::config([
            'worker.task_type' => 'unknown',
        ]);

        $this->expectException(WorkerStartFailedException::class);

        WorkerRuntimeDriverContributions::fromConfig($config);
    }

    private static function workerPoolSpec(string $taskType): WorkerPoolSpec
    {
        return WorkerPoolSpec::fromConfig(
            config: [
                'workers' => 1,
                'max_requests' => 100,
                'task_type' => $taskType,
                'socket_path' => 'var/run/coretsia-worker.sock',
                'driver' => 'proc',
                'control' => [
                    'transport' => 'tcp',
                ],
                'tcp' => [
                    'host' => '127.0.0.1',
                    'port' => 9501,
                ],
                'state_path' => 'var/run/coretsia-worker.state',
                'stop_flag_path' => 'var/run/coretsia-worker.stop',
                'stop_timeout_ms' => 1000,
            ],
            pcntlForkAvailable: false,
            platformFamily: 'Linux',
            unixDomainSocketsSupported: false,
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
                throw new RuntimeException('worker-runtime-driver-contributions-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('worker-runtime-driver-contributions-test-config-source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('worker-runtime-driver-contributions-test-config-explain-forbidden');
            }
        };
    }
}
