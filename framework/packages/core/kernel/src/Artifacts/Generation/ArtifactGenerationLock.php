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

namespace Coretsia\Kernel\Artifacts\Generation;

use Closure;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;

/**
 * Process-shared lock for generation publication and current-generation reads.
 *
 * The lock file is a persistent cache-control file. It is created when absent
 * and is never removed between operations. POSIX runtimes request close-on-exec
 * for the local lock handle; Windows uses the equivalent valid mode without the
 * POSIX-only `e` flag.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final readonly class ArtifactGenerationLock
{
    private const int DIRECTORY_PERMISSIONS = 0775;
    private const int FILE_PERMISSIONS = 0644;

    public function __construct(
        private ArtifactGenerationPathResolver $pathResolver = new ArtifactGenerationPathResolver(),
    ) {
    }

    public function shared(string $artifactRoot, Closure $operation): mixed
    {
        return $this->withLock(
            artifactRoot: $artifactRoot,
            mode: \LOCK_SH,
            operation: $operation,
        );
    }

    public function exclusive(string $artifactRoot, Closure $operation): mixed
    {
        return $this->withLock(
            artifactRoot: $artifactRoot,
            mode: \LOCK_EX,
            operation: $operation,
        );
    }

    private function withLock(
        string $artifactRoot,
        int $mode,
        Closure $operation,
    ): mixed {
        $lockPath = $this->pathResolver->generationLockPath($artifactRoot);
        $lockDirectory = \dirname($lockPath);

        if (
            !$this->ensureDirectory($lockDirectory)
            || @\is_link($lockPath)
        ) {
            throw self::lockFailed();
        }

        $handle = @\fopen(
            $lockPath,
            self::openMode(),
        );

        if (!\is_resource($handle)) {
            throw self::lockFailed();
        }

        @\chmod($lockPath, self::FILE_PERMISSIONS);

        if (!@\flock($handle, $mode)) {
            @\fclose($handle);

            throw self::lockFailed();
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
            throw self::lockFailed();
        }

        return $result;
    }

    /**
     * Returns the local generation-lock mode.
     */
    private static function openMode(): string
    {
        return \PHP_OS_FAMILY === 'Windows'
            ? 'c+b'
            : 'c+be';
    }

    private function ensureDirectory(string $directory): bool
    {
        if (self::isSafeDirectory($directory)) {
            return true;
        }

        if (@\is_link($directory)) {
            return false;
        }

        if (
            !@\mkdir(
                $directory,
                self::DIRECTORY_PERMISSIONS,
                true,
            )
            && !self::isSafeDirectory($directory)
        ) {
            return false;
        }

        return self::isSafeDirectory($directory);
    }

    /**
     * @phpstan-impure
     */
    private static function isSafeDirectory(string $directory): bool
    {
        return @\is_dir($directory) && !@\is_link($directory);
    }

    private static function lockFailed(): ArtifactGenerationPublishException
    {
        return ArtifactGenerationPublishException::withReason(
            ArtifactGenerationPublishException::REASON_LOCK_FAILED,
        );
    }
}
