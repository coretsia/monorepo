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

/**
 * Deterministic CLI parsing, command selection, and input validation failure.
 *
 * Used by ArgvInputParser, CommandInputValidator, CommandCatalog, and
 * KernelOpsRequestResolver. Input values and option contents are never
 * incorporated into exception messages.
 *
 * The public message contains only the package error code and a stable
 * allowlisted reason token. Raw input, configuration, service metadata,
 * filesystem paths, rendered payloads, secrets, and previous throwable
 * messages MUST NOT be included.
 *
 * @internal
 */
final class CliInputInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_INPUT_INVALID';

    public const string REASON_INVALID = 'cli-input-invalid';
    public const string REASON_COMMAND_MISSING = 'cli-command-missing';
    public const string REASON_COMMAND_UNKNOWN = 'cli-command-unknown';
    public const string REASON_COMMAND_INVALID = 'cli-command-invalid';
    public const string REASON_OPTION_INVALID = 'cli-option-invalid';
    public const string REASON_OPTION_UNKNOWN = 'cli-option-unknown';
    public const string REASON_OPTION_REPEATED = 'cli-option-repeated';
    public const string REASON_ARGUMENT_INVALID = 'cli-argument-invalid';
    public const string REASON_ARGUMENT_COUNT_INVALID = 'cli-argument-count-invalid';
    public const string REASON_TARGET_INVALID = 'cli-target-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_COMMAND_MISSING => true,
        self::REASON_COMMAND_UNKNOWN => true,
        self::REASON_COMMAND_INVALID => true,
        self::REASON_OPTION_INVALID => true,
        self::REASON_OPTION_UNKNOWN => true,
        self::REASON_OPTION_REPEATED => true,
        self::REASON_ARGUMENT_INVALID => true,
        self::REASON_ARGUMENT_COUNT_INVALID => true,
        self::REASON_TARGET_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('cli-input-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $this->reason);
    }

    public static function withReason(
        string $reason = self::REASON_INVALID,
    ): self {
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
