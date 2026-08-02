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

namespace Coretsia\Platform\Worker\Process;

use Coretsia\Platform\Worker\Communication\WorkerChildReadinessEndpoint;

/**
 * Mutable runtime handle for one supervisor-owned worker child.
 *
 * The object stores only bounded process metadata and opaque runtime resources.
 * Command vectors, raw endpoints, and absolute paths are intentionally absent.
 */
final class WorkerChildProcess
{
    private bool $closed = false;

    public function __construct(
        private readonly int $workerIndex,
        private readonly int $pid,
        private readonly string $driverName,
        private readonly mixed $processHandle,
        private readonly WorkerChildReadinessEndpoint $readinessEndpoint,
        private readonly int $generation,
        private readonly int $startedAtNs,
    ) {
        if ($workerIndex < 0 || $pid < 1 || $generation < 1 || $startedAtNs < 1) {
            throw new \InvalidArgumentException('worker-child-process-invalid');
        }
        if (!\in_array($driverName, ['pcntl', 'proc'], true)) {
            throw new \InvalidArgumentException('worker-child-process-invalid');
        }
        if (
            $driverName === 'proc'
            && (
                !\is_string($processHandle)
                || \preg_match(
                    '/\Achild-[1-9][0-9]*\z/',
                    $processHandle,
                ) !== 1
            )
        ) {
            throw new \InvalidArgumentException('worker-child-process-invalid');
        }
        if ($driverName === 'pcntl' && $processHandle !== null) {
            throw new \InvalidArgumentException('worker-child-process-invalid');
        }
    }

    public function workerIndex(): int
    {
        return $this->workerIndex;
    }

    public function pid(): int
    {
        return $this->pid;
    }

    public function driverName(): string
    {
        return $this->driverName;
    }

    public function processHandle(): mixed
    {
        return $this->processHandle;
    }

    public function readinessEndpoint(): WorkerChildReadinessEndpoint
    {
        return $this->readinessEndpoint;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function startedAtNs(): int
    {
        return $this->startedAtNs;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    public function withGeneration(int $generation): self
    {
        if ($generation < 1) {
            throw new \InvalidArgumentException('worker-child-generation-invalid');
        }

        return new self(
            workerIndex: $this->workerIndex,
            pid: $this->pid,
            driverName: $this->driverName,
            processHandle: $this->processHandle,
            readinessEndpoint: $this->readinessEndpoint,
            generation: $generation,
            startedAtNs: $this->startedAtNs,
        );
    }

    public function markClosed(): void
    {
        $this->closed = true;
    }
}
