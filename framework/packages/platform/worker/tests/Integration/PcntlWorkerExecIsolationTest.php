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
use Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Supervisor\WorkerChildTable;
use Coretsia\Platform\Worker\Supervisor\WorkerSignalController;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;

final class PcntlWorkerExecIsolationTest extends PackageTestCase
{
    public function testForkedChildExecutesFreshPhpProcessImageWhenSupported(): void
    {
        $root = $this->temporaryDirectory('pcntl-exec-isolation');
        $readiness = new WorkerChildReadinessChannel();
        $driver = self::driver($root, $readiness);
        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'pcntl',
        ]);
        $markerPath = $root . '/var/cache/coretsia/pcntl-exec-marker.json';

        if (!$driver->supports($spec)) {
            try {
                $driver->prepare($spec);
                self::fail('Unsupported PCNTL execution must fail before fork.');
            } catch (WorkerStartFailedException $exception) {
                self::assertSame(
                    WorkerStartFailedException::REASON_CHILD_START_FAILED,
                    $exception->reason(),
                );
            }

            self::assertFileDoesNotExist($markerPath);

            return;
        }

        $driver->prepare($spec);
        $child = $driver->spawn($spec, 0);
        $readiness->await($child, 2000);

        $exit = null;
        self::waitUntil(
            function () use ($driver, $child, &$exit): bool {
                $exit = $driver->pollExit($child, 1_000);

                return $exit !== null;
            },
            failureMessage: 'PCNTL exec-isolation child was not reaped.',
        );

        self::assertNotNull($exit);
        self::assertTrue($exit->expected());
        self::assertSame(0, $exit->exitCode());
        self::assertFileExists($markerPath);

        $marker = \json_decode(
            (string)\file_get_contents($markerPath),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($marker);
        self::assertTrue($marker['fresh_process_image'] ?? false);
        self::assertSame($child->pid(), $marker['pid'] ?? null);

        $driver->close($child, 1_000);
        $driver->shutdown(1_000);
    }

    private static function driver(
        string $root,
        WorkerChildReadinessChannel $readiness,
    ): PcntlWorkerProcessDriver {
        return new PcntlWorkerProcessDriver(
            skeletonRoot: $root,
            workerCommand: [
                \PHP_BINARY,
                self::packageRoot() . '/tests/Fixtures/pcntl-exec-worker-fixture.php',
            ],
            commandBuilder: new WorkerChildCommandBuilder('var/cache/coretsia'),
            readinessChannel: $readiness,
            forkIsolation: new WorkerForkIsolation(
                new WorkerLifecycleLock($root),
                new WorkerControlServer(
                    new WorkerControlTransport($root),
                    new WorkerControlProtocol(
                        new StableJsonEncoder(),
                        new StableJsonDecoder(),
                    ),
                ),
                new WorkerSignalController(),
                new WorkerChildTable(),
            ),
            pcntlAvailable: self::pcntlAvailable(),
            platformFamily: \PHP_OS_FAMILY,
        );
    }

    private static function pcntlAvailable(): bool
    {
        foreach (
            [
                'pcntl_fork',
                'pcntl_exec',
                'pcntl_waitpid',
                'pcntl_wifexited',
                'pcntl_wexitstatus',
                'pcntl_wifsignaled',
                'pcntl_wtermsig',
                'posix_kill',
            ] as $function
        ) {
            if (!\function_exists($function)) {
                return false;
            }
        }

        return true;
    }
}
