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
 * Domain error: configured command registry entry is invalid (class/shape/name/ctor policy).
 */
final class CliCommandInvalidException extends CliException
{
    public const string REASON_RESERVED_COMMAND_NAME = 'reserved-command-name';
    public const string REASON_CLASS_NOT_A_COMMAND = 'not-a-command';
    public const string REASON_NON_PUBLIC_CONSTRUCTOR = 'non-public-constructor';
    public const string REASON_NON_ZERO_ARG_CONSTRUCTOR = 'non-zero-arg-constructor';

    /** @var list<string> */
    private const array ALLOWED_REASONS = [
        self::REASON_RESERVED_COMMAND_NAME,
        self::REASON_CLASS_NOT_A_COMMAND,
        self::REASON_NON_PUBLIC_CONSTRUCTOR,
        self::REASON_NON_ZERO_ARG_CONSTRUCTOR,
    ];

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCodes::CORETSIA_CLI_COMMAND_INVALID,
            self::normalizeReason($reason, self::ALLOWED_REASONS),
            $previous,
        );
    }

    public static function reservedCommandName(?\Throwable $previous = null): self
    {
        return new self(self::REASON_RESERVED_COMMAND_NAME, $previous);
    }

    public static function notACommand(?\Throwable $previous = null): self
    {
        return new self(self::REASON_CLASS_NOT_A_COMMAND, $previous);
    }

    public static function nonPublicConstructor(?\Throwable $previous = null): self
    {
        return new self(self::REASON_NON_PUBLIC_CONSTRUCTOR, $previous);
    }

    public static function nonZeroArgConstructor(?\Throwable $previous = null): self
    {
        return new self(self::REASON_NON_ZERO_ARG_CONSTRUCTOR, $previous);
    }
}
