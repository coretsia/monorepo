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
 * The message is intentionally stable and safe. It may include only stable
 * reason tokens and conservative safe key identifiers. Unsafe rejected keys are
 * represented by a stable placeholder and MUST NOT be exposed raw.
 */
final class ContextInvalidKeyException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTEXT_INVALID_KEY';
    private const string REASON_KEY_INVALID = 'context-key-invalid';
    private const string REASON_KEY_EMPTY = 'context-key-empty';
    private const string REASON_KEY_RESERVED = 'context-key-reserved';
    private const string REASON_KEY_UNKNOWN = 'context-key-unknown';
    private const string UNSAFE_KEY_PLACEHOLDER = '<key>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_KEY_INVALID => true,
        self::REASON_KEY_EMPTY => true,
        self::REASON_KEY_RESERVED => true,
        self::REASON_KEY_UNKNOWN => true,
    ];

    private const string SAFE_KEY_PATTERN = '/\A@?[A-Za-z_][A-Za-z0-9_]{0,63}\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_KEY_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';
    private readonly string $reason;
    private readonly ?string $safeKey;

    public function __construct(
        string $key = '',
        string $reason = self::REASON_KEY_INVALID,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('context-invalid-key-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('context-invalid-key-reason-invalid');
        }

        $this->reason = $reason;
        $this->safeKey = self::safeDiagnosticKey($key);

        parent::__construct(self::message($this->reason, $this->safeKey), 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function safeKey(): ?string
    {
        return $this->safeKey;
    }

    private static function message(string $reason, ?string $safeKey): string
    {
        if ($safeKey === null) {
            return $reason;
        }

        return $reason . ': ' . $safeKey;
    }

    private static function safeDiagnosticKey(string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        if (!self::isSafeDiagnosticKey($key)) {
            return self::UNSAFE_KEY_PLACEHOLDER;
        }

        return $key;
    }

    private static function isSafeDiagnosticKey(string $key): bool
    {
        if (\preg_match(self::SAFE_KEY_PATTERN, $key) !== 1) {
            return false;
        }

        if (\preg_match(self::CONTROL_CHARACTER_PATTERN, $key) === 1) {
            return false;
        }

        if (\preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
            return false;
        }

        if (\preg_match(self::SQL_LIKE_PATTERN, $key) === 1) {
            return false;
        }

        return true;
    }
}
