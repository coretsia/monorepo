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

namespace Coretsia\Kernel\Artifacts\Generation;

/**
 * Resolves immutable artifact generation and cache-control paths below an
 * already-resolved artifact root.
 *
 * This resolver does not read or write the filesystem, select the current
 * generation, calculate fingerprints, or include staging randomness in
 * artifact payloads or bytes.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactGenerationPathResolver
{
    public const string GENERATIONS_DIRECTORY = 'generations';
    public const string CURRENT_BASENAME = 'current';
    public const string GENERATION_LOCK_BASENAME = 'generation.lock';

    private const string STAGING_PREFIX = '.staging-';
    private const int STAGING_RANDOM_BYTES = 16;
    private const int MAX_PATH_BYTES = 4096;

    private const string STAGING_SUFFIX_PATTERN = '/\A[a-f0-9]{32}\z/';

    public function generation(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): ArtifactGeneration {
        return new ArtifactGeneration(
            generationId: $generationId,
            generationDirectory: $this->generationDirectory(
                artifactRoot: $artifactRoot,
                generationId: $generationId,
            ),
        );
    }

    public function generationsDirectory(string $artifactRoot): string
    {
        return self::joinPath(
            self::normalizeArtifactRoot($artifactRoot),
            self::GENERATIONS_DIRECTORY,
        );
    }

    public function generationDirectory(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return self::joinPath(
            $this->generationsDirectory($artifactRoot),
            $generationId->value(),
        );
    }

    public function moduleManifestPath(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        )->moduleManifestPath();
    }

    public function configPath(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        )->configPath();
    }

    public function containerPath(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        )->containerPath();
    }

    public function generationManifestPath(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        )->generationManifestPath();
    }

    public function currentPath(string $artifactRoot): string
    {
        return self::joinPath(
            self::normalizeArtifactRoot($artifactRoot),
            self::CURRENT_BASENAME,
        );
    }

    public function generationLockPath(string $artifactRoot): string
    {
        return self::joinPath(
            self::normalizeArtifactRoot($artifactRoot),
            self::GENERATION_LOCK_BASENAME,
        );
    }

    public function stagingDirectory(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
        string $randomSuffix,
    ): string {
        if (
            \preg_match(
                self::STAGING_SUFFIX_PATTERN,
                $randomSuffix,
            ) !== 1
        ) {
            throw new \InvalidArgumentException('artifact-generation-staging-suffix-invalid');
        }

        return self::joinPath(
            $this->generationsDirectory($artifactRoot),
            self::STAGING_PREFIX
            . $generationId->value()
            . '-'
            . $randomSuffix,
        );
    }

    public function newStagingDirectory(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        try {
            $randomSuffix = \bin2hex(
                \random_bytes(self::STAGING_RANDOM_BYTES),
            );
        } catch (\Throwable) {
            throw new \RuntimeException('artifact-generation-staging-suffix-generation-failed');
        }

        return $this->stagingDirectory(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
            randomSuffix: $randomSuffix,
        );
    }

    private static function normalizeArtifactRoot(
        string $artifactRoot,
    ): string {
        if (
            $artifactRoot === ''
            || \trim($artifactRoot) !== $artifactRoot
        ) {
            throw new \InvalidArgumentException('artifact-generation-root-invalid');
        }

        if (
            \strlen($artifactRoot) > self::MAX_PATH_BYTES
            || \preg_match(
                '/[\x00-\x1F\x7F]/',
                $artifactRoot,
            ) === 1
        ) {
            throw new \InvalidArgumentException('artifact-generation-root-invalid');
        }

        $normalized = \str_replace('\\', '/', $artifactRoot);

        /*
         * A UNC path must use exactly two leading separators and contain both
         * non-empty server and share components.
         */
        $isUncPath = \str_starts_with($normalized, '//');

        if ($isUncPath) {
            if (\str_starts_with($normalized, '///')) {
                throw new \InvalidArgumentException('artifact-generation-root-invalid');
            }

            $uncPath = \substr($normalized, 2);
            $uncSegments = \explode('/', $uncPath);

            if (
                \count($uncSegments) < 2
                || $uncSegments[0] === ''
                || $uncSegments[1] === ''
            ) {
                throw new \InvalidArgumentException('artifact-generation-root-invalid');
            }

            $separatorCheck = $uncPath;
        } else {
            $separatorCheck = $normalized;
        }

        if (
            \str_contains($normalized, '://')
            || \str_contains($separatorCheck, '//')
            || $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        ) {
            throw new \InvalidArgumentException('artifact-generation-root-invalid');
        }

        if (
            \str_contains($normalized, ':')
            && \preg_match('/\A[A-Za-z]:\//', $normalized) !== 1
        ) {
            throw new \InvalidArgumentException('artifact-generation-root-invalid');
        }

        if ($normalized === '/') {
            return $normalized;
        }

        if (
            \preg_match(
                '/\A[A-Za-z]:\/\z/',
                $normalized,
            ) === 1
        ) {
            return $normalized;
        }

        $normalized = \rtrim($normalized, '/');

        if ($normalized === '') {
            throw new \InvalidArgumentException('artifact-generation-root-invalid');
        }

        return $normalized;
    }

    private static function joinPath(
        string $left,
        string $right,
    ): string {
        $path = $left === '/'
            ? '/' . \ltrim($right, '/')
            : \rtrim($left, '/') . '/' . \ltrim($right, '/');

        if (\strlen($path) > self::MAX_PATH_BYTES) {
            throw new \InvalidArgumentException('artifact-generation-path-invalid');
        }

        return $path;
    }
}
