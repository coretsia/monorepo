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

use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Worker\Runtime\WorkerStopSignal;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;
use Coretsia\Platform\Worker\Tests\Support\RecordingKernelRuntime;
use Coretsia\Platform\Worker\Tests\Support\RecordingMeter;
use Coretsia\Platform\Worker\Tests\Support\RecordingTaskFactory;
use Coretsia\Platform\Worker\Tests\Support\RecordingTracer;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;
use Coretsia\Platform\Worker\Worker\ApplicationWorker;

final class ApplicationWorkerMaxRequestsTest extends PackageTestCase
{
    public function testLoopStopsExactlyAtMaxRequests(): void
    {
        $root = $this->temporaryDirectory('worker-max-requests');
        $kernel = new RecordingKernelRuntime();
        $tasks = new RecordingTaskFactory();

        $worker = new ApplicationWorker(
            stopSignal: new WorkerStopSignal($root),
            kernelRuntime: $kernel,
            taskFactory: $tasks,
            stopwatch: new Stopwatch(),
            tracer: new RecordingTracer(),
            meter: new RecordingMeter(),
        );

        $processed = $worker->run(WorkerSpecFactory::create([
            'max_requests' => 3,
        ]));

        self::assertSame(3, $processed);
        self::assertSame(3, $kernel->calls);
        self::assertSame(3, $tasks->createCalls);
    }

    public function testStopFlagIsObservedOnlyBetweenTasks(): void
    {
        $root = $this->temporaryDirectory('worker-stop-between-tasks');
        $spec = WorkerSpecFactory::create([
            'max_requests' => 3,
        ]);
        $stopSignal = new WorkerStopSignal($root);
        $kernel = new class($stopSignal, $spec) extends RecordingKernelRuntime {
            public function __construct(
                private readonly WorkerStopSignal $stopSignal,
                private readonly \Coretsia\Platform\Worker\Runtime\WorkerPoolSpec $spec,
            ) {
            }

            public function runUnitOfWork(
                string $type,
                callable $body,
                array $attributes = [],
            ): mixed {
                $result = parent::runUnitOfWork($type, $body, $attributes);
                $this->stopSignal->request($this->spec);

                return $result;
            }
        };

        $worker = new ApplicationWorker(
            stopSignal: $stopSignal,
            kernelRuntime: $kernel,
            taskFactory: new RecordingTaskFactory(),
            stopwatch: new Stopwatch(),
            tracer: new RecordingTracer(),
            meter: new RecordingMeter(),
        );

        self::assertSame(1, $worker->run($spec));
        self::assertSame(1, $kernel->calls);
    }
}
