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
 * Generic normalization intentionally collapses nested empty objects/lists.
 * The opt-in strict string-map decoders validate native JSON shapes first,
 * reject duplicate object keys at every depth, and enforce resource bounds.
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
    private const int STRICT_MAX_BYTES = 65_536;
    private const int STRICT_MAX_DEPTH = 64;
    private const int STRICT_MAX_OBJECT_KEYS = 4_096;

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

    /**
     * Opt-in bounded JSON object -> map of string lists.
     * JSON object values are checked before stdClass/list normalization.
     *
     * @return array<string, list<string>>
     */
    public static function decodeStrictStringListMap(string $json): array
    {
        $decoded = self::decodeStrictObject($json);
        $result = [];

        foreach (\get_object_vars($decoded) as $key => $items) {
            if (!\is_string($key) || self::isAmbiguousPhpArrayKey($key)) {
                throw new \InvalidArgumentException('stable-json-map-key-must-be-string');
            }

            if (!\is_array($items) || !\array_is_list($items)) {
                throw new \InvalidArgumentException('stable-json-string-list-required');
            }

            foreach ($items as $item) {
                if (!\is_string($item)) {
                    throw new \InvalidArgumentException('stable-json-string-list-required');
                }
            }

            $result[$key] = $items;
        }

        \ksort($result, \SORT_STRING);

        return $result;
    }

    /** @return array<string, string> */
    public static function decodeStrictStringMap(string $json): array
    {
        $decoded = self::decodeStrictObject($json);
        $result = [];

        foreach (\get_object_vars($decoded) as $key => $value) {
            if (!\is_string($key) || self::isAmbiguousPhpArrayKey($key)) {
                throw new \InvalidArgumentException('stable-json-map-key-must-be-string');
            }

            if (!\is_string($value)) {
                throw new \InvalidArgumentException('stable-json-string-value-required');
            }

            $result[$key] = $value;
        }

        \ksort($result, \SORT_STRING);

        return $result;
    }

    /**
     * Shared duplicate-object-key guard for already syntactically validated JSON.
     * Scans only object-key tokens; native json_decode remains the JSON parser.
     * Escaped-equivalent keys are compared after JSON string decoding.
     *
     * @throws \InvalidArgumentException with a payload-free reason
     */
    public static function assertNoDuplicateObjectKeys(
        string $json,
        int $maxBytes = self::STRICT_MAX_BYTES,
        int $maxDepth = self::STRICT_MAX_DEPTH,
        int $maxKeys = self::STRICT_MAX_OBJECT_KEYS,
    ): void {
        $length = \strlen($json);

        if (
            $maxBytes < 1 || $maxDepth < 1 || $maxDepth > 512 || $maxKeys < 1
            || $length > $maxBytes
        ) {
            throw new \InvalidArgumentException('stable-json-bounds-invalid');
        }

        $stack = [];
        $keyCount = 0;

        for ($index = 0; $index < $length; $index++) {
            $char = $json[$index];

            if ($char === '{' || $char === '[') {
                if (\count($stack) >= $maxDepth) {
                    throw new \InvalidArgumentException('stable-json-depth-invalid');
                }

                $stack[] = ['object' => $char === '{', 'seen' => []];
                continue;
            }

            if ($char === '}' || $char === ']') {
                if ($stack === []) {
                    throw new \InvalidArgumentException('stable-json-structure-invalid');
                }

                \array_pop($stack);
                continue;
            }

            if ($char !== '"') {
                continue;
            }

            $start = $index;
            $escaped = false;

            for ($index++; $index < $length; $index++) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($json[$index] === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($json[$index] === '"') {
                    break;
                }
            }

            if ($index >= $length) {
                throw new \InvalidArgumentException('stable-json-string-invalid');
            }

            $next = $index + 1;

            while ($next < $length && \str_contains(" \t\r\n", $json[$next])) {
                $next++;
            }

            if (
                $next >= $length || $json[$next] !== ':' || $stack === []
                || !$stack[\array_key_last($stack)]['object']
            ) {
                continue;
            }

            if (++$keyCount > $maxKeys) {
                throw new \InvalidArgumentException('stable-json-key-count-invalid');
            }

            try {
                $key = \json_decode(
                    \substr($json, $start, $index - $start + 1),
                    false,
                    2,
                    \JSON_THROW_ON_ERROR,
                );
            } catch (\JsonException) {
                throw new \InvalidArgumentException('stable-json-object-key-invalid');
            }

            if (!\is_string($key)) {
                throw new \InvalidArgumentException('stable-json-object-key-invalid');
            }

            $frame = \array_key_last($stack);

            if (isset($stack[$frame]['seen'][$key])) {
                throw new \InvalidArgumentException('stable-json-duplicate-object-key');
            }

            $stack[$frame]['seen'][$key] = true;
        }
    }

    private static function decodeStrictObject(string $json): \stdClass
    {
        if (\strlen($json) > self::STRICT_MAX_BYTES) {
            throw new \InvalidArgumentException('stable-json-bounds-invalid');
        }

        $decoded = self::decodeJson($json, self::STRICT_MAX_DEPTH);

        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException('stable-json-root-map-required');
        }

        self::assertNoDuplicateObjectKeys($json);

        return $decoded;
    }

    private static function decodeJson(string $json, int $depth = 512): mixed
    {
        try {
            return \json_decode(
                json: $json,
                associative: false,
                depth: $depth,
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
        $properties = (array) $value;

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
