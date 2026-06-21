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

namespace Coretsia\Foundation\Serialization;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;

/**
 * Canonical runtime json-like value normalizer.
 *
 * Accepted input types are intentionally narrow:
 *
 * - null
 * - bool
 * - int
 * - string
 * - list<value>
 * - array<string, value>
 *
 * Rejected input types:
 *
 * - float, including NaN, INF, -INF
 * - resource
 * - object, including Closure
 * - map keys that are not strings
 *
 * Maps are sorted recursively by byte-order string comparison (`strcmp`).
 * Lists preserve caller-supplied order.
 *
 * Empty maps and empty lists both normalize to PHP `[]`. This normalizer does
 * not preserve JSON object/list syntax shape and does not distinguish an empty
 * map from an empty list after normalization.
 *
 * Root or nested shape requirements belong to callers or to shape-aware
 * encoder/decoder entrypoints. This normalizer operates only on the baseline
 * runtime json-like value model.
 *
 * Diagnostics are intentionally stable and safe. They include only a safe
 * path-to-value and a stable reason token. They must never include rejected
 * raw values, object class names, resource ids, payloads, secrets, raw SQL,
 * authorization data, cookies, tokens, session ids, absolute local paths, or
 * environment-specific data.
 */
final class JsonLikeNormalizer
{
    private const string SAFE_MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/';

    private const string SAFE_ROOT_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]+\]|\[<key>\]|\[<empty-key>\])*\z/';

    private function __construct()
    {
    }

    /**
     * Normalizes a runtime value into a deterministic json-like shape.
     *
     * This method is shape-insensitive for empty arrays: an empty map and an
     * empty list both normalize to PHP `[]`.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     */
    public static function normalize(mixed $value, string $path = 'value'): mixed
    {
        return self::normalizeValue($value, self::safeRootPath($path));
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeValue(mixed $value, string $path): null|bool|int|string|array
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (\is_float($value)) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            );
        }

        if (\is_resource($value)) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
            );
        }

        if ($value instanceof \Closure) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            );
        }

        if (\is_object($value)) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            );
        }

        if (!\is_array($value)) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            );
        }

        if (\array_is_list($value)) {
            return self::normalizeList($value, $path);
        }

        return self::normalizeMap($value, $path);
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<mixed>
     */
    private static function normalizeList(array $value, string $path): array
    {
        $normalized = [];

        foreach ($value as $index => $item) {
            $normalized[] = self::normalizeValue(
                $item,
                self::listPath($path, $index),
            );
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function normalizeMap(array $value, string $path): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw JsonLikeNormalizationException::atPath(
                    $path,
                    JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
                );
            }

            $normalized[$key] = self::normalizeValue(
                $item,
                self::mapPath($path, $key),
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    private static function listPath(string $path, int $index): string
    {
        return $path . '[' . $index . ']';
    }

    private static function mapPath(string $path, string $key): string
    {
        if (self::isSafeMapKey($key)) {
            if ($path === '') {
                return $key;
            }

            return $path . '.' . $key;
        }

        if ($path === '') {
            return '[' . self::safeKeySegment($key) . ']';
        }

        return $path . '[' . self::safeKeySegment($key) . ']';
    }

    private static function safeRootPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (\preg_match(self::SAFE_ROOT_PATH_PATTERN, $path) === 1) {
            return $path;
        }

        return '[<path>]';
    }

    private static function isSafeMapKey(string $key): bool
    {
        return \preg_match(self::SAFE_MAP_KEY_PATTERN, $key) === 1;
    }

    private static function safeKeySegment(string $key): string
    {
        if ($key === '') {
            return '<empty-key>';
        }

        return '<key>';
    }
}
