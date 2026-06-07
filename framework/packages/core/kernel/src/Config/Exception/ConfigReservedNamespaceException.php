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
 * Deterministic Kernel config reserved namespace violation.
 *
 * This exception is used by ConfigNamespaceGuard and DirectiveProcessor when
 * config input writes into a namespace reserved by Kernel config processing.
 *
 * Reserved directive keys use the "@" prefix. The only allowed directive keys
 * are the canonical ConfigDirective keys:
 *
 * @append
 * @prepend
 * @remove
 * @merge
 * @replace
 *
 * Any other "@*" key is a reserved namespace violation and MUST fail before
 * directive mixed-level or directive type-mismatch validation.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_CONFIG_RESERVED_NAMESPACE_USED: reason-token
 *
 * The message MUST NOT include raw config values, raw environment values,
 * unknown directive keys, payloads, secrets, tokens, DSNs, cookies, headers,
 * raw SQL, object dumps, PHP warnings, absolute local paths, stack traces, or
 * previous throwable messages.
 *
 * @internal
 */
final class ConfigReservedNamespaceException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED';

    public const string REASON_RESERVED_NAMESPACE_USED = 'config-reserved-namespace-used';
    public const string REASON_RESERVED_DIRECTIVE_KEY = 'config-reserved-directive-key';
    public const string REASON_RESERVED_TOP_LEVEL_ROOT = 'config-reserved-top-level-root';

    private const string RESERVED_DIRECTIVE_NAMESPACE = '@*';
    private const string RESERVED_TOP_LEVEL_ROOT_NAMESPACE = 'top-level-root';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_RESERVED_NAMESPACE_USED => true,
        self::REASON_RESERVED_DIRECTIVE_KEY => true,
        self::REASON_RESERVED_TOP_LEVEL_ROOT => true,
    ];

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_-]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_-]{0,63})|\[(?:<key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private readonly string $path;

    /**
     * @param list<non-empty-string> $allowedDirectiveKeys
     */
    private function __construct(
        private readonly string $reason,
        string $path = '',
        private readonly string $reservedNamespace = self::RESERVED_DIRECTIVE_NAMESPACE,
        private readonly array $allowedDirectiveKeys = [],
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('config-reserved-namespace-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('config-reserved-namespace-reason-invalid');
        }

        if ($reservedNamespace === '') {
            throw new \InvalidArgumentException('config-reserved-namespace-empty');
        }

        $this->path = self::safeDiagnosticPath($path);

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function forReservedDirectiveKey(
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_RESERVED_DIRECTIVE_KEY,
            path: $path,
            reservedNamespace: self::RESERVED_DIRECTIVE_NAMESPACE,
            allowedDirectiveKeys: ConfigDirective::keys(),
            previous: $previous,
        );
    }

    public static function forReservedTopLevelRoot(
        string $root,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_RESERVED_TOP_LEVEL_ROOT,
            path: $root,
            reservedNamespace: self::RESERVED_TOP_LEVEL_ROOT_NAMESPACE,
            allowedDirectiveKeys: [],
            previous: $previous,
        );
    }

    public static function withReason(
        string $reason = self::REASON_RESERVED_NAMESPACE_USED,
        string $path = '',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: $reason,
            path: $path,
            reservedNamespace: self::RESERVED_DIRECTIVE_NAMESPACE,
            allowedDirectiveKeys: ConfigDirective::keys(),
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

    public function reservedNamespace(): string
    {
        return $this->reservedNamespace;
    }

    /**
     * @return list<non-empty-string>
     */
    public function allowedDirectiveKeys(): array
    {
        return $this->allowedDirectiveKeys;
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
