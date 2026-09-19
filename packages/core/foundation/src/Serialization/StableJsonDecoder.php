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
 * Deterministic JSON decoder for runtime-safe diagnostics and artifacts.
 *
 * Accepted decoded value types are intentionally narrow:
 *
 * - null
 * - bool
 * - int
 * - string
 * - list<value>
 * - array<string, value>
 *
 * Rejected decoded value types:
 *
 * - float, including decoded decimal/exponent numbers
 * - unsupported runtime values rejected by JsonLikeNormalizer
 * - JSON object keys that cannot be represented safely as PHP string map keys
 *
 * JSON objects are decoded as objects first and then converted to associative
 * arrays by this decoder. This avoids letting PHP associative decoding collapse
 * JSON object/list distinctions too early.
 *
 * The generic decode/decodeStable path is shape-insensitive: empty JSON object
 * `{}` and empty JSON array `[]` both normalize to PHP `[]`.
 *
 * Callers that require a root JSON object must use decodeMap/decodeStableMap.
 * Callers that require a root JSON array must use decodeList/decodeStableList.
 *
 * Nested empty object/list distinction is not preserved by the baseline
 * json-like model. Schema-specific formats must not rely on that distinction
 * unless a shape-preserving decoder is introduced.
 *
 * Baseline json-like value validation and deterministic recursive
 * normalization are delegated to JsonLikeNormalizer.
 *
 * Conversion-stage and normalization-stage JsonLikeNormalizationException
 * failures are mapped to stable `stable-json-*` failures by this decoder.
 *
 * Maps are sorted recursively by byte-order string comparison (`strcmp`) by
 * JsonLikeNormalizer. Lists preserve decoded order.
 *
 * The decoder does not inspect environment variables, does not read files,
 * does not write files, does not emit stdout/stderr, does not perform
 * schema-specific validation, and does not perform redaction. Schema validation
 * and redaction are caller responsibilities.
 *
 * Failure messages are intentionally stable and safe. They must not expose raw
 * JSON payloads, file paths, secrets, tokens, payload fragments, or
 * environment-specific data.
 */
final class StableJsonDecoder
{
    /**
     * Decodes stable JSON bytes into a normalized json-like runtime value.
     *
     * This method is shape-insensitive at the root.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     */
    public function decode(string $json): mixed
    {
        return self::decodeStable($json);
    }

    /**
     * Decodes stable JSON bytes into a normalized json-like runtime value.
     *
     * This method is shape-insensitive at the root.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     */
    public static function decodeStable(string $json): mixed
    {
        return self::convertAndNormalizeValue(self::decodeJson($json));
    }

    /**
     * Decodes stable JSON bytes that must have a JSON object root.
     *
     * The returned value is a PHP string-key map. Empty JSON object `{}` returns
     * an empty PHP array after the root object shape has already been verified.
     *
     * @return array<string, mixed>
     */
    public function decodeMap(string $json): array
    {
        return self::decodeStableMap($json);
    }

    /**
     * Decodes stable JSON bytes that must have a JSON object root.
     *
     * The returned value is a PHP string-key map. Empty JSON object `{}` returns
     * an empty PHP array after the root object shape has already been verified.
     *
     * @return array<string, mixed>
     */
    public static function decodeStableMap(string $json): array
    {
        $decoded = self::decodeJson($json);

        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        return self::convertAndNormalizeObject($decoded);
    }

    /**
     * Decodes stable JSON bytes that must have a JSON array root.
     *
     * @return list<mixed>
     */
    public function decodeList(string $json): array
    {
        return self::decodeStableList($json);
    }

    /**
     * Decodes stable JSON bytes that must have a JSON array root.
     *
     * @return list<mixed>
     */
    public static function decodeStableList(string $json): array
    {
        $decoded = self::decodeJson($json);

        if (!\is_array($decoded) || !\array_is_list($decoded)) {
            throw new \InvalidArgumentException('stable-json-root-list-required');
        }

        return self::convertAndNormalizeList($decoded);
    }

    private static function decodeJson(string $json): mixed
    {
        try {
            return \json_decode(
                json: $json,
                associative: false,
                depth: 512,
                flags: \JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('stable-json-decode-failed', 0, $exception);
        }
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function convertAndNormalizeValue(mixed $decoded): mixed
    {
        try {
            $converted = self::convertDecodedValue($decoded, 'value');

            return JsonLikeNormalizer::normalize($converted, 'value');
        } catch (JsonLikeNormalizationException $exception) {
            throw self::stableJsonFailure($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertAndNormalizeObject(\stdClass $decoded): array
    {
        try {
            $converted = self::convertDecodedObject($decoded, 'value');
            $normalized = JsonLikeNormalizer::normalize($converted, 'value');
        } catch (JsonLikeNormalizationException $exception) {
            throw self::stableJsonFailure($exception);
        }

        if (!\is_array($normalized)) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @param list<mixed> $decoded
     *
     * @return list<mixed>
     */
    private static function convertAndNormalizeList(array $decoded): array
    {
        try {
            $converted = self::convertDecodedList($decoded, 'value');
            $normalized = JsonLikeNormalizer::normalize($converted, 'value');
        } catch (JsonLikeNormalizationException $exception) {
            throw self::stableJsonFailure($exception);
        }

        if (!\is_array($normalized) || !\array_is_list($normalized)) {
            throw new \InvalidArgumentException('stable-json-root-list-required');
        }

        /** @var list<mixed> $normalized */
        return $normalized;
    }

    /**
     * @return null|bool|int|float|string|array<int|string, mixed>
     */
    private static function convertDecodedValue(mixed $value, string $path): mixed
    {
        if (
            $value === null
            || \is_bool($value)
            || \is_int($value)
            || \is_float($value)
            || \is_string($value)
        ) {
            return $value;
        }

        if (\is_array($value)) {
            return self::convertDecodedList($value, $path);
        }

        if ($value instanceof \stdClass) {
            return self::convertDecodedObject($value, $path);
        }

        throw JsonLikeNormalizationException::atPath(
            $path,
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
        );
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<mixed>
     */
    private static function convertDecodedList(array $value, string $path): array
    {
        if (!\array_is_list($value)) {
            throw JsonLikeNormalizationException::atPath(
                $path,
                JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            );
        }

        $converted = [];

        foreach ($value as $index => $item) {
            $converted[] = self::convertDecodedValue(
                $item,
                self::listPath($path, $index),
            );
        }

        return $converted;
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertDecodedObject(\stdClass $value, string $path): array
    {
        $converted = [];

        /** @var array<int|string, mixed> $properties */
        $properties = (array)$value;

        foreach ($properties as $key => $item) {
            if (!\is_string($key) || self::isAmbiguousPhpArrayKey($key)) {
                throw JsonLikeNormalizationException::atPath(
                    $path,
                    JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
                );
            }

            $converted[$key] = self::convertDecodedValue(
                $item,
                self::mapPath($path, $key),
            );
        }

        return $converted;
    }

    private static function isAmbiguousPhpArrayKey(string $key): bool
    {
        return \preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $key) === 1;
    }

    private static function listPath(string $path, int $index): string
    {
        return $path . '[' . $index . ']';
    }

    private static function mapPath(string $path, string $key): string
    {
        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $key) === 1) {
            return $path . '.' . $key;
        }

        if ($key === '') {
            return $path . '[<empty-key>]';
        }

        return $path . '[<key>]';
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
