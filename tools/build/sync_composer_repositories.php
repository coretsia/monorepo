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

final class SyncComposerRepositories
{
    private const string MANAGED_FLAG = 'coretsia_managed';

    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);
        $check = self::argFlag($argv, '--check');

        $releaseLine = ReleaseLine::load($repository);
        $catalog = WorkspacePackageCatalog::discover($repository);
        $desiredManaged = self::desiredManagedRepos($catalog, $releaseLine->devVersion());
        $composerJsonPath = $repository->resolveExistingFile('composer.json');

        $result = self::syncOne(
            $composerJsonPath,
            $desiredManaged,
            !$check,
            $repository,
        );

        $relativePath = $repository->relativeToRepo($composerJsonPath);

        if ($check) {
            if ($result['invalidManagedBlock']) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID,
                    [$relativePath],
                );

                return 1;
            }

            if ($result['changed']) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_WORKSPACE_MANAGED_REPOS_OUT_OF_SYNC,
                    [$relativePath],
                );

                return 1;
            }

            return 0;
        }

        ConsoleOutput::line('OK', false);

        if ($result['changed']) {
            ConsoleOutput::line($relativePath, false);
        }

        return 0;
    }

    /**
     * @param list<array<string,mixed>> $desiredManaged
     *
     * @return array{changed:bool,invalidManagedBlock:bool}
     */
    private static function syncOne(
        string $composerJsonPath,
        array $desiredManaged,
        bool $apply,
        RepositoryContext $repository,
    ): array {
        $originalBytes = DeterministicFile::readBytesExact($composerJsonPath);
        $data = ComposerJson::readObject($composerJsonPath);

        $repos = $data['repositories'] ?? [];

        if (!is_array($repos)) {
            throw new RuntimeException('repositories-must-be-array');
        }

        $repos = array_values($repos);

        $managedIdx = [];

        foreach ($repos as $i => $repo) {
            if (!is_array($repo)) {
                throw new RuntimeException('repository-entry-must-be-object');
            }

            if (self::isManaged($repo)) {
                $managedIdx[] = (int) $i;
            }
        }

        $invalidManagedBlock = false;

        if ($managedIdx !== []) {
            $min = min($managedIdx);
            $max = max($managedIdx);

            if (count($managedIdx) !== (($max - $min) + 1)) {
                $invalidManagedBlock = true;
            }
        }

        $userRepos = [];

        foreach ($repos as $repo) {
            /** @var array<string,mixed> $repo */
            if (!self::isManaged($repo)) {
                $userRepos[] = $repo;
            }
        }

        $managed = self::canonicalizeManaged($desiredManaged);

        $data['repositories'] = array_values(
            array_merge($managed, $userRepos),
        );

        $newJson = ComposerJson::encodeCanonical($data);

        $changed = $newJson !== ComposerJson::normalizeToLfFinalNewline($originalBytes);

        if (!$changed) {
            return [
                'changed' => false,
                'invalidManagedBlock' => $invalidManagedBlock,
            ];
        }

        if ($apply) {
            self::writeBackupIfNeeded(
                $composerJsonPath,
                $originalBytes,
                $repository,
            );

            DeterministicFile::writeTextLf($composerJsonPath, $newJson);
        }

        return [
            'changed' => true,
            'invalidManagedBlock' => $invalidManagedBlock,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function desiredManagedRepos(
        WorkspacePackageCatalog $catalog,
        string $devVersion,
    ): array {
        $products = $catalog->all();

        if ($products === []) {
            throw new RuntimeException('workspace-package-catalog-empty');
        }

        $repos = [];

        foreach ($products as $product) {
            $repos[] = self::managedPathRepo(
                $product['relativePath'],
                true,
                [$product['composerName'] => $devVersion],
            );
        }

        return $repos;
    }

    /**
     * @param array<string,string>|null $workspacePackageVersions
     *
     * @return array<string,mixed>
     */
    private static function managedPathRepo(string $url, bool $symlink, ?array $workspacePackageVersions = null): array
    {
        /** @var array<string,mixed> $options */
        $options = [
            'symlink' => $symlink,
        ];

        if ($workspacePackageVersions !== null) {
            $options['reference'] = 'config';
            $options['versions'] = $workspacePackageVersions;
        }

        return [
            'type' => 'path',
            'url' => $url,
            'options' => $options,
            self::MANAGED_FLAG => true,
        ];
    }

    /**
     * @param array<string,mixed> $repo
     */
    private static function isManaged(array $repo): bool
    {
        return ($repo[self::MANAGED_FLAG] ?? false) === true;
    }

    /**
     * @param list<array<string,mixed>> $desired
     *
     * @return list<array<string,mixed>>
     */
    private static function canonicalizeManaged(array $desired): array
    {
        $out = [];

        foreach ($desired as $r) {
            if (!is_array($r)) {
                throw new RuntimeException('desiredManaged must be list<object>');
            }

            $type = $r['type'] ?? null;
            $url = $r['url'] ?? null;

            if (!is_string($type) || $type === '' || !is_string($url) || $url === '') {
                throw new RuntimeException('managed repo must contain type+url');
            }

            $options = $r['options'] ?? [];
            if (!is_array($options) || array_is_list($options)) {
                throw new RuntimeException('options must be object');
            }

            /** @var array<string,mixed> $canonicalOptions */
            $canonicalOptions = [
                'symlink' => (bool) ($options['symlink'] ?? true),
            ];

            if (array_key_exists('reference', $options)) {
                $reference = $options['reference'];

                if (!is_string($reference) || $reference === '') {
                    throw new RuntimeException('options.reference must be non-empty string');
                }

                $canonicalOptions['reference'] = $reference;
            }

            if (array_key_exists('versions', $options)) {
                $versions = $options['versions'];

                if (!is_array($versions)) {
                    throw new RuntimeException('options.versions must be non-empty object');
                }

                if ($versions === [] || array_is_list($versions)) {
                    throw new RuntimeException('options.versions must be non-empty object');
                }

                /** @var array<string,string> $canonicalVersions */
                $canonicalVersions = [];

                foreach ($versions as $name => $version) {
                    if (!is_string($name) || $name === '') {
                        throw new RuntimeException('options.versions package name must be non-empty string');
                    }

                    if (!is_string($version) || $version === '') {
                        throw new RuntimeException('options.versions package version must be non-empty string');
                    }

                    $canonicalVersions[$name] = $version;
                }

                ksort($canonicalVersions, SORT_STRING);

                $canonicalOptions['versions'] = $canonicalVersions;
            }

            $out[] = [
                'type' => $type,
                'url' => $url,
                'options' => $canonicalOptions,
                self::MANAGED_FLAG => true,
            ];
        }

        return $out;
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
        $relativeBackup = 'var/backups/workspace/' . $base;
        $dst = $repository->resolve($relativeBackup);

        if (is_file($dst)) {
            for ($i = 1; $i <= 999; $i++) {
                $candidate = $repository->resolve($relativeBackup . '.' . $i);

                if (!is_file($candidate)) {
                    $dst = $candidate;
                    break;
                }

                if ($i === 999) {
                    throw new RuntimeException('workspace-backup-limit-exceeded');
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
    exit(SyncComposerRepositories::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_WORKSPACE_SYNC_FAILED,
    );

    exit(1);
}
