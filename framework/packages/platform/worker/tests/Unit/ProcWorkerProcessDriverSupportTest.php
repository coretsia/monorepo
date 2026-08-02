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

use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel;
use Coretsia\Platform\Worker\Process\Driver\ProcWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostProtocol;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;

final class ProcWorkerProcessDriverSupportTest extends PackageTestCase
{
    public function testSupportIsNarrowedToResolvedProcSpecAndProcOpenCapability(): void
    {
        $root = $this->temporaryDirectory('worker-proc-support');
        $driver = self::driver($root);

        self::assertSame('proc', $driver->name());
        self::assertSame(
            \function_exists('proc_open'),
            $driver->supports(WorkerSpecFactory::create([
                'driver' => 'proc',
            ])),
        );
        self::assertFalse($driver->supports(
            WorkerSpecFactory::create([
                'driver' => 'pcntl',
            ]),
        ));
    }

    public function testConstructorRejectsShellStringsAndUnsafeArtifactPaths(): void
    {
        $root = $this->temporaryDirectory('worker-proc-invalid');
        $protocol = new WorkerProcProcessHostProtocol(
            new StableJsonEncoder(),
            new StableJsonDecoder(),
        );
        $host = new WorkerProcProcessHostClient(
            command: [\PHP_BINARY, self::packageRoot() . '/bin/coretsia-worker-proc-host'],
            workingDirectory: $root,
            protocol: $protocol,
        );

        $this->expectException(\InvalidArgumentException::class);

        new ProcWorkerProcessDriver(
            skeletonRoot: $root,
            workerCommand: ['php child.php &'],
            artifactRoot: '../private',
            readinessChannel: new WorkerChildReadinessChannel(),
            processHost: $host,
        );
    }

    private static function driver(string $root): ProcWorkerProcessDriver
    {
        $protocol = new WorkerProcProcessHostProtocol(
            new StableJsonEncoder(),
            new StableJsonDecoder(),
        );

        return new ProcWorkerProcessDriver(
            skeletonRoot: $root,
            workerCommand: [
                \PHP_BINARY,
                self::packageRoot() . '/tests/Fixtures/proc-worker-fixture.php',
            ],
            artifactRoot: 'var/cache/worker',
            readinessChannel: new WorkerChildReadinessChannel(),
            processHost: new WorkerProcProcessHostClient(
                command: [
                    \PHP_BINARY,
                    self::packageRoot() . '/bin/coretsia-worker-proc-host',
                ],
                workingDirectory: $root,
                protocol: $protocol,
            ),
        );
    }
}
