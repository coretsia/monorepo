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
 * Deterministic fail-closed CLI output redaction policy violation.
 *
 * The underlying redactor failure MUST NOT be chained into this public
 * exception. A fixed safe diagnostic is emitted instead.
 *
 * The public message is fixed and contains only:
 *
 *     CORETSIA_CLI_REDACTION_VIOLATION: output-redaction-failed
 *
 * No raw value, hash, byte length, command payload, path, rendered output,
 * environment detail, or previous throwable is retained or rendered.
 *
 * @internal
 */
final class RedactionViolationException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_REDACTION_VIOLATION';
    public const string REASON_REDACTION_FAILED = 'output-redaction-failed';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_REDACTION_FAILED);
    }

    public static function failed(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_REDACTION_FAILED;
    }
}
