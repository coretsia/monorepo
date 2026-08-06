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

namespace Coretsia\Platform\Worker\Process\Driver;

use Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel;
use Coretsia\Platform\Worker\Exception\WorkerLifecycleFailedException;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient;
use Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Cross-platform proc_open single-child process adapter.
 *
 * The adapter never calls proc_open() from the supervisor process. A pre-lock
 * process host owns raw proc resources so worker children cannot inherit the
 * supervisor lifecycle lock, control listener, or readiness listeners. Before
 * each child launch, the host rotates its authenticated supervisor connection
 * through a one-shot handoff so no proc-host protocol descriptor crosses the
 * proc_open() boundary.
 *
 * Child readiness is delivered through a dedicated per-child loopback TCP
 * endpoint owned by the supervisor. Standard input, output, and error streams
 * are disconnected from the worker child and are not protocol transports.
 */
final readonly class ProcWorkerProcessDriver implements WorkerProcessDriverInterface
{
    /** @param non-empty-list<non-empty-string> $workerCommand */
    public function __construct(
        private string $skeletonRoot,
        private array $workerCommand,
        private WorkerChildCommandBuilder $commandBuilder,
        private WorkerChildReadinessChannel $readinessChannel,
        private WorkerProcProcessHostClient $processHost,
        private bool $processHostAvailable,
    ) {
        if (
            $skeletonRoot === ''
            || \str_contains($skeletonRoot, "\0")
            || $workerCommand === []
            || !\array_is_list($workerCommand)
        ) {
            throw new \InvalidArgumentException('proc-worker-process-driver-invalid');
        }

        foreach ($workerCommand as $part) {
            if (
                !\is_string($part)
                || $part === ''
                || \trim($part) !== $part
                || \preg_match('/[\x00-\x1F\x7F]/', $part) === 1
            ) {
                throw new \InvalidArgumentException('proc-worker-process-driver-invalid');
            }
        }
    }

    public function name(): string
    {
        return self::DRIVER_PROC;
    }

    public function supports(WorkerPoolSpec $spec): bool
    {
        return $spec->driver() === self::DRIVER_PROC && $this->processHostAvailable;
    }

    public function prepare(WorkerPoolSpec $spec): void
    {
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::childStartFailed();
        }

        $this->processHost->start($spec->startTimeoutMs());
    }

    public function spawn(
        WorkerPoolSpec $spec,
        int $workerIndex,
    ): WorkerChildProcess {
        if (
            !$this->supports($spec)
            || $workerIndex < 0
            || $workerIndex >= $spec->workers()
        ) {
            throw WorkerStartFailedException::childStartFailed();
        }

        $readinessEndpoint = $this->readinessChannel->createProcessEndpoint();
        $command = $this->commandBuilder->build(
            baseCommand: $this->workerCommand,
            spec: $spec,
            workerIndex: $workerIndex,
            readinessEndpoint: $readinessEndpoint,
        );

        try {
            $hostChild = $this->processHost->spawn(
                command: $command,
                workingDirectory: $this->skeletonRoot,
                timeoutMs: $spec->startTimeoutMs(),
            );
        } catch (WorkerStartFailedException|WorkerLifecycleFailedException $exception) {
            $readinessEndpoint->close();

            throw $exception;
        } catch (\Throwable) {
            $readinessEndpoint->close();

            throw WorkerStartFailedException::childStartFailed();
        }

        return new WorkerChildProcess(
            workerIndex: $workerIndex,
            pid: $hostChild->pid(),
            driverName: self::DRIVER_PROC,
            processHandle: $hostChild->id(),
            readinessEndpoint: $readinessEndpoint,
            generation: 1,
            startedAtNs: \hrtime(true),
        );
    }

    public function pollExit(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): ?WorkerProcessExit {
        self::assertTimeout($timeoutMs);

        return $this->processHost->pollExit(
            self::childId($child),
            $timeoutMs,
        );
    }

    public function terminate(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void {
        self::assertTimeout($timeoutMs);

        $this->processHost->terminate(
            self::childId($child),
            $timeoutMs,
        );
    }

    public function kill(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void {
        self::assertTimeout($timeoutMs);

        $this->processHost->kill(
            self::childId($child),
            $timeoutMs,
        );
    }

    public function close(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void {
        self::assertTimeout($timeoutMs);

        if ($child->closed()) {
            return;
        }

        $child->readinessEndpoint()->close();
        $this->processHost->close(
            self::childId($child),
            $timeoutMs,
        );
        $child->markClosed();
    }

    public function shutdown(int $timeoutMs): void
    {
        self::assertTimeout($timeoutMs);
        $this->processHost->shutdown($timeoutMs);
    }

    private static function assertTimeout(int $timeoutMs): void
    {
        if ($timeoutMs < 1 || $timeoutMs > 86_400_000) {
            throw WorkerLifecycleFailedException::invalidState();
        }
    }

    private static function childId(WorkerChildProcess $child): string
    {
        $handle = $child->processHandle();

        if (
            $child->driverName() !== self::DRIVER_PROC
            || !\is_string($handle)
            || \preg_match('/\Achild-[1-9][0-9]*\z/', $handle) !== 1
        ) {
            throw WorkerLifecycleFailedException::childExited();
        }

        return $handle;
    }
}
