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
 * The generic encode/encodeStable path is shape-insensitive: PHP `[]` is
 * encoded as JSON array `[]`.
 *
 * Callers that require a root JSON object must use encodeMap/encodeStableMap.
 * Callers that require a root JSON array must use encodeList/encodeStableList.
 *
 * Empty root maps encoded through encodeMap/encodeStableMap are emitted as
 * JSON object `{}`. Empty root lists encoded through encodeList/encodeStableList
 * are emitted as JSON array `[]`.
 *
 * Nested empty map/list distinction is not preserved by the baseline json-like
 * model. Schema-specific formats must not rely on that distinction unless a
 * shape-preserving encoder is introduced.
 *
 * Baseline json-like value validation and deterministic recursive
 * normalization are delegated to JsonLikeNormalizer.
 *
 * Maps are sorted recursively by byte-order string comparison (`strcmp`).
 * Lists preserve caller-supplied order.
 *
 * The encoder does not inspect environment variables, does not read files,
 * does not write files, does not emit stdout/stderr, does not perform
 * schema-specific validation, and does not perform redaction. Schema validation
 * and redaction are caller responsibilities.
 *
 * Failure messages are intentionally stable and safe. They must not expose raw
 * values, file paths, secrets, tokens, payload fragments, or
 * environment-specific data.
 */
final class StableJsonEncoder
{
    /**
     * Encodes a JSON-safe deterministic value into stable JSON bytes.
     *
     * This method is shape-insensitive at the root.
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
     * This method is shape-insensitive at the root.
     *
     * The returned JSON string always ends with a final LF.
     */
    public static function encodeStable(mixed $value): string
    {
        return self::encodeNormalized(self::normalizeValue($value));
    }

    /**
     * Encodes a JSON-safe deterministic map into stable JSON object bytes.
     *
     * Empty PHP array `[]` is treated as an empty root map and encoded as `{}`.
     *
     * The returned JSON string always ends with a final LF.
     */
    public function encodeMap(mixed $value): string
    {
        return self::encodeStableMap($value);
    }

    /**
     * Encodes a JSON-safe deterministic map into stable JSON object bytes.
     *
     * Empty PHP array `[]` is treated as an empty root map and encoded as `{}`.
     *
     * The returned JSON string always ends with a final LF.
     */
    public static function encodeStableMap(mixed $value): string
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        if ($value === []) {
            return "{}\n";
        }

        if (\array_is_list($value)) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        $normalized = self::normalizeValue($value);

        if (!\is_array($normalized) || $normalized === [] || \array_is_list($normalized)) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        return self::encodeNormalized($normalized);
    }

    /**
     * Encodes a JSON-safe deterministic list into stable JSON array bytes.
     *
     * The returned JSON string always ends with a final LF.
     */
    public function encodeList(mixed $value): string
    {
        return self::encodeStableList($value);
    }

    /**
     * Encodes a JSON-safe deterministic list into stable JSON array bytes.
     *
     * The returned JSON string always ends with a final LF.
     */
    public static function encodeStableList(mixed $value): string
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw new \InvalidArgumentException('stable-json-root-list-required');
        }

        $normalized = self::normalizeValue($value);

        if (!\is_array($normalized) || !\array_is_list($normalized)) {
            throw new \InvalidArgumentException('stable-json-root-list-required');
        }

        return self::encodeNormalized($normalized);
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeValue(mixed $value): mixed
    {
        try {
            return JsonLikeNormalizer::normalize($value, 'value');
        } catch (JsonLikeNormalizationException $exception) {
            throw self::stableJsonFailure($exception);
        }
    }

    /**
     * @param null|bool|int|string|array<int|string, mixed> $normalized
     */
    private static function encodeNormalized(mixed $normalized): string
    {
        try {
            $json = \json_encode(
                value: $normalized,
                flags: \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_THROW_ON_ERROR,
                depth: 512,
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('stable-json-encode-failed', 0, $exception);
        }

        if (!\is_string($json)) {
            throw new \InvalidArgumentException('stable-json-encode-failed');
        }

        return $json . "\n";
    }

    private static function stableJsonFailure(JsonLikeNormalizationException $exception): \InvalidArgumentException
    {
        return new \InvalidArgumentException(
            self::message($exception->path(), self::mapReason($exception->reason())),
            0,
            $exception,
        );
    }

    private static function mapReason(string $reason): string
    {
        return match ($reason) {
            JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN => 'stable-json-float-forbidden',
            JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => 'stable-json-resource-forbidden',
            JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => 'stable-json-closure-forbidden',
            JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => 'stable-json-object-forbidden',
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => 'stable-json-map-key-must-be-string',
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => 'stable-json-type-forbidden',
            default => 'stable-json-type-forbidden',
        };
    }

    private static function message(string $path, string $reason): string
    {
        if ($path === '') {
            return $reason;
        }

        return $reason . ': value at ' . $path;
    }
}
