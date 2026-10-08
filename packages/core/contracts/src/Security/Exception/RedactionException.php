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

namespace Coretsia\Contracts\Security\Exception;

/**
 * Deterministic contracts-level failure boundary for sensitive-data redaction.
 *
 * The public message contains only the stable contracts error code and one
 * allowlisted reason token. The exception intentionally never retains a
 * previous Throwable and MUST NOT expose rejected values, map keys, scopes,
 * hashes, lengths, paths, patterns, class names, resource ids, or payload
 * fragments.
 *
 * Native Throwable stack-trace storage is not redefined or sanitized here.
 * Export of stack traces remains governed by the Core error/observability
 * boundary policy.
 */
final class RedactionException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_REDACTION_FAILED';

    public const string REASON_INPUT_INVALID = 'input-invalid';
    public const string REASON_INPUT_LIMIT_EXCEEDED = 'input-limit-exceeded';
    public const string REASON_SENSITIVE_MAP_KEY = 'sensitive-map-key';
    public const string REASON_OUTPUT_INVALID = 'output-invalid';
    public const string REASON_INTERNAL_FAILURE = 'internal-failure';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INPUT_INVALID => true,
        self::REASON_INPUT_LIMIT_EXCEEDED => true,
        self::REASON_SENSITIVE_MAP_KEY => true,
        self::REASON_OUTPUT_INVALID => true,
        self::REASON_INTERNAL_FAILURE => true,
    ];

    private readonly string $reason;

    private function __construct(string $reason)
    {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('redaction-exception-reason-invalid');
        }

        $this->reason = $reason;

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function inputInvalid(): self
    {
        return new self(self::REASON_INPUT_INVALID);
    }

    public static function inputLimitExceeded(): self
    {
        return new self(self::REASON_INPUT_LIMIT_EXCEEDED);
    }

    public static function sensitiveMapKey(): self
    {
        return new self(self::REASON_SENSITIVE_MAP_KEY);
    }

    public static function outputInvalid(): self
    {
        return new self(self::REASON_OUTPUT_INVALID);
    }

    public static function internalFailure(): self
    {
        return new self(self::REASON_INTERNAL_FAILURE);
    }

    /**
     * @return non-empty-string
     */
    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    /**
     * @return 'input-invalid'|'input-limit-exceeded'|'sensitive-map-key'|'output-invalid'|'internal-failure'
     */
    public function reason(): string
    {
        return $this->reason;
    }
}
