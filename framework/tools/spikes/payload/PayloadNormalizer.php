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

namespace Coretsia\Tools\Spikes\payload;

/**
 * PayloadNormalizer (Epic 0.70.0):
 * - Deterministic normalization for json-like arrays:
 *   - maps: keys sorted ascending by byte-order (strcmp) at every nesting level
 *   - lists: order preserved (array_is_list)
 *   - empty array [] is treated as list and kept as []
 *
 * Notes:
 * - This is a pure in-memory normalizer (no IO, no output).
 * - Float policy is enforced by FloatPolicy (separate deliverable) and/or by Json::encodeStable.
 * - This class does not print or log and does not embed raw payload values in errors.
 */
final class PayloadNormalizer
{
    private function __construct()
    {
    }

    /**
     * Deterministically normalizes a json-like payload array (maps sorted; lists preserved).
     *
     * @param array $payload
     * @return array
     */
    public static function normalize(array $payload): array
    {
        return self::normalizeArray($payload);
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::normalizeArray($value);
        }

        // Scalar or other types are returned as-is.
        // Type/float enforcement is handled by FloatPolicy + stable encoder.
        return $value;
    }

    /**
     * @param array $arr
     * @return array
     */
    private static function normalizeArray(array $arr): array
    {
        // Cemented: empty array is a list; keep as-is.
        if ($arr === []) {
            return [];
        }

        // List: preserve order, normalize values.
        if (array_is_list($arr)) {
            $out = [];

            foreach ($arr as $v) {
                $out[] = self::normalizeValue($v);
            }

            return $out;
        }

        // Map: normalize values and sort keys by byte-order (strcmp).
        // Json-like policy expects string keys; if caller violates this, stable encoder will reject.
        // Here we keep behavior deterministic and avoid locale-dependent ordering.
        $tmp = [];

        foreach ($arr as $k => $v) {
            // Keep original key if string; otherwise keep it as-is (int keys remain int keys).
            // We must NOT cast here to avoid collisions ("1" vs 1) and silent shape changes.
            $tmp[$k] = self::normalizeValue($v);
        }

        uksort(
            $tmp,
            /**
             * @param int|string $a
             * @param int|string $b
             * @return int
             */
            static function (int|string $a, int|string $b): int {
                // Deterministic compare:
                // - if both strings => strcmp
                // - if both ints => numeric compare
                // - mixed => compare by type-tag first, then by string form (stable)
                $aIsString = is_string($a);
                $bIsString = is_string($b);

                if ($aIsString && $bIsString) {
                    return strcmp($a, $b);
                }

                if (!$aIsString && !$bIsString) {
                    return $a <=> $b;
                }

                // Mixed: stable type order (int < string), then byte-order on string form.
                if (!$aIsString && $bIsString) {
                    return -1;
                }

                if ($aIsString && !$bIsString) {
                    return 1;
                }

                // Unreachable, but keep strict.
                return strcmp((string)$a, (string)$b);
            }
        );

        return $tmp;
    }
}
