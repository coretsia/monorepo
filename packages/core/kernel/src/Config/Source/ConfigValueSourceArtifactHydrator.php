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

namespace Coretsia\Kernel\Config\Source;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Reconstructs canonical ConfigValueSource instances from config@1 sources.
 *
 * Artifact entry shape and metadata safety are checked at this boundary;
 * semantic construction and metadata normalization remain owned by
 * ConfigValueSource. Source list order is preserved.
 *
 * @internal Kernel artifact-runtime boundary.
 */
final readonly class ConfigValueSourceArtifactHydrator
{
    private const int MAX_SAFE_STRING_BYTES = 512;
    private const string CONFIG_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/';

    /**
     * @var array<string, true>
     */
    private const array ENTRY_KEYS = [
        'directive' => true,
        'keyPath' => true,
        'meta' => true,
        'path' => true,
        'precedence' => true,
        'redacted' => true,
        'root' => true,
        'schemaVersion' => true,
        'sourceId' => true,
        'type' => true,
    ];

    /**
     * @return list<ConfigValueSource>
     * @throws ArtifactInvalidException
     */
    public static function hydrate(mixed $sources): array
    {
        if (!\is_array($sources) || !\array_is_list($sources)) {
            throw self::invalid();
        }

        $hydrated = [];

        foreach ($sources as $source) {
            $hydrated[] = self::hydrateEntry($source);
        }

        return $hydrated;
    }

    private static function hydrateEntry(mixed $source): ConfigValueSource
    {
        if (!\is_array($source) || \array_is_list($source) || \array_diff_key($source, self::ENTRY_KEYS) !== []) {
            throw self::invalid();
        }

        if (
            !\is_int($source['precedence'] ?? null)
            || $source['precedence'] < 0
            || !\is_bool($source['redacted'] ?? null)
            || !\is_string($source['root'] ?? null)
            || \preg_match('/\A[a-z][a-z0-9_]*\z/', $source['root']) !== 1
            || ($source['schemaVersion'] ?? null) !== ConfigValueSource::SCHEMA_VERSION
            || !\is_string($source['sourceId'] ?? null)
            || !self::isSafeArtifactPath($source['sourceId'], true)
            || !\is_string($source['type'] ?? null)
        ) {
            throw self::invalid();
        }

        $type = ConfigSourceType::tryFrom($source['type']);

        if ($type === null) {
            throw self::invalid();
        }

        $path = self::optionalString($source, 'path');
        $keyPath = self::optionalString($source, 'keyPath');
        $directive = self::optionalString($source, 'directive');

        if (
            ($path !== null && !self::isSafeArtifactPath($path, false))
            || ($keyPath !== null && \preg_match(self::CONFIG_PATH_PATTERN, $keyPath) !== 1)
            || ($directive !== null && \preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/', $directive) !== 1)
        ) {
            throw self::invalid();
        }

        $meta = [];

        if (\array_key_exists('meta', $source)) {
            if (!\is_array($source['meta']) || \array_is_list($source['meta'])) {
                throw self::invalid();
            }

            $meta = self::validatedMetadataMap($source['meta']);

            if (\array_key_exists('sourceOrder', $meta) && (!\is_int(
                $meta['sourceOrder'],
            ) || $meta['sourceOrder'] < 0)) {
                throw self::invalid();
            }
        }

        try {
            return new ConfigValueSource(
                type: $type,
                root: $source['root'],
                sourceId: $source['sourceId'],
                path: $path,
                keyPath: $keyPath,
                directive: $directive,
                precedence: $source['precedence'],
                redacted: $source['redacted'],
                meta: $meta,
            );
        } catch (\InvalidArgumentException) {
            // Contract validation failures may contain metadata key paths.
            throw self::invalid();
        }
    }

    /**
     * @param array<array-key,mixed> $source
     * @return non-empty-string|null
     */
    private static function optionalString(array $source, string $key): ?string
    {
        if (!\array_key_exists($key, $source)) {
            return null;
        }

        $value = $source[$key];

        if (!\is_string($value) || $value === '') {
            throw self::invalid();
        }

        return $value;
    }

    private static function isSafeArtifactPath(string $value, bool $logicalIdentifier): bool
    {
        $normalized = \str_replace('\\', '/', $value);

        return $normalized !== ''
            && \strlen($normalized) <= self::MAX_SAFE_STRING_BYTES
            && (!$logicalIdentifier || \preg_match('/\s/u', $normalized) !== 1)
            && !self::containsUnsafeBytes($normalized)
            && !self::looksLikeAbsolutePath($normalized)
            && !\str_contains($normalized, ':')
            && !\str_contains($normalized, '//')
            && $normalized !== '.'
            && $normalized !== '..'
            && !\str_starts_with($normalized, './')
            && !\str_starts_with($normalized, '../')
            && !\str_contains($normalized, '/./')
            && !\str_contains($normalized, '/../')
            && !\str_ends_with($normalized, '/.')
            && !\str_ends_with($normalized, '/..');
    }

    /**
     * @param array<array-key,mixed> $map
     * @return array<string,mixed>
     */
    private static function validatedMetadataMap(array $map): array
    {
        $validated = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key) || \preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $key) !== 1) {
                throw self::invalid();
            }

            $validated[$key] = self::validatedMetadataValue($value);
        }

        return $validated;
    }

    private static function validatedMetadataValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (
                $value === ''
                || \strlen($value) > self::MAX_SAFE_STRING_BYTES
                || self::containsUnsafeBytes($value)
                || self::looksLikeAbsolutePath($value)
                || \str_contains($value, '://')
            ) {
                throw self::invalid();
            }

            return $value;
        }

        if (\is_array($value)) {
            if (!\array_is_list($value)) {
                return self::validatedMetadataMap($value);
            }

            $validated = [];

            foreach ($value as $item) {
                $validated[] = self::validatedMetadataValue($item);
            }

            return $validated;
        }

        throw self::invalid();
    }

    private static function containsUnsafeBytes(string $value): bool
    {
        return \preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }

    private static function invalid(): ArtifactInvalidException
    {
        return ArtifactInvalidException::withReason(ArtifactInvalidException::REASON_SCHEMA_INVALID);
    }
}
