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
 * Invalid Kernel-owned runtime-driver selector configuration.
 *
 * RuntimeDriverInvalidConfigException represents invalid Kernel-owned
 * runtime-driver selector configuration only.
 *
 * It does not represent owner-package prerequisites, module participation,
 * adapter readiness, Worker configuration, or external runtime implementation
 * availability.
 *
 * The message is intentionally stable and contains only the package error code
 * and a stable reason token:
 *
 *     CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: reason-token
 */
final class RuntimeDriverInvalidConfigException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG';

    public const string REASON_CONFIG_KEY_MISSING = 'config-key-missing';
    public const string REASON_CONFIG_KEY_INVALID = 'config-key-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_CONFIG_KEY_MISSING => true,
        self::REASON_CONFIG_KEY_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-reason-invalid');
        }

        parent::__construct(self::message($this->reason));
    }

    public static function configKeyMissing(): self
    {
        return new self(self::REASON_CONFIG_KEY_MISSING);
    }

    public static function configKeyInvalid(): self
    {
        return new self(self::REASON_CONFIG_KEY_INVALID);
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
