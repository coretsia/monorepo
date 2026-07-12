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

namespace Coretsia\Kernel\Artifacts\Paths;

use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Boot\BootstrapConfig;

/**
 * Resolves Kernel-owned artifact output paths.
 *
 * Artifact paths are derived exclusively from:
 *
 * - BootstrapConfig::skeletonRoot();
 * - BootstrapConfig::appTarget()->value;
 * - BootstrapConfig::artifactsCacheDir();
 * - a canonical Kernel-owned artifact basename.
 *
 * The artifact cache directory is resolved and validated during Bootstrap
 * Phase A. This resolver does not read Kernel config and does not resolve
 * bootstrap defaults or application overrides.
 *
 * Runtime behavior:
 *
 * - artifacts are resolved under
 *   `<skeletonRoot>/<artifactsCacheDir>/<appTarget>/`;
 * - only Kernel-owned artifact basenames are accepted;
 * - `routes.php` is intentionally not accepted because `routes@1` is owned by
 *   platform/routing, not core/kernel;
 * - the final artifact path must remain under the resolved cache directory;
 * - ArtifactPathInvalidException messages never include configured path
 *   values or absolute resolved paths.
 *
 * @internal
 */
final class ArtifactPathResolver
{
    public const string MODULE_MANIFEST_BASENAME = 'module-manifest.php';
    public const string CONFIG_BASENAME = 'config.php';
    public const string CONTAINER_BASENAME = 'container.php';
    private const int MAX_RELATIVE_PATH_BYTES = 512;

    /**
     * @var array<string, true>
     */
    private const array CANONICAL_BASENAMES = [
        self::MODULE_MANIFEST_BASENAME => true,
        self::CONFIG_BASENAME => true,
        self::CONTAINER_BASENAME => true,
    ];

    public function moduleManifestPath(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            basename: self::MODULE_MANIFEST_BASENAME,
        );
    }

    public function configPath(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            basename: self::CONFIG_BASENAME,
        );
    }

    public function containerPath(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            basename: self::CONTAINER_BASENAME,
        );
    }

    /**
     * Resolves an absolute or caller-supplied-root-relative artifact path.
     *
     * The returned path uses `/` separators deterministically. On Windows, PHP
     * accepts `/` separators for normal filesystem operations.
     *
     * @throws ArtifactPathInvalidException
     */
    public function resolve(
        BootstrapConfig $bootstrapConfig,
        string $basename,
    ): string {
        $skeletonRoot = self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot());
        $relativePath = $this->relativePath(
            bootstrapConfig: $bootstrapConfig,
            basename: $basename,
        );

        $path = self::joinPath($skeletonRoot, $relativePath);

        $expectedDirectoryPrefix = self::joinPath(
            $skeletonRoot,
            $this->relativeCacheDirectory($bootstrapConfig),
        ) . '/';

        if (!\str_starts_with($path, $expectedDirectoryPrefix)) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_TARGET_OUTSIDE_CACHE_DIR,
            );
        }

        return $path;
    }

    /**
     * Resolves a normalized artifact path relative to BootstrapConfig::skeletonRoot().
     *
     * Example:
     *
     *     var/cache/web/config.php
     *
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    public function relativePath(
        BootstrapConfig $bootstrapConfig,
        string $basename,
    ): string {
        $basename = self::canonicalBasename($basename);

        $relativePath = $bootstrapConfig->artifactsCacheDir()
            . '/'
            . $bootstrapConfig->appTarget()->value
            . '/'
            . $basename;

        if (\strlen($relativePath) > self::MAX_RELATIVE_PATH_BYTES) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_PATH_INVALID,
            );
        }

        return $relativePath;
    }

    /**
     * Resolves the normalized artifact cache directory path relative to
     * BootstrapConfig::skeletonRoot().
     *
     * Example:
     *
     *     var/cache/web
     *
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    public function relativeCacheDirectory(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $bootstrapConfig->artifactsCacheDir()
            . '/'
            . $bootstrapConfig->appTarget()->value;
    }

    /**
     * Resolves the absolute or caller-supplied-root-relative cache directory.
     *
     * @throws ArtifactPathInvalidException
     */
    public function cacheDirectory(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return self::joinPath(
            self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot()),
            $this->relativeCacheDirectory($bootstrapConfig),
        );
    }

    /**
     * @return list<non-empty-string>
     */
    public static function canonicalBasenames(): array
    {
        return [
            self::MODULE_MANIFEST_BASENAME,
            self::CONFIG_BASENAME,
            self::CONTAINER_BASENAME,
        ];
    }

    /**
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    private static function canonicalBasename(string $basename): string
    {
        if (!isset(self::CANONICAL_BASENAMES[$basename])) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_BASENAME_INVALID,
            );
        }

        return $basename;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeSkeletonRoot(string $skeletonRoot): string
    {
        $normalized = self::normalizeSeparators($skeletonRoot);
        $trimmed = \rtrim($normalized, '/');

        if ($trimmed === '') {
            return '/';
        }

        return $trimmed;
    }

    private static function normalizeSeparators(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    private static function joinPath(string $left, string $right): string
    {
        if ($left === '/') {
            return '/' . \ltrim($right, '/');
        }

        return \rtrim($left, '/') . '/' . \ltrim($right, '/');
    }
}
