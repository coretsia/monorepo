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

namespace Coretsia\Kernel\Module\Exception;

/**
 * Abstract Kernel-owned module resolution failure.
 *
 * This class is the base class for Kernel module plan resolution exceptions.
 *
 * It is intentionally owned by `core/kernel`.
 *
 * It is not a contracts port, must not be moved to `core/contracts`, and must
 * not be treated as a cross-package interface. Future adapters may catch this
 * package exception base, but ownership of the exception hierarchy remains with
 * Kernel module resolution policy.
 *
 * The message is intentionally stable and safe. It contains only the stable
 * module resolution error code and a stable reason token:
 *
 *     ERROR_CODE: reason-token
 *
 * Context values are normalized to deterministic scalar/json-like diagnostics
 * and must contain only stable safe identifiers such as module ids, preset
 * names, reason tokens, and stable error codes.
 *
 * Context MUST NOT contain Composer raw payloads, preset raw payloads, service
 * internals, secrets, absolute paths, filesystem layout, environment-specific
 * data, stack traces, or previous throwable messages.
 */
abstract class ModuleResolutionException extends \RuntimeException
{
    private const int MAX_CONTEXT_DEPTH = 8;
    private const int MAX_CONTEXT_KEYS = 64;
    private const int MAX_CONTEXT_KEY_BYTES = 64;
    private const int MAX_CONTEXT_STRING_BYTES = 256;

    private const string SAFE_REASON_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';
    private const string SAFE_CONTEXT_KEY_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.-';
    private const string SAFE_CONTEXT_STRING_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.-';

    /**
     * @var array<string, mixed>
     */
    private readonly array $context;

    /**
     * @param array<string, mixed> $context
     */
    protected function __construct(
        private readonly string $moduleErrorCode,
        private readonly string $reason,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        if (!ModuleErrorCodes::has($moduleErrorCode)) {
            throw new \InvalidArgumentException('module-resolution-error-code-unknown');
        }

        if ($moduleErrorCode === ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING) {
            throw new \InvalidArgumentException('module-resolution-error-code-warning-not-exception');
        }

        if (!self::isSafeReasonToken($reason)) {
            throw new \InvalidArgumentException('module-resolution-reason-invalid');
        }

        $this->context = self::normalizeContext($context);

        parent::__construct(self::message($this->moduleErrorCode, $this->reason), 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->moduleErrorCode;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    private static function message(string $errorCode, string $reason): string
    {
        return $errorCode . ': ' . $reason;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function normalizeContext(array $context): array
    {
        if ($context === []) {
            return [];
        }

        if (\array_is_list($context)) {
            throw new \InvalidArgumentException('module-resolution-context-root-must-be-map');
        }

        return self::normalizeContextMap($context, 0);
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    private static function normalizeContextMap(array $map, int $depth): array
    {
        if ($depth > self::MAX_CONTEXT_DEPTH) {
            throw new \InvalidArgumentException('module-resolution-context-depth-exceeded');
        }

        if (\count($map) > self::MAX_CONTEXT_KEYS) {
            throw new \InvalidArgumentException('module-resolution-context-too-many-keys');
        }

        $normalized = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key) || !self::isSafeContextKey($key)) {
                throw new \InvalidArgumentException('module-resolution-context-key-invalid');
            }

            $normalized[$key] = self::normalizeContextValue($value, $depth + 1);
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function normalizeContextValue(mixed $value, int $depth): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (!self::isSafeContextString($value)) {
                throw new \InvalidArgumentException('module-resolution-context-string-invalid');
            }

            return $value;
        }

        if (\is_array($value)) {
            if ($value === []) {
                return [];
            }

            if (\array_is_list($value)) {
                return self::normalizeContextList($value, $depth);
            }

            return self::normalizeContextMap($value, $depth);
        }

        throw new \InvalidArgumentException('module-resolution-context-value-invalid');
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     */
    private static function normalizeContextList(array $list, int $depth): array
    {
        if ($depth > self::MAX_CONTEXT_DEPTH) {
            throw new \InvalidArgumentException('module-resolution-context-depth-exceeded');
        }

        if (\count($list) > self::MAX_CONTEXT_KEYS) {
            throw new \InvalidArgumentException('module-resolution-context-list-too-long');
        }

        $normalized = [];

        foreach ($list as $value) {
            $normalized[] = self::normalizeContextValue($value, $depth + 1);
        }

        return $normalized;
    }

    private static function isSafeReasonToken(string $reason): bool
    {
        return self::isNonEmptySafeString(
            value: $reason,
            allowedCharacters: self::SAFE_REASON_CHARS,
            maxBytes: self::MAX_CONTEXT_STRING_BYTES,
        );
    }

    private static function isSafeContextKey(string $key): bool
    {
        return self::isNonEmptySafeString(
            value: $key,
            allowedCharacters: self::SAFE_CONTEXT_KEY_CHARS,
            maxBytes: self::MAX_CONTEXT_KEY_BYTES,
        );
    }

    private static function isSafeContextString(string $value): bool
    {
        if (!self::isNonEmptySafeString(
            value: $value,
            allowedCharacters: self::SAFE_CONTEXT_STRING_CHARS,
            maxBytes: self::MAX_CONTEXT_STRING_BYTES,
        )) {
            return false;
        }

        if (\str_contains($value, '..')) {
            return false;
        }

        return true;
    }

    private static function isNonEmptySafeString(
        string $value,
        string $allowedCharacters,
        int $maxBytes,
    ): bool {
        if ($value === '') {
            return false;
        }

        if (\strlen($value) > $maxBytes) {
            return false;
        }

        return \strspn($value, $allowedCharacters) === \strlen($value);
    }
}
