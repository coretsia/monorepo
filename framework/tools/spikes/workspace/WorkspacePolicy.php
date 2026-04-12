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

namespace Coretsia\Tools\Spikes\workspace;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class WorkspacePolicy
{
    public const string MANAGED_MARKER_KEY = 'coretsia_managed';
    public const bool MANAGED_MARKER_VALUE = true;

    public const string KEY_REPOSITORIES = 'repositories';

    public const string KEY_TYPE = 'type';
    public const string KEY_URL = 'url';
    public const string KEY_OPTIONS = 'options';

    /**
     * Managed repository entry maps are cemented to this allowlist (single-choice):
     * - type (string)
     * - url (string)
     * - options (any, optional)
     * - coretsia_managed (strict true)
     *
     * Any other key MUST fail with CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID.
     *
     * @var list<string>
     */
    private const array MANAGED_ENTRY_ALLOWED_KEYS = [
        self::KEY_TYPE,
        self::KEY_URL,
        self::KEY_OPTIONS,
        self::MANAGED_MARKER_KEY,
    ];

    private function __construct()
    {
    }

    /**
     * A “managed entry” is any repository item map where coretsia_managed === true (strict).
     */
    public static function isManagedRepositoryEntry(mixed $repoItem): bool
    {
        if (!\is_array($repoItem)) {
            return false;
        }

        return \array_key_exists(self::MANAGED_MARKER_KEY, $repoItem)
            && $repoItem[self::MANAGED_MARKER_KEY] === self::MANAGED_MARKER_VALUE;
    }

    /**
     * Safe helper (when present):
     * - If composer.json has no "repositories" key, returns composer json unchanged (does NOT add the key).
     * - If present, validates shape/invariants and rebuilds ONLY the contiguous managed block.
     *
     * @param array $composerJson Parsed composer.json associative array (insertion order preserved by json_decode).
     * @return array Updated composer json (same key order; repositories value updated in-place).
     */
    public static function rebuildManagedRepositoriesBlockIfPresent(array $composerJson): array
    {
        if (!\array_key_exists(self::KEY_REPOSITORIES, $composerJson)) {
            return $composerJson;
        }

        $composerJson[self::KEY_REPOSITORIES] = self::rebuildManagedRepositoriesBlock(
            $composerJson[self::KEY_REPOSITORIES]
        );

        return $composerJson;
    }

    /**
     * Validate repositories shape + managed-block invariants and rebuild ONLY the contiguous managed block:
     * - managed entries are sorted by normalized url (strcmp)
     * - managed entry maps are emitted with canonical key insertion order
     * - user-owned entries are preserved as-is (same order, same key insertion order, same values)
     *
     * @return list<array>
     * @throws DeterministicException
     */
    public static function rebuildManagedRepositoriesBlock(mixed $repositoriesValue): array
    {
        self::assertRepositoriesListOfMaps($repositoriesValue);

        /** @var list<array> $repositories */
        $repositories = $repositoriesValue;

        $split = self::splitIntoUserAndManagedBlocks($repositories);

        if ($split['managed'] === []) {
            // No managed entries => preserve original repositories list exactly (no normalization).
            return $repositories;
        }

        $managedSorted = self::sortManagedEntriesByNormalizedUrl($split['managed']);

        /** @var list<array> $managedRebuilt */
        $managedRebuilt = [];
        foreach ($managedSorted as $entry) {
            $managedRebuilt[] = self::rebuildManagedEntryCanonical($entry);
        }

        return \array_merge($split['prefix'], $managedRebuilt, $split['suffix']);
    }

    /**
     * @throws DeterministicException
     */
    public static function assertRepositoriesListOfMaps(mixed $repositoriesValue): void
    {
        if (!\is_array($repositoriesValue) || !\array_is_list($repositoriesValue)) {
            self::failManagedBlockInvalid();
        }

        foreach ($repositoriesValue as $item) {
            if (!\is_array($item)) {
                self::failManagedBlockInvalid();
            }
        }
    }

    /**
     * If any managed entry exists, then ALL managed entries MUST form a single contiguous block
     * inside the repositories list.
     *
     * @param list<array> $repositories
     * @return array{prefix:list<array>, managed:list<array>, suffix:list<array>}
     *
     * @throws DeterministicException
     */
    public static function splitIntoUserAndManagedBlocks(array $repositories): array
    {
        $firstManaged = null;
        $lastManaged = null;

        $n = \count($repositories);
        for ($i = 0; $i < $n; $i++) {
            if (!self::isManagedRepositoryEntry($repositories[$i])) {
                continue;
            }

            $firstManaged ??= $i;
            $lastManaged = $i;
        }

        // No managed entries => whole list is user-owned prefix.
        if ($firstManaged === null || $lastManaged === null) {
            return [
                'prefix' => $repositories,
                'managed' => [],
                'suffix' => [],
            ];
        }

        // Contiguity invariant: no user-owned entries between firstManaged..lastManaged.
        for ($i = $firstManaged; $i <= $lastManaged; $i++) {
            if (!self::isManagedRepositoryEntry($repositories[$i])) {
                self::failManagedBlockInvalid();
            }
        }

        /** @var list<array> $prefix */
        $prefix = \array_slice($repositories, 0, $firstManaged);

        /** @var list<array> $managed */
        $managed = \array_slice($repositories, $firstManaged, $lastManaged - $firstManaged + 1);

        /** @var list<array> $suffix */
        $suffix = \array_slice($repositories, $lastManaged + 1);

        return [
            'prefix' => $prefix,
            'managed' => $managed,
            'suffix' => $suffix,
        ];
    }

    /**
     * The managed block output order is deterministic (single-choice):
     * - managed entries MUST be sorted by normalized url ascending using strcmp
     * - URL normalization for sorting is single-choice: replace "\" with "/"
     * - If url is missing or not a string on a managed entry => MUST fail
     *
     * Tie-breaker (cemented for determinism): original position in the managed block (ascending).
     *
     * @param list<array> $managed
     * @return list<array>
     *
     * @throws DeterministicException
     */
    public static function sortManagedEntriesByNormalizedUrl(array $managed): array
    {
        /**
         * @var list<array{i:int, url:string, entry:array}> $decorated
         */
        $decorated = [];

        foreach ($managed as $i => $entry) {
            // managed entry shape check is performed here because url invariants are cemented.
            if (!\array_key_exists(self::KEY_URL, $entry) || !\is_string($entry[self::KEY_URL])) {
                self::failManagedBlockInvalid();
            }

            $url = (string) $entry[self::KEY_URL];

            $decorated[] = [
                'i' => (int) $i,
                'url' => self::normalizeUrlForSort($url),
                'entry' => $entry,
            ];
        }

        \usort(
            $decorated,
            static function (array $a, array $b): int {
                $c = \strcmp($a['url'], $b['url']);
                if ($c !== 0) {
                    return $c;
                }

                // Deterministic tie-breaker: preserve original relative order among duplicates.
                return $a['i'] <=> $b['i'];
            }
        );

        /** @var list<array> $out */
        $out = [];
        foreach ($decorated as $row) {
            $out[] = $row['entry'];
        }

        return $out;
    }

    /**
     * Canonical key insertion order for managed repository entry maps (single-choice):
     * - required keys order: type, url, coretsia_managed
     * - if optional keys exist (e.g. options), they MUST appear between url and coretsia_managed:
     *   type, url, options, coretsia_managed
     *
     * Strictness (single-choice):
     * - managed entries MUST NOT contain any keys beyond: type, url, options?, coretsia_managed.
     *
     * Notes:
     * - This function normalizes managed url values (single-choice): "\" => "/".
     * - User-owned entries remain untouched; caller must apply this only to managed entries.
     *
     * @throws DeterministicException
     */
    public static function rebuildManagedEntryCanonical(array $entry): array
    {
        // Strict allowlist: no extra keys are permitted on managed entries.
        foreach ($entry as $k => $_) {
            if (!\is_string($k)) {
                self::failManagedBlockInvalid();
            }
            if (!\in_array($k, self::MANAGED_ENTRY_ALLOWED_KEYS, true)) {
                self::failManagedBlockInvalid();
            }
        }

        if (
            !\array_key_exists(self::MANAGED_MARKER_KEY, $entry)
            || $entry[self::MANAGED_MARKER_KEY] !== self::MANAGED_MARKER_VALUE
        ) {
            self::failManagedBlockInvalid();
        }

        if (!\array_key_exists(self::KEY_URL, $entry) || !\is_string($entry[self::KEY_URL])) {
            self::failManagedBlockInvalid();
        }

        if (!\array_key_exists(self::KEY_TYPE, $entry) || !\is_string($entry[self::KEY_TYPE])) {
            self::failManagedBlockInvalid();
        }

        $type = (string) $entry[self::KEY_TYPE];
        $url = self::normalizeUrlForSort((string) $entry[self::KEY_URL]);

        $out = [];

        // required keys
        $out[self::KEY_TYPE] = $type;
        $out[self::KEY_URL] = $url;

        // options between url and marker (if present)
        if (\array_key_exists(self::KEY_OPTIONS, $entry)) {
            $out[self::KEY_OPTIONS] = $entry[self::KEY_OPTIONS];
        }

        // marker last, strict true
        $out[self::MANAGED_MARKER_KEY] = self::MANAGED_MARKER_VALUE;

        return $out;
    }

    /**
     * URL normalization for sorting is single-choice: replace "\" with "/".
     */
    public static function normalizeUrlForSort(string $url): string
    {
        return \str_replace('\\', '/', $url);
    }

    /**
     * @throws DeterministicException
     */
    private static function failManagedBlockInvalid(): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID,
            ErrorCodes::CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID,
        );
    }
}
