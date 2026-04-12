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
 * Directives processor (spike).
 *
 * Cemented rules:
 *  - Reserved namespace guard: any key that starts with '@' is reserved for directives only.
 *    Only the allowlisted directives are permitted. Any other '@*' MUST fail deterministically
 *    with CORETSIA_CONFIG_RESERVED_NAMESPACE_USED (even if it appears “as data”).
 *
 *  - Exclusive-level rule:
 *      If a map contains any directive key at a given level, then:
 *       - ALL keys at that level MUST be allowlisted directives (no mixing with normal keys)
 *       - EXACTLY ONE directive key is allowed at that level
 *      Violations MUST fail with CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL.
 *
 *  - Typing rules (with empty-array rule cemented):
 * @append/@prepend/@remove: value MUST be a list; [] is accepted as empty list
 * @merge: value MUST be a map; [] is accepted as empty map
 * @replace: value MAY be any scalar/list/map
 *
 *  - Error precedence (cemented):
 *      1) If any unknown '@*' key exists anywhere → CORETSIA_CONFIG_RESERVED_NAMESPACE_USED
 *      2) Else if any exclusive-level violation exists anywhere → CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL
 *      3) Else if any directive typing / merge-time kind mismatch occurs → CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH
 *
 *  - Two-phase semantics (normative):
 *      Phase A (per-file): validate (allowlist + reserved namespace + exclusive-level + typing).
 *      Phase B (merge-time): apply the directive deterministically against the resolved base value.
 *
 * Notes:
 *  - Deterministic failures MUST surface as DeterministicException.
 *  - This processor does NOT implement global config key sorting; callers are responsible for map normalization.
 */
final class DirectiveProcessor
{
    /**
     * Allowed directives (allowlist, cemented).
     *
     * @var array<string, true>
     */
    public const array DIRECTIVE_ALLOWLIST = [
        '@append' => true,
        '@prepend' => true,
        '@remove' => true,
        '@merge' => true,
        '@replace' => true,
    ];

    /**
     * Phase A: validate directives across the whole config tree.
     *
     * Error precedence is enforced globally by doing three deterministic passes:
     *  1) unknown reserved namespace guard
     *  2) exclusive-level rule (mixed-level / multi-directive)
     *  3) directive typing rules (including empty-array rule)
     */
    public function validatePhaseA(array $config): void
    {
        $this->scanUnknownReservedNamespace($config);
        $this->scanExclusiveLevelViolations($config);
        $this->scanTypingViolations($config);
    }

    /**
     * Phase A helper: if $mapLevel is a directive node, returns a normalized representation.
     *
     * @param array<string, mixed> $mapLevel
     * @return array{directive: string, value: mixed}|null
     */
    public function parseDirectiveNode(array $mapLevel): ?array
    {
        if (!self::isDirectiveNode($mapLevel)) {
            return null;
        }

        $directive = (string)array_key_first($mapLevel);
        $value = $mapLevel[$directive];

        return [
            'directive' => $directive,
            'value' => $value,
        ];
    }

    /**
     * Phase B: apply a directive node deterministically at merge-time.
     *
     * Missing-base semantics (cemented):
     *  - list directives: missing base => []
     *  - @merge: missing base => [] (interpreted as map by context)
     *  - @replace: base ignored
     *
     * @param mixed $baseValue null means "missing base"
     * @param array $directiveNode
     * @return mixed
     *
     * @throws DeterministicException
     */
    public function applyPhaseB(mixed $baseValue, array $directiveNode): mixed
    {
        if ($directiveNode === [] || array_is_list($directiveNode) || count($directiveNode) !== 1) {
            // Caller bug; keep deterministic failure.
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL);
        }

        $directive = (string)array_key_first($directiveNode);

        if (!isset(self::DIRECTIVE_ALLOWLIST[$directive])) {
            // No separate code is allowed for unknown directives.
            $this->fail(ErrorCodes::CORETSIA_CONFIG_RESERVED_NAMESPACE_USED);
        }

        $value = $directiveNode[$directive];

        if ($directive === '@replace') {
            // Replacement is unconditional.
            return $value;
        }

        if ($directive === '@append' || $directive === '@prepend' || $directive === '@remove') {
            $baseList = $this->coerceBaseList($baseValue);
            $directiveList = $this->coerceDirectiveList($value);

            if ($directive === '@append') {
                /** @var list<mixed> $result */
                $result = array_values(array_merge($baseList, $directiveList));

                return $result;
            }

            if ($directive === '@prepend') {
                /** @var list<mixed> $result */
                $result = array_values(array_merge($directiveList, $baseList));

                return $result;
            }

            // @remove: strict (===) removal, applied in order, idempotent.
            $result = $baseList;
            foreach ($directiveList as $needle) {
                $filtered = [];
                foreach ($result as $item) {
                    if ($item === $needle) {
                        continue;
                    }
                    $filtered[] = $item;
                }
                $result = $filtered;
            }

            /** @var list<mixed> $result */
            $result = array_values($result);

            return $result;
        }

        if ($directive === '@merge') {
            $baseMap = $this->coerceBaseMap($baseValue);
            $deltaMap = $this->coerceDirectiveMap($value);

            return $this->mergeMapsDeep($baseMap, $deltaMap);
        }

        // Should be unreachable due to allowlist check above.
        $this->fail(ErrorCodes::CORETSIA_CONFIG_RESERVED_NAMESPACE_USED);
    }

    /**
     * Directive node detection (cemented):
     *  - must be a map (non-list)
     *  - must have exactly one key
     *  - that key must be in directive allowlist
     */
    public static function isDirectiveNode(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        if (array_is_list($value)) {
            return false;
        }

        if (count($value) !== 1) {
            return false;
        }

        $keys = array_keys($value);
        $key = (string)$keys[0];

        return isset(self::DIRECTIVE_ALLOWLIST[$key]);
    }

    // -------------------------------------------------------------------------
    // Phase A validation passes (global error precedence)
    // -------------------------------------------------------------------------

    private function scanUnknownReservedNamespace(mixed $node): void
    {
        if (!is_array($node) || $node === []) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->scanUnknownReservedNamespace($item);
            }

            return;
        }

        /** @var array<string|int, mixed> $node */
        foreach ($node as $key => $value) {
            $k = (string)$key;

            if ($k !== '' && $k[0] === '@' && !isset(self::DIRECTIVE_ALLOWLIST[$k])) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_RESERVED_NAMESPACE_USED);
            }

            $this->scanUnknownReservedNamespace($value);
        }
    }

    private function scanExclusiveLevelViolations(mixed $node): void
    {
        if (!is_array($node) || $node === []) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->scanExclusiveLevelViolations($item);
            }

            return;
        }

        /** @var array<string|int, mixed> $node */
        $keys = array_keys($node);

        $directiveKeys = [];
        foreach ($keys as $key) {
            $k = (string)$key;

            if ($k !== '' && $k[0] === '@' && isset(self::DIRECTIVE_ALLOWLIST[$k])) {
                $directiveKeys[] = $k;
            }
        }

        if ($directiveKeys !== []) {
            // Must be directives-only at this level.
            if (count($directiveKeys) !== count($keys)) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL);
            }

            // Exactly one directive at this level.
            if (count($directiveKeys) !== 1) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL);
            }
        }

        foreach ($node as $v) {
            $this->scanExclusiveLevelViolations($v);
        }
    }

    private function scanTypingViolations(mixed $node): void
    {
        if (!is_array($node) || $node === []) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->scanTypingViolations($item);
            }

            return;
        }

        /** @var array<string, mixed> $node */
        $directive = null;

        foreach (array_keys($node) as $key) {
            $k = (string)$key;
            if ($k !== '' && $k[0] === '@' && isset(self::DIRECTIVE_ALLOWLIST[$k])) {
                $directive = $k;
                break;
            }
        }

        if ($directive !== null) {
            $value = $node[$directive];

            $this->validateDirectiveTyping($directive, $value);

            // Directive values may contain nested directives; validate recursively.
            $this->scanTypingViolations($value);

            return;
        }

        // No directive at this map level: validate nested containers.
        foreach ($node as $v) {
            $this->scanTypingViolations($v);
        }
    }

    private function validateDirectiveTyping(string $directive, mixed $value): void
    {
        if ($directive === '@replace') {
            return;
        }

        if ($directive === '@append' || $directive === '@prepend' || $directive === '@remove') {
            if (!is_array($value)) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
            }

            if ($value === []) {
                return; // empty-array rule: accepted as empty list
            }

            if (!array_is_list($value)) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
            }

            return;
        }

        if ($directive === '@merge') {
            if (!is_array($value)) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
            }

            if ($value === []) {
                return; // empty-array rule: accepted as empty map
            }

            if (array_is_list($value)) {
                $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
            }

            return;
        }

        // Unknown directive is always reserved-namespace usage (no separate code).
        $this->fail(ErrorCodes::CORETSIA_CONFIG_RESERVED_NAMESPACE_USED);
    }

    // -------------------------------------------------------------------------
    // Phase B helpers (missing-base semantics + deep merge for @merge)
    // -------------------------------------------------------------------------

    /**
     * Deep merge semantics for @merge:
     *  - nested maps are merged recursively
     *  - lists are replaced (no implicit list merge) unless list directives are used
     *  - directive nodes can appear inside delta maps and are applied against base values
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $delta
     * @return array<string, mixed>
     */
    private function mergeMapsDeep(array $base, array $delta): array
    {
        $out = [];

        $keys = $this->unionKeys($base, $delta);

        foreach ($keys as $key) {
            $hasDelta = array_key_exists($key, $delta);

            if (!$hasDelta) {
                $out[$key] = $base[$key];
                continue;
            }

            $deltaValue = $delta[$key];
            $baseValue = array_key_exists($key, $base) ? $base[$key] : null;

            if (is_array($deltaValue) && self::isDirectiveNode($deltaValue)) {
                $out[$key] = $this->applyPhaseB($baseValue, $deltaValue);
                continue;
            }

            if (!is_array($deltaValue)) {
                $out[$key] = $deltaValue;
                continue;
            }

            if ($deltaValue === []) {
                // Empty array is ambiguous; choose interpretation by existing base kind.
                if (is_array($baseValue) && $baseValue !== [] && array_is_list($baseValue)) {
                    // base list => replace with empty list
                    $out[$key] = [];
                    continue;
                }

                if (is_array($baseValue) && ($baseValue === [] || !array_is_list($baseValue))) {
                    // base map (or empty) => empty map delta is a no-op in deep-merge
                    $out[$key] = $baseValue;
                    continue;
                }

                // base missing or scalar => delta empty container replaces with empty
                $out[$key] = [];
                continue;
            }

            if (array_is_list($deltaValue)) {
                // list replaces
                $out[$key] = $deltaValue;
                continue;
            }

            // delta is a map
            if (is_array($baseValue) && $baseValue !== [] && array_is_list($baseValue)) {
                // base is list; delta map replaces (resolve nested directives against missing base).
                /** @var array<string, mixed> $deltaValue */
                $out[$key] = $this->resolveAgainstMissingBaseMap($deltaValue);
                continue;
            }

            $baseMap = (is_array($baseValue) && ($baseValue === [] || !array_is_list($baseValue)))
                ? $baseValue
                : [];

            /** @var array<string, mixed> $deltaValue */
            $out[$key] = $this->mergeMapsDeep($baseMap, $deltaValue);
        }

        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Resolve a map subtree as if base is missing everywhere.
     * This guarantees directive keys do not leak into a resolved subtree.
     *
     * Public: used by ConfigMerger to avoid duplicating directive-resolution logic.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    public function resolveAgainstMissingBaseMap(array $map): array
    {
        if ($map === []) {
            return [];
        }

        $out = [];

        foreach ($map as $key => $value) {
            if (is_array($value) && self::isDirectiveNode($value)) {
                $out[$key] = $this->applyPhaseB(null, $value);
                continue;
            }

            if (!is_array($value) || $value === [] || array_is_list($value)) {
                $out[$key] = $value;
                continue;
            }

            /** @var array<string, mixed> $value */
            $out[$key] = $this->resolveAgainstMissingBaseMap($value);
        }

        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * @return list<mixed>
     */
    private function coerceBaseList(mixed $baseValue): array
    {
        if ($baseValue === null) {
            return [];
        }

        if (!is_array($baseValue)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        if ($baseValue === []) {
            return [];
        }

        if (!array_is_list($baseValue)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        /** @var list<mixed> $baseValue */
        return $baseValue;
    }

    /**
     * @return list<mixed>
     */
    private function coerceDirectiveList(mixed $value): array
    {
        if (!is_array($value)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        if ($value === []) {
            return [];
        }

        if (!array_is_list($value)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        /** @var list<mixed> $value */
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function coerceBaseMap(mixed $baseValue): array
    {
        if ($baseValue === null) {
            return [];
        }

        if (!is_array($baseValue)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        if ($baseValue === []) {
            return [];
        }

        if (array_is_list($baseValue)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        /** @var array<string, mixed> $baseValue */
        return $baseValue;
    }

    /**
     * @return array<string, mixed>
     */
    private function coerceDirectiveMap(mixed $value): array
    {
        if (!is_array($value)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            $this->fail(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return list<string|int>
     */
    private function unionKeys(array $a, array $b): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($a), array_keys($b))));

        /** @var list<string|int> $keys */
        return $keys;
    }

    /**
     * @param string $code
     * @return never
     */
    private function fail(string $code): never
    {
        if (!ErrorCodes::has($code)) {
            throw new \LogicException('directive-processor-unknown-error-code');
        }

        throw new DeterministicException($code, $code);
    }
}
