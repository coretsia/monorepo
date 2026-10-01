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
use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;

/**
 * Process-shared lock for generation publication and current-generation reads.
 *
 * Artifact path ownership remains in ArtifactGenerationPathResolver. Generic
 * lock-file mechanics are delegated to Foundation ScopedFileLock.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final readonly class ArtifactGenerationLock
{
    public function __construct(
        private ArtifactGenerationPathResolver $pathResolver = new ArtifactGenerationPathResolver(),
        private ScopedFileLock $fileLock = new ScopedFileLock(),
    ) {
    }

    public function shared(string $artifactRoot, Closure $operation): mixed
    {
        return $this->withLock(
            artifactRoot: $artifactRoot,
            exclusive: false,
            operation: $operation,
        );
    }

    public function exclusive(string $artifactRoot, Closure $operation): mixed
    {
        return $this->withLock(
            artifactRoot: $artifactRoot,
            exclusive: true,
            operation: $operation,
        );
    }

    private function withLock(
        string $artifactRoot,
        bool $exclusive,
        Closure $operation,
    ): mixed {
        $lockPath = $this->pathResolver->generationLockPath($artifactRoot);
        $operationStarted = false;
        $operationCompleted = false;
        $scopedOperation = static function () use (
            $operation,
            &$operationStarted,
            &$operationCompleted,
        ): mixed {
            $operationStarted = true;
            $result = $operation();
            $operationCompleted = true;

            return $result;
        };

        try {
            return $exclusive
                ? $this->fileLock->exclusive($lockPath, $scopedOperation)
                : $this->fileLock->shared($lockPath, $scopedOperation);
        } catch (ScopedFileLockException $exception) {
            if ($operationStarted && !$operationCompleted) {
                throw $exception;
            }

            throw self::lockFailed();
        }
    }

    private static function lockFailed(): ArtifactGenerationPublishException
    {
        return ArtifactGenerationPublishException::withReason(
            ArtifactGenerationPublishException::REASON_LOCK_FAILED,
        );
    }
}
