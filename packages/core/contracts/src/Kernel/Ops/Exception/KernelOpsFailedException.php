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

namespace Coretsia\Contracts\Kernel\Ops\Exception;

/**
 * Deterministic public failure boundary for unexpected Kernel Ops failures.
 *
 * The public message contains only the stable contracts error code and one
 * allowlisted safe reason token. The exception intentionally never retains a
 * previous Throwable and MUST NOT expose implementation exception messages,
 * filesystem paths, config or env values, fingerprints, generation data,
 * provider details, or stack-trace-derived diagnostics.
 */
final class KernelOpsFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_KERNEL_OPS_FAILED';

    public const string REASON_OPERATION_FAILED = 'operation-failed';
    public const string REASON_HOST_BOOT_FAILED = 'host-boot-failed';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_HOST_BOOT_FAILED => true,
        self::REASON_OPERATION_FAILED => true,
    ];

    private readonly string $reason;

    public function __construct(string $reason)
    {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('kernel-ops-failed-reason-invalid');
        }

        $this->reason = $reason;

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    /**
     * @return non-empty-string
     */
    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    /**
     * @return 'operation-failed'|'host-boot-failed'
     */
    public function reason(): string
    {
        return $this->reason;
    }
}
