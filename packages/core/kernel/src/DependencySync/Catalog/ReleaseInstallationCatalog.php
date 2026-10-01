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

namespace Coretsia\Kernel\DependencySync\Catalog;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlanEntry;

/** @internal */
final readonly class ReleaseInstallationCatalog
{
    /** @var list<ModulePlanEntry> */
    private array $entries;

    /** @var array<string, ModulePlanEntry> */
    private array $entryByModuleId;

    /**
     * @param list<ModulePlanEntry> $entries
     */
    public function __construct(
        private string $releaseLine,
        private string $publicConstraint,
        array $entries,
    ) {
        if (
            \preg_match('/\A(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\z/D', $this->releaseLine) !== 1
            || $this->publicConstraint !== '^' . $this->releaseLine . '.0'
            || !\array_is_list($entries)
        ) {
            throw new \InvalidArgumentException('release-installation-catalog-invalid');
        }

        $indexed = [];
        $composerNames = [];
        $previousModuleId = null;

        foreach ($entries as $entry) {
            if (!$entry instanceof ModulePlanEntry) {
                throw new \InvalidArgumentException('release-installation-catalog-entry-invalid');
            }

            $moduleId = $entry->moduleIdString();
            $composerName = $entry->composerName();

            if (
                isset($indexed[$moduleId])
                || isset($composerNames[$composerName])
                || !\str_starts_with($composerName, 'coretsia/')
                || ($previousModuleId !== null && \strcmp($previousModuleId, $moduleId) >= 0)
            ) {
                throw new \InvalidArgumentException('release-installation-catalog-entry-invalid');
            }

            $indexed[$moduleId] = $entry;
            $composerNames[$composerName] = true;
            $previousModuleId = $moduleId;
        }

        foreach ($entries as $entry) {
            foreach ([...$entry->requires(), ...$entry->conflicts()] as $relatedModuleId) {
                if (!isset($indexed[$relatedModuleId->value()])) {
                    throw new \InvalidArgumentException('release-installation-catalog-reference-invalid');
                }
            }
        }

        $this->entries = $entries;
        $this->entryByModuleId = $indexed;
    }

    public function releaseLine(): string
    {
        return $this->releaseLine;
    }

    public function publicConstraint(): string
    {
        return $this->publicConstraint;
    }

    /** @return list<ModulePlanEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function entry(ModuleId $moduleId): ?ModulePlanEntry
    {
        return $this->entryByModuleId[$moduleId->value()] ?? null;
    }
}
