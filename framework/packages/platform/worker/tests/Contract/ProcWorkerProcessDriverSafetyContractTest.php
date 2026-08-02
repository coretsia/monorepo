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

final class ProcWorkerProcessDriverSafetyContractTest extends PackageTestCase
{
    public function testDriverDelegatesRawProcessOwnershipToPreLockHost(): void
    {
        $source = self::source(
            'src/Process/Driver/ProcWorkerProcessDriver.php',
        );

        foreach (
            [
                'processHost->start',
                'processHost->spawn',
                'processHost->pollExit',
                'processHost->terminate',
                'processHost->kill',
                'processHost->close',
                'processHost->shutdown',
            ] as $required
        ) {
            self::assertStringContainsString($required, $source);
        }

        $codeOnly = \preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source) ?? $source;

        foreach (
            [
                'proc_open(',
                'proc_get_status(',
                'proc_terminate(',
                'proc_close(',
                'WorkerStateStore',
                'WorkerControlServer',
                'WorkerStopSignal',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $codeOnly);
        }
    }

    public function testHostProtocolIsVersionedBoundedAndRejectsUnknownShapes(): void
    {
        $source = self::source(
            'src/Process/Proc/WorkerProcProcessHostProtocol.php',
        );

        self::assertStringContainsString('VERSION = 1', $source);
        self::assertStringContainsString('MAX_FRAME_BYTES', $source);
        self::assertStringContainsString('StableJsonEncoder', $source);
        self::assertStringContainsString('StableJsonDecoder', $source);
        self::assertStringNotContainsString('serialize(', $source);
        self::assertStringNotContainsString('unserialize(', $source);
    }
}
