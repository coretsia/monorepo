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

use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class WorkerProviderSourceDefinitionsParityTest extends PackageTestCase
{
    public function testProviderContainsCanonicalSupervisorProcessAndControlDefinitions(): void
    {
        $provider = self::source('src/Provider/WorkerServiceProvider.php');
        foreach ([
            'WorkerSupervisor::class',
            'ContainerWorkerSupervisorResolver::class',
            'PcntlWorkerProcessDriver::class',
            'ProcWorkerProcessDriver::class',
            'WorkerProcProcessHostClient::class',
            'WorkerControlClient::class',
            'WorkerHealthCommand::class',
            "PROCESS_DRIVER_TAG = 'worker.process_driver'",
        ] as $required) {
            self::assertStringContainsString($required, $provider);
        }

        foreach (['WorkerManager', 'WorkerSocketServer', 'worker.manager_driver'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $provider);
        }
    }
}
