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

use Coretsia\Kernel\Boot\BootstrapConfig;

/**
 * Resolves the Kernel-owned artifact root from Bootstrap Phase A state.
 *
 * ArtifactPathResolver owns only the root shared by immutable generations. All
 * paths below that root are owned by ArtifactGenerationPathResolver and
 * ArtifactGeneration.
 *
 * This resolver does not read or write files, select the current generation,
 * resolve individual runtime artifact files, calculate fingerprints, or include
 * path values in diagnostics.
 *
 * @internal
 */
final class ArtifactPathResolver
{
    /**
     * Resolves the normalized artifact root path relative to
     * BootstrapConfig::skeletonRoot().
     *
     * Example:
     *
     *     var/cache/web
     *
     * @return non-empty-string
     */
    public function relativeCacheDirectory(
        BootstrapConfig $bootstrapConfig,
    ): string {
        return $bootstrapConfig->artifactsCacheDir()
            . '/'
            . $bootstrapConfig->appTarget()->value;
    }

    /**
     * Resolves the final Kernel artifact root.
     *
     * @return non-empty-string
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
     * @return non-empty-string
     */
    private static function normalizeSkeletonRoot(
        string $skeletonRoot,
    ): string {
        $normalized = \str_replace('\\', '/', $skeletonRoot);
        $trimmed = \rtrim($normalized, '/');

        if ($trimmed === '') {
            return '/';
        }

        return $trimmed;
    }

    /**
     * @return non-empty-string
     */
    private static function joinPath(
        string $left,
        string $right,
    ): string {
        if ($left === '/') {
            return '/' . \ltrim($right, '/');
        }

        return \rtrim($left, '/') . '/' . \ltrim($right, '/');
    }
}
