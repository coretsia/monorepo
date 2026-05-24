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

namespace Coretsia\Kernel\Runtime\Internal;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;

/**
 * Internal json-like shape wrapper/guard for Kernel UnitOfWork shapes.
 *
 * This class is intentionally internal:
 *
 * - it is not a public Kernel API;
 * - it is not a DI service;
 * - it is not a transport extension point;
 * - it exists only to keep UnitOfWorkContext and UnitOfWorkResult validation
 *   behavior consistent.
 *
 * Baseline json-like value validation and deterministic recursive
 * normalization are delegated to Foundation JsonLikeNormalizer.
 *
 * The Kernel wrapper preserves only UoW-specific policy:
 *
 * - root map policy for attributes/extensions/error;
 * - unsafe metadata key denylist;
 * - attributes max-depth and max-keys limits;
 * - safe string and safe single-line string checks;
 * - UoW-specific exception reason mapping;
 * - diagnostics include path/reason only and never raw values.
 *
 * @internal
 */
final class JsonLikeShapeNormalizer
{
    /**
     * Normalized semantic metadata-key denylist.
     *
     * These are not PII-detection heuristics. They are deterministic guards for
     * known unsafe metadata key names reserved by the Kernel UoW safety policy.
     *
     * Keys are normalized by lowercasing ASCII and removing `_`, `-`, and `.`
     * before comparison. For example:
     *
     * - `sessionId`, `session_id`, `session-id`, `session.id` => `sessionid`
     * - `accessToken`, `access_token`, `access-token` => `accesstoken`
     * - `stackTrace`, `stack_trace`, `stack-trace` => `stacktrace`
     *
     * @var list<string>
     */
    private const array UNSAFE_METADATA_KEY_TOKENS = [
        'authorization',
        'cookie',
        'cookies',
        'setcookie',
        'session',
        'sessionid',
        'token',
        'tokens',
        'accesstoken',
        'refreshtoken',
        'password',
        'secret',
        'credential',
        'credentials',
        'dsn',
        'raw',
        'rawbody',
        'rawpayload',
        'payload',
        'rawsql',
        'sql',
        'stacktrace',
        'trace',
        'email',
        'phone',
        'username',
        'fullname',
        'userid',
        'tenantid',
    ];

    private function __construct()
    {
    }

    /**
     * Normalizes UnitOfWorkContext.attributes.
     *
     * @param array<string, mixed> $attributes
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxKeys
     *
     * @return array<string, mixed>
     */
    public static function normalizeContextAttributes(
        array $attributes,
        int $maxDepth,
        int $maxKeys,
    ): array {
        if ($maxDepth < 1) {
            throw UnitOfWorkContextInvalidException::atPath(
                'attributes',
                'uow-context-attributes-max-depth-invalid',
            );
        }

        if ($maxKeys < 1) {
            throw UnitOfWorkContextInvalidException::atPath(
                'attributes',
                'uow-context-attributes-max-keys-invalid',
            );
        }

        if ($attributes === []) {
            return [];
        }

        if (\array_is_list($attributes)) {
            throw UnitOfWorkContextInvalidException::atPath(
                'attributes',
                'uow-context-attributes-root-map-required',
            );
        }

        $normalized = self::normalizeContextBaseline($attributes, 'attributes');

        $keyCount = 0;

        self::assertContextMapPolicy(
            map: $normalized,
            path: 'attributes',
            depth: 1,
            maxDepth: $maxDepth,
            maxKeys: $maxKeys,
            keyCount: $keyCount,
        );

        return $normalized;
    }

    /**
     * Normalizes UnitOfWorkResult.extensions.
     *
     * @param array<string, mixed> $extensions
     *
     * @return array<string, mixed>
     */
    public static function normalizeResultExtensions(array $extensions): array
    {
        if ($extensions === []) {
            return [];
        }

        if (\array_is_list($extensions)) {
            throw UnitOfWorkResultInvalidException::atPath(
                'extensions',
                'uow-result-extensions-root-map-required',
            );
        }

        $normalized = self::normalizeResultBaseline($extensions, 'extensions');

        self::assertResultMapPolicy(
            map: $normalized,
            path: 'extensions',
            rejectUnsafeKeys: true,
        );

        return $normalized;
    }

    /**
     * Normalizes the exported UnitOfWorkResult.error map derived from ErrorDescriptor.
     *
     * @param array<string, mixed> $error
     *
     * @return array<string, mixed>
     */
    public static function normalizeExportedErrorMap(array $error): array
    {
        if ($error === []) {
            throw UnitOfWorkResultInvalidException::atPath(
                'error',
                'uow-result-error-map-empty',
            );
        }

        if (\array_is_list($error)) {
            throw UnitOfWorkResultInvalidException::atPath(
                'error',
                'uow-result-error-map-required',
            );
        }

        $normalized = self::normalizeResultBaseline($error, 'error');

        self::assertResultMapPolicy(
            map: $normalized,
            path: 'error',
            rejectUnsafeKeys: false,
        );

        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function normalizeContextBaseline(array $value, string $path): array
    {
        try {
            $normalized = JsonLikeNormalizer::normalize($value, $path);
        } catch (JsonLikeNormalizationException $exception) {
            throw UnitOfWorkContextInvalidException::atPath(
                $exception->path(),
                self::mapContextReason($exception->reason()),
                $exception,
            );
        }

        if (!\is_array($normalized)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-type-forbidden',
            );
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function normalizeResultBaseline(array $value, string $path): array
    {
        try {
            $normalized = JsonLikeNormalizer::normalize($value, $path);
        } catch (JsonLikeNormalizationException $exception) {
            throw UnitOfWorkResultInvalidException::atPath(
                $exception->path(),
                self::mapResultReason($exception->reason()),
                $exception,
            );
        }

        if (!\is_array($normalized)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-type-forbidden',
            );
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @param array<string, mixed> $map
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxKeys
     */
    private static function assertContextMapPolicy(
        array $map,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): void {
        self::assertContextDepth($path, $depth, $maxDepth);

        foreach ($map as $key => $value) {
            if (!self::isSafeSingleLineString($key)) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $path,
                    'uow-context-attributes-map-key-invalid',
                );
            }

            $keyPath = self::pathForPolicyKey($path, $key);

            self::assertSafeContextAttributeKey($key, $keyPath);

            ++$keyCount;

            if ($keyCount > $maxKeys) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $keyPath,
                    'uow-context-attributes-max-keys-exceeded',
                );
            }

            self::assertContextValuePolicy(
                value: $value,
                path: $keyPath,
                depth: $depth,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );
        }
    }

    /**
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxKeys
     */
    private static function assertContextValuePolicy(
        mixed $value,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): void {
        if (\is_string($value)) {
            if (!self::isSafeString($value)) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $path,
                    'uow-context-attributes-string-invalid',
                );
            }

            return;
        }

        if (!\is_array($value)) {
            return;
        }

        if (\array_is_list($value)) {
            self::assertContextListPolicy(
                list: $value,
                path: $path,
                depth: $depth + 1,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );

            return;
        }

        self::assertContextMapPolicy(
            map: $value,
            path: $path,
            depth: $depth + 1,
            maxDepth: $maxDepth,
            maxKeys: $maxKeys,
            keyCount: $keyCount,
        );
    }

    /**
     * @param list<mixed> $list
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxKeys
     */
    private static function assertContextListPolicy(
        array $list,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): void {
        self::assertContextDepth($path, $depth, $maxDepth);

        foreach ($list as $index => $value) {
            self::assertContextValuePolicy(
                value: $value,
                path: $path . '[' . $index . ']',
                depth: $depth,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     */
    private static function assertResultMapPolicy(
        array $map,
        string $path,
        bool $rejectUnsafeKeys,
    ): void {
        foreach ($map as $key => $value) {
            if (!self::isSafeSingleLineString($key)) {
                throw UnitOfWorkResultInvalidException::atPath(
                    $path,
                    'uow-result-map-key-invalid',
                );
            }

            $keyPath = $rejectUnsafeKeys
                ? self::pathForPolicyKey($path, $key)
                : self::pathForKey($path, $key);

            if ($rejectUnsafeKeys) {
                self::assertSafeResultExtensionKey($key, $keyPath);
            }

            self::assertResultValuePolicy(
                value: $value,
                path: $keyPath,
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );
        }
    }

    private static function assertResultValuePolicy(
        mixed $value,
        string $path,
        bool $rejectUnsafeKeys,
    ): void {
        if (\is_string($value)) {
            if (!self::isSafeString($value)) {
                throw UnitOfWorkResultInvalidException::atPath(
                    $path,
                    'uow-result-string-invalid',
                );
            }

            return;
        }

        if (!\is_array($value)) {
            return;
        }

        if (\array_is_list($value)) {
            self::assertResultListPolicy(
                list: $value,
                path: $path,
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );

            return;
        }

        self::assertResultMapPolicy(
            map: $value,
            path: $path,
            rejectUnsafeKeys: $rejectUnsafeKeys,
        );
    }

    /**
     * @param list<mixed> $list
     */
    private static function assertResultListPolicy(
        array $list,
        string $path,
        bool $rejectUnsafeKeys,
    ): void {
        foreach ($list as $index => $value) {
            self::assertResultValuePolicy(
                value: $value,
                path: $path . '[' . $index . ']',
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );
        }
    }

    private static function mapContextReason(string $reason): string
    {
        return match ($reason) {
            JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN => 'uow-context-attributes-float-forbidden',
            JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => 'uow-context-attributes-object-forbidden',
            JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => 'uow-context-attributes-closure-forbidden',
            JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => 'uow-context-attributes-resource-forbidden',
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => 'uow-context-attributes-map-key-must-be-string',
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => 'uow-context-attributes-type-forbidden',
            default => 'uow-context-attributes-type-forbidden',
        };
    }

    private static function mapResultReason(string $reason): string
    {
        return match ($reason) {
            JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN => 'uow-result-float-forbidden',
            JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => 'uow-result-object-forbidden',
            JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => 'uow-result-closure-forbidden',
            JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => 'uow-result-resource-forbidden',
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => 'uow-result-map-key-must-be-string',
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => 'uow-result-type-forbidden',
            default => 'uow-result-type-forbidden',
        };
    }

    private static function assertContextDepth(string $path, int $depth, int $maxDepth): void
    {
        if ($depth > $maxDepth) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-max-depth-exceeded',
            );
        }
    }

    private static function assertSafeContextAttributeKey(string $key, string $path): void
    {
        if (self::isUnsafeMetadataKey($key)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-unsafe-key-forbidden',
            );
        }
    }

    private static function assertSafeResultExtensionKey(string $key, string $path): void
    {
        if (self::isUnsafeMetadataKey($key)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-extensions-unsafe-key-forbidden',
            );
        }
    }

    private static function isUnsafeMetadataKey(string $key): bool
    {
        return \in_array(
            self::normalizeSemanticKey($key),
            self::UNSAFE_METADATA_KEY_TOKENS,
            true,
        );
    }

    private static function normalizeSemanticKey(string $key): string
    {
        return \strtolower(\str_replace(['_', '-', '.'], '', $key));
    }

    private static function pathForKey(string $basePath, string $key): string
    {
        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $key) === 1) {
            return $basePath . '.' . $key;
        }

        return $basePath . '[' . self::safeKeySegment($key) . ']';
    }

    private static function safeKeySegment(string $key): string
    {
        if ($key === '') {
            return '<empty-key>';
        }

        if (\preg_match('/\A[A-Za-z0-9_.:-]{1,64}\z/', $key) === 1) {
            return $key;
        }

        return '<key>';
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    private static function pathForPolicyKey(string $basePath, string $key): string
    {
        if (self::isUnsafeMetadataKey($key)) {
            return $basePath . '[<key>]';
        }

        return self::pathForKey($basePath, $key);
    }
}
