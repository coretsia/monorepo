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

namespace Coretsia\Kernel\Artifacts\Fingerprint;

use Coretsia\Kernel\Artifacts\Exception\FingerprintSymlinkForbiddenException;

/**
 * Deterministic file lister for explicitly declared fingerprint inputs.
 *
 * This lister is intentionally narrow. It MAY be used only for deterministic
 * hashing of already-declared fingerprint input roots/candidates.
 *
 * It MUST NOT be used to discover:
 *
 * - modules;
 * - config roots;
 * - app targets;
 * - installed package lists;
 * - unknown config files;
 * - arbitrary dotenv files;
 * - filesystem-derived framework state.
 *
 * Directory listing exists here only so ConfigFingerprintInputBuilder can hash
 * explicitly supplied input buckets deterministically.
 *
 * Runtime behavior:
 *
 * - does not follow symlinks;
 * - detects symlinks before recursion for non-skipped paths;
 * - fails on the first non-skipped symlink without returning partial results;
 * - when a skip callback is supplied, skipped relative paths are not inspected
 *   and are not recursed into;
 * - returns normalized relative paths using `/`;
 * - sorts paths with bytewise strcmp;
 * - does not rely on OS locale;
 * - does not include absolute paths or input path strings in exception messages.
 *
 * @internal
 */
final class DeterministicFileLister
{
    private const string REASON_ROOT_INVALID = 'fingerprint-file-listing-root-invalid';
    private const string REASON_ROOT_NOT_FOUND = 'fingerprint-file-listing-root-not-found';
    private const string REASON_ROOT_NOT_DIRECTORY = 'fingerprint-file-listing-root-not-directory';
    private const string REASON_DIRECTORY_READ_FAILED = 'fingerprint-file-listing-directory-read-failed';
    private const string REASON_ENTRY_TYPE_INVALID = 'fingerprint-file-listing-entry-type-invalid';
    private const string REASON_FILE_CANDIDATE_INVALID = 'fingerprint-file-candidate-invalid';
    private const string REASON_FILE_CANDIDATE_NOT_FOUND = 'fingerprint-file-candidate-not-found';
    private const string REASON_FILE_CANDIDATE_NOT_FILE = 'fingerprint-file-candidate-not-file';

    /**
     * Lists files below an explicitly declared fingerprint input directory.
     *
     * Returned paths are relative to the declared root and use `/` separators.
     *
     * Example:
     *
     * ```text
     * config/app.php
     * config/packages/http.php
     * ```
     *
     * @param null|\Closure(non-empty-string): bool $skipRelativePath
     *
     * @return list<non-empty-string>
     *
     * @throws FingerprintSymlinkForbiddenException
     * @throws \RuntimeException with fixed reason-token messages for non-symlink
     *                           filesystem failures.
     */
    public function listFiles(
        string $declaredRoot,
        ?\Closure $skipRelativePath = null,
    ): array {
        $root = self::normalizeDeclaredPath($declaredRoot);

        if ($root === '') {
            throw new \RuntimeException(self::REASON_ROOT_INVALID);
        }

        self::assertNotSymlink($root);

        if (!@\file_exists($root)) {
            throw new \RuntimeException(self::REASON_ROOT_NOT_FOUND);
        }

        if (!@\is_dir($root)) {
            throw new \RuntimeException(self::REASON_ROOT_NOT_DIRECTORY);
        }

        $files = [];

        self::collectFiles(
            directory: $root,
            relativeDirectory: '',
            files: $files,
            skipRelativePath: $skipRelativePath,
        );

        \usort($files, static fn (string $left, string $right): int => \strcmp($left, $right));

        return $files;
    }

    /**
     * Lists a single explicitly declared fingerprint input file candidate.
     *
     * This helper is useful when the caller already knows the candidate is a
     * file-shaped bucket and wants the same symlink and normalization policy as
     * directory buckets. Missing candidates are not silently converted to an
     * empty list here; representing `exists=false` belongs to
     * ConfigFingerprintInputBuilder.
     *
     * Returned path is the normalized basename of the declared file.
     *
     * @return list<non-empty-string>
     *
     * @throws FingerprintSymlinkForbiddenException
     * @throws \RuntimeException with fixed reason-token messages for non-symlink
     *                           filesystem failures.
     */
    public function listFileCandidate(string $declaredFile): array
    {
        $file = self::normalizeDeclaredPath($declaredFile);

        if ($file === '') {
            throw new \RuntimeException(self::REASON_FILE_CANDIDATE_INVALID);
        }

        self::assertNotSymlink($file);

        if (!@\file_exists($file)) {
            throw new \RuntimeException(self::REASON_FILE_CANDIDATE_NOT_FOUND);
        }

        if (!@\is_file($file)) {
            throw new \RuntimeException(self::REASON_FILE_CANDIDATE_NOT_FILE);
        }

        $basename = self::basename($file);

        if ($basename === '') {
            throw new \RuntimeException(self::REASON_FILE_CANDIDATE_INVALID);
        }

        return [
            $basename,
        ];
    }

    /**
     * @param non-empty-string $directory
     * @param list<non-empty-string> $files
     * @param null|\Closure(non-empty-string): bool $skipRelativePath
     *
     * @throws FingerprintSymlinkForbiddenException
     */
    private static function collectFiles(
        string $directory,
        string $relativeDirectory,
        array &$files,
        ?\Closure $skipRelativePath,
    ): void {
        self::assertNotSymlink($directory);

        $entries = @\scandir($directory, \SCANDIR_SORT_NONE);

        if (!\is_array($entries)) {
            throw new \RuntimeException(self::REASON_DIRECTORY_READ_FAILED);
        }

        $names = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $names[] = $entry;
        }

        \usort($names, static fn (string $left, string $right): int => \strcmp($left, $right));

        foreach ($names as $name) {
            $absolutePath = self::joinPath($directory, $name);
            $relativePath = $relativeDirectory === ''
                ? self::normalizeRelativePath($name)
                : self::normalizeRelativePath($relativeDirectory . '/' . $name);

            if ($skipRelativePath !== null && $skipRelativePath($relativePath) === true) {
                continue;
            }

            self::assertNotSymlink($absolutePath);

            if (@\is_dir($absolutePath)) {
                self::collectFiles(
                    directory: $absolutePath,
                    relativeDirectory: $relativePath,
                    files: $files,
                    skipRelativePath: $skipRelativePath,
                );

                continue;
            }

            if (@\is_file($absolutePath)) {
                if ($relativePath === '') {
                    throw new \RuntimeException(self::REASON_ENTRY_TYPE_INVALID);
                }

                $files[] = $relativePath;

                continue;
            }

            throw new \RuntimeException(self::REASON_ENTRY_TYPE_INVALID);
        }
    }

    /**
     * @throws FingerprintSymlinkForbiddenException
     */
    private static function assertNotSymlink(string $path): void
    {
        if (@\is_link($path)) {
            throw FingerprintSymlinkForbiddenException::withReason(
                FingerprintSymlinkForbiddenException::REASON_SYMLINK_FORBIDDEN,
            );
        }
    }

    private static function normalizeDeclaredPath(string $path): string
    {
        $normalized = self::normalizeSeparators($path);
        $normalized = \rtrim($normalized, '/');

        if ($normalized === '') {
            return '';
        }

        return $normalized;
    }

    private static function normalizeRelativePath(string $path): string
    {
        $normalized = self::normalizeSeparators($path);
        $normalized = \ltrim($normalized, '/');

        while (\str_contains($normalized, '//')) {
            $normalized = \str_replace('//', '/', $normalized);
        }

        return $normalized;
    }

    private static function normalizeSeparators(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    private static function joinPath(string $left, string $right): string
    {
        return \rtrim($left, '/') . '/' . \ltrim($right, '/');
    }

    private static function basename(string $path): string
    {
        $normalized = self::normalizeSeparators($path);
        $position = \strrpos($normalized, '/');

        if ($position === false) {
            return $normalized;
        }

        return \substr($normalized, $position + 1);
    }
}
