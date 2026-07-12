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

/**
 * Validates the Bootstrap Phase A artifact cache directory.
 *
 * @internal
 */
final class BootstrapArtifactsCacheDir
{
    /**
     * Artifact compiler, writer, and verifier accept diagnostic relative paths
     * up to 512 bytes.
     *
     * Reserving 32 bytes leaves deterministic space for:
     *
     *     /<appTarget>/<artifact-basename>
     */
    private const int MAX_BYTES = 480;

    /**
     * These directories contain application, bootstrap, dependency, public, or
     * repository-owned sources and must not become generated artifact roots.
     *
     * @var array<string, true>
     */
    private const array FORBIDDEN_TOP_LEVEL_SEGMENTS = [
        '.git' => true,
        '.github' => true,
        'apps' => true,
        'config' => true,
        'public' => true,
        'resources' => true,
        'skeleton' => true,
        'src' => true,
        'tests' => true,
        'vendor' => true,
    ];

    /**
     * Windows device names remain reserved even when an extension is present.
     *
     * @var array<string, true>
     */
    private const array WINDOWS_RESERVED_NAMES = [
        'AUX' => true,
        'COM1' => true,
        'COM2' => true,
        'COM3' => true,
        'COM4' => true,
        'COM5' => true,
        'COM6' => true,
        'COM7' => true,
        'COM8' => true,
        'COM9' => true,
        'CON' => true,
        'CONIN$' => true,
        'CONOUT$' => true,
        'LPT1' => true,
        'LPT2' => true,
        'LPT3' => true,
        'LPT4' => true,
        'LPT5' => true,
        'LPT6' => true,
        'LPT7' => true,
        'LPT8' => true,
        'LPT9' => true,
        'NUL' => true,
        'PRN' => true,
    ];

    private function __construct()
    {
    }

    public static function isValid(mixed $value): bool
    {
        if (
            !\is_string($value)
            || $value === ''
            || \strlen($value) > self::MAX_BYTES
        ) {
            return false;
        }

        /*
         * Reject invalid UTF-8 explicitly. Without this check, preg_match()
         * may return false and an invalid byte sequence could pass later
         * comparisons.
         */
        if (\preg_match('//u', $value) !== 1) {
            return false;
        }

        if (
            \trim($value) !== $value
            || \preg_match('/\s/u', $value) === 1
        ) {
            return false;
        }

        if (
            \str_contains($value, "\0")
            || \str_contains($value, "\r")
            || \str_contains($value, "\n")
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }

        if (
            \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1
        ) {
            return false;
        }

        if (
            \str_contains($value, ':')
            || \str_contains($value, '\\')
            || \str_contains($value, '//')
        ) {
            return false;
        }

        $segments = \explode('/', $value);

        $topLevelSegment = \strtolower($segments[0]);

        if (isset(self::FORBIDDEN_TOP_LEVEL_SEGMENTS[$topLevelSegment])) {
            return false;
        }

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || !self::isPortableSegment($segment)
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isPortableSegment(string $segment): bool
    {
        /*
         * These characters are invalid in Windows path components. Rejecting
         * them on every platform keeps config acceptance deterministic.
         */
        if (\preg_match('/[<>"|?*]/', $segment) === 1) {
            return false;
        }

        /*
         * Windows strips or rejects trailing dots, while POSIX filesystems
         * treat them as significant.
         */
        if (\str_ends_with($segment, '.')) {
            return false;
        }

        $extensionPosition = \strpos($segment, '.');

        $deviceName = \strtoupper(
            $extensionPosition === false
                ? $segment
                : \substr($segment, 0, $extensionPosition),
        );

        return !isset(self::WINDOWS_RESERVED_NAMES[$deviceName]);
    }
}
