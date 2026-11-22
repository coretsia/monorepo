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

use Coretsia\Contracts\Config\ConfigValueSource;

/**
 * Canonical effective source selection over already produced config traces.
 *
 * Explicit precedence, recorded source order, path specificity, and stable
 * identity tie-breakers determine the effective source. No config values are
 * needed. The original ConfigValueSource is retained for runtime consumption;
 * the normalized fields also support the safe config explanation projection.
 *
 * @phpstan-type NormalizedSource array{
 *     directive: non-empty-string|null,
 *     keyPath: non-empty-string|null,
 *     meta: array<string, null|bool|int|string>,
 *     order: int<0,max>,
 *     path: non-empty-string|null,
 *     precedence: int<0,max>,
 *     redacted: bool,
 *     root: non-empty-string,
 *     source: ConfigValueSource,
 *     sourceId: non-empty-string,
 *     type: non-empty-string
 * }
 * @internal
 */
final readonly class EffectiveConfigSourceSelector
{
    /**
     * @var array<string, true>
     */
    private const array SAFE_META_KEYS = [
        'appEnv' => true,
        'appTarget' => true,
        'envName' => true,
        'envSourceId' => true,
        'envSourceType' => true,
        'hash' => true,
        'hashAlgorithm' => true,
        'kind' => true,
        'layer' => true,
        'length' => true,
        'mappingKind' => true,
        'moduleId' => true,
        'packageId' => true,
        'sourceOrder' => true,
        'valueType' => true,
    ];

    /**
     * Selects the existing source object for a concrete logical config path.
     *
     * @param array<array-key, ConfigValueSource> $sources
     */
    public static function select(string $path, array $sources): ?ConfigValueSource
    {
        $root = \substr($path, 0, \strcspn($path, '.['));

        if (\preg_match('/\A[a-z][a-z0-9_]*\z/', $root) !== 1) {
            return null;
        }

        $selected = self::selectNormalized(
            path: $path,
            root: $root,
            sources: self::normalizeSources(self::sourceListToMap($sources)),
        );

        return $selected['source'] ?? null;
    }

    /**
     * Reconstructs the canonical trace identity map from a source list.
     *
     * @param array<array-key, ConfigValueSource> $sources
     * @return array<string, ConfigValueSource>
     */
    public static function sourceListToMap(array $sources): array
    {
        $map = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $key = \str_pad((string) $source->precedence(), 10, '0', \STR_PAD_LEFT)
                . "\0" . ($source->keyPath() ?? $source->path() ?? $source->root())
                . "\0" . $source->sourceId()
                . "\0" . $source->type()->value
                . "\0" . ($source->directive() ?? '');

            $map[$key] = $source;
        }

        \ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @param array<array-key, ConfigValueSource> $sources
     * @return list<NormalizedSource>
     */
    public static function normalizeSources(array $sources): array
    {
        $normalized = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $meta = self::sanitizeMeta($source->meta());

            $normalized[] = [
                'directive' => $source->directive(),
                'keyPath' => $source->keyPath(),
                'meta' => $meta,
                'order' => self::sourceOrderFromMeta($meta),
                'path' => $source->path(),
                'precedence' => $source->precedence(),
                'redacted' => $source->isRedacted(),
                'root' => $source->root(),
                'source' => $source,
                'sourceId' => $source->sourceId(),
                'type' => $source->type()->value,
            ];
        }

        \usort(
            $normalized,
            static function (array $a, array $b): int {
                return ($a['precedence'] <=> $b['precedence'])
                    ?: \strcmp($a['keyPath'] ?? $a['path'] ?? $a['root'], $b['keyPath'] ?? $b['path'] ?? $b['root'])
                        ?: \strcmp($a['sourceId'], $b['sourceId'])
                            ?: \strcmp($a['type'], $b['type'])
                                ?: \strcmp($a['directive'] ?? '', $b['directive'] ?? '')
                                    ?: ($a['order'] <=> $b['order']);
            },
        );

        return $normalized;
    }

    /**
     * Selects from a normalized trace set without introducing an unknown source.
     *
     * @param list<NormalizedSource> $sources
     * @return NormalizedSource|null
     */
    public static function selectNormalized(
        string $path,
        string $root,
        array $sources,
    ): ?array {
        $best = null;

        foreach ($sources as $source) {
            if ($source['root'] !== $root) {
                continue;
            }

            if (!self::sourceAppliesToPath($source, $path, $root)) {
                continue;
            }

            if ($best === null || self::compareEffectiveSources($source, $best, $path, $root) > 0) {
                $best = $source;
            }
        }

        return $best;
    }

    /**
     * @param NormalizedSource $candidate
     * @param NormalizedSource $current
     */
    private static function compareEffectiveSources(
        array $candidate,
        array $current,
        string $path,
        string $root,
    ): int {
        return ($candidate['precedence'] <=> $current['precedence'])
            ?: ($candidate['order'] <=> $current['order'])
                ?: (
                    self::sourceSpecificityForPath($candidate, $path, $root)
                    <=> self::sourceSpecificityForPath($current, $path, $root)
                )
                    ?: \strcmp($candidate['sourceId'], $current['sourceId'])
                        ?: \strcmp($candidate['type'], $current['type'])
                            ?: \strcmp($candidate['directive'] ?? '', $current['directive'] ?? '');
    }

    /**
     * @param NormalizedSource $source
     */
    private static function sourceSpecificityForPath(
        array $source,
        string $path,
        string $root,
    ): int {
        $keyPath = $source['keyPath'];

        if ($keyPath === null) {
            return \strlen($root);
        }

        if (self::sourceAppliesToPath($source, $path, $root)) {
            return \strlen($keyPath);
        }

        return 0;
    }

    /**
     * @param NormalizedSource $source
     */
    private static function sourceAppliesToPath(
        array $source,
        string $path,
        string $root,
    ): bool {
        $keyPath = $source['keyPath'] ?? $root;

        return $path === $keyPath || \str_starts_with($path, $keyPath . '.') || \str_starts_with($path, $keyPath . '[');
    }

    /**
     * @param array<string, null|bool|int|string> $meta
     * @return int<0,max>
     */
    private static function sourceOrderFromMeta(array $meta): int
    {
        $sourceOrder = $meta['sourceOrder'] ?? null;

        if (!\is_int($sourceOrder) || $sourceOrder < 0) {
            return 0;
        }

        return $sourceOrder;
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string, null|bool|int|string>
     */
    private static function sanitizeMeta(array $meta): array
    {
        $out = [];

        foreach ($meta as $key => $value) {
            if (!isset(self::SAFE_META_KEYS[$key])) {
                continue;
            }

            $safeValue = self::sanitizeMetaValue($key, $value);

            if ($safeValue === null && $value !== null) {
                continue;
            }

            $out[$key] = $safeValue;
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    private static function sanitizeMetaValue(
        string $key,
        mixed $value,
    ): null|bool|int|string {
        if ($key === 'length') {
            return \is_int($value) && $value >= 0 ? $value : null;
        }

        if ($key === 'hash') {
            return \is_string($value) && \preg_match('/\A[a-f0-9]{32,128}\z/', $value) === 1 ? $value : null;
        }

        if ($key === 'hashAlgorithm') {
            return \is_string($value) && self::isSafeToken($value) ? $value : null;
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        return \is_string($value) && self::isSafeMetadataString($value) ? $value : null;
    }

    private static function isSafeToken(string $token): bool
    {
        return $token !== ''
            && \strlen($token) <= 64
            && \preg_match('/\A[a-z][a-z0-9_-]*\z/', $token) === 1;
    }

    private static function isSafeMetadataString(string $value): bool
    {
        return $value !== ''
            && !\str_contains($value, "\0")
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && !\str_contains($value, '://')
            && \preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) !== 1
            && !\str_starts_with($value, '/')
            && !\str_starts_with($value, '\\');
    }
}
