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
 * Deterministic Kernel runtime lifecycle failure.
 *
 * This exception is used by KernelRuntime, hook invocation, and hook payload
 * normalization boundaries.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token. It must never include raw context
 * arrays, hook payloads, transport payloads, tokens, cookies, raw SQL, object
 * dumps, local paths, environment-specific data, or previous throwable
 * messages.
 */
final class KernelRuntimeException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_KERNEL_RUNTIME_ERROR';

    public const string REASON_INVALID_TYPE = 'kernel-runtime-invalid-type';
    public const string REASON_INVALID_OUTCOME = 'kernel-runtime-invalid-outcome';
    public const string REASON_INVALID_CONTEXT = 'kernel-runtime-invalid-context';
    public const string REASON_INVALID_RESULT = 'kernel-runtime-invalid-result';
    public const string REASON_UOW_ATTRIBUTES_MAX_DEPTH_INVALID = 'kernel-runtime-uow-attributes-max-depth-invalid';
    public const string REASON_UOW_ATTRIBUTES_MAX_KEYS_INVALID = 'kernel-runtime-uow-attributes-max-keys-invalid';
    public const string REASON_HOOK_SERVICE_NOT_FOUND = 'kernel-runtime-hook-service-not-found';
    public const string REASON_HOOK_SERVICE_INVALID = 'kernel-runtime-hook-service-invalid';
    public const string REASON_HOOK_PAYLOAD_INVALID = 'kernel-runtime-hook-payload-invalid';
    public const string REASON_RESET_FAILED = 'kernel-runtime-reset-failed';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID_TYPE => true,
        self::REASON_INVALID_OUTCOME => true,
        self::REASON_INVALID_CONTEXT => true,
        self::REASON_INVALID_RESULT => true,
        self::REASON_UOW_ATTRIBUTES_MAX_DEPTH_INVALID => true,
        self::REASON_UOW_ATTRIBUTES_MAX_KEYS_INVALID => true,
        self::REASON_HOOK_SERVICE_NOT_FOUND => true,
        self::REASON_HOOK_SERVICE_INVALID => true,
        self::REASON_HOOK_PAYLOAD_INVALID => true,
        self::REASON_RESET_FAILED => true,
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
            throw new \InvalidArgumentException('kernel-runtime-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('kernel-runtime-reason-invalid');
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
