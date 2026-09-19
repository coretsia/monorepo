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
 * Deterministic Kernel config directive type violation.
 *
 * This exception is used for two related but distinct cases:
 *
 * - DirectiveProcessor reports directive payload type violations after reserved
 *   directive namespace validation and exclusive-level validation have already
 *   passed.
 * - ConfigMerger reports merge-time base type violations when a normalized
 *   directive is valid by itself, but the existing lower-rank/base value has an
 *   incompatible container kind.
 *
 * Directive payload typing is intentionally strict:
 *
 * - @append, @prepend, and @remove values MUST be lists;
 * - @merge value MUST be a map;
 * - @replace value MAY be scalar, list, or map.
 *
 * Empty array values are accepted by directive context:
 *
 * - list directives treat [] as an empty list;
 * - @merge treats [] as an empty map.
 *
 * Unknown "@*" keys MUST be reported as ConfigReservedNamespaceException before
 * this exception is considered. Directive mixed-level violations MUST be
 * reported as ConfigDirectiveMixedLevelException before this exception is
 * considered.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH: reason-token
 *
 * The message MUST NOT include raw config values, raw environment values,
 * payloads, secrets, tokens, DSNs, cookies, headers, raw SQL, object dumps, PHP
 * warnings, absolute local paths, stack traces, or previous throwable messages.
 *
 * @internal
 */
final class ConfigDirectiveTypeMismatchException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH';

    public const string REASON_DIRECTIVE_TYPE_MISMATCH = 'config-directive-type-mismatch';
    public const string REASON_LIST_DIRECTIVE_VALUE_MUST_BE_LIST = 'config-directive-list-value-must-be-list';
    public const string REASON_MERGE_DIRECTIVE_VALUE_MUST_BE_MAP = 'config-directive-merge-value-must-be-map';
    public const string REASON_LIST_DIRECTIVE_BASE_MUST_BE_LIST = 'list-directive-base-must-be-list';
    public const string REASON_MERGE_DIRECTIVE_BASE_MUST_BE_MAP = 'merge-directive-base-must-be-map';

    public const string EXPECTED_LIST = 'list';
    public const string EXPECTED_MAP = 'map';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_DIRECTIVE_TYPE_MISMATCH => true,
        self::REASON_LIST_DIRECTIVE_VALUE_MUST_BE_LIST => true,
        self::REASON_MERGE_DIRECTIVE_VALUE_MUST_BE_MAP => true,
        self::REASON_LIST_DIRECTIVE_BASE_MUST_BE_LIST => true,
        self::REASON_MERGE_DIRECTIVE_BASE_MUST_BE_MAP => true,
    ];

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_-]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_-]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private readonly string $path;

    private function __construct(
        private readonly string $reason,
        private readonly ConfigDirective $directive,
        private readonly string $expectedType,
        string $path = '',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('config-directive-type-mismatch-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('config-directive-type-mismatch-reason-invalid');
        }

        if ($expectedType === '') {
            throw new \InvalidArgumentException('config-directive-type-mismatch-expected-type-empty');
        }

        if ($expectedType !== self::EXPECTED_LIST && $expectedType !== self::EXPECTED_MAP) {
            throw new \InvalidArgumentException('config-directive-type-mismatch-expected-type-invalid');
        }

        $this->path = self::safeDiagnosticPath($path);

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function listDirectiveValueMustBeList(
        ConfigDirective $directive,
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        if (
            $directive !== ConfigDirective::Append
            && $directive !== ConfigDirective::Prepend
            && $directive !== ConfigDirective::Remove
        ) {
            throw new \InvalidArgumentException('config-directive-list-directive-invalid');
        }

        return new self(
            reason: self::REASON_LIST_DIRECTIVE_VALUE_MUST_BE_LIST,
            directive: $directive,
            expectedType: self::EXPECTED_LIST,
            path: $path,
            previous: $previous,
        );
    }

    public static function listDirectiveBaseMustBeList(
        ConfigDirective $directive,
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        if (
            $directive !== ConfigDirective::Append
            && $directive !== ConfigDirective::Prepend
            && $directive !== ConfigDirective::Remove
        ) {
            throw new \InvalidArgumentException('config-directive-list-directive-invalid');
        }

        return new self(
            reason: self::REASON_LIST_DIRECTIVE_BASE_MUST_BE_LIST,
            directive: $directive,
            expectedType: self::EXPECTED_LIST,
            path: $path,
            previous: $previous,
        );
    }

    public static function mergeDirectiveValueMustBeMap(
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_MERGE_DIRECTIVE_VALUE_MUST_BE_MAP,
            directive: ConfigDirective::Merge,
            expectedType: self::EXPECTED_MAP,
            path: $path,
            previous: $previous,
        );
    }

    public static function mergeDirectiveBaseMustBeMap(
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_MERGE_DIRECTIVE_BASE_MUST_BE_MAP,
            directive: ConfigDirective::Merge,
            expectedType: self::EXPECTED_MAP,
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

    public function directive(): ConfigDirective
    {
        return $this->directive;
    }

    /**
     * @return non-empty-string
     */
    public function directiveKey(): string
    {
        return $this->directive->key();
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
     * @return 'list'|'map'
     */
    public function expectedType(): string
    {
        return $this->expectedType;
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
