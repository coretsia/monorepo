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
use Coretsia\Platform\Worker\Communication\WorkerChildReadinessEndpoint;
use Coretsia\Platform\Worker\Exception\WorkerForkFailedException;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Optional Unix pcntl single-child process adapter.
 *
 * This driver is selected only when the normalized worker pool specification
 * has resolved to `pcntl`, pcntl_fork is available, and the platform is not
 * Windows.
 *
 * It owns only fork/process-driver lifecycle behavior. It does not contain task
 * execution logic, does not call KernelRuntimeInterface, does not know about
 * CLI command dispatch, does not depend on platform/cli, and does not depend on
 * platform/http.
 *
 * Child task execution is provided as an injected Closure so the fork strategy
 * remains independent from ApplicationWorker wiring.
 *
 * This driver must not log payloads, raw socket paths, raw TCP endpoints,
 * absolute paths, headers, tokens, config dumps, or raw process internals.
 */
final readonly class PcntlWorkerProcessDriver implements WorkerProcessDriverInterface
{
    /** @param \Closure(WorkerPoolSpec, int): (\Closure(): int) $childBootstrap */
    public function __construct(
        private \Closure $childBootstrap,
        private WorkerForkIsolation $forkIsolation,
        private bool $pcntlAvailable,
        private string $platformFamily,
    ) {
        if ($platformFamily === '' || \preg_match('/[\x00-\x1F\x7F]/', $platformFamily) === 1) {
            throw new \InvalidArgumentException('pcntl-worker-process-driver-invalid');
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
            && \function_exists('pcntl_waitpid')
            && \function_exists('pcntl_wifexited')
            && \function_exists('pcntl_wexitstatus')
            && \function_exists('pcntl_wifsignaled')
            && \function_exists('pcntl_wtermsig')
            && \function_exists('posix_kill')
            && \function_exists('stream_socket_pair');
    }

    public function prepare(WorkerPoolSpec $spec): void
    {
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::childStartFailed();
        }
    }

    public function spawn(WorkerPoolSpec $spec, int $workerIndex): WorkerChildProcess
    {
        if (!$this->supports($spec) || $workerIndex < 0 || $workerIndex >= $spec->workers()) {
            throw WorkerStartFailedException::childStartFailed();
        }
        $pair = @\stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        if (!\is_array($pair) || \count($pair) !== 2 || !\is_resource($pair[0]) || !\is_resource($pair[1])) {
            throw WorkerStartFailedException::childStartFailed();
        }

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
                $runner = ($this->childBootstrap)($spec, $workerIndex);
                if (!$runner instanceof \Closure) {
                    throw WorkerStartFailedException::childStartFailed();
                }
                WorkerChildReadinessChannel::signalReady($pair[1]);
                @\fclose($pair[1]);
                $exitCode = $runner();
                if ($exitCode < 0 || $exitCode > 255) {
                    $exitCode = 1;
                }
            } catch (\Throwable) {
                $exitCode = 1;
            }
            exit($exitCode);
        }

        @\fclose($pair[1]);

        return new WorkerChildProcess(
            workerIndex: $workerIndex,
            pid: $pid,
            driverName: self::DRIVER_PCNTL,
            processHandle: null,
            readinessEndpoint: WorkerChildReadinessEndpoint::stream($pair[0]),
            generation: 1,
            startedAtNs: \hrtime(true),
        );
    }

    public function pollExit(WorkerChildProcess $child): ?WorkerProcessExit
    {
        $status = 0;
        $result = @\pcntl_waitpid($child->pid(), $status, \WNOHANG);
        if ($result === 0) {
            return null;
        }
        if ($result !== $child->pid()) {
            throw WorkerStartFailedException::childExited();
        }

        $signaled = \pcntl_wifsignaled($status);
        $signal = $signaled ? \pcntl_wtermsig($status) : 0;
        $exitCode = \pcntl_wifexited($status) ? \pcntl_wexitstatus($status) : 128 + $signal;

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
        // Pcntl process resources are owned directly per WorkerChildProcess.
    }
}
