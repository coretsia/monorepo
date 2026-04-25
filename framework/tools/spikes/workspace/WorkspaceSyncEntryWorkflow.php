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
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class WorkspaceSyncEntryWorkflow
{
    private const string MODE_DRY_RUN = 'dry-run';
    private const string MODE_APPLY = 'apply';
    private const string MSG_RESULT_INVALID = 'workspace-sync-entry-result-invalid';

    private function __construct()
    {
    }

    /**
     * @return array{
     *   mode:'dry-run'|'apply',
     *   result: array{
     *     changedPaths:list<string>,
     *     files:list<array{
     *       path:string,
     *       changed:bool,
     *       oldLen:int,
     *       newLen:int,
     *       oldSha256:string,
     *       newSha256:string
     *     }>,
     *     packageIndex:array{count:int,sha256:string}
     *   }
     * }
     *
     * @throws DeterministicException
     */
    public static function run(string $workspaceRoot, bool $apply): array
    {
        $result = WorkspaceSyncWorkflow::run($workspaceRoot, $apply);

        return [
            'mode' => $apply ? self::MODE_APPLY : self::MODE_DRY_RUN,
            'result' => self::normalizeResult($result),
        ];
    }

    /**
     * @param mixed $raw
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
    private static function normalizeResult(mixed $raw): array
    {
        if (!\is_array($raw) || \array_is_list($raw)) {
            self::failResultInvalid();
        }

        return [
            'changedPaths' => self::normalizeChangedPaths($raw['changedPaths'] ?? null),
            'files' => self::normalizeFiles($raw['files'] ?? null),
            'packageIndex' => self::normalizePackageIndex($raw['packageIndex'] ?? null),
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private static function normalizeChangedPaths(mixed $raw): array
    {
        if (!\is_array($raw) || !\array_is_list($raw)) {
            self::failResultInvalid();
        }

        $out = [];

        foreach ($raw as $value) {
            if (!\is_string($value) || $value === '') {
                self::failResultInvalid();
            }

            $out[] = self::normalizeRepoRelativePath($value);
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        /** @var list<string> $out */
        return \array_values(\array_unique($out));
    }

    /**
     * @param mixed $raw
     * @return list<array{
     *   path:string,
     *   changed:bool,
     *   oldLen:int,
     *   newLen:int,
     *   oldSha256:string,
     *   newSha256:string
     * }>
     *
     * @throws DeterministicException
     */
    private static function normalizeFiles(mixed $raw): array
    {
        if (!\is_array($raw) || !\array_is_list($raw)) {
            self::failResultInvalid();
        }

        $out = [];

        foreach ($raw as $row) {
            if (!\is_array($row) || \array_is_list($row)) {
                self::failResultInvalid();
            }

            $path = $row['path'] ?? null;
            $changed = $row['changed'] ?? null;
            $oldLen = $row['oldLen'] ?? null;
            $newLen = $row['newLen'] ?? null;
            $oldSha256 = $row['oldSha256'] ?? null;
            $newSha256 = $row['newSha256'] ?? null;

            if (!\is_string($path) || $path === '') {
                self::failResultInvalid();
            }
            if (!\is_bool($changed)) {
                self::failResultInvalid();
            }
            if (!\is_int($oldLen) || $oldLen < 0 || !\is_int($newLen) || $newLen < 0) {
                self::failResultInvalid();
            }
            if (!\is_string($oldSha256) || !\is_string($newSha256)) {
                self::failResultInvalid();
            }
            if (
                \preg_match('/\A[a-f0-9]{64}\z/', $oldSha256) !== 1
                || \preg_match('/\A[a-f0-9]{64}\z/', $newSha256) !== 1
            ) {
                self::failResultInvalid();
            }

            $out[] = [
                'path' => self::normalizeRepoRelativePath($path),
                'changed' => $changed,
                'oldLen' => $oldLen,
                'newLen' => $newLen,
                'oldSha256' => $oldSha256,
                'newSha256' => $newSha256,
            ];
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp((string) $a['path'], (string) $b['path'])
        );

        /** @var list<array{
         *   path:string,
         *   changed:bool,
         *   oldLen:int,
         *   newLen:int,
         *   oldSha256:string,
         *   newSha256:string
         * }> $out
         */
        return \array_values($out);
    }

    /**
     * @param mixed $raw
     * @return array{count:int,sha256:string}
     *
     * @throws DeterministicException
     */
    private static function normalizePackageIndex(mixed $raw): array
    {
        if (!\is_array($raw) || \array_is_list($raw)) {
            self::failResultInvalid();
        }

        $count = $raw['count'] ?? null;
        $sha256 = $raw['sha256'] ?? null;

        if (!\is_int($count) || $count < 0 || !\is_string($sha256)) {
            self::failResultInvalid();
        }

        if (\preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1) {
            self::failResultInvalid();
        }

        return [
            'count' => $count,
            'sha256' => $sha256,
        ];
    }

    /**
     * @throws DeterministicException
     */
    private static function normalizeRepoRelativePath(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = \ltrim($path, '/');

        if (
            $path === ''
            || \str_contains($path, ':')
            || \str_starts_with($path, '\\\\')
            || \str_starts_with($path, '//')
        ) {
            self::failResultInvalid();
        }

        return $path;
    }

    /**
     * @throws DeterministicException
     */
    private static function failResultInvalid(): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_WORKSPACE_SYNC_RESULT_INVALID,
            self::MSG_RESULT_INVALID,
        );
    }
}
