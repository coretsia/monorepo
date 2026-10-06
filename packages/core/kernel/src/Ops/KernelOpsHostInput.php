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

namespace Coretsia\Kernel\Ops;

/**
 * Immutable public input for booting the Kernel source operations host.
 *
 * The application root is preserved exactly as accepted. This value performs
 * no filesystem reads, path normalization, realpath resolution, CWD lookup, or
 * target/preset inference.
 */
final readonly class KernelOpsHostInput
{
    public function __construct(
        private string $applicationRoot,
    ) {
        if (!self::isNonEmptySafeSingleLineString($this->applicationRoot)) {
            throw new \InvalidArgumentException('kernel-ops-host-application-root-invalid');
        }
    }

    public function applicationRoot(): string
    {
        return $this->applicationRoot;
    }

    private static function isNonEmptySafeSingleLineString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
