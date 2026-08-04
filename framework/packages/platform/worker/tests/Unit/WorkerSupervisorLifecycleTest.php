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

use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class WorkerSupervisorLifecycleTest extends PackageTestCase
{
    public function testSupervisorSourceOwnsLifecycleAndContainsNoManagerFacade(): void
    {
        $source = self::source('src/Supervisor/WorkerSupervisor.php');

        foreach (
            [
                'lifecycleLock->acquire',
                'controlServer->listen',
                'signals->install',
                'shutdownChildren',
                'stateStore->delete',
                'lifecycleLock->release',
                'respondStopped',
                'driverResolver->resolve',
            ] as $required
        ) {
            self::assertStringContainsString($required, $source);
        }

        self::assertStringNotContainsString('WorkerManager', $source);
        self::assertStringNotContainsString('normalizeDrivers', $source);
        self::assertStringNotContainsString('selectDriver', $source);
        self::assertStringNotContainsString('proc_open(', $source);
        self::assertStringNotContainsString('echo ', $source);
        self::assertStringNotContainsString('STDOUT', $source);
        self::assertStringNotContainsString('STDERR', $source);
    }

    public function testProcessDriverContractHasPreparationAndShutdownButNoPoolOperations(): void
    {
        $reflection = new \ReflectionClass(
            WorkerProcessDriverInterface::class,
        );

        self::assertSame(
            [
                'name',
                'supports',
                'prepare',
                'spawn',
                'pollExit',
                'terminate',
                'kill',
                'close',
                'shutdown',
            ],
            \array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ),
        );

        foreach (['start', 'stop', 'status', 'health'] as $forbidden) {
            self::assertFalse($reflection->hasMethod($forbidden));
        }
    }
}
