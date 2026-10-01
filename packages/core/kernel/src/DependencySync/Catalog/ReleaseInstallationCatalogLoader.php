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
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\Module\ModulePlanEntry;

/** @internal */
final readonly class ReleaseInstallationCatalogLoader
{
    private const string RESOURCE_RELATIVE_PATH = 'resources/packaging/installation-catalog.php';

    public function load(): ReleaseInstallationCatalog
    {
        $catalogPath = \dirname(__DIR__, 3)
            . \DIRECTORY_SEPARATOR
            . \str_replace('/', \DIRECTORY_SEPARATOR, self::RESOURCE_RELATIVE_PATH);

        if (
            !\is_file($catalogPath)
            || !\is_readable($catalogPath)
            || \is_link($catalogPath)
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }

        try {
            $previous = \set_error_handler(
                static function (): never {
                    throw new \ErrorException('installation-catalog-load-failed');
                },
            );

            try {
                $raw = require $catalogPath;
            } finally {
                \restore_error_handler();
                unset($previous);
            }

            return $this->fromRaw($raw);
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_INVALID);
        }
    }

    private function fromRaw(mixed $raw): ReleaseInstallationCatalog
    {
        if (
            !\is_array($raw)
            || \array_is_list($raw)
            || !self::hasExactKeys(
                $raw,
                ['schemaVersion', 'releaseLine', 'publicConstraint', 'modules'],
            )
            || $raw['schemaVersion'] !== 1
            || !\is_string($raw['releaseLine'])
            || !\is_string($raw['publicConstraint'])
            || !\is_array($raw['modules'])
            || \array_is_list($raw['modules'])
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_INVALID);
        }

        $moduleKeys = \array_keys($raw['modules']);
        $sortedModuleKeys = $moduleKeys;
        \usort($sortedModuleKeys, static fn (string $a, string $b): int => \strcmp($a, $b));

        if ($moduleKeys !== $sortedModuleKeys) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_INVALID);
        }

        $entries = [];

        foreach ($raw['modules'] as $rawModuleId => $record) {
            if (!\is_string($rawModuleId)) {
                $this->invalid();
            }

            $moduleId = $this->canonicalModuleId($rawModuleId);

            if (
                !\is_array($record)
                || \array_is_list($record)
                || !self::hasExactKeys(
                    $record,
                    ['composerName', 'requires', 'conflicts'],
                )
                || !\is_string($record['composerName'])
                || !\str_starts_with($record['composerName'], 'coretsia/')
            ) {
                $this->invalid();
            }

            $requires = $this->moduleIdList($record['requires'], $rawModuleId);
            $conflicts = $this->moduleIdList($record['conflicts'], $rawModuleId);
            $requiresMap = [];

            foreach ($requires as $required) {
                $requiresMap[$required->value()] = true;
            }

            foreach ($conflicts as $conflict) {
                if (isset($requiresMap[$conflict->value()])) {
                    $this->invalid();
                }
            }

            try {
                $entries[] = new ModulePlanEntry(
                    $moduleId,
                    $record['composerName'],
                    $requires,
                    $conflicts,
                );
            } catch (\InvalidArgumentException) {
                $this->invalid();
            }
        }

        try {
            return new ReleaseInstallationCatalog(
                $raw['releaseLine'],
                $raw['publicConstraint'],
                $entries,
            );
        } catch (\InvalidArgumentException) {
            $this->invalid();
        }
    }

    private function canonicalModuleId(string $rawModuleId): ModuleId
    {
        try {
            $moduleId = ModuleId::fromString($rawModuleId);
        } catch (\InvalidArgumentException) {
            $this->invalid();
        }

        if ($rawModuleId !== $moduleId->value()) {
            $this->invalid();
        }

        return $moduleId;
    }

    /** @return list<ModuleId> */
    private function moduleIdList(mixed $raw, string $ownerModuleId): array
    {
        if (!\is_array($raw) || !\array_is_list($raw)) {
            $this->invalid();
        }

        $result = [];
        $seen = [];
        $previous = null;

        foreach ($raw as $rawModuleId) {
            if (!\is_string($rawModuleId)) {
                $this->invalid();
            }

            $moduleId = $this->canonicalModuleId($rawModuleId);

            if (
                $rawModuleId === $ownerModuleId
                || isset($seen[$rawModuleId])
                || ($previous !== null && \strcmp($previous, $rawModuleId) >= 0)
            ) {
                $this->invalid();
            }

            $seen[$rawModuleId] = true;
            $previous = $rawModuleId;
            $result[] = $moduleId;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $value, array $expected): bool
    {
        $keys = \array_keys($value);
        \sort($keys, \SORT_STRING);
        \sort($expected, \SORT_STRING);

        return $keys === $expected;
    }

    private function invalid(): never
    {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_INVALID);
    }
}
