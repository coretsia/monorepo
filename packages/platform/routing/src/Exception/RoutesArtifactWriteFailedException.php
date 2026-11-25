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

namespace Coretsia\Platform\Routing\Exception;

use Coretsia\Foundation\Filesystem\Exception\AtomicFileWriteException;
use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;

/**
 * Deterministic Routing artifact publication failure.
 *
 * Messages contain only the fixed Routing error code and an allowlisted
 * reason token. Filesystem paths, temporary names, artifact bytes, warning
 * text, and previous throwable messages are never retained.
 *
 * Successful atomic replacement is the publication commit point. Lock-release
 * failures after that point MUST NOT be converted into publication failures.
 *
 * @internal
 */
final class RoutesArtifactWriteFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ROUTES_ARTIFACT_WRITE_FAILED';

    public const string REASON_WRITE_FAILED = 'routes-artifact-write-failed';
    public const string REASON_LOCK_ACQUIRE_FAILED = 'routes-artifact-lock-acquire-failed';
    public const string REASON_TARGET_UNSAFE = 'routes-artifact-target-unsafe';
    public const string REASON_TARGET_DIRECTORY_CREATE_FAILED = 'routes-artifact-target-directory-create-failed';
    public const string REASON_TEMP_FILE_CREATE_FAILED = 'routes-artifact-temp-file-create-failed';
    public const string REASON_TEMP_FILE_WRITE_FAILED = 'routes-artifact-temp-file-write-failed';
    public const string REASON_PUBLICATION_FAILED = 'routes-artifact-publication-failed';
    public const string REASON_EXISTING_READ_FAILED = 'routes-artifact-existing-read-failed';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_WRITE_FAILED => true,
        self::REASON_LOCK_ACQUIRE_FAILED => true,
        self::REASON_TARGET_UNSAFE => true,
        self::REASON_TARGET_DIRECTORY_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_WRITE_FAILED => true,
        self::REASON_PUBLICATION_FAILED => true,
        self::REASON_EXISTING_READ_FAILED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('routes-artifact-write-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function withReason(string $reason): self
    {
        return new self($reason);
    }

    /**
     * Translate an atomic-write failure without retaining the source throwable.
     */
    public static function fromAtomicWriteFailure(AtomicFileWriteException $failure): self
    {
        return new self(
            match ($failure->reason()) {
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID => self::REASON_TARGET_UNSAFE,
                AtomicFileWriteException::REASON_TARGET_DIRECTORY_CREATE_FAILED => self::REASON_TARGET_DIRECTORY_CREATE_FAILED,
                AtomicFileWriteException::REASON_TEMP_FILE_CREATE_FAILED => self::REASON_TEMP_FILE_CREATE_FAILED,
                AtomicFileWriteException::REASON_TEMP_FILE_WRITE_FAILED => self::REASON_TEMP_FILE_WRITE_FAILED,
                AtomicFileWriteException::REASON_TEMP_FILE_RENAME_FAILED => self::REASON_PUBLICATION_FAILED,
                default => self::REASON_WRITE_FAILED,
            },
        );
    }

    /**
     * Translate lock acquisition failures only, before the publication commit.
     */
    public static function fromLockAcquisitionFailure(ScopedFileLockException $failure): self
    {
        return new self(self::REASON_LOCK_ACQUIRE_FAILED);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
