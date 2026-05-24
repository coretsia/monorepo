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
 * Baseline json-like value validation and deterministic recursive
 * normalization are delegated to JsonLikeNormalizer.
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
        try {
            $normalized = JsonLikeNormalizer::normalize($value, 'value');
        } catch (JsonLikeNormalizationException $exception) {
            throw new \InvalidArgumentException(
                self::message($exception->path(), self::mapReason($exception->reason())),
                0,
                $exception,
            );
        }

        try {
            $json = \json_encode(
                $normalized,
                \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('stable-json-encode-failed', 0, $exception);
        }

        if (!\is_string($json)) {
            throw new \InvalidArgumentException('stable-json-encode-failed');
        }

        return $json . "\n";
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
