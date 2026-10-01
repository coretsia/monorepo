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

namespace Coretsia\Kernel\DependencySync\Exception;

final class DependencySyncException extends \RuntimeException
{
    private const int MAX_CONTEXT_VALUE_BYTES = 256;

    private const string SAFE_CONTEXT_VALUE_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.-';

    /** @var array<string, true> */
    private const array CONTEXT_KEYS = [
        'causeCode' => true,
        'recoveryReceiptId' => true,
        'sourceErrorCode' => true,
        'sourceReason' => true,
    ];

    /** @var array<string, non-empty-string> */
    private readonly array $context;

    /** @param array<string, non-empty-string> $context */
    private function __construct(
        private readonly string $dependencySyncErrorCode,
        private readonly string $reason,
        array $context,
    ) {
        $this->context = $context;

        parent::__construct($this->dependencySyncErrorCode . ': ' . $this->reason);
    }

    /** @param array<string, non-empty-string> $context */
    public static function forCode(
        string $errorCode,
        array $context = [],
    ): self {
        if (!DependencySyncErrorCodes::has($errorCode)) {
            throw new \InvalidArgumentException('dependency-sync-error-code-unknown');
        }

        return new self(
            dependencySyncErrorCode: $errorCode,
            reason: self::reasonForCode($errorCode),
            context: self::normalizeContext($context),
        );
    }

    public function errorCode(): string
    {
        return $this->dependencySyncErrorCode;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** @return array<string, non-empty-string> */
    public function context(): array
    {
        return $this->context;
    }

    private static function reasonForCode(string $errorCode): string
    {
        return \strtolower(
            \str_replace('_', '-', $errorCode),
        );
    }

    /**
     * @param array<string, non-empty-string> $context
     *
     * @return array<string, non-empty-string>
     */
    private static function normalizeContext(array $context): array
    {
        if ($context === []) {
            return [];
        }

        if (
            \array_is_list($context)
            || \count($context) > \count(self::CONTEXT_KEYS)
        ) {
            throw new \InvalidArgumentException('dependency-sync-context-invalid');
        }

        $normalized = [];

        foreach ($context as $key => $value) {
            if (
                !\is_string($key)
                || !isset(self::CONTEXT_KEYS[$key])
                || !\is_string($value)
                || $value === ''
                || \strlen($value) > self::MAX_CONTEXT_VALUE_BYTES
                || \strspn($value, self::SAFE_CONTEXT_VALUE_CHARS) !== \strlen($value)
            ) {
                throw new \InvalidArgumentException('dependency-sync-context-invalid');
            }

            if (
                $key === 'causeCode'
                && !DependencySyncErrorCodes::has($value)
            ) {
                throw new \InvalidArgumentException('dependency-sync-context-cause-code-invalid');
            }

            if (
                $key === 'recoveryReceiptId'
                && \preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $value) !== 1
            ) {
                throw new \InvalidArgumentException('dependency-sync-context-recovery-receipt-id-invalid');
            }

            $normalized[$key] = $value;
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }
}
