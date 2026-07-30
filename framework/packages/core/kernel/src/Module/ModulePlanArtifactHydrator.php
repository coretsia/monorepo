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
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;

/**
 * Hydrates one immutable ModulePlan from a validated `module-manifest@1`
 * artifact payload without Composer discovery or preset resolution.
 *
 * The hydrator validates the complete payload shape and rejects payloads that
 * cannot round-trip to the canonical ModulePlan export.
 *
 * @internal Kernel artifact-runtime boundary.
 */
final readonly class ModulePlanArtifactHydrator
{
    private const string REASON_INVALID = 'module-plan-artifact-payload-invalid';

    /**
     * @var list<string>
     */
    private const array PAYLOAD_KEYS = [
        'app',
        'disabled',
        'enabled',
        'modules',
        'optionalMissing',
        'preset',
        'schemaVersion',
        'topologicalOrder',
        'warnings',
    ];

    /**
     * @var list<string>
     */
    private const array MODULE_ENTRY_KEYS = [
        'composerName',
        'conflicts',
        'moduleId',
        'requires',
    ];

    /**
     * @var list<string>
     */
    private const array WARNING_KEYS = [
        'code',
        'moduleId',
        'preset',
        'reason',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function hydrate(array $payload): ModulePlan
    {
        try {
            self::assertExactKeys(
                map: $payload,
                expectedKeys: self::PAYLOAD_KEYS,
            );

            if (
                ($payload['schemaVersion'] ?? null)
                !== ModulePlan::SCHEMA_VERSION
            ) {
                throw self::invalid();
            }

            $app = self::requiredString($payload, 'app');

            if (!AppTarget::isKnown($app)) {
                throw self::invalid();
            }

            $preset = self::requiredString($payload, 'preset');
            $enabled = self::moduleIdSet($payload['enabled'] ?? null);
            $disabled = self::moduleIdSet($payload['disabled'] ?? null);
            $optionalMissing = self::moduleIdSet($payload['optionalMissing'] ?? null);
            $topologicalOrder = self::moduleIdList(
                value: $payload['topologicalOrder'] ?? null,
                requireSortedSet: false,
            );
            $modules = self::moduleEntries($payload['modules'] ?? null);
            $warnings = self::warnings(
                value: $payload['warnings'] ?? null,
                preset: $preset,
                optionalMissing: $optionalMissing,
            );

            $plan = new ModulePlan(
                app: $app,
                preset: $preset,
                enabled: $enabled,
                disabled: $disabled,
                optionalMissing: $optionalMissing,
                topologicalOrder: $topologicalOrder,
                modules: $modules,
                warnings: $warnings,
            );

            self::assertGraphSemantics($plan);

            /*
             * ModulePlan and ModulePlanEntry own the final normalization and
             * cross-set invariants. A valid artifact must round-trip to exactly
             * the same semantic payload. Map insertion order is not semantic;
             * list order remains semantic.
             */
            if (
                self::normalizeMapOrder($plan->toArray())
                !== self::normalizeMapOrder($payload)
            ) {
                throw self::invalid();
            }

            return $plan;
        } catch (\Throwable $exception) {
            if (
                $exception instanceof \InvalidArgumentException
                && $exception->getMessage() === self::REASON_INVALID
            ) {
                throw $exception;
            }

            throw self::invalid($exception);
        }
    }

    private static function assertGraphSemantics(ModulePlan $plan): void
    {
        $enabledModuleIds = [];

        foreach ($plan->enabled() as $moduleId) {
            $enabledModuleIds[$moduleId->value()] = true;
        }

        $modules = $plan->modules();

        foreach ($modules as $entry) {
            foreach ($entry->requires() as $requiredModuleId) {
                $requiredModuleIdValue = $requiredModuleId->value();

                if (
                    !isset($enabledModuleIds[$requiredModuleIdValue])
                    || !isset($modules[$requiredModuleIdValue])
                ) {
                    throw self::invalid();
                }
            }

            foreach ($entry->conflicts() as $conflictingModuleId) {
                if (isset($enabledModuleIds[$conflictingModuleId->value()])) {
                    throw self::invalid();
                }
            }
        }

        $canonicalTopologicalOrder = new TopologicalSorter()->sort(
            \array_values($modules),
        );

        if (
            self::moduleIdValues($plan->topologicalOrder())
            !== self::moduleIdValues($canonicalTopologicalOrder)
        ) {
            throw self::invalid();
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }

    /**
     * @return list<ModuleId>
     */
    private static function moduleIdSet(mixed $value): array
    {
        return self::moduleIdList(
            value: $value,
            requireSortedSet: true,
        );
    }

    /**
     * @return list<ModuleId>
     */
    private static function moduleIdList(
        mixed $value,
        bool $requireSortedSet,
    ): array {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw self::invalid();
        }

        $ids = [];
        $values = [];
        $seen = [];

        foreach ($value as $item) {
            $moduleId = self::moduleId($item);
            $moduleIdValue = $moduleId->value();

            if (isset($seen[$moduleIdValue])) {
                throw self::invalid();
            }

            $seen[$moduleIdValue] = true;
            $ids[] = $moduleId;
            $values[] = $moduleIdValue;
        }

        if ($requireSortedSet) {
            $sorted = $values;

            \usort(
                $sorted,
                static fn (
                    string $left,
                    string $right,
                ): int => \strcmp($left, $right),
            );

            if ($values !== $sorted) {
                throw self::invalid();
            }
        }

        return $ids;
    }

    /**
     * @return list<ModulePlanEntry>
     */
    private static function moduleEntries(mixed $value): array
    {
        if (!\is_array($value) || !self::isMapArray($value)) {
            throw self::invalid();
        }

        $entries = [];

        foreach ($value as $mapModuleId => $entry) {
            if (!\is_string($mapModuleId)) {
                throw self::invalid();
            }

            $moduleId = self::moduleId($mapModuleId);

            if (!\is_array($entry) || \array_is_list($entry)) {
                throw self::invalid();
            }

            self::assertExactKeys(
                map: $entry,
                expectedKeys: self::MODULE_ENTRY_KEYS,
            );

            $entryModuleId = self::requiredString(
                map: $entry,
                key: 'moduleId',
            );

            if ($entryModuleId !== $moduleId->value()) {
                throw self::invalid();
            }

            $entries[] = new ModulePlanEntry(
                moduleId: $moduleId,
                composerName: self::requiredString(
                    map: $entry,
                    key: 'composerName',
                ),
                requires: self::moduleIdSet($entry['requires'] ?? null),
                conflicts: self::moduleIdSet($entry['conflicts'] ?? null),
            );
        }

        return $entries;
    }

    /**
     * @param list<ModuleId> $optionalMissing
     *
     * @return list<ModuleOptionalMissingWarning>
     */
    private static function warnings(
        mixed $value,
        string $preset,
        array $optionalMissing,
    ): array {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw self::invalid();
        }

        $warnings = [];
        $warningModuleIds = [];

        foreach ($value as $warning) {
            if (!\is_array($warning) || \array_is_list($warning)) {
                throw self::invalid();
            }

            self::assertExactKeys(
                map: $warning,
                expectedKeys: self::WARNING_KEYS,
            );

            if (
                self::requiredString($warning, 'code')
                !== ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING
                || self::requiredString($warning, 'reason')
                !== ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING
                || self::requiredString($warning, 'preset') !== $preset
            ) {
                throw self::invalid();
            }

            $moduleId = self::moduleId(
                self::requiredString($warning, 'moduleId'),
            );
            $moduleIdValue = $moduleId->value();

            if (isset($warningModuleIds[$moduleIdValue])) {
                throw self::invalid();
            }

            $warningModuleIds[$moduleIdValue] = true;
            $warnings[] = ModuleOptionalMissingWarning::forPresetOptionalModule(
                moduleId: $moduleId,
                preset: $preset,
            );
        }

        $optionalMissingIds = [];

        foreach ($optionalMissing as $moduleId) {
            $optionalMissingIds[$moduleId->value()] = true;
        }

        \ksort($warningModuleIds, \SORT_STRING);
        \ksort($optionalMissingIds, \SORT_STRING);

        /*
         * ModuleGraphResolver emits exactly one warning for every optional
         * missing module. An artifact must preserve that invariant.
         */
        if ($warningModuleIds !== $optionalMissingIds) {
            throw self::invalid();
        }

        return $warnings;
    }

    private static function moduleId(mixed $value): ModuleId
    {
        if (!\is_string($value) || $value === '') {
            throw self::invalid();
        }

        $moduleId = ModuleId::fromString($value);

        /*
         * ModuleId normalizes ASCII case. Artifact identity must already be
         * canonical rather than relying on hydration-time normalization.
         */
        if ($moduleId->value() !== $value) {
            throw self::invalid();
        }

        return $moduleId;
    }

    /**
     * @param array<string, mixed> $map
     */
    private static function requiredString(
        array $map,
        string $key,
    ): string {
        $value = $map[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw self::invalid();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expectedKeys
     */
    private static function assertExactKeys(
        array $map,
        array $expectedKeys,
    ): void {
        $actual = \array_keys($map);
        \sort($actual, \SORT_STRING);

        $expected = $expectedKeys;
        \sort($expected, \SORT_STRING);

        if ($actual !== $expected) {
            throw self::invalid();
        }
    }

    /**
     * Recursively sorts map keys while preserving list order.
     */
    private static function normalizeMapOrder(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalizeMapOrder($item);
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw self::invalid();
            }

            $normalized[$key] = self::normalizeMapOrder($item);
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }

    private static function invalid(
        ?\Throwable $previous = null,
    ): \InvalidArgumentException {
        return new \InvalidArgumentException(
            self::REASON_INVALID,
            0,
            $previous,
        );
    }
}
