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

namespace Coretsia\Foundation\Id\Exception;

/**
 * Deterministic Foundation id generation failure.
 *
 * This exception wraps failures from entropy-backed id generation without
 * leaking raw entropy bytes, generated partial ids, payloads, host-specific
 * values, or environment data.
 */
final class IdGenerationFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ID_GENERATION_FAILED';

    public function __construct(
        string $reason = 'id-generation-failed',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('id-generation-failed-reason-empty');
        }

        parent::__construct($reason, 0, $previous);
    }

    public static function entropyUnavailable(?\Throwable $previous = null): self
    {
        return new self('id-generation-entropy-unavailable', $previous);
    }

    public static function timeSourceInvalid(?\Throwable $previous = null): self
    {
        return new self('id-generation-time-source-invalid', $previous);
    }

    public static function timestampOutOfRange(?\Throwable $previous = null): self
    {
        return new self('id-generation-timestamp-out-of-range', $previous);
    }

    public static function bytesInvalid(?\Throwable $previous = null): self
    {
        return new self('id-generation-bytes-invalid', $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
