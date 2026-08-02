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

namespace Coretsia\Platform\Worker\Tests\Fake;

use Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel;
use Coretsia\Platform\Worker\Communication\WorkerChildReadinessEndpoint;
use Coretsia\Platform\Worker\Exception\WorkerForkFailedException;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Runtime\WorkerStopSignal;

/**
 * Deterministic process fake that still uses real forked child processes.
 *
 * It is intentionally test-only. Pool ownership remains with WorkerSupervisor;
 * this fake exposes only the single-child process-driver contract.
 */
final class FakeWorkerProcessDriver implements WorkerProcessDriverInterface
{
    /** @var array<int, int> */
    private array $spawnCounts = [];

    /**
     * @param array{
     *     ready_delay_ms?: int,
     *     ready_delay_by_slot?: array<int, int>,
     *     ready_gate_slots?: list<int>,
     *     never_ready_slots?: list<int>,
     *     crash_before_ready_slots?: list<int>,
     *     exit_after_ready?: array{
     *         slot: int,
     *         code: int,
     *         delay_ms?: int,
     *         first_generation_only?: bool,
     *         wait_for_release?: bool,
     *     },
     *     ignore_stop_slots?: list<int>
     * } $behavior
     */
    public function __construct(
        private readonly WorkerForkIsolation $forkIsolation,
        private readonly WorkerStopSignal $stopSignal,
        private readonly array $behavior = [],
        private readonly ?string $pidLogPath = null,
    ) {
    }

    public function name(): string
    {
        return self::DRIVER_PCNTL;
    }

    public function supports(WorkerPoolSpec $spec): bool
    {
        return $spec->driver() === self::DRIVER_PCNTL
            && \function_exists('pcntl_fork')
            && \function_exists('pcntl_waitpid')
            && \function_exists('posix_kill')
            && \function_exists('stream_socket_pair');
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

        $pair = @\stream_socket_pair(
            \STREAM_PF_UNIX,
            \STREAM_SOCK_STREAM,
            0,
        );

        if (
            !\is_array($pair)
            || \count($pair) !== 2
            || !\is_resource($pair[0])
            || !\is_resource($pair[1])
        ) {
            throw WorkerStartFailedException::childStartFailed();
        }

        $generation = ($this->spawnCounts[$workerIndex] ?? 0) + 1;
        $this->spawnCounts[$workerIndex] = $generation;

        $pid = @\pcntl_fork();

        if ($pid === -1) {
            @\fclose($pair[0]);
            @\fclose($pair[1]);

            throw WorkerForkFailedException::forkFailed();
        }

        if ($pid === 0) {
            @\fclose($pair[0]);

            $exitCode = 1;

            try {
                $this->forkIsolation->prepareForkedChild();
                $exitCode = $this->runChild(
                    spec: $spec,
                    workerIndex: $workerIndex,
                    generation: $generation,
                    readinessStream: $pair[1],
                );
            } catch (\Throwable) {
                $exitCode = 1;
            }

            if (\is_resource($pair[1])) {
                @\fclose($pair[1]);
            }

            exit($exitCode);
        }

        @\fclose($pair[1]);
        $this->recordSpawn($workerIndex, $generation, $pid);

        return new WorkerChildProcess(
            workerIndex: $workerIndex,
            pid: $pid,
            driverName: self::DRIVER_PCNTL,
            processHandle: null,
            readinessEndpoint: WorkerChildReadinessEndpoint::stream(
                $pair[0],
            ),
            generation: 1,
            startedAtNs: \hrtime(true),
        );
    }

    public function pollExit(
        WorkerChildProcess $child,
    ): ?WorkerProcessExit {
        $status = 0;
        $result = @\pcntl_waitpid(
            $child->pid(),
            $status,
            \WNOHANG,
        );

        if ($result === 0) {
            return null;
        }

        if ($result !== $child->pid()) {
            throw WorkerStartFailedException::childExited();
        }

        $signaled = \pcntl_wifsignaled($status);
        $signal = $signaled
            ? \pcntl_wtermsig($status)
            : 0;

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

    public function terminate(WorkerChildProcess $child): void
    {
        @\posix_kill($child->pid(), \SIGTERM);
    }

    public function kill(WorkerChildProcess $child): void
    {
        @\posix_kill($child->pid(), \SIGKILL);
    }

    public function close(WorkerChildProcess $child): void
    {
        if ($child->closed()) {
            return;
        }

        $child->readinessEndpoint()->close();
        $child->markClosed();
    }

    public function shutdown(): void
    {
    }

    private function runChild(
        WorkerPoolSpec $spec,
        int $workerIndex,
        int $generation,
        mixed $readinessStream,
    ): int {
        if (
            self::containsSlot(
                $this->behavior['crash_before_ready_slots'] ?? [],
                $workerIndex,
            )
        ) {
            return 1;
        }

        if (
            self::containsSlot(
                $this->behavior['ready_gate_slots'] ?? [],
                $workerIndex,
            )
        ) {
            $gatePath = $this->readinessGatePath();

            if ($gatePath === null) {
                return 1;
            }

            while (!\is_file($gatePath)) {
                if ($this->stopSignal->isRequested($spec)) {
                    return 0;
                }

                \usleep(10_000);
            }
        }

        $delayMs = $this->behavior['ready_delay_by_slot'][$workerIndex]
            ?? $this->behavior['ready_delay_ms']
            ?? 0;

        if (\is_int($delayMs) && $delayMs > 0) {
            \usleep($delayMs * 1000);
        }

        $neverReady = self::containsSlot(
            $this->behavior['never_ready_slots'] ?? [],
            $workerIndex,
        );

        if (!$neverReady) {
            WorkerChildReadinessChannel::signalReady(
                $readinessStream,
            );

            @\fclose($readinessStream);
        }

        $exitAfterReady = $this->behavior['exit_after_ready'] ?? null;

        if (
            \is_array($exitAfterReady)
            && ($exitAfterReady['slot'] ?? null) === $workerIndex
            && (
                ($exitAfterReady['first_generation_only'] ?? true) === false
                || $generation === 1
            )
        ) {
            if (
                ($exitAfterReady['wait_for_release'] ?? false)
                === true
            ) {
                $gatePath = $this->exitGatePath();

                if ($gatePath === null) {
                    return 1;
                }

                while (!\is_file($gatePath)) {
                    if (
                        $this->stopSignal->isRequested(
                            $spec,
                        )
                    ) {
                        return 0;
                    }

                    \usleep(10_000);
                }
            }

            $exitDelayMs = $exitAfterReady['delay_ms'] ?? 100;

            if (\is_int($exitDelayMs) && $exitDelayMs > 0) {
                \usleep($exitDelayMs * 1000);
            }

            $code = $exitAfterReady['code'] ?? 0;

            return \is_int($code) && $code >= 0 && $code <= 255
                ? $code
                : 1;
        }

        $ignoreStop = self::containsSlot(
            $this->behavior['ignore_stop_slots'] ?? [],
            $workerIndex,
        );

        while (true) {
            if (!$ignoreStop && $this->stopSignal->isRequested($spec)) {
                return 0;
            }

            \usleep(10_000);
        }
    }

    private function readinessGatePath(): ?string
    {
        if ($this->pidLogPath === null) {
            return null;
        }

        return \dirname($this->pidLogPath)
            . '/worker-ready-gate';
    }

    private function exitGatePath(): ?string
    {
        if ($this->pidLogPath === null) {
            return null;
        }

        return \dirname($this->pidLogPath)
            . '/worker-exit-gate';
    }

    /**
     * @param list<int>|mixed $slots
     */
    private static function containsSlot(
        mixed $slots,
        int $workerIndex,
    ): bool {
        return \is_array($slots)
            && \in_array($workerIndex, $slots, true);
    }

    private function recordSpawn(
        int $workerIndex,
        int $generation,
        int $pid,
    ): void {
        if ($this->pidLogPath === null) {
            return;
        }

        $directory = \dirname($this->pidLogPath);

        if (
            !\is_dir($directory)
            && !@\mkdir($directory, 0777, true)
            && !\is_dir($directory)
        ) {
            return;
        }

        $line = \json_encode(
            [
                    'generation' => $generation,
                    'pid' => $pid,
                    'slot' => $workerIndex,
                ],
            \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_THROW_ON_ERROR,
        ) . "\n";

        @\file_put_contents(
            $this->pidLogPath,
            $line,
            \FILE_APPEND | \LOCK_EX,
        );
    }
}
