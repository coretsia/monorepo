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

namespace Coretsia\Foundation\Runtime\Reset;

/**
 * Deterministic Foundation reset failure.
 *
 * ResetException carries a stable machine-readable reset error code from
 * ResetErrorCodes and a separate fixed safe message token.
 *
 * Messages MUST NOT include service internals, payloads, secrets, raw context
 * values, absolute paths, headers, cookies, Authorization values, tokens,
 * session ids, host-specific values, or environment-specific data.
 */
final class ResetException extends \RuntimeException
{
    private const string REASON_META_INVALID = 'reset-meta-invalid';
    private const string REASON_SERVICE_NOT_RESETTABLE = 'reset-not-resettable';
    private const string REASON_SERVICE_FAILED = 'reset-service-failed';

    /**
     * @var array<string, string>
     */
    private const array REASONS_BY_CODE = [
        ResetErrorCodes::CORETSIA_RESET_META_INVALID => self::REASON_META_INVALID,
        ResetErrorCodes::CORETSIA_RESET_SERVICE_NOT_RESETTABLE => self::REASON_SERVICE_NOT_RESETTABLE,
        ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED => self::REASON_SERVICE_FAILED,
    ];

    public function __construct(
        private readonly string $resetCode,
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('reset-exception-reason-empty');
        }

        if (!ResetErrorCodes::has($resetCode)) {
            throw new \InvalidArgumentException('reset-error-code-unknown');
        }

        if (!isset(self::REASONS_BY_CODE[$resetCode])) {
            throw new \InvalidArgumentException('reset-exception-reason-unknown');
        }

        if ($reason !== self::REASONS_BY_CODE[$resetCode]) {
            throw new \InvalidArgumentException('reset-exception-reason-invalid');
        }

        parent::__construct($reason, 0, $previous);
    }

    public static function metaInvalid(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_META_INVALID,
            self::REASON_META_INVALID,
            $previous,
        );
    }

    public static function serviceNotResettable(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_NOT_RESETTABLE,
            self::REASON_SERVICE_NOT_RESETTABLE,
            $previous,
        );
    }

    public static function serviceFailed(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED,
            self::REASON_SERVICE_FAILED,
            $previous,
        );
    }

    public function code(): string
    {
        return $this->resetCode;
    }

    public function errorCode(): string
    {
        return $this->code();
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function withoutPrevious(): self
    {
        return new self(
            $this->resetCode,
            $this->reason,
        );
    }
}
