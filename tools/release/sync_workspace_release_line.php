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

use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\ReleaseLine;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/ReleaseLine.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';

final class SyncWorkspaceReleaseLine
{
    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);
        $check = self::argFlag($argv, '--check');

        $releaseLine = ReleaseLine::load($repository);
        $catalog = WorkspacePackageCatalog::discover($repository);
        $packageNames = self::workspacePackageNames($catalog);
        $workspaceComposerPath = $repository->resolveExistingFile('composer.json');

        $changed = self::syncWorkspaceComposerRequireDev(
            $workspaceComposerPath,
            $packageNames,
            $releaseLine->devVersion(),
            !$check,
            $repository,
        );

        $relativePath = $repository->relativeToRepo($workspaceComposerPath);

        if ($check) {
            if ($changed) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_RELEASE_LINE_WORKSPACE_OUT_OF_SYNC,
                    [$relativePath],
                );

                return 1;
            }

            return 0;
        }

        ConsoleOutput::line('OK', false);

        if ($changed) {
            ConsoleOutput::line($relativePath, false);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private static function workspacePackageNames(WorkspacePackageCatalog $catalog): array
    {
        $names = [];

        foreach ($catalog->all() as $product) {
            $names[] = $product['composerName'];
        }

        sort($names, SORT_STRING);

        if ($names === []) {
            throw new RuntimeException('workspace-package-catalog-empty');
        }

        return $names;
    }

    /**
     * @param list<string> $packageNames
     */
    private static function syncWorkspaceComposerRequireDev(
        string $workspaceComposerPath,
        array $packageNames,
        string $devVersion,
        bool $apply,
        RepositoryContext $repository,
    ): bool {
        $originalBytes = DeterministicFile::readBytesExact($workspaceComposerPath);
        $data = ComposerJson::readObject($workspaceComposerPath);

        if (!array_key_exists('require-dev', $data)) {
            $requireDev = [];
        } else {
            $requireDev = $data['require-dev'];

            if ($requireDev instanceof \stdClass) {
                $requireDev = get_object_vars($requireDev);
            } elseif (!is_array($requireDev) || array_is_list($requireDev)) {
                throw new RuntimeException('workspace-composer-require-dev-invalid');
            }
        }

        /** @var array<string,string> $extRequirements */
        $extRequirements = [];

        /** @var array<string,string> $externalRequirements */
        $externalRequirements = [];

        /** @var array<string,true> $discovered */
        $discovered = [];

        foreach ($packageNames as $packageName) {
            $discovered[$packageName] = true;
        }

        foreach ($requireDev as $name => $constraint) {
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('workspace-composer-require-dev-name-invalid');
            }

            if (!is_string($constraint) || $constraint === '') {
                throw new RuntimeException('workspace-composer-require-dev-constraint-invalid');
            }

            if (str_starts_with($name, 'ext-')) {
                $extRequirements[$name] = $constraint;
                continue;
            }

            if (str_starts_with($name, 'coretsia/')) {
                if (!isset($discovered[$name])) {
                    throw new RuntimeException('workspace-composer-internal-package-unknown');
                }

                // Managed below from the workspace package catalog.
                continue;
            }

            $externalRequirements[$name] = $constraint;
        }

        ksort($extRequirements, SORT_STRING);
        ksort($externalRequirements, SORT_STRING);

        /** @var array<string,string> $internalRequirements */
        $internalRequirements = [];

        foreach ($packageNames as $packageName) {
            $internalRequirements[$packageName] = $devVersion;
        }

        ksort($internalRequirements, SORT_STRING);

        $data['require-dev'] = array_merge(
            $extRequirements,
            $internalRequirements,
            $externalRequirements,
        );

        $newJson = ComposerJson::encodeCanonical($data);
        $changed = $newJson !== ComposerJson::normalizeToLfFinalNewline($originalBytes);

        if ($changed && $apply) {
            self::writeBackupIfNeeded(
                $workspaceComposerPath,
                $originalBytes,
                $repository,
            );

            DeterministicFile::writeTextLf($workspaceComposerPath, $newJson);
        }

        return $changed;
    }

    private static function writeBackupIfNeeded(
        string $composerJsonPath,
        string $originalBytes,
        RepositoryContext $repository,
    ): void {
        $relativeDir = dirname(
            $repository->relativeToRepo($composerJsonPath),
        );

        $owner = $relativeDir === '.'
            ? 'root'
            : str_replace('/', '__', $relativeDir);

        $base = $owner . '__composer.json.bak';
        $relativeBackup = 'var/backups/release-line/' . $base;
        $dst = $repository->resolve($relativeBackup);

        if (is_file($dst)) {
            for ($i = 1; $i <= 999; $i++) {
                $candidate = $repository->resolve($relativeBackup . '.' . $i);

                if (!is_file($candidate)) {
                    $dst = $candidate;
                    break;
                }

                if ($i === 999) {
                    throw new RuntimeException('release-line-backup-limit-exceeded');
                }
            }
        }

        DeterministicFile::writeBytesExact($dst, $originalBytes);
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    /**
     * Read `--repo-root` argument:
     * - `--repo-root=/path`
     * - `--repo-root /path`
     */
    private static function argRepoRoot(array $argv): ?string
    {
        $n = count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string) $argv[$i];

            if (str_starts_with($a, '--repo-root=')) {
                $v = trim(substr($a, strlen('--repo-root=')));

                if ($v === '') {
                    throw new RuntimeException('repo-root-argument-invalid');
                }

                return $v;
            }

            if ($a === '--repo-root') {
                $next = ($i + 1 < $n) ? trim((string) $argv[$i + 1]) : '';

                if ($next === '' || str_starts_with($next, '--')) {
                    throw new RuntimeException('repo-root-argument-invalid');
                }

                return $next;
            }
        }

        return null;
    }

    private static function resolveRepository(array $argv): RepositoryContext
    {
        $repoRoot = self::argRepoRoot($argv);

        return $repoRoot === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($repoRoot);
    }
}

try {
    exit(SyncWorkspaceReleaseLine::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_RELEASE_LINE_WORKSPACE_SYNC_FAILED,
    );

    exit(1);
}
