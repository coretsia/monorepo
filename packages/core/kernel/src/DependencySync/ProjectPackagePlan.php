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

namespace Coretsia\Kernel\DependencySync;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Module\ModulePlanEntry;

final readonly class ProjectPackagePlan
{
    /**
     * @param non-empty-list<string> $applications
     * @param array<string, non-empty-string> $effectivePresetsByTarget
     * @param array<string, non-empty-string> $fixedPresetByTarget
     * @param array<string, list<string>> $enabledModuleIdsByTarget
     * @param array<string, list<string>> $excludedModuleIdsByTarget
     * @param list<string> $unionModuleIds
     * @param array<string, array{
     *     composerName: non-empty-string,
     *     requires: list<string>,
     *     conflicts: list<string>,
     * }> $expectedModuleMetadataById
     * @param array<string, non-empty-string> $desiredRootRequirements
     */
    public function __construct(
        private array $applications,
        private array $effectivePresetsByTarget,
        private array $fixedPresetByTarget,
        private array $enabledModuleIdsByTarget,
        private array $excludedModuleIdsByTarget,
        private array $unionModuleIds,
        private array $expectedModuleMetadataById,
        private array $desiredRootRequirements,
    ) {
        $this->validate();
    }

    /** @return array<string, non-empty-string> */
    public function desiredRootRequirements(): array
    {
        return $this->desiredRootRequirements;
    }

    /** @return non-empty-list<string> */
    public function applications(): array
    {
        return $this->applications;
    }

    /** @return array<string, non-empty-string> */
    public function effectivePresetsByTarget(): array
    {
        return $this->effectivePresetsByTarget;
    }

    /** @return array<string, non-empty-string> */
    public function fixedPresetByTarget(): array
    {
        return $this->fixedPresetByTarget;
    }

    /** @return array<string, list<string>> */
    public function enabledModuleIdsByTarget(): array
    {
        return $this->enabledModuleIdsByTarget;
    }

    /** @return array<string, list<string>> */
    public function excludedModuleIdsByTarget(): array
    {
        return $this->excludedModuleIdsByTarget;
    }

    /** @return list<string> */
    public function unionModuleIds(): array
    {
        return $this->unionModuleIds;
    }

    /**
     * @return array<string, array{
     *     composerName: non-empty-string,
     *     requires: list<string>,
     *     conflicts: list<string>,
     * }>
     */
    public function expectedModuleMetadataById(): array
    {
        return $this->expectedModuleMetadataById;
    }

    public function equals(self $other): bool
    {
        return $this->applications === $other->applications
            && $this->effectivePresetsByTarget === $other->effectivePresetsByTarget
            && $this->fixedPresetByTarget === $other->fixedPresetByTarget
            && $this->enabledModuleIdsByTarget === $other->enabledModuleIdsByTarget
            && $this->excludedModuleIdsByTarget === $other->excludedModuleIdsByTarget
            && $this->unionModuleIds === $other->unionModuleIds
            && $this->expectedModuleMetadataById === $other->expectedModuleMetadataById
            && $this->desiredRootRequirements === $other->desiredRootRequirements;
    }

    private function validate(): void
    {
        if ($this->applications === [] || !\array_is_list($this->applications)) {
            throw new \InvalidArgumentException('project-package-plan-applications-invalid');
        }

        self::assertSortedUniqueStrings($this->applications, 'project-package-plan-applications-invalid');

        foreach ($this->applications as $application) {
            $resolved = AppTarget::fromString($application);

            if ($resolved->value !== $application) {
                throw new \InvalidArgumentException('project-package-plan-applications-invalid');
            }
        }

        self::assertExactMapKeys(
            $this->effectivePresetsByTarget,
            $this->applications,
            'project-package-plan-effective-presets-invalid',
        );
        self::assertExactMapKeys(
            $this->enabledModuleIdsByTarget,
            $this->applications,
            'project-package-plan-enabled-invalid',
        );
        self::assertExactMapKeys(
            $this->excludedModuleIdsByTarget,
            $this->applications,
            'project-package-plan-excluded-invalid',
        );

        foreach ($this->effectivePresetsByTarget as $preset) {
            self::assertSafeToken($preset, 'project-package-plan-effective-presets-invalid');
        }

        self::assertCanonicalMap($this->fixedPresetByTarget, 'project-package-plan-fixed-presets-invalid');
        $applicationMap = \array_fill_keys($this->applications, true);

        foreach ($this->fixedPresetByTarget as $target => $preset) {
            if (!isset($applicationMap[$target])) {
                throw new \InvalidArgumentException('project-package-plan-fixed-presets-invalid');
            }

            self::assertSafeToken($preset, 'project-package-plan-fixed-presets-invalid');

            if ($this->effectivePresetsByTarget[$target] !== $preset) {
                throw new \InvalidArgumentException('project-package-plan-fixed-presets-invalid');
            }
        }

        $computedUnion = [];

        foreach ($this->applications as $target) {
            $enabled = self::canonicalModuleIdStrings(
                $this->enabledModuleIdsByTarget[$target],
                'project-package-plan-enabled-invalid',
            );
            $excluded = self::canonicalModuleIdStrings(
                $this->excludedModuleIdsByTarget[$target],
                'project-package-plan-excluded-invalid',
            );
            $excludedMap = \array_fill_keys($excluded, true);

            foreach ($enabled as $moduleId) {
                if (isset($excludedMap[$moduleId])) {
                    throw new \InvalidArgumentException('project-package-plan-module-overlap');
                }

                $computedUnion[$moduleId] = true;
            }
        }

        $canonicalUnion = self::canonicalModuleIdStrings(
            $this->unionModuleIds,
            'project-package-plan-union-invalid',
        );
        $computedUnionIds = \array_keys($computedUnion);
        \sort($computedUnionIds, \SORT_STRING);

        if ($canonicalUnion !== $computedUnionIds) {
            throw new \InvalidArgumentException('project-package-plan-union-invalid');
        }

        self::assertExactMapKeys(
            $this->expectedModuleMetadataById,
            $canonicalUnion,
            'project-package-plan-metadata-invalid',
        );

        $composerNames = [];
        $unionMap = \array_fill_keys($canonicalUnion, true);

        foreach ($this->expectedModuleMetadataById as $rawModuleId => $record) {
            self::canonicalModuleId($rawModuleId, 'project-package-plan-metadata-invalid');

            if (
                !\is_array($record)
                || \array_keys($record) !== ['composerName', 'requires', 'conflicts']
                || !\is_string($record['composerName'])
                || !\is_array($record['requires'])
                || !\is_array($record['conflicts'])
            ) {
                throw new \InvalidArgumentException('project-package-plan-metadata-invalid');
            }

            $requires = self::canonicalModuleIdStrings($record['requires'], 'project-package-plan-metadata-invalid');
            $conflicts = self::canonicalModuleIdStrings($record['conflicts'], 'project-package-plan-metadata-invalid');

            if (\in_array($rawModuleId, $requires, true) || \in_array($rawModuleId, $conflicts, true)) {
                throw new \InvalidArgumentException('project-package-plan-metadata-invalid');
            }

            if (\array_intersect($requires, $conflicts) !== []) {
                throw new \InvalidArgumentException('project-package-plan-metadata-invalid');
            }

            foreach ($requires as $required) {
                if (!isset($unionMap[$required])) {
                    throw new \InvalidArgumentException('project-package-plan-metadata-invalid');
                }
            }

            $requiresObjects = \array_map(
                static fn (string $value): ModuleId => ModuleId::fromString($value),
                $requires,
            );
            $conflictsObjects = \array_map(
                static fn (string $value): ModuleId => ModuleId::fromString($value),
                $conflicts,
            );
            $entry = new ModulePlanEntry(
                ModuleId::fromString($rawModuleId),
                $record['composerName'],
                $requiresObjects,
                $conflictsObjects,
            );
            $composerName = $entry->composerName();

            if (isset($composerNames[$composerName])) {
                throw new \InvalidArgumentException('project-package-plan-metadata-invalid');
            }

            $composerNames[$composerName] = true;
        }

        $expectedComposerNames = \array_keys($composerNames);
        \sort($expectedComposerNames, \SORT_STRING);
        self::assertExactMapKeys(
            $this->desiredRootRequirements,
            $expectedComposerNames,
            'project-package-plan-requirements-invalid',
        );

        foreach ($this->desiredRootRequirements as $constraint) {
            self::assertSafeToken($constraint, 'project-package-plan-requirements-invalid');
        }
    }

    private static function assertSafeToken(mixed $value, string $reason): void
    {
        if (
            !\is_string($value)
            || $value === ''
            || \trim($value) !== $value
            || \preg_match('/[\x00-\x1F\x7F]/', $value) !== 0
        ) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /** @param array<mixed> $values */
    private static function assertSortedUniqueStrings(array $values, string $reason): void
    {
        $previous = null;

        foreach ($values as $value) {
            if (
                !\is_string($value)
                || ($previous !== null && \strcmp($previous, $value) >= 0)
            ) {
                throw new \InvalidArgumentException($reason);
            }

            $previous = $value;
        }
    }

    /** @param array<string, mixed> $map */
    private static function assertCanonicalMap(array $map, string $reason): void
    {
        if ($map !== [] && \array_is_list($map)) {
            throw new \InvalidArgumentException($reason);
        }

        $keys = \array_keys($map);
        self::assertSortedUniqueStrings($keys, $reason);
    }

    /** @param array<string, mixed> $map @param list<string> $expected */
    private static function assertExactMapKeys(array $map, array $expected, string $reason): void
    {
        self::assertCanonicalMap($map, $reason);

        if (\array_keys($map) !== $expected) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /** @param list<string> $values @return list<string> */
    private static function canonicalModuleIdStrings(array $values, string $reason): array
    {
        if (!\array_is_list($values)) {
            throw new \InvalidArgumentException($reason);
        }

        self::assertSortedUniqueStrings($values, $reason);

        foreach ($values as $value) {
            self::canonicalModuleId($value, $reason);
        }

        return $values;
    }

    private static function canonicalModuleId(string $value, string $reason): ModuleId
    {
        try {
            $moduleId = ModuleId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException($reason);
        }

        if ($moduleId->value() !== $value) {
            throw new \InvalidArgumentException($reason);
        }

        return $moduleId;
    }
}
