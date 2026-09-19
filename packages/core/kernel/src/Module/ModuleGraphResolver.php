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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;

/**
 * Resolves module graph policy for a validated mode preset and installed modules.
 *
 * This resolver owns Kernel module-selection policy:
 *
 * - preset required modules;
 * - preset optional modules;
 * - preset disabled modules;
 * - transitive required dependency closure;
 * - enabled-module conflict checks;
 * - optional missing warnings;
 * - deterministic topological order.
 *
 * It intentionally reads runtime dependency and conflict edges only from
 * ModuleDescriptor metadata:
 *
 * - metadata()["requires"]
 * - metadata()["conflicts"]
 *
 * These metadata values are normalized from composer.json extra.coretsia.requires
 * and extra.coretsia.conflicts by ComposerManifestReader.
 *
 * This resolver does not read Composer package-level require/conflict, does not
 * scan filesystem paths, and does not inspect module classes.
 *
 * Graph failures are collected before throwing so the selected failure is not
 * an incidental traversal-order result.
 *
 * @internal
 */
final readonly class ModuleGraphResolver
{
    public function __construct(
        private TopologicalSorter $topologicalSorter,
    ) {
    }

    /**
     * Resolves a deterministic ModulePlan payload.
     *
     * `$app` is output metadata only. It must be supplied by ModulePlanResolver
     * from BootstrapConfig::appTarget() and must not affect module selection.
     */
    public function resolve(
        string $app,
        ModuleManifest $installed,
        ModePresetInterface $preset,
    ): ModulePlan {
        $installedEntries = $this->buildInstalledEntries($installed);
        $disabled = self::normalizeModuleIdSet($preset->disabled());
        $disabledMap = self::moduleIdMap($disabled);

        $enabledMap = [];
        $optionalMissingMap = [];
        $warningMap = [];

        $conflictCandidates = [];
        $requiredMissingCandidates = [];

        $this->seedRequiredModules(
            preset: $preset,
            installedEntries: $installedEntries,
            disabledMap: $disabledMap,
            enabledMap: $enabledMap,
            conflictCandidates: $conflictCandidates,
            requiredMissingCandidates: $requiredMissingCandidates,
        );

        $this->seedOptionalModules(
            preset: $preset,
            installedEntries: $installedEntries,
            disabledMap: $disabledMap,
            enabledMap: $enabledMap,
            optionalMissingMap: $optionalMissingMap,
            warningMap: $warningMap,
        );

        $this->expandRequiredDependencyClosure(
            installedEntries: $installedEntries,
            disabledMap: $disabledMap,
            enabledMap: $enabledMap,
            conflictCandidates: $conflictCandidates,
            requiredMissingCandidates: $requiredMissingCandidates,
        );

        $enabledEntries = $this->enabledEntries(
            installedEntries: $installedEntries,
            enabledMap: $enabledMap,
        );

        $this->collectEnabledModuleConflicts(
            enabledEntries: $enabledEntries,
            conflictCandidates: $conflictCandidates,
        );

        /*
         * Graph-level precedence:
         *
         * 1. conflicts
         * 2. required missing
         * 3. cycle detection
         *
         * Manifest-invalid failures are still thrown immediately because they
         * are not graph-policy candidates; they represent invalid installed
         * metadata and belong to the earlier global failure class.
         */
        self::throwFirstGraphFailure(
            conflictCandidates: $conflictCandidates,
            requiredMissingCandidates: $requiredMissingCandidates,
        );

        $topologicalOrder = $this->topologicalSorter->sort($enabledEntries);

        return new ModulePlan(
            app: $app,
            preset: $preset->name(),
            enabled: self::moduleIdsFromMap($enabledMap),
            disabled: $disabled,
            optionalMissing: self::moduleIdsFromMap($optionalMissingMap),
            topologicalOrder: $topologicalOrder,
            modules: $enabledEntries,
            warnings: self::warningsFromMap($warningMap),
        );
    }

    /**
     * @return array<string, ModulePlanEntry>
     */
    private function buildInstalledEntries(ModuleManifest $installed): array
    {
        $entries = [];

        foreach ($installed->modules() as $descriptor) {
            $entry = $this->entryFromDescriptor($descriptor);
            $entries[$entry->moduleIdString()] = $entry;
        }

        \ksort($entries, \SORT_STRING);

        return $entries;
    }

    private function entryFromDescriptor(ModuleDescriptor $descriptor): ModulePlanEntry
    {
        $composerName = $descriptor->composerName();

        if ($composerName === null) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid();
        }

        $requires = $this->readDescriptorModuleIdList(
            descriptor: $descriptor,
            key: 'requires',
        );

        $conflicts = $this->readDescriptorModuleIdList(
            descriptor: $descriptor,
            key: 'conflicts',
        );

        try {
            return new ModulePlanEntry(
                moduleId: $descriptor->id(),
                composerName: $composerName,
                requires: $requires,
                conflicts: $conflicts,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid($exception);
        }
    }

    /**
     * @return list<ModuleId>
     */
    private function readDescriptorModuleIdList(
        ModuleDescriptor $descriptor,
        string $key,
    ): array {
        $metadata = $descriptor->metadata();
        $value = $metadata[$key] ?? [];

        if (!\is_array($value) || !\array_is_list($value)) {
            throw ModuleManifestInvalidException::dependencyMetadataInvalid($descriptor->id());
        }

        $set = [];

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw ModuleManifestInvalidException::dependencyMetadataInvalid($descriptor->id());
            }

            try {
                $moduleId = ModuleId::fromString($item);
            } catch (\InvalidArgumentException $exception) {
                throw ModuleManifestInvalidException::dependencyMetadataInvalid(
                    $descriptor->id(),
                    $exception,
                );
            }

            $set[$moduleId->value()] = $moduleId;
        }

        \ksort($set, \SORT_STRING);

        return \array_values($set);
    }

    /**
     * @param array<string, ModulePlanEntry> $installedEntries
     * @param array<string, true> $disabledMap
     * @param array<string, ModuleId> $enabledMap
     * @param array<string, ModuleConflictException> $conflictCandidates
     * @param array<string, ModuleRequiredMissingException> $requiredMissingCandidates
     */
    private function seedRequiredModules(
        ModePresetInterface $preset,
        array $installedEntries,
        array $disabledMap,
        array &$enabledMap,
        array &$conflictCandidates,
        array &$requiredMissingCandidates,
    ): void {
        foreach (self::normalizeModuleIdSet($preset->required()) as $moduleId) {
            $moduleIdValue = $moduleId->value();

            if (isset($disabledMap[$moduleIdValue])) {
                self::addConflictCandidate(
                    candidates: $conflictCandidates,
                    exception: ModuleConflictException::requiredModuleDisabled(
                        moduleId: $moduleId,
                        disabledModuleId: $moduleId,
                    ),
                    firstModuleId: $moduleIdValue,
                    secondModuleId: $moduleIdValue,
                );

                continue;
            }

            if (!isset($installedEntries[$moduleIdValue])) {
                self::addRequiredMissingCandidate(
                    candidates: $requiredMissingCandidates,
                    exception: ModuleRequiredMissingException::presetRequiredModuleMissing(
                        presetName: $preset->name(),
                        missingModuleId: $moduleId,
                    ),
                    requiredBy: $preset->name(),
                    missingModuleId: $moduleIdValue,
                );

                continue;
            }

            $enabledMap[$moduleIdValue] = $moduleId;
        }

        \ksort($enabledMap, \SORT_STRING);
    }

    /**
     * @param array<string, ModulePlanEntry> $installedEntries
     * @param array<string, true> $disabledMap
     * @param array<string, ModuleId> $enabledMap
     * @param array<string, ModuleId> $optionalMissingMap
     * @param array<string, ModuleOptionalMissingWarning> $warningMap
     */
    private function seedOptionalModules(
        ModePresetInterface $preset,
        array $installedEntries,
        array $disabledMap,
        array &$enabledMap,
        array &$optionalMissingMap,
        array &$warningMap,
    ): void {
        foreach (self::normalizeModuleIdSet($preset->optional()) as $moduleId) {
            $moduleIdValue = $moduleId->value();

            if (isset($disabledMap[$moduleIdValue])) {
                continue;
            }

            if (isset($installedEntries[$moduleIdValue])) {
                $enabledMap[$moduleIdValue] = $moduleId;

                continue;
            }

            $optionalMissingMap[$moduleIdValue] = $moduleId;

            $warning = ModuleOptionalMissingWarning::forPresetOptionalModule(
                moduleId: $moduleId,
                preset: $preset->name(),
            );

            $warningMap[$warning->canonicalKey()] = $warning;
        }

        \ksort($enabledMap, \SORT_STRING);
        \ksort($optionalMissingMap, \SORT_STRING);
        \ksort($warningMap, \SORT_STRING);
    }

    /**
     * @param array<string, ModulePlanEntry> $installedEntries
     * @param array<string, true> $disabledMap
     * @param array<string, ModuleId> $enabledMap
     * @param array<string, ModuleConflictException> $conflictCandidates
     * @param array<string, ModuleRequiredMissingException> $requiredMissingCandidates
     */
    private function expandRequiredDependencyClosure(
        array $installedEntries,
        array $disabledMap,
        array &$enabledMap,
        array &$conflictCandidates,
        array &$requiredMissingCandidates,
    ): void {
        $queue = self::sortModuleIdStrings(\array_keys($enabledMap));
        $processed = [];

        while ($queue !== []) {
            $moduleIdValue = \array_shift($queue);

            if (!\is_string($moduleIdValue)) {
                throw new \LogicException('module-graph-queue-module-id-invalid');
            }

            if (isset($processed[$moduleIdValue])) {
                continue;
            }

            if (!isset($installedEntries[$moduleIdValue])) {
                throw new \LogicException('module-graph-enabled-module-entry-missing');
            }

            $processed[$moduleIdValue] = true;
            $entry = $installedEntries[$moduleIdValue];

            foreach ($entry->requires() as $requiredModuleId) {
                $requiredModuleIdValue = $requiredModuleId->value();

                if (isset($disabledMap[$requiredModuleIdValue])) {
                    self::addConflictCandidate(
                        candidates: $conflictCandidates,
                        exception: ModuleConflictException::requiredModuleDisabled(
                            moduleId: $entry->moduleId(),
                            disabledModuleId: $requiredModuleId,
                        ),
                        firstModuleId: $entry->moduleIdString(),
                        secondModuleId: $requiredModuleIdValue,
                    );

                    continue;
                }

                if (!isset($installedEntries[$requiredModuleIdValue])) {
                    self::addRequiredMissingCandidate(
                        candidates: $requiredMissingCandidates,
                        exception: ModuleRequiredMissingException::dependencyRequiredModuleMissing(
                            requiredByModuleId: $entry->moduleId(),
                            missingModuleId: $requiredModuleId,
                        ),
                        requiredBy: $entry->moduleIdString(),
                        missingModuleId: $requiredModuleIdValue,
                    );

                    continue;
                }

                if (!isset($enabledMap[$requiredModuleIdValue])) {
                    $enabledMap[$requiredModuleIdValue] = $requiredModuleId;
                    $queue[] = $requiredModuleIdValue;
                    $queue = self::sortModuleIdStrings($queue);
                }
            }
        }

        \ksort($enabledMap, \SORT_STRING);
    }

    /**
     * @param array<string, ModulePlanEntry> $installedEntries
     * @param array<string, ModuleId> $enabledMap
     *
     * @return list<ModulePlanEntry>
     */
    private function enabledEntries(
        array $installedEntries,
        array $enabledMap,
    ): array {
        \ksort($enabledMap, \SORT_STRING);

        $entries = [];

        foreach ($enabledMap as $moduleIdValue => $_moduleId) {
            if (!isset($installedEntries[$moduleIdValue])) {
                throw new \LogicException('module-graph-enabled-module-entry-missing');
            }

            $entries[] = $installedEntries[$moduleIdValue];
        }

        return $entries;
    }

    /**
     * @param list<ModulePlanEntry> $enabledEntries
     * @param array<string, ModuleConflictException> $conflictCandidates
     */
    private function collectEnabledModuleConflicts(
        array $enabledEntries,
        array &$conflictCandidates,
    ): void {
        $enabledMap = [];

        foreach ($enabledEntries as $entry) {
            $enabledMap[$entry->moduleIdString()] = $entry->moduleId();
        }

        \ksort($enabledMap, \SORT_STRING);

        foreach ($enabledEntries as $entry) {
            foreach ($entry->conflicts() as $conflictModuleId) {
                $conflictModuleIdValue = $conflictModuleId->value();

                if (!isset($enabledMap[$conflictModuleIdValue])) {
                    continue;
                }

                [$lowerModuleId, $higherModuleId] = self::sortedPair(
                    $entry->moduleIdString(),
                    $conflictModuleIdValue,
                );

                if ($lowerModuleId === $higherModuleId) {
                    throw ModuleManifestInvalidException::dependencyMetadataInvalid(
                        $entry->moduleId(),
                    );
                }

                self::addConflictCandidate(
                    candidates: $conflictCandidates,
                    exception: ModuleConflictException::between(
                        firstModuleId: $enabledMap[$lowerModuleId],
                        secondModuleId: $enabledMap[$higherModuleId],
                    ),
                    firstModuleId: $lowerModuleId,
                    secondModuleId: $higherModuleId,
                );
            }
        }
    }

    /**
     * @param array<string, ModuleConflictException> $candidates
     */
    private static function addConflictCandidate(
        array &$candidates,
        ModuleConflictException $exception,
        string $firstModuleId,
        string $secondModuleId,
    ): void {
        [$lowerModuleId, $higherModuleId] = self::sortedPair($firstModuleId, $secondModuleId);

        $key = $lowerModuleId . "\0" . $higherModuleId . "\0" . $exception->reason();
        $candidates[$key] = $exception;

        \ksort($candidates, \SORT_STRING);
    }

    /**
     * @param array<string, ModuleRequiredMissingException> $candidates
     */
    private static function addRequiredMissingCandidate(
        array &$candidates,
        ModuleRequiredMissingException $exception,
        string $requiredBy,
        string $missingModuleId,
    ): void {
        $key = $requiredBy . "\0" . $missingModuleId . "\0" . $exception->reason();
        $candidates[$key] = $exception;

        \ksort($candidates, \SORT_STRING);
    }

    /**
     * @param array<string, ModuleConflictException> $conflictCandidates
     * @param array<string, ModuleRequiredMissingException> $requiredMissingCandidates
     */
    private static function throwFirstGraphFailure(
        array $conflictCandidates,
        array $requiredMissingCandidates,
    ): void {
        if ($conflictCandidates !== []) {
            \ksort($conflictCandidates, \SORT_STRING);

            $exception = \reset($conflictCandidates);

            if (!$exception instanceof ModuleConflictException) {
                throw new \LogicException('module-graph-conflict-candidate-invalid');
            }

            throw $exception;
        }

        if ($requiredMissingCandidates !== []) {
            \ksort($requiredMissingCandidates, \SORT_STRING);

            $exception = \reset($requiredMissingCandidates);

            if (!$exception instanceof ModuleRequiredMissingException) {
                throw new \LogicException('module-graph-required-missing-candidate-invalid');
            }

            throw $exception;
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function normalizeModuleIdSet(array $moduleIds): array
    {
        if (!\array_is_list($moduleIds)) {
            throw new \InvalidArgumentException('module-graph-module-id-set-must-be-list');
        }

        $set = [];

        foreach ($moduleIds as $moduleId) {
            if (!$moduleId instanceof ModuleId) {
                throw new \InvalidArgumentException('module-graph-module-id-invalid');
            }

            $set[$moduleId->value()] = $moduleId;
        }

        \ksort($set, \SORT_STRING);

        return \array_values($set);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return array<string, true>
     */
    private static function moduleIdMap(array $moduleIds): array
    {
        $map = [];

        foreach ($moduleIds as $moduleId) {
            $map[$moduleId->value()] = true;
        }

        \ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @param array<string, ModuleId> $moduleIdMap
     *
     * @return list<ModuleId>
     */
    private static function moduleIdsFromMap(array $moduleIdMap): array
    {
        \ksort($moduleIdMap, \SORT_STRING);

        return \array_values($moduleIdMap);
    }

    /**
     * @param array<string, ModuleOptionalMissingWarning> $warningMap
     *
     * @return list<ModuleOptionalMissingWarning>
     */
    private static function warningsFromMap(array $warningMap): array
    {
        \ksort($warningMap, \SORT_STRING);

        return \array_values($warningMap);
    }

    /**
     * @param list<string> $moduleIds
     *
     * @return list<string>
     */
    private static function sortModuleIdStrings(array $moduleIds): array
    {
        $moduleIds = \array_values(\array_unique($moduleIds));

        \usort(
            $moduleIds,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $moduleIds;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function sortedPair(string $first, string $second): array
    {
        if (\strcmp($first, $second) <= 0) {
            return [$first, $second];
        }

        return [$second, $first];
    }
}
