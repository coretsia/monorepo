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
 * Immutable validated finalized artifact generation.
 *
 * The generation owns only normalized runtime filesystem paths. Those paths are
 * never included in exception messages, artifact payloads, fingerprints, or
 * generated artifact bytes.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactGeneration
{
    public const string MODULE_MANIFEST_BASENAME = 'module-manifest.php';
    public const string CONFIG_BASENAME = 'config.php';
    public const string CONTAINER_BASENAME = 'container.php';
    public const string GENERATION_MANIFEST_BASENAME = 'generation-manifest.php';

    private const int MAX_PATH_BYTES = 4096;

    private string $generationDirectory;
    private string $moduleManifestPath;
    private string $configPath;
    private string $containerPath;
    private string $generationManifestPath;

    public function __construct(
        private ArtifactGenerationId $generationId,
        string $generationDirectory,
    ) {
        $generationDirectory = self::normalizeGenerationDirectory(
            generationDirectory: $generationDirectory,
            generationId: $generationId,
        );

        $this->generationDirectory = $generationDirectory;
        $this->moduleManifestPath = self::childPath(
            $generationDirectory,
            self::MODULE_MANIFEST_BASENAME,
        );
        $this->configPath = self::childPath(
            $generationDirectory,
            self::CONFIG_BASENAME,
        );
        $this->containerPath = self::childPath(
            $generationDirectory,
            self::CONTAINER_BASENAME,
        );
        $this->generationManifestPath = self::childPath(
            $generationDirectory,
            self::GENERATION_MANIFEST_BASENAME,
        );
    }

    public function generationId(): ArtifactGenerationId
    {
        return $this->generationId;
    }

    public function generationDirectory(): string
    {
        return $this->generationDirectory;
    }

    public function moduleManifestPath(): string
    {
        return $this->moduleManifestPath;
    }

    public function configPath(): string
    {
        return $this->configPath;
    }

    public function containerPath(): string
    {
        return $this->containerPath;
    }

    public function generationManifestPath(): string
    {
        return $this->generationManifestPath;
    }

    private static function normalizeGenerationDirectory(
        string $generationDirectory,
        ArtifactGenerationId $generationId,
    ): string {
        $normalized = self::normalizePath(
            path: $generationDirectory,
            reason: 'artifact-generation-directory-invalid',
        );

        $lastSeparator = \strrpos($normalized, '/');

        if ($lastSeparator === false) {
            throw new \InvalidArgumentException('artifact-generation-directory-invalid');
        }

        $basename = \substr($normalized, $lastSeparator + 1);
        $parent = \substr($normalized, 0, $lastSeparator);

        if (
            $basename !== $generationId->value()
            || self::pathBasename($parent)
            !== ArtifactGenerationPathResolver::GENERATIONS_DIRECTORY
        ) {
            throw new \InvalidArgumentException('artifact-generation-directory-invalid');
        }

        return $normalized;
    }

    private static function childPath(
        string $generationDirectory,
        string $basename,
    ): string {
        $path = $generationDirectory . '/' . $basename;

        if (\strlen($path) > self::MAX_PATH_BYTES) {
            throw new \InvalidArgumentException('artifact-generation-path-invalid');
        }

        return $path;
    }

    private static function normalizePath(
        string $path,
        string $reason,
    ): string {
        if ($path === '' || \trim($path) !== $path) {
            throw new \InvalidArgumentException($reason);
        }

        if (
            \strlen($path) > self::MAX_PATH_BYTES
            || \preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException($reason);
        }

        $normalized = \str_replace('\\', '/', $path);

        /*
         * A UNC path must use exactly two leading separators and contain both
         * non-empty server and share components.
         */
        $isUncPath = \str_starts_with($normalized, '//');

        if ($isUncPath) {
            if (\str_starts_with($normalized, '///')) {
                throw new \InvalidArgumentException($reason);
            }

            $uncPath = \substr($normalized, 2);
            $uncSegments = \explode('/', $uncPath);

            if (
                \count($uncSegments) < 2
                || $uncSegments[0] === ''
                || $uncSegments[1] === ''
            ) {
                throw new \InvalidArgumentException($reason);
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
            throw new \InvalidArgumentException($reason);
        }

        if (
            \str_contains($normalized, ':')
            && \preg_match('/\A[A-Za-z]:\//', $normalized) !== 1
        ) {
            throw new \InvalidArgumentException($reason);
        }

        $normalized = \rtrim($normalized, '/');

        if ($normalized === '') {
            throw new \InvalidArgumentException($reason);
        }

        return $normalized;
    }

    private static function pathBasename(string $path): string
    {
        $separator = \strrpos($path, '/');

        return $separator === false
            ? $path
            : \substr($path, $separator + 1);
    }
}
