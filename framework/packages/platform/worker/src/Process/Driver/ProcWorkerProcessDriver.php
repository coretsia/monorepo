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
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient;
use Coretsia\Platform\Worker\Process\WorkerChildProcess;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Cross-platform proc_open single-child process adapter.
 *
 * The adapter never calls proc_open() from the supervisor process. A pre-lock
 * process host owns raw proc resources so worker children cannot inherit the
 * supervisor lifecycle lock, control listener, or readiness listeners.
 *
 * Child readiness is delivered through a dedicated per-child loopback TCP
 * endpoint owned by the supervisor. Standard input, output, and error streams
 * are disconnected from the worker child and are not protocol transports.
 */
final readonly class ProcWorkerProcessDriver implements WorkerProcessDriverInterface
{
    /** @param list<non-empty-string> $workerCommand */
    public function __construct(
        private string $skeletonRoot,
        private array $workerCommand,
        private string $artifactRoot,
        private WorkerChildReadinessChannel $readinessChannel,
        private WorkerProcProcessHostClient $processHost,
    ) {
        if (
            $skeletonRoot === ''
            || \str_contains($skeletonRoot, "\0")
            || $workerCommand === []
            || !\array_is_list($workerCommand)
            || !self::isRelativeSafePath($artifactRoot)
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
        return $spec->driver() === self::DRIVER_PROC && \function_exists('proc_open');
    }

    public function prepare(WorkerPoolSpec $spec): void
    {
        if (!$this->supports($spec)) {
            throw WorkerStartFailedException::childStartFailed();
        }

        $this->processHost->start(
            $spec->startTimeoutMs(),
        );
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

        $readinessEndpoint = $this->readinessChannel->createProcEndpoint();

        $command = [
            ...$this->workerCommand,
            '--coretsia-worker-index=' . $workerIndex,
            '--coretsia-worker-count=' . $spec->workers(),
            '--coretsia-worker-max-requests=' . $spec->maxRequests(),
            '--coretsia-worker-task-type=' . $spec->taskType(),
            '--coretsia-worker-driver=proc',
            '--coretsia-worker-artifact-root=' . $this->artifactRoot,
            '--coretsia-worker-readiness-port=' . $readinessEndpoint->port(),
            '--coretsia-worker-readiness-token=' . $readinessEndpoint->token(),
        ];

        try {
            $hostChild = $this->processHost->spawn(
                command: $command,
                workingDirectory: $this->skeletonRoot,
                timeoutMs: $spec->startTimeoutMs(),
            );
        } catch (WorkerStartFailedException $exception) {
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
    ): ?WorkerProcessExit {
        return $this->processHost->pollExit(
            self::childId($child),
        );
    }

    public function terminate(
        WorkerChildProcess $child,
    ): void {
        $this->processHost->terminate(
            self::childId($child),
        );
    }

    public function kill(
        WorkerChildProcess $child,
    ): void {
        $this->processHost->kill(
            self::childId($child),
        );
    }

    public function close(
        WorkerChildProcess $child,
    ): void {
        if ($child->closed()) {
            return;
        }

        $child->readinessEndpoint()->close();

        $this->processHost->close(
            self::childId($child),
        );

        $child->markClosed();
    }

    public function shutdown(): void
    {
        $this->processHost->shutdown();
    }

    private static function childId(
        WorkerChildProcess $child,
    ): string {
        $handle = $child->processHandle();

        if (
            $child->driverName() !== self::DRIVER_PROC
            || !\is_string($handle)
            || \preg_match(
                '/\Achild-[1-9][0-9]*\z/',
                $handle,
            ) !== 1
        ) {
            throw WorkerStartFailedException::childExited();
        }

        return $handle;
    }

    private static function isRelativeSafePath(string $path): bool
    {
        if (
            $path === ''
            || \trim($path) !== $path
            || \preg_match('/[\x00-\x20\x7F]/', $path) === 1
            || \str_starts_with($path, '/')
            || \str_starts_with($path, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) === 1
            || \str_contains($path, '\\')
            || \str_contains($path, '://')
        ) {
            return false;
        }

        foreach (\explode('/', $path) as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || \str_starts_with($segment, '@')
            ) {
                return false;
            }
        }

        return true;
    }
}
