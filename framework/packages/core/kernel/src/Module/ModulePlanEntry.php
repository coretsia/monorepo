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
 * Immutable resolved module entry for Kernel ModulePlan export.
 *
 * This value object intentionally exposes only ModulePlan-facing data:
 *
 * - module id;
 * - composer package name;
 * - resolved required module ids;
 * - resolved conflict module ids.
 *
 * It must not expose provider class lists, defaultsConfigPath, package
 * filesystem metadata, Composer raw payloads, install paths, runtime services,
 * service instances, closures, resources, or filesystem handles.
 *
 * Dependency and conflict lists are canonical string sets sorted by byte-order
 * strcmp through their module id values.
 */
final readonly class ModulePlanEntry
{
    private const int MAX_COMPOSER_NAME_BYTES = 128;

    private const string COMPOSER_NAME_PART_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_.-';

    private ModuleId $moduleId;

    private string $composerName;

    /**
     * @var list<ModuleId>
     */
    private array $requires;

    /**
     * @var list<ModuleId>
     */
    private array $conflicts;

    /**
     * @param list<ModuleId> $requires
     * @param list<ModuleId> $conflicts
     */
    public function __construct(
        ModuleId $moduleId,
        string $composerName,
        array $requires = [],
        array $conflicts = [],
    ) {
        $this->moduleId = $moduleId;
        $this->composerName = self::normalizeComposerName($composerName);
        $this->requires = self::normalizeModuleIdSet($requires, 'requires');
        $this->conflicts = self::normalizeModuleIdSet($conflicts, 'conflicts');
    }

    public function moduleId(): ModuleId
    {
        return $this->moduleId;
    }

    public function moduleIdString(): string
    {
        return $this->moduleId->value();
    }

    public function composerName(): string
    {
        return $this->composerName;
    }

    /**
     * @return list<ModuleId>
     */
    public function requires(): array
    {
        return $this->requires;
    }

    /**
     * @return list<ModuleId>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * @return array{
     *     composerName: string,
     *     conflicts: list<string>,
     *     moduleId: string,
     *     requires: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'composerName' => $this->composerName,
            'conflicts' => self::moduleIdsToStrings($this->conflicts),
            'moduleId' => $this->moduleId->value(),
            'requires' => self::moduleIdsToStrings($this->requires),
        ];
    }

    private static function normalizeComposerName(string $composerName): string
    {
        if ($composerName === '') {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-empty');
        }

        if (\strlen($composerName) > self::MAX_COMPOSER_NAME_BYTES) {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-too-long');
        }

        if (\str_contains($composerName, '\\') || \str_contains($composerName, ':')) {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-invalid');
        }

        if (\str_contains($composerName, '..')) {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-invalid');
        }

        $parts = \explode('/', $composerName);

        if (\count($parts) !== 2) {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-invalid');
        }

        [$vendor, $package] = $parts;

        if (!self::isSafeComposerNamePart($vendor) || !self::isSafeComposerNamePart($package)) {
            throw new \InvalidArgumentException('module-plan-entry-composer-name-invalid');
        }

        return $composerName;
    }

    private static function isSafeComposerNamePart(string $part): bool
    {
        if ($part === '') {
            return false;
        }

        return \strspn($part, self::COMPOSER_NAME_PART_CHARS) === \strlen($part);
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
                throw new \InvalidArgumentException('module-plan-entry-' . $field . '-module-id-invalid');
            }

            $set[$moduleId->value()] = $moduleId;
        }

        \ksort($set, \SORT_STRING);

        return \array_values($set);
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
}
