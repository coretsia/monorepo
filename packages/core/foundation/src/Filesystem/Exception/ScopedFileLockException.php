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

final class ScopedFileLockException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_SCOPED_FILE_LOCK_FAILED';

    public const string REASON_BUSY = 'scoped-file-lock-busy';
    public const string REASON_FAILED = 'scoped-file-lock-failed';

    /** @var array<string, true> */
    private const array REASONS = [
        self::REASON_BUSY => true,
        self::REASON_FAILED => true,
    ];

    private readonly string $reason;

    public function __construct(
        string $reason = self::REASON_FAILED,
        ?\Throwable $previous = null,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('scoped-file-lock-reason-invalid');
        }

        $this->reason = $reason;

        parent::__construct(
            $reason,
            0,
            $previous,
        );
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
