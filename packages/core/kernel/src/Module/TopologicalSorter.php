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

namespace Coretsia\Kernel\Module;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Foundation\Discovery\DeterministicOrder;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;

/**
 * Deterministic topological sorter for enabled module plan entries.
 *
 * Edge direction:
 *
 *     A requires B
 *
 * means:
 *
 *     B must appear before A in topologicalOrder.
 *
 * This sorter intentionally owns only deterministic topo ordering and cycle
 * detection.
 *
 * It does not classify missing modules and does not classify conflicts.
 * Missing required modules, disabled required modules, and conflicts must be
 * classified before this sorter is called by ModuleGraphResolver.
 *
 * Only enabled modules participate in topo sorting. The caller must pass the
 * enabled resolved ModulePlanEntry list. Dependency edges pointing to module ids
 * outside this enabled set are ignored here because required-missing and
 * transitive-closure policy is owned by ModuleGraphResolver.
 *
 * Diagnostics intentionally expose only deterministic module ids through
 * ModuleCycleDetectedException. They must not expose graph dumps, filesystem
 * paths, filesystem layout, Composer raw metadata, preset raw payloads, or
 * service internals.
 *
 * @internal
 */
final class TopologicalSorter
{
    /**
     * @param list<ModulePlanEntry> $enabledModules
     *
     * @return list<ModuleId>
     */
    public function sort(array $enabledModules): array
    {
        $entriesById = $this->normalizeEnabledModules($enabledModules);

        if ($entriesById === []) {
            return [];
        }

        [$requiresByModuleId, $dependentsByDependencyId, $incomingEdgeCounts] = $this->buildGraph($entriesById);

        $available = [];

        foreach ($incomingEdgeCounts as $moduleId => $count) {
            if ($count === 0) {
                $available[] = $moduleId;
            }
        }

        $available = self::sortModuleIdStrings($available);

        $sorted = [];
        $sortedMap = [];

        while ($available !== []) {
            $moduleId = \array_shift($available);

            if (!\is_string($moduleId)) {
                throw new \LogicException('topological-sort-available-module-id-invalid');
            }

            $sorted[] = $entriesById[$moduleId]->moduleId();
            $sortedMap[$moduleId] = true;

            foreach ($dependentsByDependencyId[$moduleId] ?? [] as $dependentModuleId) {
                --$incomingEdgeCounts[$dependentModuleId];

                if ($incomingEdgeCounts[$dependentModuleId] === 0) {
                    $available[] = $dependentModuleId;
                }
            }

            $available = self::sortModuleIdStrings($available);
        }

        if (\count($sorted) !== \count($entriesById)) {
            $cycleModuleIds = $this->detectCycleModuleIds(
                requiresByModuleId: $requiresByModuleId,
                entriesById: $entriesById,
                sortedMap: $sortedMap,
            );

            throw ModuleCycleDetectedException::forModules($cycleModuleIds);
        }

        return $sorted;
    }

    /**
     * @param list<ModulePlanEntry> $enabledModules
     *
     * @return array<string, ModulePlanEntry>
     */
    private function normalizeEnabledModules(array $enabledModules): array
    {
        if (!\array_is_list($enabledModules)) {
            throw new \InvalidArgumentException('topological-sort-enabled-modules-must-be-list');
        }

        $entriesById = [];

        foreach ($enabledModules as $entry) {
            if (!$entry instanceof ModulePlanEntry) {
                throw new \InvalidArgumentException('topological-sort-module-entry-invalid');
            }

            $moduleId = $entry->moduleIdString();

            if (isset($entriesById[$moduleId])) {
                throw new \InvalidArgumentException('topological-sort-module-entry-duplicate');
            }

            $entriesById[$moduleId] = $entry;
        }

        \ksort($entriesById, \SORT_STRING);

        return $entriesById;
    }

    /**
     * @param array<string, ModulePlanEntry> $entriesById
     *
     * @return array{
     *     0: array<string, list<string>>,
     *     1: array<string, list<string>>,
     *     2: array<string, int>
     * }
     */
    private function buildGraph(array $entriesById): array
    {
        $requiresByModuleId = [];
        $dependentsByDependencyId = [];
        $incomingEdgeCounts = [];

        foreach ($entriesById as $moduleId => $_entry) {
            $requiresByModuleId[$moduleId] = [];
            $dependentsByDependencyId[$moduleId] = [];
            $incomingEdgeCounts[$moduleId] = 0;
        }

        foreach ($entriesById as $moduleId => $entry) {
            $enabledDependencies = [];

            foreach ($entry->requires() as $dependencyModuleId) {
                $dependencyId = $dependencyModuleId->value();

                if (!isset($entriesById[$dependencyId])) {
                    continue;
                }

                $enabledDependencies[$dependencyId] = true;
            }

            $dependencies = self::sortModuleIdStrings(\array_keys($enabledDependencies));
            $requiresByModuleId[$moduleId] = $dependencies;
            $incomingEdgeCounts[$moduleId] = \count($dependencies);

            foreach ($dependencies as $dependencyId) {
                $dependentsByDependencyId[$dependencyId][] = $moduleId;
            }
        }

        foreach ($dependentsByDependencyId as $dependencyId => $dependents) {
            $dependentsByDependencyId[$dependencyId] = self::sortModuleIdStrings($dependents);
        }

        return [
            $requiresByModuleId,
            $dependentsByDependencyId,
            $incomingEdgeCounts,
        ];
    }

    /**
     * @param array<string, list<string>> $requiresByModuleId
     * @param array<string, ModulePlanEntry> $entriesById
     * @param array<string, true> $sortedMap
     *
     * @return list<ModuleId>
     */
    private function detectCycleModuleIds(
        array $requiresByModuleId,
        array $entriesById,
        array $sortedMap,
    ): array {
        $remainingIds = [];

        foreach ($entriesById as $moduleId => $_entry) {
            if (!isset($sortedMap[$moduleId])) {
                $remainingIds[] = $moduleId;
            }
        }

        $remainingIds = self::sortModuleIdStrings($remainingIds);
        $remainingSet = \array_fill_keys($remainingIds, true);

        $state = [];
        $path = [];
        $pathIndex = [];

        foreach ($remainingIds as $moduleId) {
            if (($state[$moduleId] ?? null) === 'visited') {
                continue;
            }

            $cycleIds = $this->visitForCycle(
                moduleId: $moduleId,
                requiresByModuleId: $requiresByModuleId,
                remainingSet: $remainingSet,
                state: $state,
                path: $path,
                pathIndex: $pathIndex,
            );

            if ($cycleIds !== []) {
                return $this->moduleIdsFromStrings($cycleIds, $entriesById);
            }
        }

        return $this->moduleIdsFromStrings($remainingIds, $entriesById);
    }

    /**
     * @param array<string, list<string>> $requiresByModuleId
     * @param array<string, true> $remainingSet
     * @param array<string, string> $state
     * @param list<string> $path
     * @param array<string, int> $pathIndex
     *
     * @return list<string>
     */
    private function visitForCycle(
        string $moduleId,
        array $requiresByModuleId,
        array $remainingSet,
        array &$state,
        array &$path,
        array &$pathIndex,
    ): array {
        $state[$moduleId] = 'visiting';
        $pathIndex[$moduleId] = \count($path);
        $path[] = $moduleId;

        foreach ($requiresByModuleId[$moduleId] ?? [] as $dependencyId) {
            if (!isset($remainingSet[$dependencyId])) {
                continue;
            }

            $dependencyState = $state[$dependencyId] ?? null;

            if ($dependencyState === 'visiting') {
                return \array_slice($path, $pathIndex[$dependencyId]);
            }

            if ($dependencyState !== 'visited') {
                $cycle = $this->visitForCycle(
                    moduleId: $dependencyId,
                    requiresByModuleId: $requiresByModuleId,
                    remainingSet: $remainingSet,
                    state: $state,
                    path: $path,
                    pathIndex: $pathIndex,
                );

                if ($cycle !== []) {
                    return $cycle;
                }
            }
        }

        \array_pop($path);
        unset($pathIndex[$moduleId]);
        $state[$moduleId] = 'visited';

        return [];
    }

    /**
     * @param list<string> $moduleIds
     * @param array<string, ModulePlanEntry> $entriesById
     *
     * @return list<ModuleId>
     */
    private function moduleIdsFromStrings(array $moduleIds, array $entriesById): array
    {
        $out = [];

        foreach (self::sortModuleIdStrings($moduleIds) as $moduleId) {
            if (!isset($entriesById[$moduleId])) {
                throw new \LogicException('topological-sort-cycle-module-id-missing');
            }

            $out[] = $entriesById[$moduleId]->moduleId();
        }

        return $out;
    }

    /**
     * @param list<string> $moduleIds
     *
     * @return list<string>
     */
    private static function sortModuleIdStrings(array $moduleIds): array
    {
        $unique = \array_values(\array_unique($moduleIds));

        return DeterministicOrder::sort(
            $unique,
            static fn (string $moduleId): string => $moduleId,
            static fn (string $_moduleId): int => 0,
        );
    }
}
