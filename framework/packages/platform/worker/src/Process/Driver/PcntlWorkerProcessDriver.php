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
use Coretsia\Platform\Worker\Exception\WorkerForkFailedException;
use Coretsia\Platform\Worker\Exception\WorkerLifecycleFailedException;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Unix-like fork-and-exec single-child process adapter.
 *
 * The supervisor forks only to establish the child PID. The child immediately
 * detaches Worker-owned inherited resources and replaces the forked supervisor
 * process image with the package-owned artifact-only worker launcher. No parent
 * container, ApplicationWorker instance, or shared runtime object graph crosses
 * the execution boundary.
 */
final readonly class PcntlWorkerProcessDriver implements WorkerProcessDriverInterface
{
    /**
     * @param non-empty-list<non-empty-string> $workerCommand
     */
    public function __construct(
        private string $skeletonRoot,
        private array $workerCommand,
        private WorkerChildCommandBuilder $commandBuilder,
        private WorkerChildReadinessChannel $readinessChannel,
        private WorkerForkIsolation $forkIsolation,
        private bool $pcntlAvailable,
        private string $platformFamily,
    ) {
        if (
            $skeletonRoot === ''
            || \str_contains($skeletonRoot, "\0")
            || $workerCommand === []
            || !\array_is_list($workerCommand)
            || $platformFamily === ''
            || \preg_match('/[\x00-\x1F\x7F]/', $platformFamily) === 1
        ) {
            throw new \InvalidArgumentException('pcntl-worker-process-driver-invalid');
        }

        foreach ($workerCommand as $part) {
            if (
                !\is_string($part)
                || $part === ''
                || \trim($part) !== $part
                || \preg_match('/[\x00-\x1F\x7F]/', $part) === 1
            ) {
                throw new \InvalidArgumentException('pcntl-worker-process-driver-invalid');
            }
        }
    }

    public function name(): string
    {
        return self::DRIVER_PCNTL;
    }

    public function supports(WorkerPoolSpec $spec): bool
    {
        return $spec->driver() === self::DRIVER_PCNTL
            && $this->pcntlAvailable
            && \strcasecmp($this->platformFamily, 'Windows') !== 0
            && \function_exists('pcntl_fork')
            && \function_exists('pcntl_exec')
            && \function_exists('pcntl_waitpid')
            && \function_exists('pcntl_wifexited')
            && \function_exists('pcntl_wexitstatus')
            && \function_exists('pcntl_wifsignaled')
            && \function_exists('pcntl_wtermsig')
            && \function_exists('posix_kill');
    }

    public function prepare(WorkerPoolSpec $spec): void
    {
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::childStartFailed();
        }
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

        $pid = @\pcntl_fork();

        if ($pid === -1) {
            $readinessEndpoint->close();

            throw WorkerForkFailedException::forkFailed();
        }

        if ($pid === 0) {
            $readinessEndpoint->close();

            try {
                $this->forkIsolation->prepareForkedChild();

                if (!@\chdir($this->skeletonRoot)) {
                    exit(1);
                }

                $binary = \array_shift($command);

                if (!\is_string($binary) || $binary === '') {
                    exit(1);
                }

                @\pcntl_exec($binary, $command);
            } catch (\Throwable) {
                // Process-image replacement failures collapse to child exit 1.
            }

            // pcntl_exec() returns only when process-image replacement failed.
            exit(1);
        }

        return new WorkerChildProcess(
            workerIndex: $workerIndex,
            pid: $pid,
            driverName: self::DRIVER_PCNTL,
            processHandle: null,
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

        $status = 0;
        $result = @\pcntl_waitpid($child->pid(), $status, \WNOHANG);

        if ($result === 0) {
            return null;
        }

        if ($result !== $child->pid()) {
            throw WorkerLifecycleFailedException::childExited();
        }

        $signaled = \pcntl_wifsignaled($status);
        $signal = $signaled ? \pcntl_wtermsig($status) : 0;
        $exitCode = \pcntl_wifexited($status)
            ? \pcntl_wexitstatus($status)
            : 128 + $signal;

        return new WorkerProcessExit(
            pid: $child->pid(),
            exitCode: $exitCode,
            signaled: $signaled,
            terminatingSignal: $signal,
            expected: !$signaled && $exitCode === 0,
        );
    }

    public function terminate(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void {
        self::assertTimeout($timeoutMs);
        @\posix_kill($child->pid(), \SIGTERM);
    }

    public function kill(
        WorkerChildProcess $child,
        int $timeoutMs,
    ): void {
        self::assertTimeout($timeoutMs);
        @\posix_kill($child->pid(), \SIGKILL);
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
        $child->markClosed();
    }

    public function shutdown(int $timeoutMs): void
    {
        self::assertTimeout($timeoutMs);

        // Pcntl process resources are owned directly per WorkerChildProcess.
    }

    private static function assertTimeout(int $timeoutMs): void
    {
        if ($timeoutMs < 1 || $timeoutMs > 86_400_000) {
            throw WorkerLifecycleFailedException::invalidState();
        }
    }
}
