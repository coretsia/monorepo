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
    private const string RELEASE_LINE_PATH = 'framework/tools/release/release-line.json';
    private const string RELEASE_LINE_SCHEMA_VERSION = 'coretsia.releaseLine.v1';

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
     * - Package wildcard path repositories receive release-line generated options.versions.
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
        $workspaceRoot = self::normalizeWorkspaceRoot($workspaceRoot);
        $composerJsonRelPath = self::normalizeRelativePath($composerJsonRelPath);
        $composerJsonPath = self::joinRootAndRelPath($workspaceRoot, $composerJsonRelPath);

        // Keep semantic: missing target file => BACKUP_PATH_MISSING (nothing to backup and nothing to update).
        if (!\is_file($composerJsonPath)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING);
        }

        $releaseLine = self::loadReleaseLine($workspaceRoot);
        $workspacePackageVersions = self::discoverWorkspacePackageVersions(
            $workspaceRoot,
            $releaseLine['devVersion'],
        );

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

        $managedRepositories = self::desiredManagedRepositoriesFor(
            $composerJsonRelPath,
            $workspacePackageVersions,
        );

        $composerJson[WorkspacePolicy::KEY_REPOSITORIES] = \array_values(
            \array_merge($userOwnedRepositories, $managedRepositories),
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
     * @return array{currentMinor:string,devVersion:string,publicConstraint:string}
     *
     * @throws DeterministicException
     */
    private static function loadReleaseLine(string $workspaceRoot): array
    {
        $data = self::readJsonObjectFromWorkspace($workspaceRoot, self::RELEASE_LINE_PATH);

        $schemaVersion = $data['schemaVersion'] ?? null;
        $currentMinor = $data['currentMinor'] ?? null;
        $devVersion = $data['devVersion'] ?? null;
        $publicConstraint = $data['publicConstraint'] ?? null;

        if ($schemaVersion !== self::RELEASE_LINE_SCHEMA_VERSION) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
        }

        if (!\is_string($currentMinor) || \preg_match('~\A[0-9]+\.[0-9]+\z~', $currentMinor) !== 1) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
        }

        if (!\is_string($devVersion) || $devVersion !== $currentMinor . '.x-dev') {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
        }

        if (!\is_string($publicConstraint) || $publicConstraint !== '^' . $currentMinor . '.0') {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
        }

        return [
            'currentMinor' => $currentMinor,
            'devVersion' => $devVersion,
            'publicConstraint' => $publicConstraint,
        ];
    }

    /**
     * @return array<string,string>
     *
     * @throws DeterministicException
     */
    private static function discoverWorkspacePackageVersions(string $workspaceRoot, string $devVersion): array
    {
        $packagesRoot = self::joinRootAndRelPath($workspaceRoot, 'framework/packages');

        if (!\is_dir($packagesRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $layers = self::listChildDirectoriesSorted($packagesRoot);

        /** @var array<string,string> $versions */
        $versions = [];

        foreach ($layers as $layer) {
            if (\preg_match('~\A[a-z][a-z0-9-]*\z~', $layer) !== 1) {
                self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
            }

            $layerDir = self::joinRootAndRelPath($packagesRoot, $layer);
            $slugs = self::listChildDirectoriesSorted($layerDir);

            foreach ($slugs as $slug) {
                if (\preg_match('~\A[a-z0-9][a-z0-9-]*\z~', $slug) !== 1) {
                    self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
                }

                $composerRelPath = 'framework/packages/' . $layer . '/' . $slug . '/composer.json';
                $composerPath = self::joinRootAndRelPath($workspaceRoot, $composerRelPath);

                if (!\is_file($composerPath)) {
                    continue;
                }

                $composer = self::readJsonObjectFromWorkspace($workspaceRoot, $composerRelPath);

                $name = $composer['name'] ?? null;
                $expectedName = 'coretsia/' . $layer . '-' . $slug;

                if ($name !== $expectedName) {
                    self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
                }

                $versions[$expectedName] = $devVersion;
            }
        }

        \ksort($versions, \SORT_STRING);

        if ($versions === []) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
        }

        return $versions;
    }

    /**
     * @return list<array<string,mixed>>
     *
     * @throws DeterministicException
     */
    private static function desiredManagedRepositoriesFor(
        string $composerJsonRelPath,
        array $workspacePackageVersions,
    ): array {
        return match ($composerJsonRelPath) {
            'composer.json' => [
                self::managedPathRepository('framework', true),
                self::managedPathRepository('framework/packages/*/*', true, $workspacePackageVersions),
                self::managedPathRepository('skeleton', true),
            ],
            'framework/composer.json' => [
                self::managedPathRepository('packages/*/*', true, $workspacePackageVersions),
            ],
            'skeleton/composer.json' => [
                self::managedPathRepository('../framework', true),
                self::managedPathRepository('../framework/packages/*/*', true, $workspacePackageVersions),
            ],
            default => self::fail(ErrorCodes::CORETSIA_WORKSPACE_BACKUP_PATH_MISSING),
        };
    }

    /**
     * @param array<string,string>|null $workspacePackageVersions
     * @return array<string,mixed>
     */
    private static function managedPathRepository(
        string $url,
        bool $symlink,
        ?array $workspacePackageVersions = null,
    ): array {
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
     * @param array<string,mixed> $repository
     */
    private static function isManagedRepositoryEntry(array $repository): bool
    {
        return ($repository[self::MANAGED_FLAG] ?? false) === true;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws DeterministicException
     */
    private static function readJsonObjectFromWorkspace(string $workspaceRoot, string $relPath): array
    {
        $path = self::joinRootAndRelPath($workspaceRoot, $relPath);

        if (!\is_file($path)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        try {
            $raw = DeterministicFile::readBytesExact($path);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED, $e);
        }

        $data = self::decodeComposerJsonOrFail(self::stripUtf8Bom($raw));

        if (\array_is_list($data)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED);
        }

        return $data;
    }

    /**
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private static function listChildDirectoriesSorted(string $path): array
    {
        try {
            $iterator = new \DirectoryIterator($path);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID, $e);
        }

        $out = [];

        foreach ($iterator as $fi) {
            if ($fi->isDot()) {
                continue;
            }

            if (!$fi->isDir()) {
                continue;
            }

            $name = $fi->getFilename();
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $out[] = $name;
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values($out);
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

    private static function normalizeWorkspaceRoot(string $path): string
    {
        return \rtrim(\str_replace('\\', '/', $path), '/');
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

    private static function stripUtf8Bom(string $bytes): string
    {
        if (\str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return \substr($bytes, 3);
        }

        return $bytes;
    }

    /**
     * @throws DeterministicException
     */
    private static function fail(string $code, ?\Throwable $previous = null): never
    {
        throw new DeterministicException($code, $code, $previous);
    }
}
