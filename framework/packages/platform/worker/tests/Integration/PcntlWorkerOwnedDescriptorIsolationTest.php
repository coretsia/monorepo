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
use Coretsia\Platform\Worker\Communication\WorkerControlCredential;
use Coretsia\Platform\Worker\Communication\WorkerControlProtocol;
use Coretsia\Platform\Worker\Communication\WorkerControlServer;
use Coretsia\Platform\Worker\Communication\WorkerControlTransport;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Process\Driver\PcntlWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Supervisor\WorkerChildTable;
use Coretsia\Platform\Worker\Supervisor\WorkerSignalController;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;

final class PcntlWorkerOwnedDescriptorIsolationTest extends PackageTestCase
{
    public function testCurrentReadinessListenerDoesNotCrossExecBoundary(): void
    {
        $root = $this->temporaryDirectory('pcntl-current-readiness-descriptor');
        $readiness = new WorkerChildReadinessChannel();
        $table = new WorkerChildTable();
        $server = self::controlServer($root);
        [$driver, $readyFile, $releaseFile] = self::driver(
            root: $root,
            readiness: $readiness,
            table: $table,
            server: $server,
        );
        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'pcntl',
        ]);

        if (!self::assertSupportedOrDeterministicallyRejected($driver, $spec)) {
            return;
        }

        $child = $driver->spawn($spec, 0);
        $port = $child->readinessEndpoint()->port();

        try {
            $readiness->await($child, 2000);
            self::waitUntil(
                static fn (): bool => @\file_exists($readyFile),
                failureMessage: 'The descriptor-isolation child did not enter its hold phase.',
            );

            self::assertTrue(self::processExists($child->pid()));
            self::assertTcpPortBindable($port);
        } finally {
            self::releaseAndReap(
                driver: $driver,
                child: $child,
                releaseFile: $releaseFile,
            );
        }
    }

    public function testSiblingReadinessListenersDoNotCrossExecBoundary(): void
    {
        $root = $this->temporaryDirectory('pcntl-sibling-readiness-descriptor');
        $readiness = new WorkerChildReadinessChannel();
        $table = new WorkerChildTable();
        $server = self::controlServer($root);
        [$driver, $readyFile, $releaseFile] = self::driver(
            root: $root,
            readiness: $readiness,
            table: $table,
            server: $server,
        );
        $spec = WorkerSpecFactory::create([
            'workers' => 2,
            'driver' => 'pcntl',
        ]);

        if (!self::assertSupportedOrDeterministicallyRejected($driver, $spec)) {
            return;
        }

        $siblingEndpoint = $readiness->createProcessEndpoint();
        $siblingPort = $siblingEndpoint->port();
        $table->add(
            new WorkerChildProcess(
                workerIndex: 1,
                pid: (int)\getmypid(),
                driverName: 'pcntl',
                processHandle: null,
                readinessEndpoint: $siblingEndpoint,
                generation: 1,
                startedAtNs: \hrtime(true),
            ),
        );

        $child = $driver->spawn($spec, 0);

        try {
            $readiness->await($child, 2000);
            self::waitUntil(
                static fn (): bool => @\file_exists($readyFile),
                failureMessage: 'The sibling-descriptor child did not enter its hold phase.',
            );

            $siblingEndpoint->close();
            self::assertTcpPortBindable($siblingPort);
        } finally {
            $siblingEndpoint->close();
            $table->clear();
            self::releaseAndReap(
                driver: $driver,
                child: $child,
                releaseFile: $releaseFile,
            );
        }
    }

    public function testControlListenerDoesNotCrossExecBoundary(): void
    {
        $root = $this->temporaryDirectory('pcntl-control-descriptor');
        $controlPort = self::unusedTcpPort();
        $readiness = new WorkerChildReadinessChannel();
        $table = new WorkerChildTable();
        $server = self::controlServer($root);
        [$driver, $readyFile, $releaseFile] = self::driver(
            root: $root,
            readiness: $readiness,
            table: $table,
            server: $server,
        );
        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'pcntl',
            'control' => [
                'transport' => 'tcp',
            ],
            'tcp' => [
                'host' => '127.0.0.1',
                'port' => $controlPort,
            ],
        ]);

        if (!self::assertSupportedOrDeterministicallyRejected($driver, $spec)) {
            return;
        }

        $server->listen(
            $spec,
            WorkerControlCredential::fromEncoded(
                \str_repeat('a', 64),
            ),
        );
        $child = $driver->spawn($spec, 0);

        try {
            $readiness->await($child, 2000);
            self::waitUntil(
                static fn (): bool => @\file_exists($readyFile),
                failureMessage: 'The control-descriptor child did not enter its hold phase.',
            );

            $server->close();
            self::assertTcpPortBindable($controlPort);
        } finally {
            $server->close();
            self::releaseAndReap(
                driver: $driver,
                child: $child,
                releaseFile: $releaseFile,
            );
        }
    }

    /**
     * @return array{0: PcntlWorkerProcessDriver, 1: string, 2: string}
     */
    private static function driver(
        string $root,
        WorkerChildReadinessChannel $readiness,
        WorkerChildTable $table,
        WorkerControlServer $server,
    ): array {
        $readyFile = $root . '/exec-ready';
        $releaseFile = $root . '/exec-release';

        return [
            new PcntlWorkerProcessDriver(
                skeletonRoot: $root,
                workerCommand: [
                    \PHP_BINARY,
                    self::packageRoot() . '/tests/Fixtures/exec-hold-fixture.php',
                    '--ready-file=' . $readyFile,
                    '--release-file=' . $releaseFile,
                    '--timeout-ms=5000',
                ],
                commandBuilder: new WorkerChildCommandBuilder('var/cache/coretsia'),
                readinessChannel: $readiness,
                forkIsolation: new WorkerForkIsolation(
                    new WorkerLifecycleLock($root),
                    $server,
                    new WorkerSignalController(),
                    $table,
                ),
                pcntlAvailable: self::pcntlAvailable(),
                platformFamily: \PHP_OS_FAMILY,
            ),
            $readyFile,
            $releaseFile,
        ];
    }

    private static function controlServer(string $root): WorkerControlServer
    {
        return new WorkerControlServer(
            new WorkerControlTransport($root),
            new WorkerControlProtocol(
                new StableJsonEncoder(),
                new StableJsonDecoder(),
            ),
        );
    }

    private static function assertSupportedOrDeterministicallyRejected(
        PcntlWorkerProcessDriver $driver,
        WorkerPoolSpec $spec,
    ): bool {
        if ($driver->supports($spec)) {
            $driver->prepare($spec);

            return true;
        }

        try {
            $driver->prepare($spec);
            self::fail('Unsupported PCNTL execution must fail before fork.');
        } catch (WorkerStartFailedException $exception) {
            self::assertSame(
                WorkerStartFailedException::REASON_CHILD_START_FAILED,
                $exception->reason(),
            );
        }

        return false;
    }

    private static function assertTcpPortBindable(int $port): void
    {
        $server = @\stream_socket_server(
            'tcp://127.0.0.1:' . $port,
            $errorCode,
            $errorMessage,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
        );

        self::assertIsResource(
            $server,
            'The Worker-owned TCP listener crossed the exec boundary.',
        );

        @\fclose($server);
    }

    private static function releaseAndReap(
        PcntlWorkerProcessDriver $driver,
        WorkerChildProcess $child,
        string $releaseFile,
    ): void {
        @\file_put_contents($releaseFile, "release\n", \LOCK_EX);

        $exit = null;

        try {
            self::waitUntil(
                static function () use ($driver, $child, &$exit): bool {
                    $exit = $driver->pollExit($child, 1_000);

                    return $exit !== null;
                },
                failureMessage: 'The descriptor-isolation child was not reaped.',
            );

            self::assertNotNull($exit);
            self::assertTrue($exit->expected());
            self::assertSame(0, $exit->exitCode());
        } finally {
            if (self::processExists($child->pid())) {
                $driver->kill($child, 1_000);

                try {
                    self::waitUntil(
                        static fn (): bool => $driver->pollExit($child, 1_000) !== null,
                        timeoutMs: 2000,
                        failureMessage: 'The descriptor-isolation child survived forced cleanup.',
                    );
                } catch (\Throwable) {
                    // Preserve the original test failure while completing cleanup.
                }
            }

            $driver->close($child, 1_000);
            $driver->shutdown(1_000);
        }
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
