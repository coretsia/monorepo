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

/**
 * Immutable deterministic Kernel ModulePlan.
 *
 * This value object is the stable payload shape used by future artifacts,
 * debug output, and adapters.
 *
 * It intentionally stores only resolved plan data:
 *
 * - selected app target;
 * - enabled module ids;
 * - explicitly excluded module ids;
 * - topological module order;
 * - resolved module entries;
 *
 * It must not store or expose applicationRoot, appRoot, defaultsPath,
 * overridesPath, absolute paths, provider class lists, Composer raw payloads,
 * preset raw payloads, raw config payloads, runtime services, service
 * instances, closures, resources, or filesystem handles.
 */
final readonly class ModulePlan
{
    public const int SCHEMA_VERSION = 1;
    /**
     * @var array<string, true>
     */
    private const array APP_TARGETS = [
        'api' => true,
        'console' => true,
        'web' => true,
        'worker' => true,
    ];

    private string $app;

    /**
     * @var list<ModuleId>
     */
    private array $enabled;

    /**
     * @var list<ModuleId>
     */
    private array $excluded;

    /**
     * @var list<ModuleId>
     */
    private array $topologicalOrder;

    /**
     * @var array<string, ModulePlanEntry>
     */
    private array $modules;

    /**
     * @param list<ModuleId> $enabled
     * @param list<ModuleId> $excluded
     * @param list<ModuleId> $topologicalOrder
     * @param list<ModulePlanEntry> $modules
     */
    public function __construct(string $app, array $enabled, array $excluded, array $topologicalOrder, array $modules)
    {
        if (!self::isValidAppTarget($app)) {
            throw new \InvalidArgumentException('module-plan-app-invalid');
        }
        $enabledSet = self::normalizeModuleIdSet($enabled, 'enabled');
        $excludedSet = self::normalizeModuleIdSet($excluded, 'excluded');
        $order = self::normalizeTopologicalOrder($topologicalOrder);
        $entries = self::normalizeModuleEntries($modules);
        self::assertModuleIdSetsDoNotOverlap($enabledSet, $excludedSet, 'module-plan-enabled-excluded-overlap');
        self::assertTopologicalOrderReferencesEnabledModules($order, $enabledSet);
        self::assertTopologicalOrderContainsAllEnabledModules($order, $enabledSet);
        self::assertEnabledModulesHaveEntries($enabledSet, $entries);
        self::assertModuleEntriesReferenceEnabledModules($entries, $enabledSet);
        foreach ($entries as $entry) {
            foreach ($entry->requires() as $id) {
                if (!isset($entries[$id->value()])) {
                    throw new \InvalidArgumentException('module-plan-required-entry-missing');
                }
            }
            foreach ($entry->conflicts() as $id) {
                if (isset($entries[$id->value()])) {
                    throw new \InvalidArgumentException('module-plan-enabled-module-conflict');
                }
            }
        }
        $this->app = $app;
        $this->enabled = $enabledSet;
        $this->excluded = $excludedSet;
        $this->topologicalOrder = $order;
        $this->modules = $entries;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function app(): string
    {
        return $this->app;
    }

    /**
     * @return list<ModuleId>
     */
    public function enabled(): array
    {
        return $this->enabled;
    }

    /**
     * @return list<ModuleId>
     */
    public function excluded(): array
    {
        return $this->excluded;
    }

    public function hasEnabledModule(string|ModuleId $moduleId): bool
    {
        return self::moduleIdListContains($this->enabled, $moduleId);
    }

    public function hasExcludedModule(string|ModuleId $moduleId): bool
    {
        return self::moduleIdListContains($this->excluded, $moduleId);
    }

    /**
     * @return list<ModuleId>
     */
    public function topologicalOrder(): array
    {
        return $this->topologicalOrder;
    }

    /**
     * @return array<string, ModulePlanEntry>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'app' => $this->app,
            'enabled' => self::moduleIdsToStrings($this->enabled),
            'excluded' => self::moduleIdsToStrings($this->excluded),
            'modules' => self::moduleEntriesToArray($this->modules),
            'schemaVersion' => self::SCHEMA_VERSION,
            'topologicalOrder' => self::moduleIdsToStrings($this->topologicalOrder),
        ];
    }

    private static function isValidAppTarget(string $app): bool
    {
        return isset(self::APP_TARGETS[$app]);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function normalizeModuleIdSet(array $moduleIds, string $field): array
    {
        if (!\array_is_list($moduleIds)) {
            throw new \InvalidArgumentException('module-plan-' . $field . '-module-ids-must-be-list');
        }
        $set = [];

        foreach ($moduleIds as $moduleId) {
            if (!$moduleId instanceof ModuleId) {
                throw new \InvalidArgumentException('module-plan-' . $field . '-module-id-invalid');
            }

            $set[$moduleId->value()] = $moduleId;
        }

        \ksort($set, \SORT_STRING);

        return \array_values($set);
    }

    /**
     * Topological order is not sorted here because dependency order is semantic.
     * The graph resolver/topological sorter owns deterministic ordering.
     *
     * @param list<ModuleId> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function normalizeTopologicalOrder(array $moduleIds): array
    {
        if (!\array_is_list($moduleIds)) {
            throw new \InvalidArgumentException('module-plan-topological-order-must-be-list');
        }

        $seen = [];
        $normalized = [];

        foreach ($moduleIds as $moduleId) {
            if (!$moduleId instanceof ModuleId) {
                throw new \InvalidArgumentException('module-plan-topological-order-module-id-invalid');
            }

            $value = $moduleId->value();

            if (isset($seen[$value])) {
                throw new \InvalidArgumentException('module-plan-topological-order-duplicate-module-id');
            }

            $seen[$value] = true;
            $normalized[] = $moduleId;
        }

        return $normalized;
    }

    /**
     * @param list<ModulePlanEntry> $modules
     *
     * @return array<string, ModulePlanEntry>
     */
    private static function normalizeModuleEntries(array $modules): array
    {
        if (!\array_is_list($modules)) {
            throw new \InvalidArgumentException('module-plan-modules-must-be-list');
        }

        $map = [];

        foreach ($modules as $entry) {
            if (!$entry instanceof ModulePlanEntry) {
                throw new \InvalidArgumentException('module-plan-module-entry-invalid');
            }

            $moduleId = $entry->moduleIdString();

            if (isset($map[$moduleId])) {
                throw new \InvalidArgumentException('module-plan-module-entry-duplicate');
            }

            $map[$moduleId] = $entry;
        }

        \ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @param list<ModuleId> $left
     * @param list<ModuleId> $right
     */
    private static function assertModuleIdSetsDoNotOverlap(
        array $left,
        array $right,
        string $reason,
    ): void {
        $leftMap = self::moduleIdPresenceMap($left);

        foreach ($right as $moduleId) {
            if (isset($leftMap[$moduleId->value()])) {
                throw new \InvalidArgumentException($reason);
            }
        }
    }

    /**
     * @param list<ModuleId> $topologicalOrder
     * @param list<ModuleId> $enabled
     */
    private static function assertTopologicalOrderReferencesEnabledModules(
        array $topologicalOrder,
        array $enabled,
    ): void {
        $enabledMap = self::moduleIdPresenceMap($enabled);

        foreach ($topologicalOrder as $moduleId) {
            if (!isset($enabledMap[$moduleId->value()])) {
                throw new \InvalidArgumentException('module-plan-topological-order-module-not-enabled');
            }
        }
    }

    /**
     * @param list<ModuleId> $topologicalOrder
     * @param list<ModuleId> $enabled
     */
    private static function assertTopologicalOrderContainsAllEnabledModules(
        array $topologicalOrder,
        array $enabled,
    ): void {
        $topologicalOrderMap = self::moduleIdPresenceMap($topologicalOrder);

        foreach ($enabled as $moduleId) {
            if (!isset($topologicalOrderMap[$moduleId->value()])) {
                throw new \InvalidArgumentException('module-plan-topological-order-enabled-module-missing');
            }
        }
    }

    /**
     * @param list<ModuleId> $enabled
     * @param array<string, ModulePlanEntry> $modules
     */
    private static function assertEnabledModulesHaveEntries(array $enabled, array $modules): void
    {
        foreach ($enabled as $moduleId) {
            if (!isset($modules[$moduleId->value()])) {
                throw new \InvalidArgumentException('module-plan-enabled-module-entry-missing');
            }
        }
    }

    /**
     * @param array<string, ModulePlanEntry> $modules
     * @param list<ModuleId> $enabled
     */
    private static function assertModuleEntriesReferenceEnabledModules(array $modules, array $enabled): void
    {
        $enabledMap = self::moduleIdPresenceMap($enabled);

        foreach ($modules as $moduleId => $_entry) {
            if (!isset($enabledMap[$moduleId])) {
                throw new \InvalidArgumentException('module-plan-module-entry-not-enabled');
            }
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return array<string, true>
     */
    private static function moduleIdPresenceMap(array $moduleIds): array
    {
        $map = [];

        foreach ($moduleIds as $moduleId) {
            $map[$moduleId->value()] = true;
        }

        return $map;
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }

    /**
     * @param array<string, ModulePlanEntry> $modules
     *
     * @return array<string, array{
     *     composerName: string,
     *     conflicts: list<string>,
     *     moduleId: string,
     *     requires: list<string>
     * }>
     */
    private static function moduleEntriesToArray(array $modules): array
    {
        $out = [];

        foreach ($modules as $moduleId => $entry) {
            $out[$moduleId] = $entry->toArray();
        }

        return $out;
    }

    /**
     * @param list<ModuleId> $moduleIds
     */
    private static function moduleIdListContains(array $moduleIds, string|ModuleId $needle): bool
    {
        $needleValue = self::moduleIdValue($needle);

        foreach ($moduleIds as $moduleId) {
            if ($moduleId->value() === $needleValue) {
                return true;
            }
        }

        return false;
    }

    private static function moduleIdValue(string|ModuleId $moduleId): string
    {
        if ($moduleId instanceof ModuleId) {
            return $moduleId->value();
        }

        return ModuleId::fromString($moduleId)->value();
    }
}
