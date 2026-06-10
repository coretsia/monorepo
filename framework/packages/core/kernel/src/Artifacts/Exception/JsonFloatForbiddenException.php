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

namespace Coretsia\Kernel\Artifacts\Exception;

/**
 * Deterministic artifact payload normalization failure for forbidden floats.
 *
 * This exception is used by PayloadNormalizer when artifact payload data
 * contains a float, including NaN, INF, or -INF.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code, a stable reason token, and optionally a safe path-to-value token:
 *
 *     CORETSIA_JSON_FLOAT_FORBIDDEN: json-float-forbidden
 *     CORETSIA_JSON_FLOAT_FORBIDDEN: json-float-forbidden at a.b[3].c
 *
 * The message MUST NOT include the rejected raw value, raw payloads, config
 * values, env values, secrets, object dumps, stack traces, absolute paths, or
 * previous throwable messages.
 *
 * @internal
 */
final class JsonFloatForbiddenException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_JSON_FLOAT_FORBIDDEN';

    public const string REASON_FLOAT_FORBIDDEN = 'json-float-forbidden';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_FLOAT_FORBIDDEN => true,
    ];

    private readonly string $path;

    private function __construct(
        private readonly string $reason,
        string $path = '',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('json-float-forbidden-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('json-float-forbidden-reason-invalid');
        }

        $this->path = self::safeDiagnosticPath($path);

        parent::__construct(self::message($this->reason, $this->path), 0, $previous);
    }

    public static function atPath(
        string $path,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_FLOAT_FORBIDDEN,
            path: $path,
            previous: $previous,
        );
    }

    public static function withReason(
        string $reason = self::REASON_FLOAT_FORBIDDEN,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: $reason,
            path: '',
            previous: $previous,
        );
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Returns the safe diagnostic path.
     *
     * Unsafe paths are returned as the stable placeholder `<path>`.
     */
    public function path(): string
    {
        return $this->path;
    }

    private static function message(string $reason, string $path): string
    {
        $message = self::ERROR_CODE . ': ' . $reason;

        if ($path === '') {
            return $message;
        }

        return $message . ' at ' . $path;
    }

    private static function safeDiagnosticPath(string $path): string
    {
        if ($path === '') {
            return '';
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
