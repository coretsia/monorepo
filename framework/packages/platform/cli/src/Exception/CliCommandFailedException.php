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

namespace Coretsia\Platform\Cli\Exception;

use Coretsia\Platform\Cli\Error\ErrorCodes;

/**
 * Command-level failure (execution returned non-zero or explicitly signaled failure).
 *
 * Phase 0 policy:
 * - This exception is domain-level (handled by Application boundary).
 * - reason() MUST be a short fixed token; MUST NOT contain paths/secrets.
 */
final class CliCommandFailedException extends CliException
{
    public const string REASON_COMMAND_FAILED = 'command-failed';

    /** @var list<string> */
    private const array ALLOWED_REASONS = [
        self::REASON_COMMAND_FAILED,
    ];

    private readonly int $exitCode;

    public function __construct(
        string      $reason = self::REASON_COMMAND_FAILED,
        int         $exitCode = 1,
        ?\Throwable $previous = null
    )
    {
        $this->exitCode = $exitCode;

        parent::__construct(
            ErrorCodes::CORETSIA_CLI_COMMAND_FAILED,
            self::normalizeReason($reason, self::ALLOWED_REASONS),
            $previous,
        );
    }

    /**
     * Internal: desired exit code for the failed command (Phase 0 policy may still force 1
     * when rendered via the Application CliExceptionInterface boundary).
     */
    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public static function commandFailed(int $exitCode = 1, ?\Throwable $previous = null): self
    {
        return new self(self::REASON_COMMAND_FAILED, $exitCode, $previous);
    }
}
