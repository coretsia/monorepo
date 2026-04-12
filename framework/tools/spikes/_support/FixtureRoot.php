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

namespace Coretsia\Tools\Spikes\_support;

final class FixtureRoot
{
    private const string INVALID_MESSAGE = 'fixture-path-invalid';

    /**
     * @var string|null
     */
    private static ?string $rootDir = null;

    private function __construct()
    {
    }

    public static function rootDir(): string
    {
        if (self::$rootDir !== null) {
            return self::$rootDir;
        }

        $dir = realpath(__DIR__ . '/../fixtures');
        if ($dir === false) {
            // Developer/environment error; MUST NOT leak absolute paths.
            throw new \RuntimeException('fixtures-root-missing');
        }

        self::$rootDir = $dir;

        return $dir;
    }

    /**
     * Returns an absolute canonical path under fixtures root for a fixtures-root-relative input.
     *
     * @throws DeterministicException
     */
    public static function path(string $relative): string
    {
        self::assertSafeRelative($relative);

        $normalized = str_replace('\\', '/', $relative);

        // Reject empty or invalid segments deterministically.
        $parts = explode('/', $normalized);
        if ($parts === []) {
            self::throwInvalid();
        }

        $outParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                self::throwInvalid();
            }

            $outParts[] = $part;
        }

        // Root is canonical absolute; relative is normalized and traversal-free.
        return self::rootDir() . '/' . implode('/', $outParts);
    }

    private static function assertSafeRelative(string $relative): void
    {
        if ($relative === '') {
            self::throwInvalid();
        }

        // Null bytes are never allowed in paths.
        if (str_contains($relative, "\0")) {
            self::throwInvalid();
        }

        // Absolute path rejections (minimum cemented set).
        if (str_starts_with($relative, '/')) {             // POSIX absolute
            self::throwInvalid();
        }

        if (str_starts_with($relative, '\\\\')) {          // Windows UNC
            self::throwInvalid();
        }

        if (str_starts_with($relative, '\\')) {            // Windows rooted
            self::throwInvalid();
        }

        // Windows drive-letter rooted: MUST reject both "C:/" and "C:\"
        if (preg_match('~(?i)\A[A-Z]:[\\\\/]~', $relative) === 1) {
            self::throwInvalid();
        }

        // Parent traversal rejection (segment-based, both separators).
        $probe = str_replace('\\', '/', $relative);
        foreach (explode('/', $probe) as $part) {
            if ($part === '..') {
                self::throwInvalid();
            }
        }
    }

    /**
     * @throws DeterministicException
     */
    private static function throwInvalid(): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID,
            self::INVALID_MESSAGE,
        );
    }
}
