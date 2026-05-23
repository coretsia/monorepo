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

namespace Coretsia\Kernel\Runtime\Exception;

/**
 * Deterministic UnitOfWorkResult validation failure.
 *
 * This exception is used when Kernel rejects an invalid UnitOfWorkResult shape,
 * invalid UnitOfWorkResult.extensions payload, or invalid exported error map.
 *
 * The message is intentionally stable and safe. It may include only a
 * path-to-value and a safe reason token. It must never include rejected raw
 * values, payloads, secrets, raw SQL, authorization data, cookies, tokens,
 * session ids, stack traces, PII, absolute local paths, or environment-specific
 * data.
 */
final class UnitOfWorkResultInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_UOW_RESULT_INVALID';

    private readonly string $path;

    private readonly string $reason;

    public function __construct(
        string $path = '',
        string $reason = 'uow-result-invalid',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('uow-result-invalid-reason-empty');
        }

        $this->path = $path;
        $this->reason = $reason;

        parent::__construct(self::message($path, $reason), 0, $previous);
    }

    public static function atPath(
        string $path,
        string $reason,
        ?\Throwable $previous = null,
    ): self {
        return new self($path, $reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(string $path, string $reason): string
    {
        if ($path === '') {
            return $reason;
        }

        return $reason . ': value at ' . $path;
    }
}
