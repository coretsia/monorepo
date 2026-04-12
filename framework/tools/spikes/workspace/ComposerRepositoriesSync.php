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

namespace Coretsia\Tools\Spikes\workspace;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class ComposerRepositoriesSync
{
    private const string MANAGED_FLAG = 'coretsia_managed';

    private function __construct()
    {
    }

    /**
     * Sync managed composer repositories block for a composer.json under an explicit workspace root.
     *
     * MUSTs:
     * - No implicit CWD: resolves path from $workspaceRoot only.
     * - Parse composer.json with JSON_THROW_ON_ERROR into associative arrays (insertion order preserved).
     * - Preserve user-owned repositories exactly and in original relative order.
     * - Rebuild managed repositories canonically for the concrete target file.
     * - Canonical placement: managed block is a single contiguous block at the END.
     * - Write updated JSON ONLY via ComposerJsonCanonicalizer::encodeCanonical(...) + DeterministicFile::writeTextLf().
     * - Backups are mandatory before any on-disk change:
     *   - if (and only if) output bytes differ from current bytes: write workspace backup first via writeBytesExact().
     *   - if no change: do NOT create a backup.
     *
     * @return bool True if file bytes changed and were written; false if idempotent (no change).
     *
     * @throws DeterministicException
     */
    public static function sync(string $workspaceRoot, string $composerJsonRelPath): bool
    {
        $composerJsonRelPath = self::normalizeRelativePath($composerJsonRelPath);
        $composerJsonPath = self::joinRootAndRelPath($workspaceRoot, $composerJsonRelPath);

        // Keep semantic: missing target file => BACKUP_PATH_MISSING (nothing to backup and nothing to update).
        if (!\is_file($composerJsonPath)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING);
        }

        // Read raw bytes as-is (no normalization) using the canonical IO policy helper.
        try {
            $originalBytes = DeterministicFile::readBytesExact($composerJsonPath);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED, $e);
        }

        $composerJson = self::decodeComposerJsonOrFail($originalBytes);

        $repositoriesRaw = $composerJson[WorkspacePolicy::KEY_REPOSITORIES] ?? [];
        if ($repositoriesRaw === null) {
            $repositoriesRaw = [];
        }

        WorkspacePolicy::assertRepositoriesListOfMaps($repositoriesRaw);

        /** @var list<array<string,mixed>> $repositories */
        $repositories = \array_values($repositoriesRaw);

        /** @var list<array<string,mixed>> $userOwnedRepositories */
        $userOwnedRepositories = [];
        foreach ($repositories as $repository) {
            if (self::isManagedRepositoryEntry($repository)) {
                continue;
            }

            $userOwnedRepositories[] = $repository;
        }

        $managedRepositories = self::desiredManagedRepositoriesFor($composerJsonRelPath);

        $composerJson[WorkspacePolicy::KEY_REPOSITORIES] = \array_values(
            \array_merge($userOwnedRepositories, $managedRepositories)
        );

        $syncedBytes = ComposerJsonCanonicalizer::encodeCanonical($composerJson);

        if ($syncedBytes === $originalBytes) {
            // Idempotent run => MUST NOT create a new backup.
            return false;
        }

        $backupPath = self::nextWorkspaceBackupPath($workspaceRoot, $composerJsonRelPath);

        try {
            DeterministicFile::writeBytesExact($backupPath, $originalBytes);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }

        try {
            DeterministicFile::writeTextLf($composerJsonPath, $syncedBytes);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }

        return true;
    }

    /**
     * @return array<string, mixed> top-level composer.json object (associative map; not a list)
     *
     * @throws DeterministicException
     */
    private static function decodeComposerJsonOrFail(string $bytes): array
    {
        try {
            $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED, $e);
        }

        // composer.json MUST be an object; reject scalars and top-level lists deterministically.
        if (!\is_array($decoded) || \array_is_list($decoded)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array{type:string,url:string,options:array{symlink:bool},coretsia_managed:bool}>
     *
     * @throws DeterministicException
     */
    private static function desiredManagedRepositoriesFor(string $composerJsonRelPath): array
    {
        return match ($composerJsonRelPath) {
            'composer.json' => [
                self::managedPathRepository('framework', true),
                self::managedPathRepository('framework/packages/*/*', true),
                self::managedPathRepository('skeleton', true),
            ],
            'framework/composer.json' => [
                self::managedPathRepository('packages/*/*', true),
            ],
            'skeleton/composer.json' => [
                self::managedPathRepository('../framework', true),
                self::managedPathRepository('../framework/packages/*/*', true),
            ],
            default => self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING),
        };
    }

    /**
     * @return array{type:string,url:string,options:array{symlink:bool},coretsia_managed:bool}
     */
    private static function managedPathRepository(string $url, bool $symlink): array
    {
        return [
            'type' => 'path',
            'url' => $url,
            'options' => [
                'symlink' => $symlink,
            ],
            self::MANAGED_FLAG => true,
        ];
    }

    /**
     * @param array<string,mixed> $repository
     */
    private static function isManagedRepositoryEntry(array $repository): bool
    {
        return ($repository[self::MANAGED_FLAG] ?? false) === true;
    }

    /**
     * @throws DeterministicException
     */
    private static function joinRootAndRelPath(string $root, string $relPath): string
    {
        $r = \rtrim(\str_replace('\\', '/', $root), '/');
        $p = \ltrim(\str_replace('\\', '/', $relPath), '/');

        if ($r === '') {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING);
        }

        return $p === '' ? $r : ($r . '/' . $p);
    }

    /**
     * Deterministic workspace backup naming:
     * - directory: <workspaceRoot>/framework/var/backups/workspace
     * - file names:
     *   - composer.json           => root__composer.json.bak(.N)
     *   - framework/composer.json => framework__composer.json.bak(.N)
     *   - skeleton/composer.json  => skeleton__composer.json.bak(.N)
     *
     * @throws DeterministicException
     */
    private static function nextWorkspaceBackupPath(string $workspaceRoot, string $composerJsonRelPath): string
    {
        $backupDir = self::joinRootAndRelPath($workspaceRoot, 'framework/var/backups/workspace');

        $baseName = match ($composerJsonRelPath) {
            'composer.json' => 'root__composer.json.bak',
            'framework/composer.json' => 'framework__composer.json.bak',
            'skeleton/composer.json' => 'skeleton__composer.json.bak',
            default => self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING),
        };

        $basePath = $backupDir . '/' . $baseName;

        if (!\file_exists($basePath)) {
            return $basePath;
        }

        for ($i = 1; ; $i++) {
            $candidate = $basePath . '.' . $i;
            if (!\file_exists($candidate)) {
                return $candidate;
            }
        }
    }

    private static function normalizeRelativePath(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $parts = \explode('/', $path);

        $out = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($out !== []) {
                    \array_pop($out);
                }
                continue;
            }

            $out[] = $part;
        }

        return \implode('/', $out);
    }

    /**
     * @throws DeterministicException
     */
    private static function fail(string $code, ?\Throwable $previous = null): never
    {
        throw new DeterministicException($code, $code, $previous);
    }
}
