#!/usr/bin/env php
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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\DeterministicPhpReturnFile;
use Coretsia\Tools\Support\ReleaseLine;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/bootstrap.php';

final class InstallationCatalogTool
{
    private const string OUTPUT_PATH = 'packages/core/kernel/resources/packaging/installation-catalog.php';

    public static function main(array $argv): int
    {
        [$check, $apply] = self::operation($argv);

        $repository = RepositoryContext::fromToolsRoot(dirname(__DIR__));
        $catalog = WorkspacePackageCatalog::discover($repository);
        $releaseLine = ReleaseLine::load($repository);
        $payload = self::buildPayload($catalog, $releaseLine);

        $php = DeterministicPhpReturnFile::render(
            $payload,
            [
                'GENERATED FILE (consumer installation catalog).',
                'Regenerate: composer arch:installation-catalog:generate',
            ],
        );

        $outputPath = $repository->resolve(self::OUTPUT_PATH);
        $current = is_file($outputPath)
            ? DeterministicFile::readBytesExact($outputPath)
            : null;
        $changed = $current !== $php;

        if ($check) {
            if ($changed) {
                ConsoleOutput::line('installation-catalog-out-of-date', true);

                return 1;
            }

            return 0;
        }

        if ($apply && $changed) {
            DeterministicFile::writeTextLf($outputPath, $php);
        }

        ConsoleOutput::line('OK', false);

        return 0;
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    private static function operation(array $argv): array
    {
        $check = in_array('--check', $argv, true);
        $apply = in_array('--apply', $argv, true);

        if (($check && $apply) || (!$check && !$apply)) {
            throw new RuntimeException('installation-catalog-operation-invalid');
        }

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument !== '--check' && $argument !== '--apply') {
                throw new RuntimeException('installation-catalog-argument-invalid');
            }
        }

        return [$check, $apply];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayload(
        WorkspacePackageCatalog $catalog,
        ReleaseLine $releaseLine,
    ): array {
        $modules = [];
        $composerNames = [];

        foreach ($catalog->layeredPackages() as $product) {
            $manifest = ComposerJson::readObject($product['composerJsonPath']);
            $extra = $manifest['extra'] ?? new stdClass();

            if ($extra instanceof stdClass) {
                $extra = [];
            } elseif (!is_array($extra) || array_is_list($extra)) {
                throw new RuntimeException('installation-catalog-extra-invalid');
            }

            $coretsia = $extra['coretsia'] ?? new stdClass();

            if ($coretsia instanceof stdClass) {
                $coretsia = [];
            } elseif (!is_array($coretsia) || array_is_list($coretsia)) {
                throw new RuntimeException('installation-catalog-coretsia-metadata-invalid');
            }

            if (array_key_exists('kind', $coretsia) && !is_string($coretsia['kind'])) {
                throw new RuntimeException('installation-catalog-coretsia-kind-invalid');
            }

            $kind = $coretsia['kind'] ?? null;
            $hasModuleId = array_key_exists('moduleId', $coretsia);

            if ($kind !== 'runtime') {
                if ($hasModuleId) {
                    throw new RuntimeException('installation-catalog-non-runtime-module-id');
                }

                continue;
            }

            if (!$hasModuleId || !is_string($coretsia['moduleId'])) {
                throw new RuntimeException('installation-catalog-runtime-module-id-missing');
            }

            $moduleId = self::canonicalModuleId($coretsia['moduleId']);
            $moduleIdString = $moduleId->value();
            $composerName = $product['composerName'];

            if (!str_starts_with($composerName, 'coretsia/')) {
                throw new RuntimeException('installation-catalog-composer-name-owner-invalid');
            }

            if (isset($modules[$moduleIdString]) || isset($composerNames[$composerName])) {
                throw new RuntimeException('installation-catalog-identity-duplicate');
            }

            $requires = self::moduleIdList($coretsia, 'requires', $moduleIdString);
            $conflicts = self::moduleIdList($coretsia, 'conflicts', $moduleIdString);

            if (array_intersect($requires, $conflicts) !== []) {
                throw new RuntimeException('installation-catalog-edge-overlap');
            }

            $requiresObjects = array_map(
                static fn (string $value): ModuleId => ModuleId::fromString($value),
                $requires,
            );
            $conflictsObjects = array_map(
                static fn (string $value): ModuleId => ModuleId::fromString($value),
                $conflicts,
            );

            new ModulePlanEntry(
                $moduleId,
                $composerName,
                $requiresObjects,
                $conflictsObjects,
            );

            $modules[$moduleIdString] = [
                'composerName' => $composerName,
                'requires' => $requires,
                'conflicts' => $conflicts,
            ];
            $composerNames[$composerName] = true;
        }

        ksort($modules, SORT_STRING);

        foreach ($modules as $record) {
            foreach ([...$record['requires'], ...$record['conflicts']] as $relatedModuleId) {
                if (!isset($modules[$relatedModuleId])) {
                    throw new RuntimeException('installation-catalog-edge-dangling');
                }
            }
        }

        return [
            'schemaVersion' => 1,
            'releaseLine' => $releaseLine->currentMinor(),
            'publicConstraint' => $releaseLine->publicConstraint(),
            'modules' => $modules,
        ];
    }

    private static function canonicalModuleId(string $raw): ModuleId
    {
        $moduleId = ModuleId::fromString($raw);

        if ($raw !== $moduleId->value()) {
            throw new RuntimeException('installation-catalog-module-id-non-canonical');
        }

        return $moduleId;
    }

    /**
     * @param array<string, mixed> $coretsia
     *
     * @return list<string>
     */
    private static function moduleIdList(
        array $coretsia,
        string $field,
        string $ownerModuleId,
    ): array {
        if (!array_key_exists($field, $coretsia)) {
            return [];
        }

        $raw = $coretsia[$field];

        if (!is_array($raw) || !array_is_list($raw)) {
            throw new RuntimeException('installation-catalog-edge-list-invalid');
        }

        $seen = [];
        $values = [];

        foreach ($raw as $rawModuleId) {
            if (!is_string($rawModuleId)) {
                throw new RuntimeException('installation-catalog-edge-invalid');
            }

            $moduleId = self::canonicalModuleId($rawModuleId)->value();

            if ($moduleId === $ownerModuleId || isset($seen[$moduleId])) {
                throw new RuntimeException('installation-catalog-edge-invalid');
            }

            $seen[$moduleId] = true;
            $values[] = $moduleId;
        }

        sort($values, SORT_STRING);

        return $values;
    }
}

try {
    exit(InstallationCatalogTool::main($argv));
} catch (Throwable) {
    ConsoleOutput::line('installation-catalog-invalid', true);

    exit(1);
}
