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

use Coretsia\Platform\Worker\Tests\Support\SupervisorIntegrationTestCase;
use Coretsia\Platform\Worker\Tests\Support\WorkerCommandHarness;

final class WorkerLifecycleLockFilesystemTest extends SupervisorIntegrationTestCase
{
    public function testSecondStartFailsDeterministically(): void
    {
        ['root' => $root, 'harness' => $first] = $this->newHarness();
        $first->startAndWaitForSummary();

        $second = new WorkerCommandHarness(
            skeletonRoot: $root,
            workerOverride: $first->workerConfig(),
        );
        $second->start();
        $message = $second->waitForStartMessage();
        self::assertSame('error', $message['type'] ?? null);
        self::assertSame('CORETSIA_WORKER_ALREADY_RUNNING', $message['code'] ?? null);
        self::assertNotSame(0, $second->finishStart()['exit_code']);

        self::onlyPayload($first->invoke('stop'));
        self::assertSame(0, $first->finishStart()['exit_code']);
    }

    public function testStaleStateWithFreeLockIsNotRunning(): void
    {
        ['root' => $root, 'harness' => $harness] = $this->newHarness();
        \mkdir($root . '/var/tmp', 0777, true);
        \file_put_contents($harness->statePath(), '{"version":1}');

        $error = self::onlyError($harness->invoke('status'));
        self::assertSame('CORETSIA_WORKER_NOT_RUNNING', $error['code'] ?? null);
    }

    public function testHeldLockWithUnavailableControlEndpointIsCommunicationFailure(): void
    {
        ['harness' => $harness] = $this->newHarness();
        \mkdir(\dirname($harness->lockPath()), 0777, true);
        $handle = \fopen($harness->lockPath(), 'c+b');
        self::assertIsResource($handle);
        self::assertTrue(\flock($handle, \LOCK_EX | \LOCK_NB));

        try {
            $error = self::onlyError($harness->invoke('status'));
            self::assertSame('CORETSIA_WORKER_COMMUNICATION_FAILED', $error['code'] ?? null);
        } finally {
            \flock($handle, \LOCK_UN);
            \fclose($handle);
        }
    }
}
