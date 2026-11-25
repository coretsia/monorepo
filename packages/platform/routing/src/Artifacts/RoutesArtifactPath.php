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

namespace Coretsia\Platform\Routing\Artifacts;

use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactWriteFailedException;

/**
 * Path policy for the Routing-owned artifact and owner-local publication lock.
 *
 * This class never creates, reads or writes artifacts and does not inspect
 * Kernel generations. Callers must revalidate immediately before filesystem
 * operations, including after directory creation and before opening handles.
 *
 * @internal
 */
final readonly class RoutesArtifactPath
{
    private string $root;

    public function __construct(RuntimePathContext $runtimePaths)
    {
        $this->root = self::validatedRoot($runtimePaths->artifactRoot());
    }

    public function artifactPath(): string
    {
        $this->assertSafeTargets();
        return $this->root . '/routes.php';
    }

    public function lockPath(): string
    {
        $this->assertSafeTargets();
        return $this->root . '/routes.lock';
    }

    /**
     * Fail closed on symbolic links, unsafe directories and non-regular targets.
     * Repeated validation is required after any external filesystem mutation.
     */
    public function assertSafeTargets(): void
    {
        $this->assertExistingComponents();
        self::assertRegularOrAbsent($this->root . '/routes.php');
        self::assertRegularOrAbsent($this->root . '/routes.lock');
    }

    public function readableArtifactPath(): string
    {
        try {
            $this->assertSafeTargets();
        } catch (RoutesArtifactWriteFailedException) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_UNSAFE,
            );
        }
        return $this->root . '/routes.php';
    }

    public function assertReadableArtifact(): void
    {
        $target = $this->readableArtifactPath();
        $stat = @\lstat($target);
        if ($stat === false) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_MISSING,
            );
        }
        if (($stat['mode'] & 0170000) !== 0100000) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_UNSAFE,
            );
        }
        if (!\is_readable($target)) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_UNREADABLE,
            );
        }
    }

    private static function validatedRoot(string $path): string
    {
        if (
            $path === ''
            || \str_contains($path, '://')
            || \preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
        $path = \str_replace('\\', '/', $path);
        $unix = \str_starts_with($path, '/') && !\str_starts_with($path, '//');
        $drive = \preg_match('~\A[A-Za-z]:/~D', $path) === 1;
        $unc = \preg_match('~\A//[^/]+/[^/]+(?:/.*)?\z~D', $path) === 1;
        if (
            (!$unix && !$drive && !$unc)
            || (\DIRECTORY_SEPARATOR === '/' && ($drive || $unc))
            || (\DIRECTORY_SEPARATOR === '\\' && $unix)
        ) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
        if ($path === '/' || \preg_match('~\A[A-Za-z]:/\z~D', $path) === 1) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
        if (
            \str_contains($path, '//') && !$unc
            || \str_contains($path, '/./')
            || \str_contains($path, '/../')
            || \str_ends_with($path, '/.')
            || \str_ends_with($path, '/..')
        ) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
        $root = \rtrim($path, '/');
        $parts = \explode('/', $unc ? \substr($root, 2) : ($drive ? \substr($root, 3) : \substr($root, 1)));
        foreach ($parts as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || \preg_match('/[<>:"|?*]/', $segment) === 1
                || \str_ends_with($segment, '.')
                || \str_ends_with($segment, ' ')
            ) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
                );
            }
        }
        if ($unc && \count($parts) < 3) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
        return $root;
    }

    private function assertExistingComponents(): void
    {
        $path = $this->root;
        if (\preg_match('~\A[A-Za-z]:/~D', $path) === 1) {
            $prefix = \substr($path, 0, 3);
            $parts = \explode('/', \substr($path, 3));
        } elseif (\str_starts_with($path, '//')) {
            $parts = \explode('/', \substr($path, 2));
            $prefix = '//' . \array_shift($parts) . '/' . \array_shift($parts);

            $stat = @\lstat($prefix);
            if ($stat === false || ($stat['mode'] & 0170000) !== 0040000) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
                );
            }
        } else {
            $prefix = '';
            $parts = \explode('/', \substr($path, 1));
        }

        foreach ($parts as $part) {
            $prefix .= '/' . $part;
            $stat = @\lstat($prefix);
            if ($stat === false) {
                // The first missing directory is permitted. Publication creates
                // directories and must then revalidate before opening a target.
                return;
            }
            if (($stat['mode'] & 0170000) !== 0040000) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
                );
            }
        }
    }

    private static function assertRegularOrAbsent(string $path): void
    {
        $stat = @\lstat($path);
        if ($stat !== false && ($stat['mode'] & 0170000) !== 0100000) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }
    }
}
