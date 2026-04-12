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
 * Deterministic + safe explain trace builder.
 *
 * Normative rules (cemented):
 *  - Produces a deterministic list of trace records (no values, no secrets).
 *  - Record shape (safe): sourceType, file, keyPath, directiveApplied.
 *  - Ordering (single-choice; cemented):
 *      1) keyPath ascending by byte-order (strcmp)
 *      2) then precedenceRank ascending (lower-precedence first), fixed:
 *         defaults < skeleton < module < app
 *      3) then file ascending by byte-order (strcmp)
 *  - No locale-dependent ordering is allowed.
 *
 * Determinism hardening (cemented for this spike):
 *  - Sorting MUST be total (no unstable ties). A monotonically increasing _seq is used
 *    as the final tie-breaker if (keyPath, precedenceRank, file) are identical.
 *
 * Input contract (spike-friendly):
 *  - $sources is a list of entries:
 *      [
 *        'sourceType' => 'defaults'|'skeleton'|'module'|'app',
 *        'file' => 'path-or-id.php',
 *        'config' => array<string, mixed>
 *      ]
 *
 * Note:
 *  - Directive detection is delegated to DirectiveProcessor::isDirectiveNode()
 *    to guarantee 1:1 behavior with ConfigMerger.
 */
final class ConfigExplainer
{
    private const string MSG_SOURCE_ENTRY_INVALID = 'config-explainer-source-entry-invalid';
    private const string MSG_SOURCE_ENTRY_INVALID_TYPES = 'config-explainer-source-entry-invalid-types';
    private const string MSG_UNKNOWN_SOURCE_TYPE = 'config-explainer-unknown-source-type';

    /**
     * @var array<string, int>
     */
    private const array PRECEDENCE_RANK = [
        'defaults' => 0,
        'skeleton' => 1,
        'module' => 2,
        'app' => 3,
    ];

    /**
     * @param list<array{sourceType: string, file: string, config: array}> $sources
     * @return list<array{sourceType: string, file: string, keyPath: string, directiveApplied: bool}>
     *
     * @throws DeterministicException
     */
    public function explain(array $sources): array
    {
        $records = [];
        $seq = 0;

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

            $this->walkConfig(
                $config,
                '',
                $sourceType,
                $rank,
                $file,
                $records,
                $seq,
            );
        }

        usort(
            $records,
            static function (array $a, array $b): int {
                $cmp = strcmp($a['keyPath'], $b['keyPath']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = $a['_precedenceRank'] <=> $b['_precedenceRank'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = strcmp($a['file'], $b['file']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                // Final tie-breaker to guarantee total order and avoid unstable usort() ties.
                return $a['_seq'] <=> $b['_seq'];
            },
        );

        // Drop internal sort-only fields deterministically.
        foreach ($records as $k => $record) {
            unset($record['_precedenceRank'], $record['_seq']);
            $records[$k] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{
     *   sourceType: string,
     *   file: string,
     *   keyPath: string,
     *   directiveApplied: bool,
     *   _precedenceRank: int,
     *   _seq: int
     * }> $out
     */
    private function walkConfig(
        array $node,
        string $prefix,
        string $sourceType,
        int $precedenceRank,
        string $file,
        array &$out,
        int &$seq,
    ): void {
        if ($node === []) {
            return;
        }

        $keys = array_keys($node);

        usort(
            $keys,
            static fn(string|int $a, string|int $b): int => strcmp((string) $a, (string) $b),
        );

        foreach ($keys as $key) {
            $k = (string) $key;
            $value = $node[$key];

            $path = $prefix === '' ? $k : ($prefix . '.' . $k);

            if (is_array($value)) {
                if (DirectiveProcessor::isDirectiveNode($value)) {
                    $out[] = [
                        'sourceType' => $sourceType,
                        'file' => $file,
                        'keyPath' => $path,
                        'directiveApplied' => true,
                        '_precedenceRank' => $precedenceRank,
                        '_seq' => $seq++,
                    ];
                    continue;
                }

                if ($value === [] || array_is_list($value)) {
                    $out[] = [
                        'sourceType' => $sourceType,
                        'file' => $file,
                        'keyPath' => $path,
                        'directiveApplied' => false,
                        '_precedenceRank' => $precedenceRank,
                        '_seq' => $seq++,
                    ];
                    continue;
                }

                // Map: recurse deterministically; do not emit container record (leaves carry meaning).
                /** @var array<string, mixed> $value */
                $this->walkConfig(
                    $value,
                    $path,
                    $sourceType,
                    $precedenceRank,
                    $file,
                    $out,
                    $seq,
                );

                continue;
            }

            // Scalar leaf.
            $out[] = [
                'sourceType' => $sourceType,
                'file' => $file,
                'keyPath' => $path,
                'directiveApplied' => false,
                '_precedenceRank' => $precedenceRank,
                '_seq' => $seq++,
            ];
        }
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
    private static function failInputInvalid(string $message): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_CONFIG_EXPLAINER_INPUT_INVALID,
            $message,
        );
    }
}
