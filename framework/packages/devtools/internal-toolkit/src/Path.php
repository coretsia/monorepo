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

namespace Coretsia\Devtools\InternalToolkit;

final class Path
{
    private function __construct()
    {
    }

    /**
     * Returns a repo-relative normalized path (forward slashes).
     *
     * MUST:
     *  - return repo-relative path (no absolute prefixes),
     *  - not return ".." segments,
     *  - not escape outside repoRoot.
     *
     * Notes:
     *  - This is a deterministic *lexical* normalizer (does not require filesystem existence).
     *
     * @throws \InvalidArgumentException on invalid inputs or if path escapes repoRoot.
     */
    public static function normalizeRelative(string $absOrRelPath, string $repoRoot): string
    {
        $repoRoot = trim($repoRoot);
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_INVALID_REPO_ROOT');
        }

        $repoRootNorm = self::normalizeSeparators($repoRoot);
        if (!self::isAbsolute($repoRootNorm)) {
            throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_REPO_ROOT_NOT_ABSOLUTE');
        }

        $repoAbs = self::canonicalizeAbsolute($repoRootNorm);

        $pathNorm = self::normalizeSeparators($absOrRelPath);

        $abs = self::isAbsolute($pathNorm)
            ? self::canonicalizeAbsolute($pathNorm)
            : self::canonicalizeAbsolute(self::join($repoAbs, $pathNorm));

        if (!self::isWithinRoot($abs, $repoAbs)) {
            throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_OUTSIDE_REPO_ROOT');
        }

        if ($abs === $repoAbs) {
            return '.';
        }

        $prefix = $repoAbs;
        if ($prefix !== '/' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $rel = substr($abs, strlen($prefix));
        $rel = ltrim($rel, '/');

        // Safety: canonicalizer removes ".." by construction.
        if ($rel === '') {
            return '.';
        }

        return $rel;
    }

    private static function normalizeSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function join(string $baseAbs, string $rel): string
    {
        $rel = ltrim($rel, '/');
        if ($rel === '') {
            return $baseAbs;
        }

        return rtrim($baseAbs, '/') . '/' . $rel;
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
        return (bool)preg_match('/\A[A-Za-z]:\//', $path);
    }

    /**
     * Canonicalizes an absolute path lexically:
     *  - forward slashes
     *  - resolves "." and ".." (fails if ".." escapes the absolute root)
     *  - normalizes Windows drive letter to uppercase
     *  - Windows/MSYS: accepts "/c/..." => "C:/..." when running on Windows
     *  - Windows: strips extended-length prefix ("\\\\?\\") forms when present
     *
     * @throws \InvalidArgumentException
     */
    private static function canonicalizeAbsolute(string $absPath): string
    {
        $p = self::normalizeSeparators(trim($absPath));

        // Windows: handle extended-length paths (\\?\C:\... and \\?\UNC\server\share\...)
        if (\PHP_OS_FAMILY === 'Windows') {
            // "\\?\C:\foo" becomes "//?/C:/foo" after separator normalization.
            if (str_starts_with($p, '//?/')) {
                // "\\?\UNC\server\share\..." => "//?/UNC/server/share/..."
                if (str_starts_with($p, '//?/UNC/')) {
                    $p = '//' . substr($p, 8); // strip "//?/UNC/"
                } else {
                    // "\\?\C:\..." => "//?/C:/..."
                    if (preg_match('/\A\/\/\?\/([A-Za-z]):\/(.*)\z/', $p, $m) === 1) {
                        $p = strtoupper($m[1]) . ':/' . $m[2];
                    } else {
                        throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_UNC_INVALID');
                    }
                }
            }

            // MSYS/MinGW drive paths: "/c/..." => "C:/..."
            if (preg_match('/\A\/([A-Za-z])\/(.*)\z/', $p, $m) === 1) {
                $p = strtoupper($m[1]) . ':/' . $m[2];
            }
        }

        if (!self::isAbsolute($p)) {
            throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_NOT_ABSOLUTE');
        }

        // Windows drive root: "C:/..."
        if (preg_match('/\A([A-Za-z]):\/(.*)\z/', $p, $m) === 1) {
            $drive = strtoupper($m[1]) . ':/';
            $rest = $m[2];

            $segments = self::canonicalizeSegments(explode('/', $rest));
            if ($segments === []) {
                return $drive;
            }

            return $drive . implode('/', $segments);
        }

        // UNC: "//server/share/..."
        if (str_starts_with($p, '//')) {
            $without = substr($p, 2);
            $raw = explode('/', $without);

            if (count($raw) < 2) {
                throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_UNC_INVALID');
            }

            $server = $raw[0];
            $share = $raw[1];
            $rest = array_slice($raw, 2);

            $segments = self::canonicalizeSegments($rest);
            $root = '//' . $server . '/' . $share;

            if ($segments === []) {
                return $root;
            }

            return $root . '/' . implode('/', $segments);
        }

        // POSIX: "/..."
        $without = ltrim($p, '/');
        $segments = self::canonicalizeSegments(explode('/', $without));

        if ($segments === []) {
            return '/';
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    private static function canonicalizeSegments(array $parts): array
    {
        $out = [];

        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }

            if ($seg === '..') {
                if ($out === []) {
                    // Escapes the absolute root => forbidden.
                    throw new \InvalidArgumentException('CORETSIA_INTERNAL_TOOLKIT_PATH_DOTDOT_ESCAPES_ROOT');
                }
                array_pop($out);
                continue;
            }

            $out[] = $seg;
        }

        return $out;
    }

    private static function isWithinRoot(string $absPath, string $rootAbs): bool
    {
        if ($rootAbs === '/') {
            // POSIX root contains all absolute POSIX paths.
            return str_starts_with($absPath, '/');
        }

        // Windows: path containment is case-insensitive (drive + segments),
        // and MSYS forms must not break containment checks.
        if (\PHP_OS_FAMILY === 'Windows') {
            $a = self::windowsFoldForCompare($absPath);
            $r = self::windowsFoldForCompare($rootAbs);

            if ($a === $r) {
                return true;
            }

            $prefix = $r;
            if (!str_ends_with($prefix, '/')) {
                $prefix .= '/';
            }

            return str_starts_with($a, $prefix);
        }

        if ($absPath === $rootAbs) {
            return true;
        }

        $prefix = $rootAbs;
        if (!str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        return str_starts_with($absPath, $prefix);
    }

    /**
     * Windows-only folding for containment comparisons (case-insensitive FS).
     * Input is expected to be an absolute canonical path, but we keep it defensive.
     */
    private static function windowsFoldForCompare(string $absPath): string
    {
        $p = self::normalizeSeparators(trim($absPath));

        // MSYS: "/c/..." => "C:/..."
        if (preg_match('/\A\/([A-Za-z])\/(.*)\z/', $p, $m) === 1) {
            $p = strtoupper($m[1]) . ':/' . $m[2];
        }

        // Normalize drive letter if present.
        if (preg_match('/\A([A-Za-z]):(\/.*)?\z/', $p, $m) === 1) {
            $drive = strtoupper($m[1]) . ':';
            $rest = isset($m[2]) && is_string($m[2]) ? $m[2] : '';
            $p = $drive . $rest;
        }

        // Case-fold for comparisons.
        $p = strtolower($p);

        // Canonicalize trailing slash to keep prefix math stable.
        if ($p !== '/' && str_ends_with($p, '/')) {
            $p = rtrim($p, '/');
        }

        return $p;
    }
}
