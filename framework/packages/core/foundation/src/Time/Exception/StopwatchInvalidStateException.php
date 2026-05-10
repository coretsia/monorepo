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

namespace Coretsia\Foundation\Time\Exception;

/**
 * Deterministic Stopwatch state/token failure.
 *
 * This exception is used when Stopwatch receives an invalid state or token.
 *
 * The message is intentionally stable and safe. It must not include raw timing
 * tokens, payloads, host-specific values, or environment data.
 */
final class StopwatchInvalidStateException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_STOPWATCH_INVALID_STATE';

    public function __construct(
        string $reason = 'stopwatch-invalid-state',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('stopwatch-invalid-state-reason-empty');
        }

        parent::__construct($reason, 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
