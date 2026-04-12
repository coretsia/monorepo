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
 * Domain error: configured command class is missing (class not found / not autoloadable).
 */
final class CliCommandClassMissingException extends CliException
{
    public const string REASON_CLASS_NOT_FOUND = 'class-not-found';

    /** @var list<string> */
    private const array ALLOWED_REASONS = [
        self::REASON_CLASS_NOT_FOUND,
    ];

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCodes::CORETSIA_CLI_COMMAND_CLASS_MISSING,
            self::normalizeReason($reason, self::ALLOWED_REASONS),
            $previous,
        );
    }

    public static function classNotFound(?\Throwable $previous = null): self
    {
        return new self(self::REASON_CLASS_NOT_FOUND, $previous);
    }
}
