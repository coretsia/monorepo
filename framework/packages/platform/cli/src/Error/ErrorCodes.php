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

namespace Coretsia\Platform\Cli\Error;

/**
 * CLI-owned deterministic error codes registry (SSoT).
 *
 * Invariant:
 * - CORETSIA_CLI_UNCAUGHT_EXCEPTION is launcher-only (catch-all).
 * - Domain logic SHOULD NOT throw/emit CORETSIA_CLI_UNCAUGHT_EXCEPTION.
 */
final class ErrorCodes
{
    public const string CORETSIA_CLI_COMMAND_CLASS_MISSING = 'CORETSIA_CLI_COMMAND_CLASS_MISSING';
    public const string CORETSIA_CLI_COMMAND_FAILED = 'CORETSIA_CLI_COMMAND_FAILED';
    public const string CORETSIA_CLI_COMMAND_INVALID = 'CORETSIA_CLI_COMMAND_INVALID';
    public const string CORETSIA_CLI_CONFIG_INVALID = 'CORETSIA_CLI_CONFIG_INVALID';
    public const string CORETSIA_CLI_UNCAUGHT_EXCEPTION = 'CORETSIA_CLI_UNCAUGHT_EXCEPTION';

    /**
     * @return list<string> Codes sorted deterministically by strcmp() byte-order.
     */
    public static function all(): array
    {
        $codes = [
            self::CORETSIA_CLI_COMMAND_CLASS_MISSING,
            self::CORETSIA_CLI_COMMAND_FAILED,
            self::CORETSIA_CLI_COMMAND_INVALID,
            self::CORETSIA_CLI_CONFIG_INVALID,
            self::CORETSIA_CLI_UNCAUGHT_EXCEPTION,
        ];

        \usort($codes, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $codes;
    }
}
