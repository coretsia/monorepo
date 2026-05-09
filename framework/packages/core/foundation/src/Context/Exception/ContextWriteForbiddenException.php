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
 * Deterministic ContextStore write rejection.
 *
 * This exception is used when a context write attempts to store a forbidden
 * value or shape.
 *
 * The message is intentionally stable and safe. It may include only a
 * path-to-value and a safe reason token. It must never include the rejected raw
 * value.
 */
final class ContextWriteForbiddenException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTEXT_WRITE_FORBIDDEN';

    public function __construct(
        string $path = '',
        string $reason = 'context-write-forbidden',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('context-write-forbidden-reason-empty');
        }

        parent::__construct(self::message($path, $reason), 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    private static function message(string $path, string $reason): string
    {
        if ($path === '') {
            return $reason;
        }

        return $reason . ': value at ' . $path;
    }
}
