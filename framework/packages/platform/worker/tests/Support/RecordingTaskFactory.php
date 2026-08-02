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

namespace Coretsia\Platform\Worker\Tests\Support;

use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

final class RecordingTaskFactory implements TaskFactoryInternalInterface
{
    public int $assertReadyCalls = 0;
    public int $createCalls = 0;
    /** @var list<mixed> */ public array $results = [];
    public bool $supported = true;
    public bool $throwOnRun = false;
    public function __construct(private readonly string $type = self::TASK_TYPE_QUEUE)
    {
    }
    public function taskType(): string
    {
        return $this->type;
    }
    public function supports(WorkerPoolSpec $spec): bool
    {
        return $this->supported && $spec->taskType() === $this->type;
    }
    public function assertReady(WorkerPoolSpec $spec): void
    {
        $this->assertReadyCalls++;
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::startFailed();
        }
    }
    public function operationId(WorkerPoolSpec $spec): string
    {
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::startFailed();
        }return $this->type;
    }
    public function create(WorkerPoolSpec $spec): array
    {
        $this->createCalls++;
        $index = $this->createCalls - 1;
        $result = $this->results[$index] ?? $index;
        return ['operation_id' => $this->type, 'run' => function () use ($result): mixed {if ($this->throwOnRun) {throw new \RuntimeException('task-failure'); }return $result;}];
    }
}
