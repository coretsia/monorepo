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

namespace Coretsia\Devtools\CliSpikes\Spikes;

/**
 * Resolves repo-root + framework-root + fixtures roots safely (no absolute path leaks).
 *
 * Root resolution is single-choice and MUST NOT probe/search.
 */
final readonly class SpikesPaths
{
    private string $launcherPath;
    private string $frameworkRoot;
    private string $repoRoot;

    private function __construct(string $launcherPath, string $frameworkRoot, string $repoRoot)
    {
        $this->launcherPath = self::normalizeAbs($launcherPath);
        $this->frameworkRoot = self::normalizeAbs($frameworkRoot);
        $this->repoRoot = self::normalizeAbs($repoRoot);
    }

    /**
     * Single-choice launcher path resolution:
     *  1) $_SERVER['SCRIPT_FILENAME']
     *  2) $_SERVER['argv'][0]
     *
     * @throws SpikesBootstrapFailedException
     */
    public static function fromServerGlobals(): self
    {
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        return self::fromServer($server);
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws SpikesBootstrapFailedException
     */
    public static function fromServer(array $server): self
    {
        $launcherPathRaw = null;

        if (isset($server['SCRIPT_FILENAME']) && is_string($server['SCRIPT_FILENAME']) && $server['SCRIPT_FILENAME'] !== '') {
            $launcherPathRaw = $server['SCRIPT_FILENAME'];
        } elseif (isset($server['argv']) && is_array($server['argv']) && isset($server['argv'][0]) && is_string($server['argv'][0]) && $server['argv'][0] !== '') {
            $launcherPathRaw = $server['argv'][0];
        }

        if (!is_string($launcherPathRaw) || $launcherPathRaw === '') {
            throw new SpikesBootstrapFailedException(SpikesBootstrapFailedException::REASON_LAUNCHER_PATH_UNRESOLVABLE);
        }

        $launcherPath = realpath($launcherPathRaw);
        if (!is_string($launcherPath) || $launcherPath === '') {
            throw new SpikesBootstrapFailedException(SpikesBootstrapFailedException::REASON_LAUNCHER_PATH_UNRESOLVABLE);
        }

        $frameworkRoot = self::resolveFrameworkRootFromLauncher($launcherPath);
        $repoRoot = realpath($frameworkRoot . '/..');

        if (!is_string($repoRoot) || $repoRoot === '') {
            throw new SpikesBootstrapFailedException(SpikesBootstrapFailedException::REASON_REPO_ROOT_UNRESOLVABLE);
        }

        return new self($launcherPath, $frameworkRoot, $repoRoot);
    }

    public function launcherPath(): string
    {
        return $this->launcherPath;
    }

    public function frameworkRoot(): string
    {
        return $this->frameworkRoot;
    }

    public function repoRoot(): string
    {
        return $this->repoRoot;
    }

    public function spikesBootstrapPath(): string
    {
        return $this->frameworkRoot . '/tools/spikes/_support/bootstrap.php';
    }

    public function spikesFixturesRoot(): string
    {
        return $this->frameworkRoot . '/tools/spikes/fixtures';
    }

    /**
     * Repo-relative normalized (forward slashes) display path.
     *
     * MUST:
     * - never return absolute path
     * - be normalized with forward slashes
     * - MUST NOT contain "." or ".." segments
     * - MUST NOT escape outside repo root
     *
     * @throws \InvalidArgumentException if the path escapes repo root (typed message token, no absolutes)
     */
    public function displayPath(string $absPath): string
    {
        $raw = str_replace('\\', '/', trim($absPath));
        if ($raw === '') {
            // Deterministic token only, no path data.
            throw new \InvalidArgumentException('path-outside-repo-root');
        }

        // Be defensive: if a relative path is provided, treat it as repo-relative input.
        $candidateAbs = self::isAbsolute($raw)
            ? $raw
            : rtrim($this->repoRoot, '/') . '/' . ltrim($raw, '/');

        $abs = self::normalizeAbs($candidateAbs);

        $repo = $this->repoRoot;
        $repoCmp = self::cmpFold($repo);
        $absCmp = self::cmpFold($abs);

        if ($absCmp === $repoCmp) {
            return '.';
        }

        $repoPrefix = $repo;
        if (!str_ends_with($repoPrefix, '/')) {
            $repoPrefix .= '/';
        }

        $repoPrefixCmp = self::cmpFold($repoPrefix);

        if (!str_starts_with($absCmp, $repoPrefixCmp)) {
            // Deterministic token only, no path data.
            throw new \InvalidArgumentException('path-outside-repo-root');
        }

        $relRaw = substr($abs, strlen($repoPrefix));
        $relRaw = ltrim($relRaw, '/');

        $rel = self::canonicalizeRepoRelative($relRaw);

        return $rel !== '' ? $rel : '.';
    }

    /**
     * Deterministically derive framework root from a resolved launcher path (no probing/search).
     *
     * Supported canonical launchers (Phase 0):
     * - <repoRoot>/coretsia
     * - <repoRoot>/framework/bin/coretsia
     *
     * @throws SpikesBootstrapFailedException
     */
    private static function resolveFrameworkRootFromLauncher(string $launcherPathAbs): string
    {
        $p = str_replace('\\', '/', $launcherPathAbs);

        // Case A: launched via framework/bin/coretsia
        if (\preg_match('~/(framework)/bin/coretsia(?:\.php)?\z~', $p) === 1) {
            $frameworkRoot = realpath(dirname(dirname($launcherPathAbs)));
            if (!is_string($frameworkRoot) || $frameworkRoot === '') {
                throw new SpikesBootstrapFailedException(SpikesBootstrapFailedException::REASON_FRAMEWORK_ROOT_UNRESOLVABLE);
            }

            return $frameworkRoot;
        }

        // Case B: launched via repo-root/coretsia
        $candidate = realpath(dirname($launcherPathAbs) . '/framework');
        if (!is_string($candidate) || $candidate === '') {
            throw new SpikesBootstrapFailedException(SpikesBootstrapFailedException::REASON_FRAMEWORK_ROOT_UNRESOLVABLE);
        }

        return $candidate;
    }

    private static function normalizeAbs(string $path): string
    {
        $p = str_replace('\\', '/', trim($path));

        // Normalize Windows drive letter if present.
        if (\preg_match('/\A([A-Za-z]):(\/.*)?\z/', $p, $m) === 1) {
            $drive = strtoupper($m[1]) . ':';
            $rest = isset($m[2]) && is_string($m[2]) ? $m[2] : '';
            $p = $drive . $rest;
        }

        // Avoid stripping the root slash from "/" or "C:/"
        if ($p === '/' || \preg_match('/\A[A-Z]:\/\z/', $p) === 1) {
            return $p;
        }

        return rtrim($p, '/');
    }

    private static function cmpFold(string $absPath): string
    {
        // Windows filesystem comparisons are case-insensitive in practice.
        return (\PHP_OS_FAMILY === 'Windows')
            ? strtolower($absPath)
            : $absPath;
    }

    private static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // POSIX absolute or Windows rooted "\foo" (already normalized to "/foo")
        if ($path[0] === '/') {
            return true;
        }

        // UNC after normalization may look like "//server/share/..."
        if (str_starts_with($path, '//')) {
            return true;
        }

        // Windows drive absolute: "C:/..."
        return \preg_match('/\A[A-Za-z]:\//', $path) === 1;
    }

    /**
     * Canonicalize a repo-relative path lexically:
     * - forward slashes only
     * - remove empty segments and "."
     * - resolve ".." and reject escapes beyond repo root
     *
     * @throws \InvalidArgumentException on escape attempt (token only)
     */
    private static function canonicalizeRepoRelative(string $relRaw): string
    {
        $relRaw = str_replace('\\', '/', trim($relRaw));

        if ($relRaw === '' || $relRaw === '.') {
            return '';
        }

        $parts = explode('/', $relRaw);
        $stack = [];

        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }

            if ($seg === '..') {
                if ($stack === []) {
                    // Deterministic token only, no path data.
                    throw new \InvalidArgumentException('path-outside-repo-root');
                }

                array_pop($stack);
                continue;
            }

            $stack[] = $seg;
        }

        return implode('/', $stack);
    }
}
