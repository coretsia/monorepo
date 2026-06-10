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

namespace Coretsia\Kernel\Artifacts\Exception;

/**
 * Deterministic Kernel artifact write failure.
 *
 * This exception is used by ArtifactWriter when a generated artifact cannot be
 * written atomically or safely.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_ARTIFACT_WRITE_FAILED: reason-token
 *
 * The message MUST NOT include absolute paths, input path strings, temporary
 * filenames, raw artifact bytes, payloads, config values, env values, PHP
 * warnings, filesystem warning text, stack traces, permissions, owners, host
 * data, or previous throwable messages.
 *
 * @internal
 */
final class ArtifactWriteFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ARTIFACT_WRITE_FAILED';

    public const string REASON_WRITE_FAILED = 'artifact-write-failed';
    public const string REASON_TARGET_DIRECTORY_INVALID = 'artifact-target-directory-invalid';
    public const string REASON_TARGET_DIRECTORY_CREATE_FAILED = 'artifact-target-directory-create-failed';
    public const string REASON_TEMP_FILE_CREATE_FAILED = 'artifact-temp-file-create-failed';
    public const string REASON_TEMP_FILE_WRITE_FAILED = 'artifact-temp-file-write-failed';
    public const string REASON_TEMP_FILE_RENAME_FAILED = 'artifact-temp-file-rename-failed';
    public const string REASON_TEMP_FILE_CLEANUP_FAILED = 'artifact-temp-file-cleanup-failed';
    public const string REASON_FINAL_BYTES_INVALID = 'artifact-final-bytes-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_WRITE_FAILED => true,
        self::REASON_TARGET_DIRECTORY_INVALID => true,
        self::REASON_TARGET_DIRECTORY_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_WRITE_FAILED => true,
        self::REASON_TEMP_FILE_RENAME_FAILED => true,
        self::REASON_TEMP_FILE_CLEANUP_FAILED => true,
        self::REASON_FINAL_BYTES_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('artifact-write-failed-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('artifact-write-failed-reason-invalid');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason = self::REASON_WRITE_FAILED,
        ?\Throwable $previous = null,
    ): self {
        return new self($reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
    }
}
