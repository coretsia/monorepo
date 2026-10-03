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

namespace Coretsia\Foundation\Filesystem;

use Closure;
use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;

/**
 * Generic scoped filesystem lock primitive.
 *
 * The caller owns lock-path derivation and domain-level failure translation.
 * This class owns only lock-directory creation, lock-file opening, flock,
 * unlock/close, and the persistent lock-anchor lifecycle.
 */
final readonly class ScopedFileLock
{
    private const int DIRECTORY_PERMISSIONS = 0775;
    private const int FILE_PERMISSIONS = 0644;

    public function shared(
        string $lockPath,
        Closure $operation,
    ): mixed {
        return $this->withLock(
            lockPath: $lockPath,
            mode: \LOCK_SH,
            nonBlocking: false,
            operation: $operation,
        );
    }

    public function exclusive(
        string $lockPath,
        Closure $operation,
    ): mixed {
        return $this->withLock(
            lockPath: $lockPath,
            mode: \LOCK_EX,
            nonBlocking: false,
            operation: $operation,
        );
    }

    public function exclusiveNonBlocking(
        string $lockPath,
        Closure $operation,
    ): mixed {
        return $this->withLock(
            lockPath: $lockPath,
            mode: \LOCK_EX,
            nonBlocking: true,
            operation: $operation,
        );
    }

    private function withLock(
        string $lockPath,
        int $mode,
        bool $nonBlocking,
        Closure $operation,
    ): mixed {
        $lockDirectory = \dirname($lockPath);

        if (
            !self::ensureDirectory($lockDirectory)
            || @\is_link($lockPath)
        ) {
            throw new ScopedFileLockException();
        }

        $handle = @\fopen($lockPath, self::openMode());

        if (!\is_resource($handle)) {
            throw new ScopedFileLockException();
        }

        @\chmod($lockPath, self::FILE_PERMISSIONS);

        $wouldBlock = 0;

        $locked = $nonBlocking
            ? @\flock($handle, $mode | \LOCK_NB, $wouldBlock)
            : @\flock($handle, $mode);

        if (!$locked) {
            @\fclose($handle);

            if ($nonBlocking && $wouldBlock === 1) {
                throw new ScopedFileLockException(ScopedFileLockException::REASON_BUSY);
            }

            throw new ScopedFileLockException();
        }

        $operationException = null;
        $result = null;

        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            $operationException = $exception;
        }

        $unlockSucceeded = @\flock($handle, \LOCK_UN);
        $closeSucceeded = @\fclose($handle);

        if ($operationException instanceof \Throwable) {
            throw $operationException;
        }

        if (!$unlockSucceeded || !$closeSucceeded) {
            throw new ScopedFileLockException();
        }

        return $result;
    }

    private static function openMode(): string
    {
        return \PHP_OS_FAMILY === 'Windows' ? 'c+b' : 'c+be';
    }

    private static function ensureDirectory(
        string $directory,
    ): bool {
        if (self::isSafeDirectory($directory)) {
            return true;
        }

        if (@\is_link($directory)) {
            return false;
        }

        if (
            !@\mkdir($directory, self::DIRECTORY_PERMISSIONS, true)
            && !self::isSafeDirectory($directory)
        ) {
            return false;
        }

        return self::isSafeDirectory($directory);
    }

    /**
     * @phpstan-impure
     */
    private static function isSafeDirectory(
        string $directory,
    ): bool {
        return @\is_dir($directory) && !@\is_link($directory);
    }
}
