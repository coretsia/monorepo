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

namespace Coretsia\Platform\Worker\Tests\Integration;

use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel;
use Coretsia\Platform\Worker\Communication\WorkerControlProtocol;
use Coretsia\Platform\Worker\Communication\WorkerControlServer;
use Coretsia\Platform\Worker\Communication\WorkerControlTransport;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Process\Driver\PcntlWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Supervisor\WorkerChildTable;
use Coretsia\Platform\Worker\Supervisor\WorkerSignalController;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;

final class PcntlWorkerProcessDriverTest extends PackageTestCase
{
    public function testCapabilityAndProcessBehaviorMatchCurrentPlatform(): void
    {
        $available = self::pcntlAvailable();
        $root = $this->temporaryDirectory('pcntl-driver');

        $lock = new WorkerLifecycleLock($root);

        $server = new WorkerControlServer(
            new WorkerControlTransport($root),
            new WorkerControlProtocol(
                new StableJsonEncoder(),
                new StableJsonDecoder(),
            ),
        );

        $table = new WorkerChildTable();
        $signals = new WorkerSignalController();

        $isolation = new WorkerForkIsolation(
            $lock,
            $server,
            $signals,
            $table,
        );

        $driver = new PcntlWorkerProcessDriver(
            childBootstrap: static fn (): \Closure => static fn (): int => 0,
            forkIsolation: $isolation,
            pcntlAvailable: $available,
            platformFamily: \PHP_OS_FAMILY,
        );

        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'pcntl',
        ]);

        if (!$driver->supports($spec)) {
            self::assertFalse(
                $driver->supports($spec),
            );

            $this->expectException(
                WorkerStartFailedException::class,
            );

            $driver->prepare($spec);

            return;
        }

        self::assertTrue(
            $driver->supports($spec),
        );

        $driver->prepare($spec);
        $child = $driver->spawn($spec, 0);

        new WorkerChildReadinessChannel()->await(
            $child,
            1000,
        );

        $exit = null;

        self::waitUntil(
            function () use (
                $driver,
                $child,
                &$exit,
            ): bool {
                $exit = $driver->pollExit($child);

                return $exit !== null;
            },
        );

        self::assertNotNull($exit);
        self::assertTrue($exit->expected());
        self::assertSame(0, $exit->exitCode());

        $driver->close($child);
        $driver->shutdown();
    }

    public function testBootstrapFailureBoundaryMatchesCurrentPlatformCapabilities(): void
    {
        $available = self::pcntlAvailable();
        $root = $this->temporaryDirectory(
            'pcntl-driver-bootstrap-failure',
        );
        $bootstrapMarker = $root . '/bootstrap-invoked';

        $lock = new WorkerLifecycleLock($root);

        $server = new WorkerControlServer(
            new WorkerControlTransport($root),
            new WorkerControlProtocol(
                new StableJsonEncoder(),
                new StableJsonDecoder(),
            ),
        );

        $table = new WorkerChildTable();
        $signals = new WorkerSignalController();

        $isolation = new WorkerForkIsolation(
            $lock,
            $server,
            $signals,
            $table,
        );

        $driver = new PcntlWorkerProcessDriver(
            childBootstrap: static function (
                WorkerPoolSpec $_spec,
                int $_workerIndex,
            ) use ($bootstrapMarker): \Closure {
                \file_put_contents(
                    $bootstrapMarker,
                    "invoked\n",
                );

                throw WorkerStartFailedException::childStartFailed();
            },
            forkIsolation: $isolation,
            pcntlAvailable: $available,
            platformFamily: \PHP_OS_FAMILY,
        );

        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'pcntl',
        ]);

        if (!$driver->supports($spec)) {
            try {
                $driver->prepare($spec);

                self::fail(
                    'Unsupported PCNTL execution must fail before bootstrap.',
                );
            } catch (WorkerStartFailedException $exception) {
                self::assertSame(
                    WorkerStartFailedException::REASON_CHILD_START_FAILED,
                    $exception->reason(),
                );
            }

            self::assertFileDoesNotExist($bootstrapMarker);

            return;
        }

        $driver->prepare($spec);
        $child = $driver->spawn($spec, 0);

        $readinessFailure = null;

        try {
            new WorkerChildReadinessChannel()->await(
                $child,
                1000,
            );
        } catch (WorkerStartFailedException $exception) {
            $readinessFailure = $exception;
        }

        self::assertNotNull(
            $readinessFailure,
            'A bootstrap failure before signalReady() must not produce a readiness frame.',
        );

        self::assertSame(
            WorkerStartFailedException::REASON_READINESS_INVALID,
            $readinessFailure->reason(),
        );

        self::assertFileExists($bootstrapMarker);

        $exit = null;

        self::waitUntil(
            function () use (
                $driver,
                $child,
                &$exit,
            ): bool {
                $exit = $driver->pollExit($child);

                return $exit !== null;
            },
            failureMessage: 'PCNTL child was not reaped after bootstrap failure.',
        );

        self::assertNotNull($exit);
        self::assertSame($child->pid(), $exit->pid());
        self::assertSame(1, $exit->exitCode());
        self::assertFalse($exit->signaled());
        self::assertSame(0, $exit->terminatingSignal());
        self::assertFalse($exit->expected());

        $driver->close($child);
        $driver->shutdown();
    }

    private static function pcntlAvailable(): bool
    {
        foreach (
            [
                'pcntl_fork',
                'pcntl_waitpid',
                'posix_kill',
                'stream_socket_pair',
            ] as $function
        ) {
            if (!\function_exists($function)) {
                return false;
            }
        }

        return true;
    }
}
