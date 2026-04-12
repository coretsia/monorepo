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

namespace Coretsia\Tools\Spikes\_support;

final class DeterministicException extends \RuntimeException
{
    private string $deterministicCode;

    /**
     * Construction rules (cemented):
     * - $code MUST exist in ErrorCodes registry; otherwise this is a developer error and the ctor MUST throw.
     * - $message MUST be stable + safe (no absolute paths, no dotenv-like secrets).
     *
     * Note: we intentionally do NOT store $code in the native Exception::$code (int).
     */
    public function __construct(string $code, string $message, ?\Throwable $previous = null)
    {
        if (!ErrorCodes::has($code)) {
            // Developer error: unregistered code.
            throw new \InvalidArgumentException('deterministic-error-code-not-registered');
        }

        self::assertSafeMessage($message);

        $this->deterministicCode = $code;

        parent::__construct($message, 0, $previous);
    }

    public function code(): string
    {
        return $this->deterministicCode;
    }

    private static function assertSafeMessage(string $message): void
    {
        // Enforce single-line and block obvious leak vectors (Phase 0 rails strictness).
        if (
            $message === ''
            || str_contains($message, "\0")
            || str_contains($message, "\r")
            || str_contains($message, "\n")
            || str_contains($message, '=')     // dotenv-like KEY=VALUE
            || str_contains($message, '\\')    // Windows paths / escapes
            || str_contains($message, '://')   // URL-like accidental output
            || str_contains($message, "\x1B")  // ANSI escapes
        ) {
            throw new \InvalidArgumentException('deterministic-exception-message-unsafe');
        }

        // Absolute-path hints (cross-OS minimum set).
        $absolutePathPatterns = [
            '/(?i)\b[A-Z]:(\\\\|\/)/',     // Windows drive letter rooted (C:\ or C:/)
            '/\\\\\\\\\S+/',               // Windows UNC prefix \\server\share
            '/\/home\//',                  // POSIX home-like
            '/\/Users\//',                 // macOS home-like
        ];

        foreach ($absolutePathPatterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                throw new \InvalidArgumentException('deterministic-exception-message-unsafe');
            }
        }

        // Also block leading "/" absolute path quickly.
        if (str_starts_with($message, '/')) {
            throw new \InvalidArgumentException('deterministic-exception-message-unsafe');
        }

        // Trim-only (deterministic).
        if (trim($message) === '') {
            throw new \InvalidArgumentException('deterministic-exception-message-unsafe');
        }
    }
}
