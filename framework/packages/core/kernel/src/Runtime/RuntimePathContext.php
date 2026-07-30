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

namespace Coretsia\Kernel\Runtime;

/**
 * Immutable runtime-only filesystem path context.
 *
 * This context may contain absolute runtime paths. It is supplied explicitly by
 * source-mode or artifact-mode boot orchestration and must never enter compiled
 * container descriptors, generated artifacts, or fingerprint input.
 *
 * The context validates path strings without reading the filesystem or resolving
 * symlinks. Returned paths use `/` separators deterministically.
 */
final readonly class RuntimePathContext
{
    private string $skeletonRoot;
    private string $artifactRoot;

    public function __construct(
        string $skeletonRoot,
        string $artifactRoot,
    ) {
        $this->skeletonRoot = self::normalizePath(
            path: $skeletonRoot,
            reason: 'runtime-path-context-skeleton-root-invalid',
        );
        $this->artifactRoot = self::normalizePath(
            path: $artifactRoot,
            reason: 'runtime-path-context-artifact-root-invalid',
        );
    }

    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    public function artifactRoot(): string
    {
        return $this->artifactRoot;
    }

    private static function normalizePath(
        string $path,
        string $reason,
    ): string {
        if ($path === '' || \trim($path) !== $path) {
            throw new \InvalidArgumentException($reason);
        }

        if (\preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException($reason);
        }

        $normalized = \str_replace('\\', '/', $path);

        if (\str_contains($normalized, '://')) {
            throw new \InvalidArgumentException($reason);
        }

        if ($normalized === '/') {
            return $normalized;
        }

        if (\preg_match('/\A[A-Za-z]:\/\z/', $normalized) === 1) {
            return $normalized;
        }

        $normalized = \rtrim($normalized, '/');

        if ($normalized === '') {
            throw new \InvalidArgumentException($reason);
        }

        return $normalized;
    }
}
