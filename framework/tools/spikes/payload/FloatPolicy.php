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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * FloatPolicy (Epic 0.70.0):
 * - Forbids float/NaN/INF/-INF anywhere in a json-like payload.
 *
 * Failure policy (cemented):
 * - throw DeterministicException with code CORETSIA_JSON_FLOAT_FORBIDDEN
 * - exception message contains ONLY the path-to-value (no raw value)
 *
 * Output policy:
 * - MUST NOT emit stdout/stderr.
 */
final class FloatPolicy
{
    private function __construct()
    {
    }

    /**
     * Enforces float-forbidden policy for a json-like payload.
     *
     * @throws DeterministicException CORETSIA_JSON_FLOAT_FORBIDDEN[:path]
     */
    public static function enforce(array $payload): void
    {
        self::walkArray($payload, '');
    }

    private static function walkValue(mixed $value, string $path): void
    {
        if (is_array($value)) {
            self::walkArray($value, $path);
            return;
        }

        // MUST reject any float (including NaN/INF/-INF).
        if (is_float($value)) {
            // Explicit checks (requested by epic), even though they are floats.
            if (\is_nan($value) || \is_infinite($value)) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_JSON_FLOAT_FORBIDDEN,
                    self::safePathOrRoot($path),
                );
            }

            throw new DeterministicException(
                ErrorCodes::CORETSIA_JSON_FLOAT_FORBIDDEN,
                self::safePathOrRoot($path),
            );
        }

        // Other types are not this policy’s concern (handled elsewhere).
    }

    /**
     * @param array $arr
     * @param string $path
     */
    private static function walkArray(array $arr, string $path): void
    {
        if ($arr === []) {
            return;
        }

        if (array_is_list($arr)) {
            foreach ($arr as $i => $v) {
                self::walkValue($v, self::appendIndex($path, (int)$i));
            }
            return;
        }

        foreach ($arr as $k => $v) {
            self::walkValue($v, self::appendKey($path, self::sanitizeKey($k)));
        }
    }

    private static function safePathOrRoot(string $path): string
    {
        // DeterministicException forbids empty messages; root float cannot happen here (root is array),
        // but keep this defensive and deterministic.
        return $path !== '' ? $path : '[root]';
    }

    private static function appendKey(string $path, string $key): string
    {
        if ($path === '') {
            return $key;
        }

        return $path . '.' . $key;
    }

    private static function appendIndex(string $path, int $index): string
    {
        if ($path === '') {
            return '[' . $index . ']';
        }

        return $path . '[' . $index . ']';
    }

    /**
     * Ensure the key segment is safe for DeterministicException message policy:
     * - single-line
     * - no "\" or "=" or "://"
     * - stable mapping (no locale), deterministic replacement
     *
     * @param int|string $key
     * @return string
     */
    private static function sanitizeKey(int|string $key): string
    {
        if (is_int($key)) {
            // Represent non-list int keys as a stable token.
            return 'k' . (string)$key;
        }

        $s = trim($key);
        if ($s === '') {
            return '_';
        }

        // Allow only a conservative safe subset to avoid leaking weird characters into messages.
        // Replace everything else with "_" deterministically.
        $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', $s);
        if (!is_string($s) || $s === '') {
            return '_';
        }

        // Avoid "://" ever appearing due to pathological input (belt-and-suspenders).
        $s = str_replace('://', '_', $s);

        // Keep it short-ish but deterministic (avoid huge messages).
        if (strlen($s) > 120) {
            $s = substr($s, 0, 120);
        }

        return $s !== '' ? $s : '_';
    }
}
