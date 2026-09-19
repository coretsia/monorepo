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

namespace Coretsia\Contracts\Module;

/**
 * Contracts-level installed module manifest.
 *
 * The manifest is a deterministic wrapper around installed module descriptors.
 * It is intentionally not a DTO-marker class. Its public exported shape is
 * governed by modules-and-manifests SSoT.
 */
final class ModuleManifest
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @var list<ModuleDescriptor>
     */
    private array $modules;

    /**
     * @var array<non-empty-string,ModuleDescriptor>
     */
    private array $byId;

    /**
     * @param list<ModuleDescriptor> $modules
     */
    public function __construct(array $modules)
    {
        if (!array_is_list($modules)) {
            throw new \InvalidArgumentException('Module manifest modules must be a list.');
        }

        $byId = [];

        foreach ($modules as $module) {
            if (!$module instanceof ModuleDescriptor) {
                throw new \InvalidArgumentException(
                    'Module manifest modules must contain only ModuleDescriptor instances.'
                );
            }

            $moduleId = $module->moduleId();

            if (isset($byId[$moduleId])) {
                throw new \InvalidArgumentException('Module manifest contains duplicate module id.');
            }

            $byId[$moduleId] = $module;
        }

        ksort($byId, \SORT_STRING);

        $this->byId = $byId;
        $this->modules = array_values($byId);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Returns installed module descriptors in deterministic moduleId order.
     *
     * @return list<ModuleDescriptor>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Returns installed module ids in deterministic byte-order strcmp order.
     *
     * @return list<non-empty-string>
     */
    public function ids(): array
    {
        return array_keys($this->byId);
    }

    /**
     * Returns whether the manifest contains a module id.
     *
     * Invalid module ids are treated as absent.
     */
    public function has(string $moduleId): bool
    {
        $normalized = self::normalizeLookupModuleId($moduleId);

        if ($normalized === null) {
            return false;
        }

        return isset($this->byId[$normalized]);
    }

    /**
     * Returns a descriptor by module id or null when absent.
     *
     * Invalid module ids are treated as absent.
     */
    public function get(string $moduleId): ?ModuleDescriptor
    {
        $normalized = self::normalizeLookupModuleId($moduleId);

        if ($normalized === null) {
            return null;
        }

        return $this->byId[$normalized] ?? null;
    }

    /**
     * Stable exported scalar/json-like manifest shape.
     *
     * @return array{
     *     moduleIds: list<non-empty-string>,
     *     modules: list<array<string,mixed>>,
     *     schemaVersion: int
     * }
     */
    public function toArray(): array
    {
        return [
            'moduleIds' => $this->ids(),
            'modules' => array_map(
                static fn (ModuleDescriptor $module): array => $module->toArray(),
                $this->modules,
            ),
            'schemaVersion' => self::SCHEMA_VERSION,
        ];
    }

    private static function normalizeLookupModuleId(string $moduleId): ?string
    {
        try {
            return ModuleId::fromString($moduleId)->value();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
