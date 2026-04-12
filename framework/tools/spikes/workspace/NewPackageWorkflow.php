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

final class NewPackageWorkflow
{
    private const string REL_FRAMEWORK_ROOT = 'framework';
    private const string REL_SKELETON_ROOT = 'skeleton';

    private const string REL_FRAMEWORK_COMPOSER_JSON = 'framework/composer.json';
    private const string REL_SKELETON_COMPOSER_JSON = 'skeleton/composer.json';

    private function __construct()
    {
    }

    /**
     * Atomic “new package” workflow prototype on a workspace tree (fixtures):
     *
     * MUST:
     * - No implicit CWD: all paths are resolved from $workspaceRoot only.
     * - Build changes in a temp dir, then apply via deterministic rename/move steps (no in-place writes that can tear state).
     * - On failure, the workspace tree MUST remain unchanged (rollback).
     * - Prepare package skeleton under framework/packages/<layer>/<slug>/.
     * - Ensure new package has a minimal composer.json with deterministic bytes (LF + final newline),
     *   encoded ONLY via ComposerJsonCanonicalizer::encodeCanonical(...).
     * - Update managed repositories blocks via ComposerRepositoriesSync (managed-only, user-owned untouched).
     * - Update package-index via PackageIndexBuilder (no ad-hoc scanning logic).
     * - MUST NOT call json_encode(...) directly in this workflow.
     *
     * Atomicity policy (single-choice; enforceable in tests):
     * - For each changed file, the apply step uses exactly ONE deterministic rename/move:
     *   staged -> target (atomic replace on POSIX; on platforms where rename cannot replace existing,
     *   the workflow performs a deterministic unlink of target AFTER capturing original bytes for rollback,
     *   then performs the single rename staged -> target).
     * - No partial in-place writes in the success path.
     *
     * @param string $workspaceRoot Workspace root (fixture root). Must contain:
     *  - framework/composer.json
     *  - framework/packages/**
     *  - skeleton/composer.json
     *
     * @param string $layer Package layer (e.g. "core", "devtools", "platform").
     * @param string $slug Package slug (directory name).
     * @param string $composerName Composer package name (e.g. "coretsia/core-foo").
     * @param array<string,string> $psr4 PSR-4 map (e.g. ["Coretsia\\Core\\Foo\\" => "src/"]).
     * @param string $kind Coretsia kind (e.g. "library" | "runtime").
     * @param string|null $moduleClass Optional module class (for kind=runtime typically).
     * @param bool $apply If false: dry-run (no on-disk changes), but still computes deterministic outputs in temp.
     *
     * @return array{
     *   packageIndex: list<array{
     *     slug:string,
     *     layer:string,
     *     path:string,
     *     composerName:string,
     *     psr4:array<string,string>,
     *     kind:string,
     *     moduleClass?:string
     *   }>,
     *   changedPaths: list<string>
     * }
     *
     * @throws DeterministicException
     */
    public static function run(
        string  $workspaceRoot,
        string  $layer,
        string  $slug,
        string  $composerName,
        array   $psr4,
        string  $kind,
        ?string $moduleClass = null,
        bool    $apply = true,
    ): array
    {
        $workspaceRoot = self::normalizePath($workspaceRoot);

        if ($workspaceRoot === '') {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        self::assertWorkspaceShape($workspaceRoot);

        $tmpRoot = self::nextTempWorkspaceRoot($workspaceRoot);

        // Stage everything in temp; if staging fails => original workspace remains unchanged.
        try {
            self::ensureDir($tmpRoot);

            self::stageWorkspaceIntoTemp($workspaceRoot, $tmpRoot);
            self::stageNewPackage($tmpRoot, $layer, $slug, $composerName, $psr4, $kind, $moduleClass);

            // Update managed repositories blocks (on temp copies only).
            ComposerRepositoriesSync::sync($tmpRoot, self::REL_FRAMEWORK_COMPOSER_JSON);
            ComposerRepositoriesSync::sync($tmpRoot, self::REL_SKELETON_COMPOSER_JSON);

            // Compute updated package-index via the single canonical scanner.
            $packageIndex = PackageIndexBuilder::build($tmpRoot);
            $changedPaths = self::computeChangedPaths($workspaceRoot, $tmpRoot, $layer, $slug);

            if ($apply !== true) {
                return [
                    'packageIndex' => $packageIndex,
                    'changedPaths' => $changedPaths,
                ];
            }

            self::applyAtomicallyOrRollback($workspaceRoot, $tmpRoot, $layer, $slug);

            return [
                'packageIndex' => $packageIndex,
                'changedPaths' => $changedPaths,
            ];
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        } finally {
            // Best-effort cleanup; MUST NOT mask the primary error.
            try {
                self::removeTreeBestEffort($tmpRoot);
            } catch (\Throwable) {
                // ignored
            }
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function assertWorkspaceShape(string $workspaceRoot): void
    {
        $frameworkRoot = self::joinPath($workspaceRoot, self::REL_FRAMEWORK_ROOT);
        $skeletonRoot = self::joinPath($workspaceRoot, self::REL_SKELETON_ROOT);

        if (!\is_dir($frameworkRoot) || !\is_dir($skeletonRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $frameworkComposer = self::joinPath($workspaceRoot, self::REL_FRAMEWORK_COMPOSER_JSON);
        $skeletonComposer = self::joinPath($workspaceRoot, self::REL_SKELETON_COMPOSER_JSON);

        if (!\is_file($frameworkComposer) || !\is_file($skeletonComposer)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $packagesRoot = self::joinPath($frameworkRoot, 'packages');
        if (!\is_dir($packagesRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function stageWorkspaceIntoTemp(string $workspaceRoot, string $tmpRoot): void
    {
        // Create top-level dirs first.
        self::ensureDir(self::joinPath($tmpRoot, self::REL_FRAMEWORK_ROOT));
        self::ensureDir(self::joinPath($tmpRoot, self::REL_SKELETON_ROOT));

        // Copy composer.json files (exact bytes).
        self::copyFileExact(
            self::joinPath($workspaceRoot, self::REL_FRAMEWORK_COMPOSER_JSON),
            self::joinPath($tmpRoot, self::REL_FRAMEWORK_COMPOSER_JSON),
        );

        self::copyFileExact(
            self::joinPath($workspaceRoot, self::REL_SKELETON_COMPOSER_JSON),
            self::joinPath($tmpRoot, self::REL_SKELETON_COMPOSER_JSON),
        );

        // Copy framework/packages tree (exact bytes; deterministic traversal).
        $srcPackages = self::joinPath($workspaceRoot, 'framework/packages');
        $dstPackages = self::joinPath($tmpRoot, 'framework/packages');

        self::copyTreeDeterministic($srcPackages, $dstPackages);
    }

    /**
     * @param array<string,string> $psr4
     * @throws DeterministicException
     */
    private static function stageNewPackage(
        string  $tmpRoot,
        string  $layer,
        string  $slug,
        string  $composerName,
        array   $psr4,
        string  $kind,
        ?string $moduleClass,
    ): void
    {
        $layer = self::normalizePath($layer);
        $slug = self::normalizePath($slug);

        if ($layer === '' || $slug === '' || \str_contains($layer, '/') || \str_contains($slug, '/')) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $packageDir = self::joinPath($tmpRoot, 'framework/packages/' . $layer . '/' . $slug);

        if (\is_dir($packageDir) || \is_file($packageDir)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        self::ensureDir($packageDir);
        self::ensureDir(self::joinPath($packageDir, 'src'));

        $composerJsonBytes = self::buildMinimalPackageComposerJsonBytes(
            $composerName,
            $psr4,
            $kind,
            $moduleClass,
        );

        // Deterministic on-disk bytes (LF + final newline) via the single canonical writer.
        try {
            DeterministicFile::writeTextLf(self::joinPath($packageDir, 'composer.json'), $composerJsonBytes);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }
    }

    /**
     * MUST NOT call json_encode(...) directly.
     * Uses the single canonical encoder pipeline.
     *
     * @param array<string,string> $psr4
     * @throws DeterministicException
     */
    private static function buildMinimalPackageComposerJsonBytes(
        string  $composerName,
        array   $psr4,
        string  $kind,
        ?string $moduleClass,
    ): string
    {
        // Minimal composer.json required by PackageIndexBuilder parsing rules:
        // - name
        // - autoload.psr-4 (map)
        // - extra.coretsia.kind (+ moduleClass optionally)
        $composerJson = [];

        // Cemented key insertion order (for deterministic bytes).
        $composerJson['name'] = $composerName;
        $composerJson['type'] = 'library';
        $composerJson['require'] = [
            'php' => '^8.4',
        ];
        $composerJson['autoload'] = [
            'psr-4' => $psr4,
        ];

        $coretsia = [
            'kind' => $kind,
        ];

        if ($moduleClass !== null) {
            $coretsia['moduleClass'] = $moduleClass;
        }

        $composerJson['extra'] = [
            'coretsia' => $coretsia,
        ];

        return ComposerJsonCanonicalizer::encodeCanonical($composerJson);
    }

    /**
     * @throws DeterministicException
     */
    private static function applyAtomicallyOrRollback(string $workspaceRoot, string $tmpRoot, string $layer, string $slug): void
    {
        $rollback = [
            'files' => [],   // list<array{target:string, backup:string, applied:bool}>
            'created' => [], // list<string>
            'dirs' => [],    // list<string>
        ];

        try {
            // 1) Replace composer.json files first.
            //    If file replace cannot be performed on this platform/filesystem, we fail BEFORE creating the new package dir.
            self::replaceFileFromStageSingleMoveOrFail(
                self::joinPath($tmpRoot, self::REL_FRAMEWORK_COMPOSER_JSON),
                self::joinPath($workspaceRoot, self::REL_FRAMEWORK_COMPOSER_JSON),
                $rollback,
            );

            self::replaceFileFromStageSingleMoveOrFail(
                self::joinPath($tmpRoot, self::REL_SKELETON_COMPOSER_JSON),
                self::joinPath($workspaceRoot, self::REL_SKELETON_COMPOSER_JSON),
                $rollback,
            );

            // 2) Move new package directory into place (directory move, atomic on same filesystem).
            $layer = self::normalizePath($layer);
            $slug = self::normalizePath($slug);

            $targetPackageDir = self::joinPath($workspaceRoot, 'framework/packages/' . $layer . '/' . $slug);
            $stagedPackageDir = self::joinPath($tmpRoot, 'framework/packages/' . $layer . '/' . $slug);

            if (!\is_dir($stagedPackageDir)) {
                self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
            }

            if (\file_exists($targetPackageDir)) {
                self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
            }

            $targetLayerDir = self::joinPath($workspaceRoot, 'framework/packages/' . $layer);
            if (!\is_dir($targetLayerDir)) {
                self::ensureDir($targetLayerDir);
                $rollback['created'][] = $targetLayerDir;
            }

            self::renameOrFail($stagedPackageDir, $targetPackageDir);
            $rollback['dirs'][] = $targetPackageDir;

            // 3) Commit (cleanup rollback anchors):
            //    Move backup files (original composer.json) into tmpRoot so the outer finally cleanup can delete them.
            //    This avoids:
            //    - in-place writes
            //    - unlink(target) windows
            //    - partial-deletion risks that would make rollback impossible
            if ($rollback['files'] !== []) {
                $trashRoot = self::joinPath($tmpRoot, '.uow.backups');
                self::ensureDir($trashRoot);

                foreach ($rollback['files'] as $i => &$row) {
                    $backup = (string)$row['backup'];
                    if (!\file_exists($backup)) {
                        continue;
                    }

                    $trashPath = self::joinPath($trashRoot, 'backup.' . (string)$i . '.uow.old');
                    self::renameOrFail($backup, $trashPath);
                    $row['backup'] = $trashPath;
                }
                unset($row);

                // Once backups are moved out of the workspace, rollback is no longer needed for files.
                $rollback['files'] = [];
            }
        } catch (DeterministicException $e) {
            self::rollbackOrFail($rollback);
            throw $e;
        } catch (\Throwable $e) {
            self::rollbackOrFail($rollback);
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }
    }

    /**
     * Replace $targetPath with the staged file at $stagedPath.
     *
     * Atomicity policy:
     * - The apply step that installs new bytes is exactly ONE rename/move per changed file: rename(staged -> target).
     * - No in-place writes are performed in apply/rollback paths.
     *
     * Platform reality (Windows-safe):
     * - We first move the existing target aside to a deterministic backup path (rename target -> backup),
     *   then perform the single apply rename (staged -> target).
     * - On failure, rollback restores using rename/move only.
     *
     * If staged bytes equal target bytes: NO-OP (does not touch target).
     *
     * @param array{
     *   files:list<array{target:string, backup:string, applied:bool}>,
     *   created:list<string>,
     *   dirs:list<string>
     * } $rollback
     *
     * @throws DeterministicException
     */
    private static function replaceFileFromStageSingleMoveOrFail(string $stagedPath, string $targetPath, array &$rollback): void
    {
        if (!\is_file($stagedPath) || !\is_file($targetPath)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $originalBytes = self::readBytesExactOrFail($targetPath);
        $stagedBytes = self::readBytesExactOrFail($stagedPath);

        // Idempotent: do not touch the workspace when bytes are identical.
        if ($stagedBytes === $originalBytes) {
            return;
        }

        $backupPath = self::nextSiblingPath($targetPath, '.uow.old');

        // 1) Move current target aside (rollback anchor).
        self::renameOrFail($targetPath, $backupPath);

        $rollback['files'][] = [
            'target' => $targetPath,
            'backup' => $backupPath,
            'applied' => false,
        ];

        $idx = \array_key_last($rollback['files']);

        // 2) Apply: single move step that installs new bytes (staged -> target).
        try {
            self::renameOrFail($stagedPath, $targetPath);
            $rollback['files'][$idx]['applied'] = true;
        } catch (DeterministicException $e) {
            $restored = false;

            try {
                if (\file_exists($backupPath) && !\file_exists($targetPath)) {
                    self::renameOrFail($backupPath, $targetPath);
                    $restored = true;
                }
            } catch (\Throwable) {
                // ignore; outer rollback will retry
            }

            // If we restored fully, remove the rollback entry to keep it consistent.
            if ($restored) {
                \array_pop($rollback['files']);
            }

            throw $e;
        } catch (\Throwable $e) {
            $restored = false;

            try {
                if (\file_exists($backupPath) && !\file_exists($targetPath)) {
                    self::renameOrFail($backupPath, $targetPath);
                    $restored = true;
                }
            } catch (\Throwable) {
                // ignore; outer rollback will retry
            }

            if ($restored) {
                \array_pop($rollback['files']);
            }

            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function unlinkOrFail(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        $ok = self::guardWrite(static fn(): bool => \unlink($path));
        if ($ok !== true) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
        }
    }

    /**
     * Rollback MUST restore pre-run bytes and remove created artifacts using rename/move only (no in-place writes).
     * If rollback fails, it throws CORETSIA_SPIKES_IO_WRITE_FAILED.
     *
     * @param array{
     *   files:list<array{target:string, backup:string, applied:bool}>,
     *   created:list<string>,
     *   dirs:list<string>
     * } $rollback
     *
     * @throws DeterministicException
     */
    private static function rollbackOrFail(array $rollback): void
    {
        // 1) Restore files first (small + critical), reverse order.
        foreach (\array_reverse($rollback['files']) as $row) {
            $target = (string)$row['target'];
            $backup = (string)$row['backup'];
            $applied = (bool)$row['applied'];

            if (!\file_exists($backup)) {
                continue;
            }

            // If new staged bytes were applied to target, move them aside then restore backup.
            if ($applied === true && \file_exists($target)) {
                $newTmp = self::nextSiblingPath($target, '.uow.rollback.new');
                self::renameOrFail($target, $newTmp);

                // Restore original bytes by rename (backup -> target).
                self::renameOrFail($backup, $target);

                // Remove the moved-aside "new" file.
                self::removeTreeOrFail($newTmp);
                continue;
            }

            // If target is missing (e.g. failure between target->backup and staged->target), restore backup.
            if (!\file_exists($target)) {
                self::renameOrFail($backup, $target);
                continue;
            }

            // Defensive: target exists but state is weird -> still restore deterministically.
            $newTmp = self::nextSiblingPath($target, '.uow.rollback.new');
            self::renameOrFail($target, $newTmp);
            self::renameOrFail($backup, $target);
            self::removeTreeOrFail($newTmp);
        }

        // 2) Remove created/moved dirs (new package dir, created layer dir), reverse order.
        foreach (\array_reverse($rollback['dirs']) as $dir) {
            self::removeTreeOrFail((string)$dir);
        }

        foreach (\array_reverse($rollback['created']) as $created) {
            self::removeTreeOrFail((string)$created);
        }
    }

    /**
     * Deterministic side-by-side sibling name:
     * - $targetPath . $suffix
     * - $targetPath . $suffix . '.1', '.2', ... (smallest available N)
     */
    private static function nextSiblingPath(string $targetPath, string $suffix): string
    {
        $base = $targetPath . $suffix;

        if (!\file_exists($base)) {
            return $base;
        }

        for ($i = 1; ; $i++) {
            $candidate = $base . '.' . $i;
            if (!\file_exists($candidate)) {
                return $candidate;
            }
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function removeTreeOrFail(string $path): void
    {
        if ($path === '' || $path === '.' || $path === '/' || $path === DIRECTORY_SEPARATOR) {
            return;
        }

        if (\is_file($path)) {
            self::unlinkOrFail($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        $entries = self::listDirectoryEntriesSorted($path);
        foreach ($entries as $name) {
            self::removeTreeOrFail(self::joinPath($path, $name));
        }

        $ok = self::guardWrite(static fn(): bool => \rmdir($path));
        if ($ok !== true) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
        }
    }

    /**
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private static function computeChangedPaths(string $workspaceRoot, string $tmpRoot, string $layer, string $slug): array
    {
        $paths = [];

        // New package directory is always a change (must not exist).
        $paths[] = self::normalizeRel('framework/packages/' . $layer . '/' . $slug . '/');

        // Compare composer.json bytes to see if sync changes them.
        $pairs = [
            [self::REL_FRAMEWORK_COMPOSER_JSON, self::REL_FRAMEWORK_COMPOSER_JSON],
            [self::REL_SKELETON_COMPOSER_JSON, self::REL_SKELETON_COMPOSER_JSON],
        ];

        foreach ($pairs as [$relA, $relB]) {
            $a = self::joinPath($workspaceRoot, (string)$relA);
            $b = self::joinPath($tmpRoot, (string)$relB);

            $orig = self::readBytesExactOrFail($a);
            $next = self::readBytesExactOrFail($b);

            if ($orig !== $next) {
                $paths[] = self::normalizeRel((string)$relA);
            }
        }

        \usort($paths, static fn(string $a, string $b): int => \strcmp($a, $b));

        return \array_values(\array_unique($paths));
    }

    /**
     * @throws DeterministicException
     */
    private static function readBytesExactOrFail(string $path): string
    {
        try {
            return DeterministicFile::readBytesExact($path);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED, $e);
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function copyFileExact(string $src, string $dst): void
    {
        $bytes = self::readBytesExactOrFail($src);

        try {
            DeterministicFile::writeBytesExact($dst, $bytes);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }
    }

    /**
     * Deterministic directory copy:
     * - Traversal order is sorted by filename (strcmp).
     * - All file bytes are copied exact.
     *
     * @throws DeterministicException
     */
    private static function copyTreeDeterministic(string $srcDir, string $dstDir): void
    {
        if (!\is_dir($srcDir)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        self::ensureDir($dstDir);

        $entries = self::listDirectoryEntriesSorted($srcDir);

        foreach ($entries as $name) {
            $src = self::joinPath($srcDir, $name);
            $dst = self::joinPath($dstDir, $name);

            if (\is_dir($src)) {
                self::copyTreeDeterministic($src, $dst);
                continue;
            }

            if (\is_file($src)) {
                self::copyFileExact($src, $dst);
                continue;
            }

            // Ignore special entries deterministically (symlinks/devices are out-of-scope for this fixture spike).
        }
    }

    /**
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private static function listDirectoryEntriesSorted(string $dir): array
    {
        try {
            $it = new \DirectoryIterator($dir);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID, $e);
        }

        $out = [];

        foreach ($it as $fi) {
            if ($fi->isDot()) {
                continue;
            }

            $name = $fi->getFilename();
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $out[] = $name;
        }

        \usort($out, static fn(string $a, string $b): int => \strcmp($a, $b));

        return \array_values($out);
    }

    /**
     * @throws DeterministicException
     */
    private static function ensureDir(string $dir): void
    {
        if (\is_dir($dir)) {
            return;
        }

        $ok = self::guardWrite(static fn(): bool => \mkdir($dir, 0777, true));
        if ($ok !== true && !\is_dir($dir)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function renameOrFail(string $from, string $to): void
    {
        $ok = self::guardWrite(static fn(): bool => \rename($from, $to));
        if ($ok !== true) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
        }
    }

    private static function removeFileBestEffort(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        try {
            self::guardWrite(static fn(): bool => \unlink($path));
        } catch (\Throwable) {
            // ignored
        }
    }

    private static function removeTreeBestEffort(string $path): void
    {
        if ($path === '' || $path === '.' || $path === '/' || $path === DIRECTORY_SEPARATOR) {
            return;
        }

        if (\is_file($path)) {
            self::removeFileBestEffort($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        try {
            $entries = self::listDirectoryEntriesSorted($path);
        } catch (\Throwable) {
            return;
        }

        foreach ($entries as $name) {
            self::removeTreeBestEffort(self::joinPath($path, $name));
        }

        try {
            self::guardWrite(static fn(): bool => \rmdir($path));
        } catch (\Throwable) {
            // ignored
        }
    }

    /**
     * Deterministic temp workspace root under $workspaceRoot:
     * - $workspaceRoot/.tmp-new-package-workflow
     * - $workspaceRoot/.tmp-new-package-workflow.1, .2, ... (smallest available N)
     */
    private static function nextTempWorkspaceRoot(string $workspaceRoot): string
    {
        $base = self::joinPath($workspaceRoot, '.tmp-new-package-workflow');

        if (!\file_exists($base)) {
            return $base;
        }

        for ($i = 1; ; $i++) {
            $candidate = $base . '.' . $i;
            if (!\file_exists($candidate)) {
                return $candidate;
            }
        }
    }

    private static function normalizeRel(string $rel): string
    {
        $rel = self::normalizePath($rel);
        $rel = \ltrim($rel, '/');

        return $rel;
    }

    private static function joinPath(string $base, string $rel): string
    {
        $b = \rtrim(self::normalizePath($base), '/');
        $r = \ltrim(self::normalizePath($rel), '/');

        return $r === '' ? $b : ($b . '/' . $r);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    /**
     * Deterministic guard for filesystem write ops: swallow warnings/notices and return boolean result.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     *
     * @throws DeterministicException
     */
    private static function guardWrite(callable $operation): mixed
    {
        $hadPhpError = false;

        \set_error_handler(
            static function () use (&$hadPhpError): bool {
                $hadPhpError = true;
                return true;
            },
        );

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            \restore_error_handler();
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED, $e);
        }

        \restore_error_handler();

        if ($hadPhpError) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
        }

        return $result;
    }

    /**
     * @throws DeterministicException
     */
    private static function fail(string $code, ?\Throwable $previous = null): never
    {
        throw new DeterministicException($code, $code, $previous);
    }
}
