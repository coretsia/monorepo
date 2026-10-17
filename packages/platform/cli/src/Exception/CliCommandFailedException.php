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
 * Deterministic failure at the CLI command execution boundary.
 *
 * CommandRunner uses this exception when a command returns an exit code
 * outside the portable 0..255 range. Command-owned exit codes within that
 * range are preserved without remapping.
 *
 * The public message is fixed and contains only:
 *
 *     CORETSIA_CLI_COMMAND_FAILED: cli-command-failed
 *
 * No raw value, hash, byte length, command payload, path, rendered output,
 * environment detail, or previous throwable is retained or rendered.
 *
 * @internal
 */
final class CliCommandFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_COMMAND_FAILED';
    public const string REASON_COMMAND_FAILED = 'cli-command-failed';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_COMMAND_FAILED);
    }

    public static function failed(): self
    {
        return new self();
    }

    public static function invalidExitCode(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_COMMAND_FAILED;
    }
}
