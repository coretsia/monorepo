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
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;

/**
 * Immutable deterministic Kernel ModulePlan.
 *
 * This value object is the stable payload shape used by future artifacts,
 * debug output, and adapters.
 *
 * It intentionally stores only resolved plan data:
 *
 * - selected app target;
 * - selected preset name;
 * - enabled module ids;
 * - disabled module ids;
 * - optional missing module ids;
 * - topological module order;
 * - resolved module entries;
 * - non-fatal warnings.
 *
 * It must not store or expose skeletonRoot, appRoot, defaultsPath,
 * overridesPath, absolute paths, provider class lists, Composer raw payloads,
 * preset raw payloads, raw config payloads, runtime services, service
 * instances, closures, resources, or filesystem handles.
 */
final readonly class ModulePlan
{
    public const int SCHEMA_VERSION = 1;
    private const int MAX_PRESET_BYTES = 64;

    private const string SAFE_PRESET_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

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

    private string $preset;

    /**
     * @var list<ModuleId>
     */
    private array $enabled;

    /**
     * @var list<ModuleId>
     */
    private array $disabled;

    /**
     * @var list<ModuleId>
     */
    private array $optionalMissing;

    /**
     * @var list<ModuleId>
     */
    private array $topologicalOrder;

    /**
     * @var array<string, ModulePlanEntry>
     */
    private array $modules;

    /**
     * @var list<ModuleOptionalMissingWarning>
     */
    private array $warnings;

    /**
     * @param list<ModuleId> $enabled
     * @param list<ModuleId> $disabled
     * @param list<ModuleId> $optionalMissing
     * @param list<ModuleId> $topologicalOrder
     * @param list<ModulePlanEntry> $modules
     * @param list<ModuleOptionalMissingWarning> $warnings
     */
    public function __construct(
        string $app,
        string $preset,
        array $enabled,
        array $disabled,
        array $optionalMissing,
        array $topologicalOrder,
        array $modules,
        array $warnings = [],
    ) {
        if (!self::isValidAppTarget($app)) {
            throw new \InvalidArgumentException('module-plan-app-invalid');
        }

        if (!self::isSafePresetName($preset)) {
            throw new \InvalidArgumentException('module-plan-preset-invalid');
        }

        $enabledSet = self::normalizeModuleIdSet($enabled, 'enabled');
        $disabledSet = self::normalizeModuleIdSet($disabled, 'disabled');
        $optionalMissingSet = self::normalizeModuleIdSet($optionalMissing, 'optionalMissing');
        $topologicalOrderList = self::normalizeTopologicalOrder($topologicalOrder);
        $moduleMap = self::normalizeModuleEntries($modules);
        $warningList = self::normalizeWarnings($warnings);

        self::assertModuleIdSetsArePairwiseDisjoint(
            enabled: $enabledSet,
            disabled: $disabledSet,
            optionalMissing: $optionalMissingSet,
        );

        self::assertTopologicalOrderReferencesEnabledModules($topologicalOrderList, $enabledSet);
        self::assertTopologicalOrderContainsAllEnabledModules($topologicalOrderList, $enabledSet);
        self::assertEnabledModulesHaveEntries($enabledSet, $moduleMap);
        self::assertModuleEntriesReferenceEnabledModules($moduleMap, $enabledSet);

        $this->app = $app;
        $this->preset = $preset;
        $this->enabled = $enabledSet;
        $this->disabled = $disabledSet;
        $this->optionalMissing = $optionalMissingSet;
        $this->topologicalOrder = $topologicalOrderList;
        $this->modules = $moduleMap;
        $this->warnings = $warningList;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function app(): string
    {
        return $this->app;
    }

    public function preset(): string
    {
        return $this->preset;
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
    public function disabled(): array
    {
        return $this->disabled;
    }

    /**
     * @return list<ModuleId>
     */
    public function optionalMissing(): array
    {
        return $this->optionalMissing;
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

    /**
     * @return list<ModuleOptionalMissingWarning>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Stable exported scalar/json-like shape.
     *
     * Top-level key order is canonical:
     *
     * - app
     * - disabled
     * - enabled
     * - modules
     * - optionalMissing
     * - preset
     * - schemaVersion
     * - topologicalOrder
     * - warnings
     *
     * @return array{
     *     app: string,
     *     disabled: list<string>,
     *     enabled: list<string>,
     *     modules: array<string, array{
     *         composerName: string,
     *         conflicts: list<string>,
     *         moduleId: string,
     *         requires: list<string>
     *     }>,
     *     optionalMissing: list<string>,
     *     preset: string,
     *     schemaVersion: int,
     *     topologicalOrder: list<string>,
     *     warnings: list<array{
     *         code: string,
     *         moduleId: string,
     *         preset: string,
     *         reason: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'app' => $this->app,
            'disabled' => self::moduleIdsToStrings($this->disabled),
            'enabled' => self::moduleIdsToStrings($this->enabled),
            'modules' => self::moduleEntriesToArray($this->modules),
            'optionalMissing' => self::moduleIdsToStrings($this->optionalMissing),
            'preset' => $this->preset,
            'schemaVersion' => self::SCHEMA_VERSION,
            'topologicalOrder' => self::moduleIdsToStrings($this->topologicalOrder),
            'warnings' => self::warningsToArray($this->warnings),
        ];
    }

    private static function isValidAppTarget(string $app): bool
    {
        return isset(self::APP_TARGETS[$app]);
    }

    private static function isSafePresetName(string $preset): bool
    {
        if ($preset === '') {
            return false;
        }

        if (\strlen($preset) > self::MAX_PRESET_BYTES) {
            return false;
        }

        if (!self::isAsciiLowerAlpha($preset[0])) {
            return false;
        }

        if (\str_contains($preset, '..')) {
            return false;
        }

        return \strspn($preset, self::SAFE_PRESET_CHARS) === \strlen($preset);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function normalizeModuleIdSet(array $moduleIds, string $field): array
    {
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
     * @param list<ModuleOptionalMissingWarning> $warnings
     *
     * @return list<ModuleOptionalMissingWarning>
     */
    private static function normalizeWarnings(array $warnings): array
    {
        if (!\array_is_list($warnings)) {
            throw new \InvalidArgumentException('module-plan-warnings-must-be-list');
        }

        $map = [];

        foreach ($warnings as $warning) {
            if (!$warning instanceof ModuleOptionalMissingWarning) {
                throw new \InvalidArgumentException('module-plan-warning-invalid');
            }

            $map[$warning->canonicalKey()] = $warning;
        }

        \ksort($map, \SORT_STRING);

        return \array_values($map);
    }

    /**
     * @param list<ModuleId> $enabled
     * @param list<ModuleId> $disabled
     * @param list<ModuleId> $optionalMissing
     */
    private static function assertModuleIdSetsArePairwiseDisjoint(
        array $enabled,
        array $disabled,
        array $optionalMissing,
    ): void {
        self::assertModuleIdSetsDoNotOverlap(
            left: $enabled,
            right: $disabled,
            reason: 'module-plan-enabled-disabled-overlap',
        );

        self::assertModuleIdSetsDoNotOverlap(
            left: $enabled,
            right: $optionalMissing,
            reason: 'module-plan-enabled-optional-missing-overlap',
        );

        self::assertModuleIdSetsDoNotOverlap(
            left: $disabled,
            right: $optionalMissing,
            reason: 'module-plan-disabled-optional-missing-overlap',
        );
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
     * @param list<ModuleOptionalMissingWarning> $warnings
     *
     * @return list<array{
     *     code: string,
     *     moduleId: string,
     *     preset: string,
     *     reason: string
     * }>
     */
    private static function warningsToArray(array $warnings): array
    {
        $out = [];

        foreach ($warnings as $warning) {
            $out[] = $warning->toArray();
        }

        return $out;
    }

    private static function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }
}
