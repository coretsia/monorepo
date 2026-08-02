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
use Coretsia\Platform\Worker\Process\Driver\ProcWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostProtocol;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;

final class ProcWorkerProcessDriverTest extends PackageTestCase
{
    public function testProcessHostAdapterSpawnsReadyChildWithoutSupervisorResourceInheritance(): void
    {
        $root = $this->temporaryDirectory('proc-driver');
        $protocol = new WorkerProcProcessHostProtocol(new StableJsonEncoder(), new StableJsonDecoder());
        $hostRoot = \is_file(self::frameworkRoot() . '/vendor/autoload.php')
            ? self::frameworkRoot()
            : self::packageRoot();

        $host = new WorkerProcProcessHostClient(
            command: [\PHP_BINARY, self::packageRoot() . '/bin/coretsia-worker-proc-host'],
            workingDirectory: $hostRoot,
            protocol: $protocol,
        );
        $readiness = new WorkerChildReadinessChannel();
        $driver = new ProcWorkerProcessDriver(
            skeletonRoot: $root,
            workerCommand: [\PHP_BINARY, self::packageRoot() . '/tests/Fixtures/proc-worker-fixture.php'],
            artifactRoot: 'var/cache/coretsia',
            readinessChannel: $readiness,
            processHost: $host,
        );
        $spec = WorkerSpecFactory::create([
            'workers' => 1,
            'driver' => 'proc',
            'start_timeout_ms' => 2000,
        ]);

        $driver->prepare($spec);
        $child = $driver->spawn($spec, 0);
        $readiness->await($child, 1000);

        $exit = null;
        self::waitUntil(function () use ($driver, $child, &$exit): bool {
            $exit = $driver->pollExit($child);
            return $exit !== null;
        });

        self::assertNotNull($exit);
        self::assertTrue($exit->expected());
        $driver->close($child);
        $driver->shutdown();
    }
}
