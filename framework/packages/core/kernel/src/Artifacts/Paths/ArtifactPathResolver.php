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
 * Artifact paths are derived from:
 *
 * - BootstrapConfig::skeletonRoot();
 * - BootstrapConfig::appTarget()->value;
 * - kernel.artifacts.cache_dir;
 * - canonical artifact basename.
 *
 * This resolver performs semantic path policy checks that cannot be fully
 * represented by the declarative config rules DSL.
 *
 * Runtime behavior:
 *
 * - artifacts are resolved under `<skeletonRoot>/var/cache/<appTarget>/`;
 * - only Kernel-owned artifact basenames are accepted;
 * - `routes.php` is intentionally not accepted because `routes@1` is owned by
 *   platform/routing, not core/kernel;
 * - cache_dir must not be absolute;
 * - cache_dir must not contain path traversal;
 * - cache_dir must not be prefixed with `skeleton/`;
 * - diagnostics and exceptions never include configured path values or absolute
 *   resolved paths.
 *
 * @internal
 */
final class ArtifactPathResolver
{
    public const string MODULE_MANIFEST_BASENAME = 'module-manifest.php';
    public const string CONFIG_BASENAME = 'config.php';
    public const string CONTAINER_BASENAME = 'container.php';

    private const string KEY_ARTIFACTS = 'artifacts';
    private const string KEY_CACHE_DIR = 'cache_dir';

    private const string CANONICAL_CACHE_DIR = 'var/cache';

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
        array $kernelConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            kernelConfig: $kernelConfig,
            basename: self::MODULE_MANIFEST_BASENAME,
        );
    }

    public function configPath(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            kernelConfig: $kernelConfig,
            basename: self::CONFIG_BASENAME,
        );
    }

    public function containerPath(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
    ): string {
        return $this->resolve(
            bootstrapConfig: $bootstrapConfig,
            kernelConfig: $kernelConfig,
            basename: self::CONTAINER_BASENAME,
        );
    }

    /**
     * Resolves an absolute or caller-supplied-root-relative artifact path.
     *
     * The returned path uses `/` separators deterministically. On Windows, PHP
     * accepts `/` separators for normal filesystem operations.
     *
     * @param array<string, mixed> $kernelConfig Kernel config subtree.
     *
     * @throws ArtifactPathInvalidException
     */
    public function resolve(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        string $basename,
    ): string {
        $skeletonRoot = self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot());
        $relativePath = $this->relativePath(
            bootstrapConfig: $bootstrapConfig,
            kernelConfig: $kernelConfig,
            basename: $basename,
        );

        $path = self::joinPath($skeletonRoot, $relativePath);

        $expectedDirectoryPrefix = self::joinPath(
            $skeletonRoot,
            self::CANONICAL_CACHE_DIR . '/' . $bootstrapConfig->appTarget()->value,
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
     * @param array<string, mixed> $kernelConfig Kernel config subtree.
     *
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    public function relativePath(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        string $basename,
    ): string {
        $cacheDir = self::cacheDir($kernelConfig);
        $basename = self::canonicalBasename($basename);

        return $cacheDir . '/' . $bootstrapConfig->appTarget()->value . '/' . $basename;
    }

    /**
     * Resolves the normalized artifact cache directory path relative to
     * BootstrapConfig::skeletonRoot().
     *
     * Example:
     *
     *     var/cache/web
     *
     * @param array<string, mixed> $kernelConfig Kernel config subtree.
     *
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    public function relativeCacheDirectory(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
    ): string {
        return self::cacheDir($kernelConfig) . '/' . $bootstrapConfig->appTarget()->value;
    }

    /**
     * Resolves the absolute or caller-supplied-root-relative cache directory.
     *
     * @param array<string, mixed> $kernelConfig Kernel config subtree.
     *
     * @throws ArtifactPathInvalidException
     */
    public function cacheDirectory(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
    ): string {
        return self::joinPath(
            self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot()),
            $this->relativeCacheDirectory($bootstrapConfig, $kernelConfig),
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
     * @param array<string, mixed> $kernelConfig
     *
     * @return non-empty-string
     *
     * @throws ArtifactPathInvalidException
     */
    private static function cacheDir(array $kernelConfig): string
    {
        $artifactsConfig = $kernelConfig[self::KEY_ARTIFACTS] ?? null;

        if (!\is_array($artifactsConfig) || \array_is_list($artifactsConfig)) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_CACHE_DIR_INVALID,
            );
        }

        $cacheDir = $artifactsConfig[self::KEY_CACHE_DIR] ?? null;

        if (!\is_string($cacheDir)) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_CACHE_DIR_INVALID,
            );
        }

        $cacheDir = self::normalizeRelativePath(
            value: $cacheDir,
            invalidReason: ArtifactPathInvalidException::REASON_CACHE_DIR_INVALID,
            absoluteReason: ArtifactPathInvalidException::REASON_CACHE_DIR_ABSOLUTE,
            traversalReason: ArtifactPathInvalidException::REASON_CACHE_DIR_TRAVERSAL,
        );

        if (\str_starts_with($cacheDir . '/', 'skeleton/')) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_CACHE_DIR_SKELETON_PREFIXED,
            );
        }

        /*
         * Kernel-owned artifacts are materialized only under:
         *
         *     <skeletonRoot>/var/cache/<appTarget>/
         *
         * The config key exists so path policy remains explicit and validated,
         * but artifact output is intentionally constrained to the canonical
         * `var/cache` subtree.
         */
        if ($cacheDir !== self::CANONICAL_CACHE_DIR) {
            throw ArtifactPathInvalidException::withReason(
                ArtifactPathInvalidException::REASON_TARGET_OUTSIDE_CACHE_DIR,
            );
        }

        return $cacheDir;
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
     *
     * @throws ArtifactPathInvalidException
     */
    private static function normalizeRelativePath(
        string $value,
        string $invalidReason,
        string $absoluteReason,
        string $traversalReason,
    ): string {
        if ($value === '') {
            throw ArtifactPathInvalidException::withReason($invalidReason);
        }

        if (\trim($value) !== $value) {
            throw ArtifactPathInvalidException::withReason($invalidReason);
        }

        if (\str_contains($value, "\0") || \str_contains($value, "\r") || \str_contains($value, "\n")) {
            throw ArtifactPathInvalidException::withReason($invalidReason);
        }

        $normalized = self::normalizeSeparators($value);

        if ($normalized === '') {
            throw ArtifactPathInvalidException::withReason($invalidReason);
        }

        if (self::isAbsolutePath($normalized)) {
            throw ArtifactPathInvalidException::withReason($absoluteReason);
        }

        if (\str_contains($normalized, ':') || \str_contains($normalized, '://')) {
            throw ArtifactPathInvalidException::withReason($absoluteReason);
        }

        if (\str_contains($normalized, '//')) {
            throw ArtifactPathInvalidException::withReason($invalidReason);
        }

        foreach (\explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                throw ArtifactPathInvalidException::withReason($invalidReason);
            }

            if ($segment === '..') {
                throw ArtifactPathInvalidException::withReason($traversalReason);
            }
        }

        return $normalized;
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

    private static function isAbsolutePath(string $path): bool
    {
        return \str_starts_with($path, '/')
            || \str_starts_with($path, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function joinPath(string $left, string $right): string
    {
        if ($left === '/') {
            return '/' . \ltrim($right, '/');
        }

        return \rtrim($left, '/') . '/' . \ltrim($right, '/');
    }
}
