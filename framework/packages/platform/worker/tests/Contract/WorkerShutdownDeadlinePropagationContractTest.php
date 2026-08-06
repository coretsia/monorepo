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

use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class WorkerShutdownDeadlinePropagationContractTest extends PackageTestCase
{
    public function testSupervisorUsesOneDeadlinePerShutdownPhase(): void
    {
        $source = self::source('src/Supervisor/WorkerSupervisor.php');

        foreach (
            [
                '$cooperativeDeadlineNs',
                '$terminateDeadlineNs',
                '$killDeadlineNs',
                'remainingMsOrNull',
                'WorkerShutdownBudget::CLEANUP_TIMEOUT_MS',
            ] as $required
        ) {
            self::assertStringContainsString($required, $source);
        }

        self::assertStringContainsString(
            '$driver->pollExit($child, $remainingMs)',
            $source,
        );
        self::assertStringContainsString(
            '$driver->terminate($child, $remainingMs)',
            $source,
        );
        self::assertStringContainsString(
            '$driver->kill($child, $remainingMs)',
            $source,
        );
        self::assertStringContainsString(
            '$driver->close($child, $remainingMs)',
            $source,
        );
    }

    public function testStopClientUsesOneLocatorDerivedRequestDeadline(): void
    {
        $client = self::source(
            'src/Communication/WorkerControlClient.php',
        );

        self::assertStringContainsString(
            '$deadlineNs = self::deadlineNs($timeoutMs)',
            $client,
        );
        self::assertStringContainsString(
            'self::remainingMs($deadlineNs)',
            $client,
        );
    }

    public function testProcHostRequestsUseCallerOwnedRemainingBudget(): void
    {
        $client = self::source(
            'src/Process/Proc/WorkerProcProcessHostClient.php',
        );
        $driver = self::source(
            'src/Process/Driver/ProcWorkerProcessDriver.php',
        );

        self::assertStringContainsString(
            'boundedRequestTimeoutMs',
            $client,
        );

        foreach (['pollExit', 'terminate', 'kill', 'close'] as $method) {
            self::assertStringContainsString(
                '$this->processHost->' . $method,
                $driver,
            );
            self::assertStringContainsString('$timeoutMs', $driver);
        }

        self::assertStringContainsString(
            '$this->processHost->shutdown($timeoutMs)',
            $driver,
        );
    }
}
