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
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Boot\BootstrapConfig;

/**
 * Resolves the Kernel-owned artifact root and delegates immutable generation
 * paths below that root.
 *
 * Artifact-root ownership remains in this resolver. The root is derived
 * exclusively from:
 *
 * - BootstrapConfig::skeletonRoot();
 * - BootstrapConfig::appTarget()->value;
 * - BootstrapConfig::artifactsCacheDir().
 *
 * The current production compiler still resolves the legacy flat artifact
 * paths:
 *
 * - module-manifest.php;
 * - config.php;
 * - container.php.
 *
 * Immutable generation-specific paths are delegated to
 * ArtifactGenerationPathResolver:
 *
 * - generations/<generation-id>/;
 * - generation artifact paths;
 * - current;
 * - generation.lock;
 * - generations/.staging-<generation-id>-<random-suffix>.
 *
 * `current` and `generation.lock` are cache-control files, not artifacts.
 * `routes.php` is intentionally not accepted because routes@1 is owned by
 * platform/routing, not core/kernel.
 *
 * This resolver does not read or write files, select the current generation,
 * calculate fingerprints, or include path values in diagnostics.
 *
 * @internal
 */
final class ArtifactPathResolver
{
    public const string MODULE_MANIFEST_BASENAME = ArtifactGeneration::MODULE_MANIFEST_BASENAME;
    public const string CONFIG_BASENAME = ArtifactGeneration::CONFIG_BASENAME;
    public const string CONTAINER_BASENAME = ArtifactGeneration::CONTAINER_BASENAME;
    private const int MAX_RELATIVE_PATH_BYTES = 512;

    /**
     * @var array<string, true>
     */
    private const array CANONICAL_BASENAMES = [
        self::MODULE_MANIFEST_BASENAME => true,
        self::CONFIG_BASENAME => true,
        self::CONTAINER_BASENAME => true,
    ];

    public function __construct(
        private readonly ArtifactGenerationPathResolver $generationPathResolver = new ArtifactGenerationPathResolver(),
    ) {
    }

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
     * Resolves the final artifact root owned by core/kernel.
     *
     * Production writing still uses the current flat layout until the
     * generation publication pipeline is enabled.
     *
     * @throws ArtifactPathInvalidException
     */
    public function artifactRoot(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return self::joinPath(
            self::normalizeSkeletonRoot(
                $bootstrapConfig->skeletonRoot(),
            ),
            $this->relativeCacheDirectory($bootstrapConfig),
        );
    }

    /**
     * Backward-compatible artifact-root alias used by the current compiler,
     * verifier, and runtime path construction.
     *
     * @throws ArtifactPathInvalidException
     */
    public function cacheDirectory(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->artifactRoot($bootstrapConfig);
    }

    public function generation(
        BootstrapConfig $bootstrapConfig,
        ArtifactGenerationId $generationId,
    ): ArtifactGeneration {
        return $this->generationPathResolver->generation(
            artifactRoot: $this->artifactRoot($bootstrapConfig),
            generationId: $generationId,
        );
    }

    public function generationDirectory(
        BootstrapConfig $bootstrapConfig,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generationPathResolver
            ->generationDirectory(
                artifactRoot: $this->artifactRoot($bootstrapConfig),
                generationId: $generationId,
            );
    }

    public function currentGenerationPath(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->generationPathResolver->currentPath(
            $this->artifactRoot($bootstrapConfig),
        );
    }

    public function generationLockPath(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $this->generationPathResolver
            ->generationLockPath(
                $this->artifactRoot($bootstrapConfig),
            );
    }

    public function stagingGenerationDirectory(
        BootstrapConfig $bootstrapConfig,
        ArtifactGenerationId $generationId,
        string $randomSuffix,
    ): string {
        return $this->generationPathResolver
            ->stagingDirectory(
                artifactRoot: $this->artifactRoot($bootstrapConfig),
                generationId: $generationId,
                randomSuffix: $randomSuffix,
            );
    }

    public function newStagingGenerationDirectory(
        BootstrapConfig $bootstrapConfig,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generationPathResolver
            ->newStagingDirectory(
                artifactRoot: $this->artifactRoot($bootstrapConfig),
                generationId: $generationId,
            );
    }

    /**
     * Returns the canonical basenames supported by the current flat-layout
     * compiler and verifier.
     *
     * generation-manifest.php is generation-scoped and MUST NOT be resolved as a
     * flat artifact below the artifact root.
     *
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
