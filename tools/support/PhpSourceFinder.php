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
 * Mechanical deterministic discovery of PHP source files.
 *
 * This class intentionally owns no DTO, observability, package, or gate policy.
 */
final class PhpSourceFinder
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $excludedSegments Exact directory names to prune.
     *
     * @return list<string> Normalized absolute file paths, strcmp-sorted.
     */
    public static function find(string $root, array $excludedSegments = []): array
    {
        $resolvedRoot = self::realpathSuppressed($root);
        if ($resolvedRoot === null || !is_dir($resolvedRoot)) {
            throw new \RuntimeException('php-source-root-invalid');
        }

        $excluded = self::excludedLookup($excludedSegments);

        try {
            $directory = new \RecursiveDirectoryIterator(
                $resolvedRoot,
                \FilesystemIterator::SKIP_DOTS,
            );

            $filter = new \RecursiveCallbackFilterIterator(
                $directory,
                static function (\SplFileInfo $current) use ($excluded): bool {
                    if ($current->isLink()) {
                        return false;
                    }

                    if (!$current->isDir()) {
                        return true;
                    }

                    return !isset($excluded[$current->getFilename()]);
                },
            );

            $iterator = new \RecursiveIteratorIterator($filter);
        } catch (\UnexpectedValueException) {
            throw new \RuntimeException('php-source-root-unreadable');
        }

        $files = [];

        try {
            foreach ($iterator as $file) {
                if (
                    !$file instanceof \SplFileInfo
                    || !$file->isFile()
                    || $file->isLink()
                ) {
                    continue;
                }

                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $realPath = $file->getRealPath();
                if (!is_string($realPath) || $realPath === '') {
                    throw new \RuntimeException('php-source-file-path-invalid');
                }

                $normalizedPath = RepositoryContext::normalizePath($realPath);

                if (!RepositoryContext::containsPath($resolvedRoot, $normalizedPath)) {
                    throw new \RuntimeException('php-source-file-outside-root');
                }

                $files[] = $normalizedPath;
            }
        } catch (\UnexpectedValueException) {
            throw new \RuntimeException('php-source-root-unreadable');
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param list<string> $roots
     * @param list<string> $excludedSegments Exact directory names to prune.
     *
     * @return list<string> Normalized absolute file paths, strcmp-sorted.
     */
    public static function findMany(array $roots, array $excludedSegments = []): array
    {
        $files = [];

        foreach ($roots as $root) {
            if (!is_string($root) || $root === '') {
                throw new \RuntimeException('php-source-root-invalid');
            }

            foreach (self::find($root, $excludedSegments) as $file) {
                $files[] = $file;
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param list<string> $segments
     *
     * @return array<string,true>
     */
    private static function excludedLookup(array $segments): array
    {
        $lookup = [];

        foreach ($segments as $segment) {
            if (
                !is_string($segment)
                || $segment === ''
                || $segment === '.'
                || $segment === '..'
                || str_contains($segment, '/')
                || str_contains($segment, '\\')
                || str_contains($segment, "\0")
            ) {
                throw new \RuntimeException('php-source-excluded-segment-invalid');
            }

            $lookup[$segment] = true;
        }

        return $lookup;
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

        return RepositoryContext::normalizePath($resolved);
    }
}
