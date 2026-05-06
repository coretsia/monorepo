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

/**
 * Deterministic JSON encoder for runtime-safe diagnostics and artifacts.
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
 * The encoder does not inspect environment variables and does not perform
 * redaction. Redaction is the caller's responsibility.
 */
final class StableJsonEncoder
{
    /**
     * Encodes a JSON-safe deterministic value into stable JSON bytes.
     *
     * The returned JSON string always ends with a final LF.
     */
    public function encode(mixed $value): string
    {
        return self::encodeStable($value);
    }

    /**
     * Encodes a JSON-safe deterministic value into stable JSON bytes.
     *
     * The returned JSON string always ends with a final LF.
     */
    public static function encodeStable(mixed $value): string
    {
        $normalized = self::normalize($value);

        try {
            $json = \json_encode(
                $normalized,
                \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new \InvalidArgumentException('stable-json-encode-failed');
        }

        if (!\is_string($json)) {
            throw new \InvalidArgumentException('stable-json-encode-failed');
        }

        return $json . "\n";
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalize(mixed $value): null|bool|int|string|array
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (\is_float($value)) {
            throw new \InvalidArgumentException('stable-json-float-forbidden');
        }

        if (\is_resource($value)) {
            throw new \InvalidArgumentException('stable-json-resource-forbidden');
        }

        if ($value instanceof \Closure) {
            throw new \InvalidArgumentException('stable-json-closure-forbidden');
        }

        if (\is_object($value)) {
            throw new \InvalidArgumentException('stable-json-object-forbidden');
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException('stable-json-type-forbidden');
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalize($item);
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('stable-json-map-key-must-be-string');
            }

            $normalized[$key] = self::normalize($item);
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }
}
