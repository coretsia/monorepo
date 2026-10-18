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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Runtime\WorkerPoolState;
use Coretsia\Platform\Worker\Runtime\WorkerPoolStatus;

/** A deterministic foreground supervisor; never starts child processes. */
final class FakeWorkerSupervisor implements WorkerSupervisorInterface
{
    public int $calls = 0;
    public bool $returned = false;
    public ?WorkerPoolSpec $receivedSpec = null;
    /** @var list<string> */
    public array $events = [];
    /** @var (\Closure():void)|null */
    public ?\Closure $afterReady = null;

    public function run(WorkerPoolSpec $spec, \Closure $onReady): int
    {
        ++$this->calls;
        $this->receivedSpec = $spec;
        $this->events[] = 'supervisor-enter';
        $onReady(
            new WorkerPoolState(
                4242,
                WorkerPoolStatus::RUNNING,
                2,
                2,
                'proc',
                'proc',
                'tcp',
                'tcp',
                \str_repeat('a', 64),
            ),
        );
        $this->events[] = 'readiness-reported';
        ($this->afterReady)?->__invoke();
        $this->events[] = 'supervisor-shutdown';
        $this->returned = true;
        return 0;
    }
}
