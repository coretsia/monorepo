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

use Coretsia\Foundation\Filesystem\Exception\AtomicFileWriteException;

/**
 * Shared atomic publication of exact bytes to an explicitly supplied target.
 *
 * The caller owns target-path policy, coordination, byte normalization, and
 * domain-level failure translation. The writer never deletes an existing
 * target before replacement and never rolls back a successful rename.
 *
 * Temporary files are created in the target directory and cleaned on failure.
 * Publication and cleanup follow filesystem/PHP rename semantics; this is
 * ordinary atomic writing, not a durable file or generation protocol.
 */
final class AtomicFileWriter
{
    private const int FILE_PERMISSIONS = 0644;

    private const string TEMP_PREFIX = '.coretsia-artifact-';

    /**
     * @throws AtomicFileWriteException
     */
    public function write(string $targetPath, string $bytes): void
    {
        if ($targetPath === '' || \str_contains($targetPath, "\0")) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        $temporaryPath = null;

        \set_error_handler(
            static fn (): bool => true,
            \E_WARNING | \E_NOTICE,
        );

        try {
            $targetDirectory = self::targetDirectory($targetPath);
            $this->ensureTargetDirectory($targetDirectory);
            $temporaryPath = $this->createTemporaryFile($targetDirectory);

            $this->writeFullTemporaryFile($temporaryPath, $bytes);
            $this->applyBestEffortPermissions($temporaryPath);
            $this->renameTemporaryFile($temporaryPath, $targetPath);

            // A successful rename is the publication commit point.
            $temporaryPath = null;
        } catch (AtomicFileWriteException $exception) {
            throw $exception;
        } catch (\Throwable) {
            // Never propagate filesystem warnings or path-bearing exceptions.
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_WRITE_FAILED,
            );
        } finally {
            try {
                if ($temporaryPath !== null) {
                    self::cleanupTemporaryFile($temporaryPath);
                }
            } finally {
                \restore_error_handler();
            }
        }
    }

    /**
     * @throws AtomicFileWriteException
     */
    private function ensureTargetDirectory(string $targetDirectory): void
    {
        if ($targetDirectory === '') {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        if (@\is_dir($targetDirectory)) {
            return;
        }

        if (@\file_exists($targetDirectory)) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        if (!@\mkdir($targetDirectory, 0775, true) && !@\is_dir($targetDirectory)) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_CREATE_FAILED,
            );
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws AtomicFileWriteException
     */
    private function createTemporaryFile(string $targetDirectory): string
    {
        $temporaryPath = @\tempnam($targetDirectory, self::TEMP_PREFIX);

        if ($temporaryPath === false) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TEMP_FILE_CREATE_FAILED,
            );
        }

        try {
            $sameDirectory = self::sameDirectory(\dirname($temporaryPath), $targetDirectory);
        } catch (\Throwable) {
            self::cleanupTemporaryFile($temporaryPath);

            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TEMP_FILE_CREATE_FAILED,
            );
        }

        if (!$sameDirectory) {
            self::cleanupTemporaryFile($temporaryPath);

            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TEMP_FILE_CREATE_FAILED,
            );
        }

        return $temporaryPath;
    }

    /**
     * @throws AtomicFileWriteException
     */
    private function writeFullTemporaryFile(string $temporaryPath, string $bytes): void
    {
        $written = @\file_put_contents($temporaryPath, $bytes, \LOCK_EX);

        if (!\is_int($written) || $written !== \strlen($bytes)) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TEMP_FILE_WRITE_FAILED,
            );
        }
    }

    private function applyBestEffortPermissions(string $temporaryPath): void
    {
        try {
            @\chmod($temporaryPath, self::FILE_PERMISSIONS);
        } catch (\Throwable) {
            // Permissions are best-effort, including with custom error handlers.
        }
    }

    /**
     * @throws AtomicFileWriteException
     */
    private function renameTemporaryFile(string $temporaryPath, string $targetPath): void
    {
        if (!@\rename($temporaryPath, $targetPath)) {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TEMP_FILE_RENAME_FAILED,
            );
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws AtomicFileWriteException
     */
    private static function targetDirectory(string $targetPath): string
    {
        $directory = \dirname($targetPath);

        if ($directory === '' || $directory === '.') {
            throw AtomicFileWriteException::withReason(
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        return $directory;
    }

    private static function sameDirectory(string $left, string $right): bool
    {
        $leftReal = @\realpath($left);
        $rightReal = @\realpath($right);

        if ($leftReal === false || $rightReal === false) {
            return false;
        }

        if (\PHP_OS_FAMILY === 'Windows') {
            $leftReal = \strtolower(\str_replace('\\', '/', $leftReal));
            $rightReal = \strtolower(\str_replace('\\', '/', $rightReal));
        }

        return $leftReal === $rightReal;
    }

    private static function cleanupTemporaryFile(string $temporaryPath): void
    {
        try {
            if (@\file_exists($temporaryPath) || @\is_link($temporaryPath)) {
                @\unlink($temporaryPath);
            }
        } catch (\Throwable) {
            // Cleanup must not replace the original failure.
        }
    }
}
