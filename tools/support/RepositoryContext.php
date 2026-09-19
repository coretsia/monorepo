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

namespace Coretsia\Tools\Support;

/**
 * Canonical repository-path context for repository tooling.
 *
 * This class owns repository-root discovery and repository-contained path
 * normalization. It intentionally knows repository topology; runtime/package
 * code must not depend on it.
 */
final class RepositoryContext
{
    private string $repoRoot;
    private string $toolsRoot;
    private string $packagesRoot;

    private function __construct(string $repoRoot)
    {
        $this->repoRoot = $repoRoot;
        $this->toolsRoot = self::joinRoot($repoRoot, 'tools');
        $this->packagesRoot = self::joinRoot($repoRoot, 'packages');
    }

    public static function fromRepoRoot(string $repoRoot): self
    {
        $resolved = self::realpathSuppressed($repoRoot);

        if ($resolved === null || !self::hasRepositoryMarkers($resolved)) {
            throw new \RuntimeException('repository-root-invalid');
        }

        return new self($resolved);
    }

    public static function fromToolsRoot(string $toolsRoot): self
    {
        $resolvedToolsRoot = self::realpathSuppressed($toolsRoot);
        if ($resolvedToolsRoot === null || !is_dir($resolvedToolsRoot)) {
            throw new \RuntimeException('repository-tools-root-invalid');
        }

        $context = self::fromRepoRoot(dirname($resolvedToolsRoot));

        if (!self::pathsEqual($context->toolsRoot(), $resolvedToolsRoot)) {
            throw new \RuntimeException('repository-tools-root-invalid');
        }

        return $context;
    }

    public static function discoverFrom(string $startPath): self
    {
        $resolved = self::realpathSuppressed($startPath);
        if ($resolved === null) {
            throw new \RuntimeException('repository-root-not-found');
        }

        $dir = is_dir($resolved) ? $resolved : dirname($resolved);

        for ($i = 0; $i < 64; $i++) {
            if (self::hasRepositoryMarkers($dir)) {
                return new self($dir);
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        throw new \RuntimeException('repository-root-not-found');
    }

    public function repoRoot(): string
    {
        return $this->repoRoot;
    }

    public function toolsRoot(): string
    {
        return $this->toolsRoot;
    }

    public function packagesRoot(): string
    {
        return $this->packagesRoot;
    }

    /**
     * Resolve a repository-relative or absolute path and require it to remain
     * inside the repository root after canonicalizing its existing path prefix.
     */
    public function resolve(string $path): string
    {
        if ($path === '') {
            throw new \RuntimeException('repository-path-invalid');
        }

        $candidate = self::isAbsolutePath($path)
            ? self::normalizeAbsolutePath($path)
            : self::normalizeAbsolutePath(self::joinRoot($this->repoRoot, $path));

        $candidate = self::canonicalizeExistingPrefix($candidate);

        if (!self::containsPath($this->repoRoot, $candidate)) {
            throw new \RuntimeException('repository-path-outside-root');
        }

        return $candidate;
    }

    public function resolveExistingFile(string $path): string
    {
        $candidate = $this->resolve($path);
        $resolved = self::realpathSuppressed($candidate);

        if ($resolved === null || !is_file($resolved)) {
            throw new \RuntimeException('repository-file-missing');
        }

        if (!self::containsPath($this->repoRoot, $resolved)) {
            throw new \RuntimeException('repository-path-outside-root');
        }

        return $resolved;
    }

    public function resolveExistingDirectory(string $path): string
    {
        $candidate = $this->resolve($path);
        $resolved = self::realpathSuppressed($candidate);

        if ($resolved === null || !is_dir($resolved)) {
            throw new \RuntimeException('repository-directory-missing');
        }

        if (!self::containsPath($this->repoRoot, $resolved)) {
            throw new \RuntimeException('repository-path-outside-root');
        }

        return $resolved;
    }

    public function relativeToRepo(string $path): string
    {
        $absolute = self::isAbsolutePath($path)
            ? self::normalizeAbsolutePath($path)
            : $this->resolve($path);

        if (!self::containsPath($this->repoRoot, $absolute)) {
            throw new \RuntimeException('repository-path-outside-root');
        }

        if (self::pathsEqual($absolute, $this->repoRoot)) {
            return '.';
        }

        $prefix = rtrim($this->repoRoot, '/') . '/';

        return substr($absolute, strlen($prefix));
    }

    public function contains(string $path): bool
    {
        try {
            $absolute = self::isAbsolutePath($path)
                ? self::normalizeAbsolutePath($path)
                : self::normalizeAbsolutePath(self::joinRoot($this->repoRoot, $path));
        } catch (\Throwable) {
            return false;
        }

        return self::containsPath($this->repoRoot, $absolute);
    }

    /**
     * Normalize directory separators and remove a trailing slash.
     *
     * This method does not resolve "." or ".." segments.
     */
    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '/' || preg_match('~\A[A-Za-z]:/\z~', $path) === 1) {
            return $path;
        }

        return rtrim($path, '/');
    }

    public static function isAbsolutePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, '/')
            || preg_match('~\A[A-Za-z]:/~', $path) === 1;
    }

    public static function containsPath(string $root, string $path): bool
    {
        try {
            $root = self::normalizeAbsolutePath($root);
            $path = self::normalizeAbsolutePath($path);
        } catch (\Throwable) {
            return false;
        }

        $rootForCompare = self::pathForComparison($root);
        $pathForCompare = self::pathForComparison($path);

        if ($pathForCompare === $rootForCompare) {
            return true;
        }

        $descendantPrefix = rtrim($rootForCompare, '/') . '/';

        return str_starts_with($pathForCompare, $descendantPrefix);
    }

    private static function hasRepositoryMarkers(string $repoRoot): bool
    {
        return is_dir(self::joinRoot($repoRoot, 'packages'))
            && is_dir(self::joinRoot($repoRoot, 'tools'))
            && is_file(self::joinRoot($repoRoot, 'composer.json'));
    }

    private static function joinRoot(string $root, string $relativePath): string
    {
        return rtrim($root, '/') . '/' . ltrim($relativePath, '/');
    }

    private static function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, '://')) {
            throw new \RuntimeException('repository-path-invalid');
        }

        $prefix = '';
        $rest = '';

        if (preg_match('~\A([A-Za-z]):/(.*)\z~s', $path, $matches) === 1) {
            $prefix = strtoupper($matches[1]) . ':/';
            $rest = $matches[2];
        } elseif (str_starts_with($path, '//')) {
            $prefix = '//';
            $rest = ltrim(substr($path, 2), '/');
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $rest = ltrim($path, '/');
        } else {
            throw new \RuntimeException('repository-path-invalid');
        }

        $segments = [];

        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new \RuntimeException('repository-path-invalid');
                }

                array_pop($segments);
                continue;
            }

            if (str_contains($segment, "\0")) {
                throw new \RuntimeException('repository-path-invalid');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return $prefix;
        }

        return $prefix . implode('/', $segments);
    }

    private static function canonicalizeExistingPrefix(string $path): string
    {
        $normalized = self::normalizeAbsolutePath($path);
        $resolved = self::realpathSuppressed($normalized);

        if ($resolved !== null) {
            return $resolved;
        }

        $suffix = [];
        $cursor = $normalized;

        for ($i = 0; $i < 64; $i++) {
            $parent = self::normalizePath(dirname($cursor));

            if ($parent === '' || $parent === $cursor) {
                break;
            }

            array_unshift($suffix, basename($cursor));
            $cursor = self::normalizeAbsolutePath($parent);
            $resolved = self::realpathSuppressed($cursor);

            if ($resolved !== null) {
                return self::normalizeAbsolutePath(
                    self::joinRoot($resolved, implode('/', $suffix)),
                );
            }
        }

        return $normalized;
    }

    private static function pathsEqual(string $left, string $right): bool
    {
        return self::pathForComparison($left) === self::pathForComparison($right);
    }

    private static function pathForComparison(string $path): string
    {
        if (PHP_OS_FAMILY === 'Windows' || preg_match('~\A[A-Za-z]:/~', $path) === 1) {
            return strtolower($path);
        }

        return $path;
    }

    private static function realpathSuppressed(string $path): ?string
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $resolved = realpath($path);
        } finally {
            restore_error_handler();
        }

        if ($resolved === false) {
            return null;
        }

        return self::normalizeAbsolutePath($resolved);
    }
}
