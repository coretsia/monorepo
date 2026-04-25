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

namespace Coretsia\Tools\Spikes\config_merge;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Deterministic config merge runner (spike).
 *
 * Normative rules (cemented for this spike):
 *  - Precedence order is fixed: defaults < skeleton < module < app (lower -> higher).
 *  - Directives are supported (two-phase semantics):
 *      Phase A: validate directives + reserved namespace guard across the whole input.
 *      Phase B: merge by precedence; directives applied deterministically at merge-time.
 *
 * Deterministic map key ordering (single-choice; cemented):
 *  - Any intermediate and final "map" structures produced by merge MUST be normalized by sorting keys
 *    using byte-order comparison (strcmp) at each map level.
 *  - Lists MUST preserve element order and MUST NOT be re-sorted.
 *  - Sorting MUST be locale-independent and MUST NOT rely on environment (LC_ALL, setlocale, etc.).
 *
 * Notes:
 *  - DirectiveProcessor is the single source of truth for directive validation + application.
 *  - This spike uses exceptions with message == ErrorCodes::* (thrown by DirectiveProcessor) to carry deterministic error codes.
 *  - The merger itself never emits secret values; any tracing should be done by ConfigExplainer.
 */
final class ConfigMerger
{
    private const string MSG_SCENARIO_INPUT_INVALID = 'config-merger-scenario-input-invalid';
    private const string MSG_SOURCE_ENTRY_INVALID = 'config-merger-source-entry-invalid';
    private const string MSG_SOURCE_ENTRY_INVALID_TYPES = 'config-merger-source-entry-invalid-types';
    private const string MSG_UNKNOWN_SOURCE_TYPE = 'config-merger-unknown-source-type';
    private const string MSG_ROOT_MUST_BE_MAP = 'config-merger-root-must-be-map';
    private const string MSG_DIRECTIVE_NODE_LEAKED = 'config-merger-directive-node-leaked';

    /**
     * @var list<string>
     */
    public const array PRECEDENCE = [
        'defaults',
        'skeleton',
        'module',
        'app',
    ];

    /**
     * @var array<string, int>
     */
    private const array PRECEDENCE_RANK = [
        'defaults' => 0,
        'skeleton' => 1,
        'module' => 2,
        'app' => 3,
    ];

    private DirectiveProcessor $directives;

    public function __construct(?DirectiveProcessor $directives = null)
    {
        $this->directives = $directives ?? new DirectiveProcessor();
    }

    /**
     * Scenario-friendly API (matches fixtures/scenarios.php shape).
     *
     * @param array{defaults: array, skeleton: array, module: array, app: array} $inputs
     * @return array<string, mixed>
     *
     * @throws DeterministicException
     */
    public function mergeScenario(array $inputs): array
    {
        foreach (self::PRECEDENCE as $layer) {
            if (!array_key_exists($layer, $inputs) || !is_array($inputs[$layer])) {
                self::failInputInvalid(self::MSG_SCENARIO_INPUT_INVALID);
            }
        }

        /** @var array $defaults */
        $defaults = $inputs['defaults'];
        /** @var array $skeleton */
        $skeleton = $inputs['skeleton'];
        /** @var array $module */
        $module = $inputs['module'];
        /** @var array $app */
        $app = $inputs['app'];

        $this->assertRootIsMap($defaults);
        $this->assertRootIsMap($skeleton);
        $this->assertRootIsMap($module);
        $this->assertRootIsMap($app);

        // Phase A: validate per-layer (full scan; independent of precedence masking).
        $this->directives->validatePhaseA($defaults);
        $this->directives->validatePhaseA($skeleton);
        $this->directives->validatePhaseA($module);
        $this->directives->validatePhaseA($app);

        // Phase B: merge deterministically by precedence.
        $merged = [];
        $merged = $this->mergeMapsByPrecedence($merged, $defaults);
        $merged = $this->mergeMapsByPrecedence($merged, $skeleton);
        $merged = $this->mergeMapsByPrecedence($merged, $module);
        $merged = $this->mergeMapsByPrecedence($merged, $app);

        /** @var array<string, mixed> $merged */
        return $merged;
    }

    /**
     * Source-oriented API (useful for explain/debug flows).
     *
     * Merge order is deterministic:
     *  1) precedenceRank ascending (defaults -> app)
     *  2) file ascending by byte-order (strcmp)
     *  3) stable _seq tie-breaker (original order)
     *
     * @param list<array{sourceType: string, file: string, config: array}> $sources
     * @return array<string, mixed>
     *
     * @throws DeterministicException
     */
    public function mergeSources(array $sources): array
    {
        $seq = 0;

        /** @var list<array{sourceType: string, file: string, config: array, _rank: int, _seq: int}> $normalized */
        $normalized = [];

        foreach ($sources as $source) {
            if (!isset($source['sourceType'], $source['file'], $source['config'])) {
                self::failInputInvalid(self::MSG_SOURCE_ENTRY_INVALID);
            }

            $sourceType = $source['sourceType'];
            $file = $source['file'];
            $config = $source['config'];

            if (!is_string($sourceType) || !is_string($file) || !is_array($config)) {
                self::failInputInvalid(self::MSG_SOURCE_ENTRY_INVALID_TYPES);
            }

            $rank = self::precedenceRankOf($sourceType);

            $this->assertRootIsMap($config);
            $this->directives->validatePhaseA($config);

            $normalized[] = [
                'sourceType' => $sourceType,
                'file' => $file,
                'config' => $config,
                '_rank' => $rank,
                '_seq' => $seq++,
            ];
        }

        usort(
            $normalized,
            static function (array $a, array $b): int {
                $cmp = $a['_rank'] <=> $b['_rank'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = strcmp($a['file'], $b['file']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $a['_seq'] <=> $b['_seq'];
            },
        );

        $merged = [];
        foreach ($normalized as $entry) {
            /** @var array<string, mixed> $config */
            $config = $entry['config'];
            $merged = $this->mergeMapsByPrecedence($merged, $config);
        }

        return $merged;
    }

    /**
     * Deterministic precedence merge for two maps.
     *
     * Default behavior for non-directive incoming values:
     *  - scalar replaces
     *  - list replaces (order preserved)
     *  - map replaces (but is resolved so directive keys never leak into output)
     *
     * To merge into an existing base map while preserving base keys, use @merge at that key.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     *
     * @throws DeterministicException
     */
    private function mergeMapsByPrecedence(array $base, array $incoming): array
    {
        $out = [];

        $keys = $this->sortedUnionKeys($base, $incoming);

        foreach ($keys as $key) {
            $hasIncoming = array_key_exists($key, $incoming);

            if (!$hasIncoming) {
                $out[$key] = $this->normalizeValue($base[$key]);
                continue;
            }

            $incomingValue = $incoming[$key];

            if (is_array($incomingValue) && DirectiveProcessor::isDirectiveNode($incomingValue)) {
                $baseValue = array_key_exists($key, $base) ? $base[$key] : null;

                $applied = $this->directives->applyPhaseB($baseValue, $incomingValue);
                $out[$key] = $this->normalizeValue($applied);

                continue;
            }

            // Non-directive incoming values replace (map/list/scalar),
            // but maps are resolved so directive keys never leak into output.
            $out[$key] = $this->replaceValueResolvingNestedDirectives($incomingValue);
        }

        /** @var array<string, mixed> $out */
        return $this->normalizeMap($out);
    }

    /**
     * @throws DeterministicException
     */
    private function replaceValueResolvingNestedDirectives(mixed $incomingValue): mixed
    {
        if (!is_array($incomingValue)) {
            return $incomingValue;
        }

        if ($incomingValue === []) {
            return [];
        }

        if (DirectiveProcessor::isDirectiveNode($incomingValue)) {
            $applied = $this->directives->applyPhaseB(null, $incomingValue);

            return $this->normalizeValue($applied);
        }

        if (array_is_list($incomingValue)) {
            return $this->normalizeList($incomingValue);
        }

        /** @var array<string, mixed> $incomingValue */
        $resolved = $this->directives->resolveAgainstMissingBaseMap($incomingValue);

        return $this->normalizeMap($resolved);
    }

    /**
     * @throws DeterministicException
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        // Safety: a directive node must never leak into normalization.
        if (DirectiveProcessor::isDirectiveNode($value)) {
            self::failResultInvalid(self::MSG_DIRECTIVE_NODE_LEAKED);
        }

        if (array_is_list($value)) {
            return $this->normalizeList($value);
        }

        /** @var array<string, mixed> $value */
        return $this->normalizeMap($value);
    }

    /**
     * @param list<mixed> $list
     * @return list<mixed>
     *
     * @throws DeterministicException
     */
    private function normalizeList(array $list): array
    {
        $out = [];

        foreach ($list as $item) {
            // Important: apply nested directive resolution here as well,
            // so '@*' keys never leak even if they appear inside list elements.
            $out[] = $this->replaceValueResolvingNestedDirectives($item);
        }

        /** @var list<mixed> $out */
        return $out;
    }

    /**
     * Normalize a map by sorting keys with strcmp at each map level, recursively.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     *
     * @throws DeterministicException
     */
    private function normalizeMap(array $map): array
    {
        if ($map === []) {
            return [];
        }

        $keys = array_keys($map);

        usort(
            $keys,
            static fn (string|int $a, string|int $b): int => strcmp((string) $a, (string) $b),
        );

        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $this->normalizeValue($map[$key]);
        }

        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Union of map keys for merge traversal.
     *
     * Important: Sorting is intentionally NOT performed here to avoid double-sorting with normalizeMap().
     * Deterministic ordering is guaranteed by normalizeMap() on every produced map boundary.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return list<string|int>
     */
    private function sortedUnionKeys(array $a, array $b): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($a), array_keys($b))));

        /** @var list<string|int> $keys */
        return $keys;
    }

    /**
     * @throws DeterministicException
     */
    private static function precedenceRankOf(string $sourceType): int
    {
        if (!isset(self::PRECEDENCE_RANK[$sourceType])) {
            self::failInputInvalid(self::MSG_UNKNOWN_SOURCE_TYPE);
        }

        return self::PRECEDENCE_RANK[$sourceType];
    }

    /**
     * @throws DeterministicException
     */
    private function assertRootIsMap(array $config): void
    {
        if ($config !== [] && array_is_list($config)) {
            self::failInputInvalid(self::MSG_ROOT_MUST_BE_MAP);
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function failInputInvalid(string $message): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_CONFIG_MERGER_INPUT_INVALID,
            $message,
        );
    }

    /**
     * @throws DeterministicException
     */
    private static function failResultInvalid(string $message): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_CONFIG_MERGER_RESULT_INVALID,
            $message,
        );
    }
}
