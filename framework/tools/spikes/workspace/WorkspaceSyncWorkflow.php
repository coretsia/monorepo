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

final class WorkspaceSyncWorkflow
{
    private const string REL_FRAMEWORK_COMPOSER_JSON = 'framework/composer.json';
    private const string REL_SKELETON_COMPOSER_JSON = 'skeleton/composer.json';

    /**
     * @var list<string>
     */
    private const array TARGETS = [
        self::REL_FRAMEWORK_COMPOSER_JSON,
        self::REL_SKELETON_COMPOSER_JSON,
    ];

    private const string MSG_PACKAGE_INDEX_DIGEST_INVALID = 'workspace-sync-package-index-digest-invalid';

    private function __construct()
    {
    }

    /**
     * @return array{
     *   changedPaths:list<string>,
     *   files:list<array{
     *     path:string,
     *     changed:bool,
     *     oldLen:int,
     *     newLen:int,
     *     oldSha256:string,
     *     newSha256:string
     *   }>,
     *   packageIndex:array{count:int,sha256:string}
     * }
     *
     * @throws DeterministicException
     */
    public static function run(string $workspaceRoot, bool $apply): array
    {
        $workspaceRoot = self::normalizePath($workspaceRoot);
        $workspaceRoot = \rtrim($workspaceRoot, '/');

        if ($workspaceRoot === '' || !\is_dir($workspaceRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        self::assertWorkspaceTargetsExist($workspaceRoot);

        $files = [];
        $changedPaths = [];

        foreach (self::TARGETS as $relPath) {
            $absPath = self::joinPath($workspaceRoot, $relPath);

            $originalBytes = self::readBytesExactOrFail($absPath);
            $composerJson = self::decodeComposerJsonOrFail($originalBytes);
            $composerJson = WorkspacePolicy::rebuildManagedRepositoriesBlockIfPresent($composerJson);
            $nextBytes = ComposerJsonCanonicalizer::encodeCanonical($composerJson);

            $changed = ($nextBytes !== $originalBytes);
            if ($changed) {
                $changedPaths[] = $relPath;
            }

            $files[] = [
                'path' => $relPath,
                'changed' => $changed,
                'oldLen' => \strlen($originalBytes),
                'newLen' => \strlen($nextBytes),
                'oldSha256' => \hash('sha256', $originalBytes),
                'newSha256' => \hash('sha256', $nextBytes),
            ];
        }

        \usort($changedPaths, static fn (string $a, string $b): int => \strcmp($a, $b));
        \usort(
            $files,
            static function (array $a, array $b): int {
                return \strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
            }
        );

        if ($apply === true) {
            foreach (self::TARGETS as $relPath) {
                $row = self::findFileRowByPath($files, $relPath);
                if ($row === null || ($row['changed'] ?? false) !== true) {
                    continue;
                }

                ComposerRepositoriesSync::sync($workspaceRoot, $relPath);
            }
        }

        $packageIndex = PackageIndexBuilder::build($workspaceRoot);
        $packageIndexDigest = \hash('sha256', \serialize($packageIndex));

        if (!\is_string($packageIndexDigest) || $packageIndexDigest === '') {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_WORKSPACE_SYNC_RESULT_INVALID,
                self::MSG_PACKAGE_INDEX_DIGEST_INVALID,
            );
        }

        return [
            'changedPaths' => \array_values($changedPaths),
            'files' => \array_values($files),
            'packageIndex' => [
                'count' => \count($packageIndex),
                'sha256' => $packageIndexDigest,
            ],
        ];
    }

    /**
     * @throws DeterministicException
     */
    private static function assertWorkspaceTargetsExist(string $workspaceRoot): void
    {
        foreach (self::TARGETS as $rel) {
            $abs = self::joinPath($workspaceRoot, $rel);

            if (!\is_file($abs) || !\is_readable($abs)) {
                self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
            }
        }

        $packagesRoot = self::joinPath($workspaceRoot, 'framework/packages');
        if (!\is_dir($packagesRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }
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
     * @return array<string, mixed>
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

        if (!\is_array($decoded) || \array_is_list($decoded)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param list<array<string,mixed>> $files
     * @return array<string,mixed>|null
     */
    private static function findFileRowByPath(array $files, string $relPath): ?array
    {
        foreach ($files as $row) {
            if (!\is_array($row)) {
                continue;
            }
            if (($row['path'] ?? null) === $relPath) {
                return $row;
            }
        }

        return null;
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
     * @throws DeterministicException
     */
    private static function fail(string $code, ?\Throwable $previous = null): never
    {
        throw new DeterministicException($code, $code, $previous);
    }
}
