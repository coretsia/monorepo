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

namespace Coretsia\Foundation\Context\Exception;

/**
 * Deterministic ContextStore key rejection.
 *
 * This exception is used when a context write attempts to use an invalid,
 * reserved, or unknown context key.
 *
 * The message is intentionally stable and safe. It may include the rejected key
 * because context keys are policy-controlled identifiers, but it must never
 * include context values.
 */
final class ContextInvalidKeyException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTEXT_INVALID_KEY';

    public function __construct(
        string $key = '',
        string $reason = 'context-key-invalid',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('context-invalid-key-reason-empty');
        }

        parent::__construct(self::message($key, $reason), 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    private static function message(string $key, string $reason): string
    {
        if ($key === '') {
            return $reason;
        }

        return $reason . ': ' . $key;
    }
}
