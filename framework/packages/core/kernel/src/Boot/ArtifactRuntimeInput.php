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

namespace Coretsia\Kernel\Boot;

use Coretsia\Kernel\Runtime\RuntimePathContext;

/**
 * Immutable entrypoint-owned input for artifact-only runtime boot.
 *
 * This input carries the skeleton root and the Kernel artifact root containing
 * `current`, `generation.lock`, and `generations/`. It does not carry individual
 * artifact paths and therefore cannot select a mixed runtime artifact set.
 *
 * It is not an artifact payload, a compiled-container definition, or fingerprint
 * input.
 *
 * The constructor validates and normalizes paths without reading the filesystem
 * or resolving symlinks.
 */
final readonly class ArtifactRuntimeInput
{
    private string $skeletonRoot;
    private string $artifactRoot;

    public function __construct(
        string $skeletonRoot,
        string $artifactRoot,
    ) {
        $paths = new RuntimePathContext(
            skeletonRoot: $skeletonRoot,
            artifactRoot: $artifactRoot,
        );

        $this->skeletonRoot = $paths->skeletonRoot();
        $this->artifactRoot = $paths->artifactRoot();
    }

    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    public function artifactRoot(): string
    {
        return $this->artifactRoot;
    }
}
