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

namespace Coretsia\Kernel\Boot\Exception;

/**
 * Deterministic Kernel Bootstrap Phase A failure.
 *
 * This exception is used by Kernel bootstrap input resolution, dotenv loading,
 * bootstrap-only overrides loading, and env source policy validation.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token. It must never include dotenv values,
 * raw env values, raw override arrays, raw PHP warnings, OS error messages,
 * absolute paths, tokens, cookies, Authorization values, secrets, stack traces,
 * or environment-specific data.
 */
final class BootstrapException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_BOOTSTRAP_FAILED';

    public const string REASON_INVALID_APP_TARGET = 'bootstrap-invalid-app-target';
    public const string REASON_INVALID_SKELETON_ROOT = 'bootstrap-invalid-skeleton-root';
    public const string REASON_DOTENV_FILE_INVALID = 'bootstrap-dotenv-file-invalid';
    public const string REASON_DOTENV_LOAD_FAILED = 'bootstrap-dotenv-load-failed';
    public const string REASON_OVERRIDES_INVALID = 'bootstrap-overrides-invalid';
    public const string REASON_ENV_SOURCE_POLICY_INVALID = 'bootstrap-env-source-policy-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID_APP_TARGET => true,
        self::REASON_INVALID_SKELETON_ROOT => true,
        self::REASON_DOTENV_FILE_INVALID => true,
        self::REASON_DOTENV_LOAD_FAILED => true,
        self::REASON_OVERRIDES_INVALID => true,
        self::REASON_ENV_SOURCE_POLICY_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason,
        ?\Throwable $previous = null,
    ): self {
        if ($reason === '') {
            throw new \InvalidArgumentException('bootstrap-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('bootstrap-reason-invalid');
        }

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
