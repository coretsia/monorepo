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

use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;

/** Observe the real CommandRunner's single high-level UoW callback. */
final class BoundaryScenarioRuntime implements KernelRuntimeInterface
{
    public int $calls = 0;
    public bool $active = false;
    /** @var list<array{type:string,attributes:array<string,mixed>}> */
    public array $invocations = [];
    /** @var list<string> */
    public array $events = [];

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        ++$this->calls;
        $this->invocations[] = ['type' => $type, 'attributes' => $attributes];
        $this->events[] = 'enter';
        if ($this->active) {
            throw new \LogicException('Nested CLI UoW is forbidden.');
        }
        $this->active = true;
        try {
            return $body();
        } finally {
            $this->active = false;
            $this->events[] = 'leave';
        }
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        throw new \LogicException('Manual UoW orchestration is forbidden.');
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        throw new \LogicException('Manual UoW orchestration is forbidden.');
    }
}
