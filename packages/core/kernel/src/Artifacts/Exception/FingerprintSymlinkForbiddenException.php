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
 * Deterministic fingerprint file-listing failure for forbidden symlinks.
 *
 * This exception is used by DeterministicFileLister when a symlink is detected
 * before recursion or while listing declared fingerprint input roots.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN: reason-token
 *
 * The message MUST NOT include absolute paths, input path strings, filesystem
 * layout, raw config values, env values, PHP warnings, stack traces, or previous
 * throwable messages.
 *
 * @internal
 */
final class FingerprintSymlinkForbiddenException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN';

    public const string REASON_SYMLINK_FORBIDDEN = 'fingerprint-symlink-forbidden';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_SYMLINK_FORBIDDEN => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('fingerprint-symlink-forbidden-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('fingerprint-symlink-forbidden-reason-invalid');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason = self::REASON_SYMLINK_FORBIDDEN,
        ?\Throwable $previous = null,
    ): self {
        return new self($reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
    }
}
