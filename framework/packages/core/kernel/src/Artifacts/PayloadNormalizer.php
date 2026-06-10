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

namespace Coretsia\Kernel\Artifacts;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;

/**
 * Kernel-owned artifact payload normalizer.
 *
 * This normalizer prepares artifact payload data before stable JSON/PHP
 * emission. It intentionally delegates the baseline json-like value rules to
 * Foundation JsonLikeNormalizer so Kernel artifacts reuse the canonical stable
 * JSON semantics instead of redefining them.
 *
 * Accepted values are the canonical json-like values:
 *
 * - null
 * - bool
 * - int
 * - string
 * - list<value>
 * - array<string, value>
 *
 * Determinism rules:
 *
 * - maps are sorted recursively by byte-order string comparison (`strcmp`);
 * - lists preserve caller-supplied order exactly;
 * - nested arrays are normalized recursively;
 * - scalar values are preserved without semantic conversion.
 *
 * Rejected values:
 *
 * - float, including NaN, INF, and -INF;
 * - resource;
 * - object, including Closure;
 * - map keys that are not strings;
 * - any non-scalar/non-array value outside the accepted json-like set.
 *
 * Diagnostics are intentionally stable and safe. Messages include only stable
 * reason tokens and safe path-to-value tokens such as `payload.a[3].c`.
 * Messages MUST NOT include rejected raw values, object class names, resource
 * ids, payload dumps, config values, env values, secrets, raw SQL, local paths,
 * stack traces, or previous throwable messages.
 *
 * @internal
 */
final class PayloadNormalizer
{
    private const string DEFAULT_ROOT_PATH = 'payload';

    private const string UNSAFE_PATH_PLACEHOLDER = '<path>';

    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string SAFE_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})|\[(?:<key>|<empty-key>|[0-9]{1,9})\])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql|raw|payloadvalue|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    /**
     * Normalizes an artifact payload into a deterministic json-like shape.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException for other non-json-like values.
     */
    public function normalize(mixed $payload, string $path = self::DEFAULT_ROOT_PATH): mixed
    {
        return self::normalizePayload($payload, $path);
    }

    /**
     * Static convenience wrapper for deterministic artifact payload
     * normalization.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException for other non-json-like values.
     */
    public static function normalizePayload(mixed $payload, string $path = self::DEFAULT_ROOT_PATH): mixed
    {
        try {
            return JsonLikeNormalizer::normalize($payload, self::safeRootPath($path));
        } catch (JsonLikeNormalizationException $exception) {
            self::throwMappedException($exception);
        }
    }

    /**
     * Normalizes an artifact payload that must be a map.
     *
     * Artifact builders normally produce schema-specific payload maps. This
     * helper keeps that common boundary explicit while still reusing the same
     * canonical json-like normalization rules.
     *
     * @return array<string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException when the normalized payload is not a map.
     */
    public function normalizeMap(array $payload, string $path = self::DEFAULT_ROOT_PATH): array
    {
        return self::normalizePayloadMap($payload, $path);
    }

    /**
     * Static convenience wrapper for schema-specific artifact payload maps.
     *
     * @return array<string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException when the normalized payload is not a map.
     */
    public static function normalizePayloadMap(array $payload, string $path = self::DEFAULT_ROOT_PATH): array
    {
        if (\array_is_list($payload)) {
            throw ArtifactPayloadInvalidException::atPath(
                self::safeDiagnosticPath($path),
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        $normalized = self::normalizePayload($payload, $path);

        if (!\is_array($normalized) || \array_is_list($normalized)) {
            throw ArtifactPayloadInvalidException::atPath(
                self::safeDiagnosticPath($path),
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function throwMappedException(JsonLikeNormalizationException $exception): never
    {
        if ($exception->reason() === JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN) {
            throw JsonFloatForbiddenException::atPath(
                $exception->path(),
                $exception,
            );
        }

        throw ArtifactPayloadInvalidException::atPath(
            self::safeDiagnosticPath($exception->path()),
            self::mapReason($exception->reason()),
            $exception,
        );
    }

    private static function mapReason(string $reason): string
    {
        return match ($reason) {
            JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => ArtifactPayloadInvalidException::REASON_RESOURCE_FORBIDDEN,
            JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => ArtifactPayloadInvalidException::REASON_CLOSURE_FORBIDDEN,
            JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => ArtifactPayloadInvalidException::REASON_OBJECT_FORBIDDEN,
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => ArtifactPayloadInvalidException::REASON_MAP_KEY_MUST_BE_STRING,
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            default => ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
        };
    }

    private static function safeRootPath(string $path): string
    {
        if ($path === '') {
            return self::DEFAULT_ROOT_PATH;
        }

        return self::safeDiagnosticPath($path);
    }

    private static function safeDiagnosticPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (!self::isSafeDiagnosticPath($path)) {
            return self::UNSAFE_PATH_PLACEHOLDER;
        }

        return $path;
    }

    private static function isSafeDiagnosticPath(string $path): bool
    {
        if (\strlen($path) > self::MAX_SAFE_PATH_BYTES) {
            return false;
        }

        if (\preg_match(self::SAFE_PATH_PATTERN, $path) !== 1) {
            return false;
        }

        if (\preg_match(self::CONTROL_CHARACTER_PATTERN, $path) === 1) {
            return false;
        }

        if (\preg_match(self::SENSITIVE_PATH_PATTERN, $path) === 1) {
            return false;
        }

        if (\preg_match(self::SQL_LIKE_PATTERN, $path) === 1) {
            return false;
        }

        return true;
    }
}
