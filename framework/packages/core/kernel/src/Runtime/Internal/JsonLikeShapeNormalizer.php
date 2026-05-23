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

use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;

/**
 * Internal json-like normalizer/guard for Kernel UnitOfWork shapes.
 *
 * This class is intentionally internal:
 *
 * - it is not a public Kernel API;
 * - it is not a DI service;
 * - it is not a transport extension point;
 * - it exists only to keep UnitOfWorkContext and UnitOfWorkResult validation
 *   behavior consistent.
 *
 * The normalizer preserves the Kernel UoW json-like policy:
 *
 * - allowed scalars: null, bool, int, string;
 * - forbidden scalars: float, including NaN, INF, and -INF;
 * - allowed containers: lists and string-keyed maps;
 * - forbidden values: objects, closures, resources, service instances;
 * - maps are sorted recursively by byte-order strcmp;
 * - lists preserve caller-supplied order;
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

        $keyCount = 0;

        return self::normalizeContextMap(
            map: $attributes,
            path: 'attributes',
            depth: 1,
            maxDepth: $maxDepth,
            maxKeys: $maxKeys,
            keyCount: $keyCount,
        );
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

        return self::normalizeResultMap(
            map: $extensions,
            path: 'extensions',
            rejectUnsafeKeys: true,
        );
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

        return self::normalizeResultMap(
            map: $error,
            path: 'error',
            rejectUnsafeKeys: false,
        );
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string, mixed>
     */
    private static function normalizeContextMap(
        array $map,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): array {
        self::assertContextDepth($path, $depth, $maxDepth);

        $normalized = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $path,
                    'uow-context-attributes-map-key-must-be-string',
                );
            }

            if (!self::isSafeSingleLineString($key)) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $path,
                    'uow-context-attributes-map-key-invalid',
                );
            }

            $keyPath = self::pathForKey($path, $key);

            self::assertSafeContextAttributeKey($key, $keyPath);

            ++$keyCount;

            if ($keyCount > $maxKeys) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $keyPath,
                    'uow-context-attributes-max-keys-exceeded',
                );
            }

            $normalized[$key] = self::normalizeContextValue(
                value: $value,
                path: $keyPath,
                depth: $depth,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeContextValue(
        mixed $value,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): null|bool|int|string|array {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (!self::isSafeString($value)) {
                throw UnitOfWorkContextInvalidException::atPath(
                    $path,
                    'uow-context-attributes-string-invalid',
                );
            }

            return $value;
        }

        if (\is_float($value)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-float-forbidden',
            );
        }

        if (\is_resource($value)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-resource-forbidden',
            );
        }

        if ($value instanceof \Closure) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-closure-forbidden',
            );
        }

        if (\is_object($value)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-object-forbidden',
            );
        }

        if (!\is_array($value)) {
            throw UnitOfWorkContextInvalidException::atPath(
                $path,
                'uow-context-attributes-type-forbidden',
            );
        }

        if (\array_is_list($value)) {
            return self::normalizeContextList(
                list: $value,
                path: $path,
                depth: $depth + 1,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );
        }

        return self::normalizeContextMap(
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
     *
     * @return list<mixed>
     */
    private static function normalizeContextList(
        array $list,
        string $path,
        int $depth,
        int $maxDepth,
        int $maxKeys,
        int &$keyCount,
    ): array {
        self::assertContextDepth($path, $depth, $maxDepth);

        $normalized = [];

        foreach ($list as $index => $value) {
            $normalized[] = self::normalizeContextValue(
                value: $value,
                path: $path . '[' . $index . ']',
                depth: $depth,
                maxDepth: $maxDepth,
                maxKeys: $maxKeys,
                keyCount: $keyCount,
            );
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string, mixed>
     */
    private static function normalizeResultMap(
        array $map,
        string $path,
        bool $rejectUnsafeKeys,
    ): array {
        $normalized = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw UnitOfWorkResultInvalidException::atPath(
                    $path,
                    'uow-result-map-key-must-be-string',
                );
            }

            if (!self::isSafeSingleLineString($key)) {
                throw UnitOfWorkResultInvalidException::atPath(
                    $path,
                    'uow-result-map-key-invalid',
                );
            }

            $keyPath = self::pathForKey($path, $key);

            if ($rejectUnsafeKeys) {
                self::assertSafeResultExtensionKey($key, $keyPath);
            }

            $normalized[$key] = self::normalizeResultValue(
                value: $value,
                path: $keyPath,
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeResultValue(
        mixed $value,
        string $path,
        bool $rejectUnsafeKeys,
    ): null|bool|int|string|array {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (!self::isSafeString($value)) {
                throw UnitOfWorkResultInvalidException::atPath(
                    $path,
                    'uow-result-string-invalid',
                );
            }

            return $value;
        }

        if (\is_float($value)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-float-forbidden',
            );
        }

        if (\is_resource($value)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-resource-forbidden',
            );
        }

        if ($value instanceof \Closure) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-closure-forbidden',
            );
        }

        if (\is_object($value)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-object-forbidden',
            );
        }

        if (!\is_array($value)) {
            throw UnitOfWorkResultInvalidException::atPath(
                $path,
                'uow-result-type-forbidden',
            );
        }

        if (\array_is_list($value)) {
            return self::normalizeResultList(
                list: $value,
                path: $path,
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );
        }

        return self::normalizeResultMap(
            map: $value,
            path: $path,
            rejectUnsafeKeys: $rejectUnsafeKeys,
        );
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     */
    private static function normalizeResultList(
        array $list,
        string $path,
        bool $rejectUnsafeKeys,
    ): array {
        $normalized = [];

        foreach ($list as $index => $value) {
            $normalized[] = self::normalizeResultValue(
                value: $value,
                path: $path . '[' . $index . ']',
                rejectUnsafeKeys: $rejectUnsafeKeys,
            );
        }

        return $normalized;
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
}
