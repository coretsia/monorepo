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

final class SyncPackagePublicConstraints
{
    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);
        $check = self::argFlag($argv, '--check');

        $releaseLine = ReleaseLine::load($repository);
        $catalog = WorkspacePackageCatalog::discover($repository);
        $products = $catalog->all();

        if ($products === []) {
            throw new RuntimeException('workspace-package-catalog-empty');
        }

        $changedComposerPaths = [];
        $changedFiles = [];

        foreach ($products as $product) {
            $composerJsonPath = $product['composerJsonPath'];

            if (!self::syncPackageComposer(
                $composerJsonPath,
                $catalog,
                $releaseLine->publicConstraint(),
                false,
                $repository,
            )) {
                continue;
            }

            $changedComposerPaths[] = $composerJsonPath;
            $changedFiles[] = $repository->relativeToRepo($composerJsonPath);
        }

        sort($changedFiles, SORT_STRING);

        if ($check) {
            if ($changedFiles !== []) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_OUT_OF_SYNC,
                    $changedFiles,
                );

                return 1;
            }

            return 0;
        }

        foreach ($changedComposerPaths as $composerJsonPath) {
            self::syncPackageComposer(
                $composerJsonPath,
                $catalog,
                $releaseLine->publicConstraint(),
                true,
                $repository,
            );
        }

        ConsoleOutput::line('OK', false);
        ConsoleOutput::lines($changedFiles, false);

        return 0;
    }

    private static function syncPackageComposer(
        string $composerJsonPath,
        WorkspacePackageCatalog $catalog,
        string $publicConstraint,
        bool $apply,
        RepositoryContext $repository,
    ): bool {
        $originalBytes = DeterministicFile::readBytesExact($composerJsonPath);
        $data = ComposerJson::readObject($composerJsonPath);
        $changed = false;

        foreach (['require', 'require-dev'] as $sectionName) {
            if (!array_key_exists($sectionName, $data)) {
                continue;
            }

            $section = $data[$sectionName];

            if ($section instanceof \stdClass) {
                $section = get_object_vars($section);
            } elseif (!is_array($section) || array_is_list($section)) {
                throw new RuntimeException('package-composer-dependency-section-invalid');
            }

            $sectionChanged = false;

            foreach ($section as $name => $constraint) {
                if (!is_string($name) || $name === '') {
                    throw new RuntimeException('package-composer-dependency-name-invalid');
                }

                if (!is_string($constraint) || $constraint === '') {
                    throw new RuntimeException('package-composer-dependency-constraint-invalid');
                }

                if (!str_starts_with($name, 'coretsia/')) {
                    continue;
                }

                if ($catalog->byComposerName($name) === null) {
                    throw new RuntimeException('package-composer-internal-dependency-unknown');
                }

                if ($constraint === $publicConstraint) {
                    continue;
                }

                $section[$name] = $publicConstraint;
                $sectionChanged = true;
                $changed = true;
            }

            if ($sectionChanged) {
                $data[$sectionName] = $section;
            }
        }

        if (!$changed) {
            return false;
        }

        $newJson = ComposerJson::encodeCanonical($data);

        if ($newJson === ComposerJson::normalizeToLfFinalNewline($originalBytes)) {
            return false;
        }

        if ($apply) {
            self::writeBackupIfNeeded(
                $composerJsonPath,
                $originalBytes,
                $repository,
            );

            DeterministicFile::writeTextLf($composerJsonPath, $newJson);
        }

        return true;
    }

    private static function writeBackupIfNeeded(
        string $composerJsonPath,
        string $originalBytes,
        RepositoryContext $repository,
    ): void {
        $relativeDir = dirname(
            $repository->relativeToRepo($composerJsonPath),
        );

        $base = str_replace('/', '__', $relativeDir) . '__composer.json.bak';
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
    exit(SyncPackagePublicConstraints::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_SYNC_FAILED,
    );

    exit(1);
}
