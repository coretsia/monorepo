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

namespace Coretsia\Platform\Metrics\Labels;

/**
 * Stateless admission of canonical global metric label keys and scalar types.
 *
 * The key set mirrors the global allowlist in docs/ssot/observability.md.
 * Metric-specific catalog restrictions, safe value derivation, and bounded
 * cardinality remain separate producer and recording-backend obligations.
 *
 * This helper performs no normalization, coercion, redaction, filesystem
 * access, configuration lookup, or metric emission.
 */
final class AllowedLabelSet
{
    /**
     * @var array<non-empty-string, true>
     */
    private const array ALLOWED_KEYS = [
        'method' => true,
        'status' => true,
        'driver' => true,
        'operation' => true,
        'table' => true,
        'outcome' => true,
    ];

    /**
     * @param array<array-key, mixed> $labels
     */
    public static function accepts(array $labels): bool
    {
        foreach ($labels as $key => $value) {
            if (
                !\is_string($key)
                || !isset(self::ALLOWED_KEYS[$key])
                || (!\is_string($value) && !\is_int($value) && !\is_bool($value))
            ) {
                return false;
            }
        }

        return true;
    }
}
