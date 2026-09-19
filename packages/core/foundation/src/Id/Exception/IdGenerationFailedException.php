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

    public const string REASON_GENERATION_FAILED = 'id-generation-failed';
    public const string REASON_ENTROPY_UNAVAILABLE = 'id-generation-entropy-unavailable';
    public const string REASON_TIME_SOURCE_INVALID = 'id-generation-time-source-invalid';
    public const string REASON_TIMESTAMP_OUT_OF_RANGE = 'id-generation-timestamp-out-of-range';
    public const string REASON_BYTES_INVALID = 'id-generation-bytes-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_GENERATION_FAILED => true,
        self::REASON_ENTROPY_UNAVAILABLE => true,
        self::REASON_TIME_SOURCE_INVALID => true,
        self::REASON_TIMESTAMP_OUT_OF_RANGE => true,
        self::REASON_BYTES_INVALID => true,
    ];

    private readonly string $reason;

    public function __construct(
        string $reason = self::REASON_GENERATION_FAILED,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('id-generation-failed-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('id-generation-failed-reason-invalid');
        }

        $this->reason = $reason;

        parent::__construct($reason, 0, $previous);
    }

    public static function entropyUnavailable(?\Throwable $previous = null): self
    {
        return new self(self::REASON_ENTROPY_UNAVAILABLE, $previous);
    }

    public static function timeSourceInvalid(?\Throwable $previous = null): self
    {
        return new self(self::REASON_TIME_SOURCE_INVALID, $previous);
    }

    public static function timestampOutOfRange(?\Throwable $previous = null): self
    {
        return new self(self::REASON_TIMESTAMP_OUT_OF_RANGE, $previous);
    }

    public static function bytesInvalid(?\Throwable $previous = null): self
    {
        return new self(self::REASON_BYTES_INVALID, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
