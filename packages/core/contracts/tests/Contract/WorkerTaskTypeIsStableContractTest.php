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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Worker\WorkerTaskType;
use PHPUnit\Framework\TestCase;

final class WorkerTaskTypeIsStableContractTest extends TestCase
{
    public function testWorkerTaskTypeValuesAreStableAndDeterministic(): void
    {
        self::assertSame('queue', WorkerTaskType::Queue->value);
        self::assertSame('http', WorkerTaskType::Http->value);
        self::assertSame(['queue', 'http'], WorkerTaskType::values());
    }
}
