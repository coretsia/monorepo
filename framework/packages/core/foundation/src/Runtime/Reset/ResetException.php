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
    public function __construct(
        private readonly string $resetCode,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('reset-exception-reason-empty');
        }

        if (!ResetErrorCodes::has($resetCode)) {
            throw new \InvalidArgumentException('reset-error-code-unknown');
        }

        parent::__construct($reason, 0, $previous);
    }

    public static function metaInvalid(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_META_INVALID,
            'reset-meta-invalid',
            $previous,
        );
    }

    public static function serviceNotResettable(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_NOT_RESETTABLE,
            'reset-not-resettable',
            $previous,
        );
    }

    public static function serviceFailed(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED,
            'reset-service-failed',
            $previous,
        );
    }

    public static function observabilityFailed(?\Throwable $previous = null): self
    {
        return new self(
            ResetErrorCodes::CORETSIA_RESET_OBSERVABILITY_FAILED,
            'reset-observability-failed',
            $previous,
        );
    }

    public function code(): string
    {
        return $this->resetCode;
    }
}
