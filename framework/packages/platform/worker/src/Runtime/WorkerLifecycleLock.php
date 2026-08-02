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

namespace Coretsia\Platform\Worker\Runtime;

use Coretsia\Platform\Worker\Exception\WorkerAlreadyRunningException;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;

/**
 * Owns the persistent filesystem lock anchor for one worker supervisor.
 *
 * The lock file is opened with `c+b` and guarded by non-blocking `flock`.
 * Releasing the lock closes the handle but never unlinks the anchor path.
 */
final class WorkerLifecycleLock
{
    /** @var resource|null */
    private mixed $handle = null;

    public function __construct(private readonly string $skeletonRoot)
    {
        if ($skeletonRoot === '' || \str_contains($skeletonRoot, "\0")) {
            throw new \InvalidArgumentException('worker-lifecycle-root-invalid');
        }
    }

    public function acquire(WorkerPoolSpec $spec): void
    {
        if (\is_resource($this->handle)) {
            throw WorkerAlreadyRunningException::alreadyRunning();
        }

        $handle = $this->open($spec);
        if (!@\flock($handle, \LOCK_EX | \LOCK_NB)) {
            @\fclose($handle);
            throw WorkerAlreadyRunningException::alreadyRunning();
        }
        $this->handle = $handle;
    }

    public function isHeld(WorkerPoolSpec $spec): bool
    {
        $handle = $this->open($spec);
        try {
            if (@\flock($handle, \LOCK_EX | \LOCK_NB)) {
                @\flock($handle, \LOCK_UN);
                return false;
            }
            return true;
        } finally {
            @\fclose($handle);
        }
    }

    public function release(): void
    {
        if (!\is_resource($this->handle)) {
            return;
        }
        @\flock($this->handle, \LOCK_UN);
        @\fclose($this->handle);
        $this->handle = null;
    }

    public function detachInForkedChild(): void
    {
        if (\is_resource($this->handle)) {
            @\fclose($this->handle);
            $this->handle = null;
        }
    }

    /** @return resource */
    private function open(WorkerPoolSpec $spec): mixed
    {
        $path = $this->path($spec);
        $dir = \dirname($path);
        if (!\is_dir($dir) && !@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            throw WorkerStartFailedException::lifecycleLockFailed();
        }
        $handle = @\fopen($path, 'c+b');
        if (!\is_resource($handle)) {
            throw WorkerStartFailedException::lifecycleLockFailed();
        }
        return $handle;
    }

    private function path(WorkerPoolSpec $spec): string
    {
        return \rtrim(\str_replace('\\', '/', $this->skeletonRoot), '/') . '/' . $spec->lockPath();
    }
}
