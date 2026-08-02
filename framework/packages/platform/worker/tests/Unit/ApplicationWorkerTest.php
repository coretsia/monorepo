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

final class ApplicationWorkerTest extends PackageTestCase
{
    public function testEachTaskUsesKernelRuntimeAndCanonicalObservability(): void
    {
        $root = $this->temporaryDirectory('worker-application');
        $kernel = new RecordingKernelRuntime();
        $tasks = new RecordingTaskFactory();
        $tasks->results = ['done'];
        $tracer = new RecordingTracer();
        $meter = new RecordingMeter();

        $worker = new ApplicationWorker(
            stopSignal: new WorkerStopSignal($root),
            kernelRuntime: $kernel,
            taskFactory: $tasks,
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
        );

        $result = $worker->runOne(WorkerSpecFactory::create([
            'max_requests' => 1,
        ]));

        self::assertSame('done', $result);
        self::assertSame(['queue'], $kernel->types);
        self::assertSame(1, $kernel->calls);

        self::assertCount(1, $tracer->spans);
        self::assertSame('worker.task', $tracer->spans[0]->name());
        self::assertSame(
            [
                'operation' => 'queue',
                'outcome' => 'success',
            ],
            $tracer->spans[0]->attributes,
        );

        self::assertSame(
            'worker.task_total',
            $meter->increments[0]['name'],
        );
        self::assertSame(
            ['operation' => 'queue', 'outcome' => 'success'],
            $meter->increments[0]['labels'],
        );
        self::assertSame(
            'worker.task_duration_ms',
            $meter->observations[0]['name'],
        );
    }

    public function testTaskFailureKeepsFailureLabelsAndRethrows(): void
    {
        $root = $this->temporaryDirectory('worker-application-failure');
        $tasks = new RecordingTaskFactory();
        $tasks->throwOnRun = true;
        $tracer = new RecordingTracer();
        $meter = new RecordingMeter();

        $worker = new ApplicationWorker(
            stopSignal: new WorkerStopSignal($root),
            kernelRuntime: new RecordingKernelRuntime(),
            taskFactory: $tasks,
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
        );

        try {
            $worker->runOne(WorkerSpecFactory::create());
            self::fail('Expected task failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('task-failure', $exception->getMessage());
        }

        self::assertSame(
            ['operation' => 'queue', 'outcome' => 'failure'],
            $tracer->spans[0]->attributes,
        );
        self::assertSame(
            ['operation' => 'queue', 'outcome' => 'failure'],
            $meter->increments[0]['labels'],
        );
    }

    public function testObservabilityFailuresDoNotChangeTaskResult(): void
    {
        $root = $this->temporaryDirectory('worker-application-noop');
        $tasks = new RecordingTaskFactory();
        $tasks->results = [42];
        $tracer = new RecordingTracer();
        $tracer->throwOnStart = true;
        $meter = new RecordingMeter();
        $meter->throw = true;

        $worker = new ApplicationWorker(
            stopSignal: new WorkerStopSignal($root),
            kernelRuntime: new RecordingKernelRuntime(),
            taskFactory: $tasks,
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
        );

        self::assertSame(
            42,
            $worker->runOne(WorkerSpecFactory::create()),
        );
    }
}
