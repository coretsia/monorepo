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

namespace Coretsia\Kernel\Config\Exception;

use Coretsia\Contracts\Config\ConfigDirective;

/**
 * Deterministic Kernel config directive exclusive-level violation.
 *
 * This exception is used by ConfigNamespaceGuard and DirectiveProcessor after
 * reserved directive namespace validation has already accepted all "@*" keys as
 * known ConfigDirective keys.
 *
 * If a map level contains any directive key, that level MUST contain exactly
 * one directive key and MUST NOT contain normal config keys.
 *
 * This exception covers only exclusive-level violations:
 *
 * - a directive key mixed with normal config keys on the same map level;
 * - more than one directive key on the same map level.
 *
 * Unknown "@*" keys MUST be reported as ConfigReservedNamespaceException before
 * this exception is considered.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL: reason-token
 *
 * The message MUST NOT include raw config values, raw environment values,
 * unknown directive keys, normal sibling keys, payloads, secrets, tokens, DSNs,
 * cookies, headers, raw SQL, object dumps, PHP warnings, absolute local paths,
 * stack traces, or previous throwable messages.
 *
 * @internal
 */
final class ConfigDirectiveMixedLevelException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL';

    public const string REASON_DIRECTIVE_MIXED_LEVEL = 'config-directive-mixed-level';
    public const string REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS = 'config-directive-mixed-with-config-keys';
    public const string REASON_MULTIPLE_DIRECTIVES = 'config-directive-multiple-directives';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_DIRECTIVE_MIXED_LEVEL => true,
        self::REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS => true,
        self::REASON_MULTIPLE_DIRECTIVES => true,
    ];

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_-]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_-]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private readonly string $path;

    private function __construct(
        private readonly string $reason,
        string $path = '',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('config-directive-mixed-level-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('config-directive-mixed-level-reason-invalid');
        }

        $this->path = self::safeDiagnosticPath($path);

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function mixedWithConfigKeys(
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS,
            path: $path,
            previous: $previous,
        );
    }

    public static function multipleDirectives(
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_MULTIPLE_DIRECTIVES,
            path: $path,
            previous: $previous,
        );
    }

    public static function withReason(
        string $reason = self::REASON_DIRECTIVE_MIXED_LEVEL,
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: $reason,
            path: $path,
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
     * Returns the safe diagnostic config path.
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

    /**
     * @return list<non-empty-string>
     */
    public function allowedDirectiveKeys(): array
    {
        return ConfigDirective::keys();
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
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
