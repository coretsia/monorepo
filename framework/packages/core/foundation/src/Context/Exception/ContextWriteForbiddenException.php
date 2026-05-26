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
 * The message is intentionally stable and safe. It may include only a safe
 * path-to-value and a stable reason token. It must never include the rejected
 * raw value.
 */
final class ContextWriteForbiddenException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTEXT_WRITE_FORBIDDEN';
    private const string REASON_WRITE_FORBIDDEN = 'context-write-forbidden';
    private const string REASON_FLOAT_FORBIDDEN = 'context-write-forbidden-float';
    private const string REASON_CLOSURE_FORBIDDEN = 'context-write-forbidden-closure';
    private const string REASON_OBJECT_FORBIDDEN = 'context-write-forbidden-object';
    private const string REASON_RESOURCE_FORBIDDEN = 'context-write-forbidden-resource';
    private const string REASON_MAP_KEY_FORBIDDEN = 'context-write-forbidden-map-key';
    private const string REASON_TYPE_FORBIDDEN = 'context-write-forbidden-type';
    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_WRITE_FORBIDDEN => true,
        self::REASON_FLOAT_FORBIDDEN => true,
        self::REASON_CLOSURE_FORBIDDEN => true,
        self::REASON_OBJECT_FORBIDDEN => true,
        self::REASON_RESOURCE_FORBIDDEN => true,
        self::REASON_MAP_KEY_FORBIDDEN => true,
        self::REASON_TYPE_FORBIDDEN => true,
    ];

    private const int MAX_SAFE_PATH_BYTES = 512;
    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private readonly string $reason;
    private readonly ?string $safePath;

    public function __construct(
        string $path = '',
        string $reason = self::REASON_WRITE_FORBIDDEN,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('context-write-forbidden-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('context-write-forbidden-reason-invalid');
        }

        $this->reason = $reason;
        $this->safePath = self::safeDiagnosticPath($path);

        parent::__construct(self::message($this->safePath, $this->reason), 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function safePath(): ?string
    {
        return $this->safePath;
    }

    private static function message(?string $safePath, string $reason): string
    {
        if ($safePath === null) {
            return $reason;
        }

        return $reason . ': value at ' . $safePath;
    }

    private static function safeDiagnosticPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (!self::isSafeDiagnosticPath($path)) {
            return self::UNSAFE_PATH_PLACEHOLDER;
        }

        return $path;
    }

    private static function isSafeDiagnosticPath(string $path): bool
    {
        if (\strlen($path) > self::MAX_SAFE_PATH_BYTES) {
            return false;
        }

        if (\preg_match(self::SAFE_PATH_PATTERN, $path) !== 1) {
            return false;
        }

        if (\preg_match(self::CONTROL_CHARACTER_PATTERN, $path) === 1) {
            return false;
        }

        if (\preg_match(self::SENSITIVE_PATH_PATTERN, $path) === 1) {
            return false;
        }

        if (\preg_match(self::SQL_LIKE_PATTERN, $path) === 1) {
            return false;
        }

        return true;
    }
}
