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
 * The message is intentionally stable and safe. It may include only a safe
 * path-to-value and a stable reason token. It must never include rejected raw
 * values, payloads, secrets, raw SQL, authorization data, cookies, tokens,
 * session ids, stack traces, PII, absolute local paths, or environment-specific
 * data.
 *
 * Unsafe diagnostic paths are represented by a stable placeholder and MUST NOT
 * be exposed raw.
 */
final class UnitOfWorkResultInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_UOW_RESULT_INVALID';

    public const string REASON_INVALID = 'uow-result-invalid';
    public const string REASON_UOW_ID_INVALID = 'uow-result-uow-id-invalid';
    public const string REASON_TYPE_INVALID = 'uow-result-type-invalid';
    public const string REASON_CORRELATION_ID_INVALID = 'uow-result-correlation-id-invalid';
    public const string REASON_STARTED_AT_INVALID = 'uow-result-started-at-invalid';
    public const string REASON_FINISHED_AT_INVALID = 'uow-result-finished-at-invalid';
    public const string REASON_DURATION_MS_INVALID = 'uow-result-duration-ms-invalid';
    public const string REASON_OUTCOME_INVALID = 'uow-result-outcome-invalid';

    public const string REASON_EXTENSIONS_ROOT_MAP_REQUIRED = 'uow-result-extensions-root-map-required';
    public const string REASON_EXTENSIONS_UNSAFE_KEY = 'uow-result-extensions-unsafe-key';

    public const string REASON_ERROR_MAP_EMPTY = 'uow-result-error-map-empty';
    public const string REASON_ERROR_MAP_REQUIRED = 'uow-result-error-map-required';

    public const string REASON_MAP_KEY_INVALID = 'uow-result-map-key-invalid';
    public const string REASON_STRING_INVALID = 'uow-result-string-invalid';

    public const string REASON_FLOAT_FORBIDDEN = 'uow-result-float-forbidden';
    public const string REASON_OBJECT_FORBIDDEN = 'uow-result-object-forbidden';
    public const string REASON_CLOSURE_FORBIDDEN = 'uow-result-closure-forbidden';
    public const string REASON_RESOURCE_FORBIDDEN = 'uow-result-resource-forbidden';
    public const string REASON_MAP_KEY_MUST_BE_STRING = 'uow-result-map-key-must-be-string';
    public const string REASON_TYPE_FORBIDDEN = 'uow-result-type-forbidden';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_UOW_ID_INVALID => true,
        self::REASON_TYPE_INVALID => true,
        self::REASON_CORRELATION_ID_INVALID => true,
        self::REASON_STARTED_AT_INVALID => true,
        self::REASON_FINISHED_AT_INVALID => true,
        self::REASON_DURATION_MS_INVALID => true,
        self::REASON_OUTCOME_INVALID => true,

        self::REASON_EXTENSIONS_ROOT_MAP_REQUIRED => true,
        self::REASON_EXTENSIONS_UNSAFE_KEY => true,

        self::REASON_ERROR_MAP_EMPTY => true,
        self::REASON_ERROR_MAP_REQUIRED => true,

        self::REASON_MAP_KEY_INVALID => true,
        self::REASON_STRING_INVALID => true,

        self::REASON_FLOAT_FORBIDDEN => true,
        self::REASON_OBJECT_FORBIDDEN => true,
        self::REASON_CLOSURE_FORBIDDEN => true,
        self::REASON_RESOURCE_FORBIDDEN => true,
        self::REASON_MAP_KEY_MUST_BE_STRING => true,
        self::REASON_TYPE_FORBIDDEN => true,
    ];

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private readonly string $path;

    private readonly string $reason;

    public function __construct(
        string $path = '',
        string $reason = self::REASON_INVALID,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('uow-result-invalid-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('uow-result-invalid-reason-invalid');
        }

        $this->path = self::safeDiagnosticPath($path);
        $this->reason = $reason;

        parent::__construct(self::message($this->path, $this->reason), 0, $previous);
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

    /**
     * Returns the safe diagnostic path.
     *
     * Unsafe paths are returned as the stable placeholder `<path>`.
     */
    public function path(): string
    {
        return $this->path;
    }

    public function safePath(): ?string
    {
        if ($this->path === '') {
            return null;
        }

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
