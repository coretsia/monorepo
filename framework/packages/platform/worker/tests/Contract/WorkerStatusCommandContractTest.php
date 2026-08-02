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

namespace Coretsia\Platform\Worker\Tests\Contract;

use Coretsia\Platform\Worker\Console\WorkerStatusCommand;
use Coretsia\Platform\Worker\Provider\WorkerServiceFactory;
use Coretsia\Platform\Worker\Runtime\WorkerPoolState;
use Coretsia\Platform\Worker\Runtime\WorkerPoolStatus;
use Coretsia\Platform\Worker\Tests\Support\ArrayConfigRepository;
use Coretsia\Platform\Worker\Tests\Support\RecordingControlClient;
use Coretsia\Platform\Worker\Tests\Support\RecordingOutput;
use Coretsia\Platform\Worker\Tests\Support\TestInput;
use PHPUnit\Framework\TestCase;

final class WorkerStatusCommandContractTest extends TestCase
{
    public function testStatusUsesLiveStateWithoutHardcodedRunningValue(): void
    {
        $client = new RecordingControlClient();
        $client->statusState = self::state(
            WorkerPoolStatus::STARTING,
            1,
        );
        $output = new RecordingOutput();
        $command = new WorkerStatusCommand(
            config: self::config(),
            factory: new WorkerServiceFactory(),
            client: $client,
        );

        self::assertSame(
            0,
            $command->run(
                new TestInput(WorkerStatusCommand::NAME),
                $output,
            ),
        );
        self::assertSame('starting', $output->json[0]['status']);
        self::assertSame(1, $output->json[0]['ready_worker_count']);
        self::assertSame(1, $client->statusCalls);
    }

    private static function config(): ArrayConfigRepository
    {
        return new ArrayConfigRepository([
            'worker' => require \dirname(__DIR__, 2) . '/config/worker.php',
        ]);
    }

    private static function state(
        WorkerPoolStatus $status = WorkerPoolStatus::RUNNING,
        int $ready = 2,
    ): WorkerPoolState {
        return new WorkerPoolState(
            pid: 1234,
            status: $status,
            workerCount: 2,
            readyWorkerCount: $ready,
            driverRequested: 'auto',
            driver: 'pcntl',
            controlTransportRequested: 'auto',
            controlTransport: 'unix',
            endpointHash: \str_repeat('a', 64),
        );
    }
}
