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

namespace Coretsia\Kernel\Config\Explain;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;

/**
 * Safe config explain trace builder.
 *
 * ConfigExplainer produces deterministic source/precedence/validation metadata
 * for final merged global config. It does not load config files, read env vars,
 * execute validation, merge config values, or store raw config/env values.
 *
 * Inputs are expected to come from ConfigKernel after Phase B orchestration:
 *
 * - final merged global config shape;
 * - source traces from package defaults, skeleton/app config, and env overlays;
 * - validation subjects from ConfigValidator;
 * - validation result;
 * - env overlay mapping metadata from EnvironmentOverlayLoader.
 *
 * The explain output is safe by construction:
 *
 * - config values are never included;
 * - raw env values are never included;
 * - secrets, tokens, DSNs, cookies, headers, raw SQL, payloads, stack traces,
 *   and absolute local paths are never included;
 * - source ids, source types, dot paths, directive names, safe logical paths,
 *   source precedence/order, and validation status may be included.
 *
 * ConfigSourceType values are vocabulary only. Effective precedence is recorded
 * from each concrete ConfigValueSource entry.
 *
 * @internal
 */
final readonly class ConfigExplainer
{
    public const int SCHEMA_VERSION = 1;

    private const string SOURCE_TYPE_UNKNOWN = 'unknown';

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
     * Builds a safe explanation for the final merged global config.
     *
     * `$sources` may be a list or a map of ConfigValueSource instances. This is
     * intentional because loaders return source metadata in different stable
     * shapes:
     *
     * - package defaults: map keyed by root;
     * - skeleton/app config: list;
     * - env overlays: map keyed by config path.
     *
     * `$validationSubjects` accepts the shape returned by
     * ConfigValidator::validationSubjects().
     *
     * `$envOverlayMappings` accepts the safe mapping metadata returned by
     * EnvironmentOverlayLoader.
     *
     * @param array<string,mixed> $config Final merged global config.
     * @param array<array-key, ConfigValueSource> $sources
     * @param array{
     *     validated?: list<array{root: string, ownership: string, validation: string}>,
     *     unvalidated?: list<array{root: string, ownership: string, validation: string}>
     * } $validationSubjects
     * @param list<array{
     *     env?: string,
     *     kind?: string,
     *     path?: string,
     *     root?: string,
     *     sourceId?: string,
     *     type?: string
     * }> $envOverlayMappings
     * @param array<string, array<string, null|bool|int|string>> $owners
     *
     * @return array{
     *     schemaVersion: int,
     *     paths: list<array{
     *         directive: non-empty-string|null,
     *         path: non-empty-string,
     *         redacted: bool,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         sourceOrder: int,
     *         sourcePath: non-empty-string|null,
     *         sourcePrecedence: int,
     *         sourceType: non-empty-string,
     *         validation: array{
     *             ownership: non-empty-string,
     *             status: non-empty-string
     *         },
     *         valueShape: non-empty-string
     *     }>,
     *     roots: list<array{
     *         ownership: non-empty-string,
     *         root: non-empty-string,
     *         validation: non-empty-string
     *     }>,
     *     owners: list<array<string, null|bool|int|string>>,
     *     sourceRanks: list<array{
     *         directive: non-empty-string|null,
     *         keyPath: non-empty-string|null,
     *         meta: array<string, null|bool|int|string>,
     *         order: int,
     *         path: non-empty-string|null,
     *         precedence: int,
     *         redacted: bool,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sourceTypes: list<non-empty-string>,
     *     precedence: array{
     *         aggregateVsRoot: non-empty-string,
     *         envOverlay: non-empty-string,
     *         sharedVsEnvironmentVsApp: non-empty-string
     *     },
     *     envOverlay: array{
     *         effectivePaths: list<non-empty-string>,
     *         mappings: list<array{
     *             env: non-empty-string,
     *             kind: non-empty-string,
     *             path: non-empty-string,
     *             root: non-empty-string,
     *             sourceId: non-empty-string,
     *             type: non-empty-string
     *         }>
     *     },
     *     validation: array{
     *         result: array<string,mixed>|null,
     *         subjects: list<array{
     *             ownership: non-empty-string,
     *             root: non-empty-string,
     *             validation: non-empty-string
     *         }>
     *     }
     * }
     */
    public function explain(
        array $config,
        array $sources,
        array $validationSubjects = [],
        ?ConfigValidationResult $validationResult = null,
        array $envOverlayMappings = [],
        array $owners = [],
    ): array {
        $normalizedSources = self::normalizeSources($sources);
        $subjectsByRoot = self::normalizeValidationSubjects($config, $validationSubjects);
        $paths = self::collectConfigPaths($config);
        $envMappings = self::normalizeEnvOverlayMappings($envOverlayMappings);
        $envEffectivePaths = self::envEffectivePaths($normalizedSources, $envMappings);

        $explainedPaths = [];

        foreach ($paths as $path => $valueShape) {
            $root = self::rootFromPath($path);
            $source = self::effectiveSourceForPath($path, $root, $normalizedSources);
            $subject = $subjectsByRoot[$root] ?? [
                'ownership' => 'user_owned',
                'root' => $root,
                'validation' => 'unvalidated',
            ];

            $explainedPaths[$path] = [
                'directive' => $source['directive'],
                'path' => $path,
                'redacted' => $source['redacted'],
                'root' => $root,
                'sourceId' => $source['sourceId'],
                'sourceOrder' => $source['order'],
                'sourcePath' => $source['path'],
                'sourcePrecedence' => $source['precedence'],
                'sourceType' => $source['type'],
                'validation' => [
                    'ownership' => $subject['ownership'],
                    'status' => $subject['validation'],
                ],
                'valueShape' => $valueShape,
            ];
        }

        \ksort($explainedPaths, \SORT_STRING);
        \ksort($subjectsByRoot, \SORT_STRING);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'paths' => \array_values($explainedPaths),
            'roots' => \array_values($subjectsByRoot),
            'owners' => self::normalizeOwners($owners),
            'sourceRanks' => self::sourceRanks($normalizedSources),
            'sourceTypes' => self::sourceTypes($normalizedSources),
            'precedence' => [
                'aggregateVsRoot' => 'split root-subtree sources have higher same-layer precedence than aggregate root-map sources',
                'sharedVsEnvironmentVsApp' => 'package defaults are weaker than skeleton shared, skeleton environment, app shared, app environment, and env overlay sources according to explicit source precedence',
                'envOverlay' => 'env overlay wins only for config paths that have a ruleset-derived or explicit env overlay mapping and a present env value',
            ],
            'envOverlay' => [
                'effectivePaths' => $envEffectivePaths,
                'mappings' => $envMappings,
            ],
            'validation' => [
                'result' => $validationResult?->toArray(),
                'subjects' => \array_values($subjectsByRoot),
            ],
        ];
    }

    /**
     * @param array<array-key, ConfigValueSource> $sources
     *
     * @return list<array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }>
     */
    private static function normalizeSources(array $sources): array
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

    private static function sourceOrderFromMeta(array $meta): int
    {
        $sourceOrder = $meta['sourceOrder'] ?? null;

        if (!\is_int($sourceOrder) || $sourceOrder < 0) {
            return 0;
        }

        return $sourceOrder;
    }

    /**
     * @param array<string,mixed> $config
     * @param array{
     *     validated?: list<array{root: string, ownership: string, validation: string}>,
     *     unvalidated?: list<array{root: string, ownership: string, validation: string}>
     * } $validationSubjects
     *
     * @return array<string, array{
     *     ownership: non-empty-string,
     *     root: non-empty-string,
     *     validation: non-empty-string
     * }>
     */
    private static function normalizeValidationSubjects(
        array $config,
        array $validationSubjects,
    ): array {
        $subjects = [];

        foreach (['validated', 'unvalidated'] as $bucket) {
            if (!isset($validationSubjects[$bucket]) || !\is_array($validationSubjects[$bucket])) {
                continue;
            }

            foreach ($validationSubjects[$bucket] as $subject) {
                if (!\is_array($subject)) {
                    continue;
                }

                $root = $subject['root'] ?? null;
                $ownership = $subject['ownership'] ?? null;
                $validation = $subject['validation'] ?? null;

                if (
                    !\is_string($root)
                    || !self::isValidRoot($root)
                    || !\is_string($ownership)
                    || !self::isSafeToken($ownership)
                    || !\is_string($validation)
                    || !self::isSafeToken($validation)
                ) {
                    continue;
                }

                $subjects[$root] = [
                    'ownership' => $ownership,
                    'root' => $root,
                    'validation' => $validation,
                ];
            }
        }

        foreach ($config as $root => $_value) {
            if (!\is_string($root) || !self::isValidRoot($root)) {
                continue;
            }

            $subjects[$root] ??= [
                'ownership' => 'user_owned',
                'root' => $root,
                'validation' => 'unvalidated',
            ];
        }

        \ksort($subjects, \SORT_STRING);

        return $subjects;
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private static function collectConfigPaths(array $config): array
    {
        $paths = [];

        foreach ($config as $root => $value) {
            if (!\is_string($root) || $root === '') {
                continue;
            }

            self::collectNodePaths(
                value: $value,
                path: $root,
                paths: $paths,
            );
        }

        $normalized = [];

        foreach ($paths as $path => $shape) {
            if ($path === '' || $shape === '') {
                continue;
            }

            $normalized[$path] = $shape;
        }

        \ksort($normalized, \SORT_STRING);

        /** @var array<non-empty-string, non-empty-string> $normalized */
        return $normalized;
    }

    /**
     * @param array<string, string> $paths
     */
    private static function collectNodePaths(
        mixed $value,
        string $path,
        array &$paths,
    ): void {
        $paths[$path] = self::valueShape($value);

        if (!\is_array($value)) {
            return;
        }

        if (\array_is_list($value)) {
            foreach ($value as $index => $item) {
                self::collectNodePaths(
                    value: $item,
                    path: $path . '[' . $index . ']',
                    paths: $paths,
                );
            }

            return;
        }

        $keys = \array_keys($value);

        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            self::collectNodePaths(
                value: $value[$key],
                path: $path . '.' . self::safePathSegment($key),
                paths: $paths,
            );
        }
    }

    /**
     * @param list<array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }> $sources
     *
     * @return array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }
     */
    private static function effectiveSourceForPath(
        string $path,
        string $root,
        array $sources,
    ): array {
        $best = null;

        foreach ($sources as $source) {
            if ($source['root'] !== $root) {
                continue;
            }

            if (!self::sourceAppliesToPath($source, $path, $root)) {
                continue;
            }

            if ($best === null) {
                $best = $source;

                continue;
            }

            if (self::compareEffectiveSources($source, $best, $path, $root) > 0) {
                $best = $source;
            }
        }

        if ($best !== null) {
            return $best;
        }

        return [
            'directive' => null,
            'keyPath' => null,
            'meta' => [],
            'order' => 0,
            'path' => null,
            'precedence' => 0,
            'redacted' => true,
            'root' => $root,
            'sourceId' => 'unknown/' . $root,
            'type' => self::SOURCE_TYPE_UNKNOWN,
        ];
    }

    /**
     * @param array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * } $candidate
     * @param array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * } $current
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
     * @param array{
     *     keyPath: non-empty-string|null,
     *     root: non-empty-string
     * } $source
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

        if (
            $path === $keyPath
            || \str_starts_with($path, $keyPath . '.')
            || \str_starts_with($path, $keyPath . '[')
        ) {
            return \strlen($keyPath);
        }

        return 0;
    }

    /**
     * @param array{
     *     keyPath: non-empty-string|null,
     *     root: non-empty-string
     * } $source
     */
    private static function sourceAppliesToPath(
        array $source,
        string $path,
        string $root,
    ): bool {
        $keyPath = $source['keyPath'];

        if ($keyPath === null) {
            return $path === $root || \str_starts_with($path, $root . '.') || \str_starts_with($path, $root . '[');
        }

        return $path === $keyPath || \str_starts_with($path, $keyPath . '.') || \str_starts_with($path, $keyPath . '[');
    }

    /**
     * @param array<string|int, mixed> $owners
     *
     * @return list<array<string, null|bool|int|string>>
     */
    private static function normalizeOwners(array $owners): array
    {
        $normalized = [];

        foreach ($owners as $owner) {
            if (!\is_array($owner)) {
                continue;
            }

            $row = self::normalizeOwner($owner);

            if ($row === []) {
                continue;
            }

            $sourceId = $row['sourceId'] ?? null;

            if (!\is_string($sourceId) || $sourceId === '') {
                continue;
            }

            $normalized[$sourceId] = $row;
        }

        \ksort($normalized, \SORT_STRING);

        return \array_values($normalized);
    }

    /**
     * @param array<string,mixed> $owner
     *
     * @return array<string, null|bool|int|string>
     */
    private static function normalizeOwner(array $owner): array
    {
        $allowedKeys = [
            'appEnv' => true,
            'appTarget' => true,
            'kind' => true,
            'layer' => true,
            'moduleId' => true,
            'packageId' => true,
            'path' => true,
            'root' => true,
            'sourceId' => true,
            'type' => true,
        ];

        $row = [];

        foreach ($owner as $key => $value) {
            if (!\is_string($key) || !isset($allowedKeys[$key])) {
                continue;
            }

            if ($value === null || \is_bool($value) || \is_int($value)) {
                $row[$key] = $value;

                continue;
            }

            if (!\is_string($value)) {
                continue;
            }

            if (!self::isSafeOwnerValue($key, $value)) {
                continue;
            }

            $row[$key] = $value;
        }

        $sourceId = $row['sourceId'] ?? null;
        $root = $row['root'] ?? null;

        if (!\is_string($sourceId) || !self::isSafeLogicalIdentifier($sourceId)) {
            return [];
        }

        if (!\is_string($root) || !self::isValidRoot($root)) {
            return [];
        }

        \ksort($row, \SORT_STRING);

        return $row;
    }

    private static function isSafeOwnerValue(
        string $key,
        string $value,
    ): bool {
        if ($key === 'root') {
            return self::isValidRoot($value);
        }

        if ($key === 'sourceId' || $key === 'path' || $key === 'packageId' || $key === 'moduleId') {
            return self::isSafeLogicalIdentifier($value);
        }

        if ($key === 'kind' || $key === 'layer' || $key === 'type') {
            return self::isSafeToken($value);
        }

        if ($key === 'appEnv' || $key === 'appTarget') {
            return self::isSafeMetadataString($value);
        }

        return false;
    }

    /**
     * @param list<array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }> $sources
     *
     * @return list<array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string, null|bool|int|string>,
     *     order: int,
     *     path: non-empty-string|null,
     *     precedence: int,
     *     redacted: bool,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }>
     */
    private static function sourceRanks(array $sources): array
    {
        $ranks = [];

        foreach ($sources as $source) {
            $ranks[] = [
                'directive' => $source['directive'],
                'keyPath' => $source['keyPath'],
                'meta' => $source['meta'],
                'order' => $source['order'],
                'path' => $source['path'],
                'precedence' => $source['precedence'],
                'redacted' => $source['redacted'],
                'root' => $source['root'],
                'sourceId' => $source['sourceId'],
                'type' => $source['type'],
            ];
        }

        return $ranks;
    }

    /**
     * @param list<array{type: non-empty-string}> $sources
     *
     * @return list<non-empty-string>
     */
    private static function sourceTypes(array $sources): array
    {
        $types = [];

        foreach ($sources as $source) {
            if (ConfigSourceType::isKnown($source['type'])) {
                $types[$source['type']] = true;
            }
        }

        \ksort($types, \SORT_STRING);

        return \array_keys($types);
    }

    /**
     * @param list<array{
     *     env?: string,
     *     kind?: string,
     *     path?: string,
     *     root?: string,
     *     sourceId?: string,
     *     type?: string
     * }> $envOverlayMappings
     *
     * @return list<array{
     *     env: non-empty-string,
     *     kind: non-empty-string,
     *     path: non-empty-string,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }>
     */
    private static function normalizeEnvOverlayMappings(array $envOverlayMappings): array
    {
        $mappings = [];

        foreach ($envOverlayMappings as $mapping) {
            if (!\is_array($mapping)) {
                continue;
            }

            $env = $mapping['env'] ?? null;
            $kind = $mapping['kind'] ?? null;
            $path = $mapping['path'] ?? null;
            $root = $mapping['root'] ?? null;
            $sourceId = $mapping['sourceId'] ?? null;
            $type = $mapping['type'] ?? null;

            if (
                !\is_string($env)
                || !self::isSafeEnvName($env)
                || !\is_string($kind)
                || !self::isSafeToken($kind)
                || !\is_string($path)
                || !self::isSafeConfigPath($path)
                || !\is_string($root)
                || !self::isValidRoot($root)
                || !\is_string($sourceId)
                || !self::isSafeLogicalIdentifier($sourceId)
                || !\is_string($type)
                || !self::isSafeToken($type)
            ) {
                continue;
            }

            $mappings[$path] = [
                'env' => $env,
                'kind' => $kind,
                'path' => $path,
                'root' => $root,
                'sourceId' => $sourceId,
                'type' => $type,
            ];
        }

        \ksort($mappings, \SORT_STRING);

        return \array_values($mappings);
    }

    /**
     * @param list<array{
     *     keyPath: non-empty-string|null,
     *     type: non-empty-string
     * }> $sources
     * @param list<array{path: non-empty-string}> $envMappings
     *
     * @return list<non-empty-string>
     */
    private static function envEffectivePaths(array $sources, array $envMappings): array
    {
        $mappedPaths = [];

        foreach ($envMappings as $mapping) {
            $mappedPaths[$mapping['path']] = true;
        }

        foreach ($sources as $source) {
            if ($source['type'] !== ConfigSourceType::Env->value || $source['keyPath'] === null) {
                continue;
            }

            $mappedPaths[$source['keyPath']] = true;
        }

        \ksort($mappedPaths, \SORT_STRING);

        return \array_keys($mappedPaths);
    }

    /**
     * @param array<string,mixed> $meta
     *
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
            if (!\is_int($value) || $value < 0) {
                return null;
            }

            return $value;
        }

        if ($key === 'hash') {
            if (!\is_string($value) || \preg_match('/\A[a-f0-9]{32,128}\z/', $value) !== 1) {
                return null;
            }

            return $value;
        }

        if ($key === 'hashAlgorithm') {
            if (!\is_string($value) || !self::isSafeToken($value)) {
                return null;
            }

            return $value;
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (!\is_string($value)) {
            return null;
        }

        if (!self::isSafeMetadataString($value)) {
            return null;
        }

        return $value;
    }

    private static function valueShape(mixed $value): string
    {
        if (\is_array($value)) {
            return \array_is_list($value)
                ? 'list'
                : 'map';
        }

        if ($value === null) {
            return 'scalar:null';
        }

        if (\is_bool($value)) {
            return 'scalar:bool';
        }

        if (\is_int($value)) {
            return 'scalar:int';
        }

        if (\is_string($value)) {
            return 'scalar:string';
        }

        if (\is_float($value)) {
            return 'scalar:float';
        }

        if (\is_object($value)) {
            return 'object';
        }

        if (\is_resource($value)) {
            return 'resource';
        }

        return 'unknown';
    }

    /**
     * @return non-empty-string
     */
    private static function rootFromPath(string $path): string
    {
        $root = \strtok($path, '.[');

        if (!\is_string($root) || !self::isValidRoot($root)) {
            return 'unknown';
        }

        return $root;
    }

    private static function safePathSegment(string $key): string
    {
        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*\z/', $key) === 1) {
            return $key;
        }

        return '<key>';
    }

    private static function isValidRoot(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
    }

    private static function isSafeConfigPath(string $path): bool
    {
        if ($path === '' || \str_contains($path, "\0") || \str_contains($path, "\r") || \str_contains($path, "\n")) {
            return false;
        }

        if (\str_contains($path, '..') || \str_starts_with($path, '.') || \str_ends_with($path, '.')) {
            return false;
        }

        $segments = \explode('.', $path);

        if (\count($segments) < 2) {
            return false;
        }

        foreach ($segments as $index => $segment) {
            if ($segment === '' || \preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*\z/', $segment) !== 1) {
                return false;
            }

            if ($index === 0 && !self::isValidRoot($segment)) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeEnvName(string $env): bool
    {
        return $env !== '' && \preg_match('/\A[A-Z][A-Z0-9_]*\z/', $env) === 1;
    }

    private static function isSafeToken(string $token): bool
    {
        return $token !== ''
            && \strlen($token) <= 64
            && \preg_match('/\A[a-z][a-z0-9_-]*\z/', $token) === 1;
    }

    private static function isSafeLogicalIdentifier(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (\preg_match('/\s/u', $value) === 1) {
            return false;
        }

        if (
            \str_contains($value, "\0")
            || \str_contains($value, "\r")
            || \str_contains($value, "\n")
            || \str_contains($value, ':')
            || \str_contains($value, '://')
            || \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
        ) {
            return false;
        }

        $normalized = \str_replace('\\', '/', $value);

        return !(
            $normalized === '..'
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/..')
        );
    }

    private static function isSafeMetadataString(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (
            \str_contains($value, "\0")
            || \str_contains($value, "\r")
            || \str_contains($value, "\n")
            || \str_contains($value, '://')
            || \preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1
            || \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
        ) {
            return false;
        }

        return true;
    }
}
