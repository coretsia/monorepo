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

namespace Coretsia\Devtools\InternalToolkit;

final class Json
{
    private function __construct()
    {
    }

    /**
     * Stable JSON encoder for json-like arrays (DTO-friendly).
     *
     * - Maps: keys are sorted ascending by byte-order (strcmp) at every nesting level.
     * - Lists: order is preserved (NOT sorted).
     * - list vs map is classified via array_is_list(...) (cemented).
     * - [] is treated as list (array_is_list([]) === true) and encoded as JSON [].
     *
     * Json-like scalar set (cemented here for DTO policy):
     *  - allowed: null|bool|int|string
     *  - forbidden: float (incl NaN/INF/-INF), objects/resources/callables
     *
     * Encoding flags (cemented):
     *  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
     *
     * Error policy (deterministic):
     *  - float anywhere => InvalidArgumentException("CORETSIA_JSON_FLOAT_FORBIDDEN[:path]")
     *  - unsupported type/key => InvalidArgumentException("CORETSIA_INTERNAL_TOOLKIT_JSON_UNSUPPORTED_TYPE[:path]")
     *
     * @throws \JsonException
     * @throws \InvalidArgumentException
     */
    public static function encodeStable(array $value): string
    {
        $normalized = self::normalizeArray($value, '');

        return json_encode(
            $normalized,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
        );
    }

    private static function normalizeValue(mixed $value, string $path): mixed
    {
        if (is_array($value)) {
            return self::normalizeArray($value, $path);
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            // Explicitly reject float/NaN/INF/-INF (float is forbidden in json-like payloads).
            throw new \InvalidArgumentException(self::codeWithPath('CORETSIA_JSON_FLOAT_FORBIDDEN', $path));
        }

        throw new \InvalidArgumentException(self::codeWithPath('CORETSIA_INTERNAL_TOOLKIT_JSON_UNSUPPORTED_TYPE', $path));
    }

    /**
     * @param array $arr
     * @return array
     */
    private static function normalizeArray(array $arr, string $path): array
    {
        // Empty array is a list by cemented PHP rule; keep as-is.
        if ($arr === []) {
            return [];
        }

        if (array_is_list($arr)) {
            $out = [];
            foreach ($arr as $i => $v) {
                $out[] = self::normalizeValue($v, self::appendIndex($path, (int)$i));
            }
            return $out;
        }

        // Map: keys must be strings (DTO/json-like policy), sort keys by byte-order, recursively normalize values.
        $tmp = [];

        foreach ($arr as $k => $v) {
            if (!is_string($k)) {
                throw new \InvalidArgumentException(self::codeWithPath(
                    'CORETSIA_INTERNAL_TOOLKIT_JSON_UNSUPPORTED_TYPE',
                    self::appendKey($path, (string)$k)
                ));
            }

            $tmp[$k] = self::normalizeValue($v, self::appendKey($path, $k));
        }

        uksort(
            $tmp,
            static fn (string $a, string $b): int => strcmp($a, $b)
        );

        return $tmp;
    }

    private static function codeWithPath(string $code, string $path): string
    {
        if ($path === '') {
            return $code;
        }

        return $code . ':' . $path;
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
}
