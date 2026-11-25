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

namespace Coretsia\Foundation\Filesystem\Exception;

/**
 * Deterministic atomic byte publication failure.
 *
 * Messages contain only a fixed error code and an allowlisted reason token.
 */
final class AtomicFileWriteException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ATOMIC_FILE_WRITE_FAILED';

    public const string REASON_WRITE_FAILED = 'atomic-file-write-failed';
    public const string REASON_TARGET_DIRECTORY_INVALID = 'atomic-file-target-directory-invalid';
    public const string REASON_TARGET_DIRECTORY_CREATE_FAILED = 'atomic-file-target-directory-create-failed';
    public const string REASON_TEMP_FILE_CREATE_FAILED = 'atomic-file-temp-file-create-failed';
    public const string REASON_TEMP_FILE_WRITE_FAILED = 'atomic-file-temp-file-write-failed';
    public const string REASON_TEMP_FILE_RENAME_FAILED = 'atomic-file-temp-file-rename-failed';

    /** @var array<string, true> */
    private const array REASONS = [
        self::REASON_WRITE_FAILED => true,
        self::REASON_TARGET_DIRECTORY_INVALID => true,
        self::REASON_TARGET_DIRECTORY_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_CREATE_FAILED => true,
        self::REASON_TEMP_FILE_WRITE_FAILED => true,
        self::REASON_TEMP_FILE_RENAME_FAILED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('atomic-file-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function withReason(string $reason): self
    {
        return new self($reason);
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
