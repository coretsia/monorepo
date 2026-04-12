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

namespace Coretsia\Tools\Spikes\fingerprint;

use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Deterministic recursive file lister for the fingerprint spike.
 *
 * Contract (cemented by epic 0.60.0):
 *  - MUST normalize paths via InternalToolkit Path::normalizeRelative(abs, repoRoot)
 *  - MUST NOT follow symlinks; MUST hard-fail on the first encountered symlink
 *  - MUST use locale-independent byte-order sorting (strcmp) on normalized repo-relative paths only
 *  - MUST emit stable lexicographic order (rerun => same order)
 */
final class DeterministicFileLister
{
    private const string MSG_SYMLINK_FORBIDDEN = 'fingerprint-symlink-forbidden';
    private const string MSG_REPO_ROOT_INVALID = 'fingerprint-repo-root-invalid';
    private const string MSG_IO_READ_FAILED = 'fingerprint-file-list-read-failed';

    private string $repoRootAbs;

    /**
     * @throws DeterministicException on invalid repo root (policy) or if repo root itself is a symlink (strict)
     */
    public function __construct(string $repoRootAbs)
    {
        $repoRootAbs = \trim($repoRootAbs);
        if ($repoRootAbs === '') {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_REPO_ROOT_INVALID,
                self::MSG_REPO_ROOT_INVALID,
            );
        }

        $rp = \realpath($repoRootAbs);
        if (!\is_string($rp) || $rp === '' || !\is_dir($rp)) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_REPO_ROOT_INVALID,
                self::MSG_REPO_ROOT_INVALID,
            );
        }

        // Strict: repo root MUST NOT be a symlink.
        // Note: realpath() resolves symlinks; for strictness we check the provided path too.
        if (\is_link($repoRootAbs) || \is_link($rp)) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN,
                self::MSG_SYMLINK_FORBIDDEN,
            );
        }

        $this->repoRootAbs = $rp;
    }

    public function repoRoot(): string
    {
        return $this->repoRootAbs;
    }

    /**
     * Lists all files under repo root as normalized repo-relative paths (forward slashes),
     * in stable lexicographic order (strcmp).
     *
     * @return list<string>
     * @throws DeterministicException on the first encountered symlink (file or directory)
     */
    public function listAllFiles(): array
    {
        /** @var list<string> $result */
        $result = [];

        // FIFO queue with single-level directory iteration only.
        // This avoids recursive re-walk / duplicate traversal.
        /** @var list<string> $queue */
        $queue = [$this->repoRootAbs];
        $qi = 0;

        while ($qi < \count($queue)) {
            $dir = $queue[$qi];
            $qi++;

            if (!\is_string($dir) || $dir === '') {
                continue;
            }

            if (\is_link($dir)) {
                self::failSymlinkForbidden();
            }

            /**
             * Collect immediate children first, then sort deterministically by normalized repo-relative path.
             *
             * @var list<array{abs:string, rel:string, is_dir:bool, is_link:bool}> $entries
             */
            $entries = [];

            try {
                $it = new \DirectoryIterator($dir);
            } catch (\Throwable $e) {
                self::failRead($e);
            }

            foreach ($it as $info) {
                if (!$info instanceof \SplFileInfo) {
                    continue;
                }

                if ($info->isDot()) {
                    continue;
                }

                $abs = $info->getPathname();
                if (!\is_string($abs) || $abs === '') {
                    continue;
                }

                try {
                    $rel = Path::normalizeRelative($abs, $this->repoRootAbs);
                } catch (\Throwable $e) {
                    self::failRead($e);
                }

                $entries[] = [
                    'abs' => $abs,
                    'rel' => $rel,
                    'is_dir' => $info->isDir(),
                    'is_link' => $info->isLink(),
                ];
            }

            \usort(
                $entries,
                static fn(array $a, array $b): int => \strcmp((string) $a['rel'], (string) $b['rel']),
            );

            foreach ($entries as $entry) {
                if (($entry['is_link'] ?? false) === true) {
                    self::failSymlinkForbidden();
                }

                $abs = (string) $entry['abs'];
                $rel = (string) $entry['rel'];

                if (($entry['is_dir'] ?? false) === true) {
                    $queue[] = $abs;
                    continue;
                }

                $result[] = $rel;
            }
        }

        \usort($result, static fn(string $a, string $b): int => \strcmp($a, $b));

        /** @var list<string> $result */
        return \array_values($result);
    }

    /**
     * @throws DeterministicException
     */
    private static function failSymlinkForbidden(): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN,
            self::MSG_SYMLINK_FORBIDDEN,
        );
    }

    /**
     * @throws DeterministicException
     */
    private static function failRead(?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
            self::MSG_IO_READ_FAILED,
            $previous,
        );
    }
}
