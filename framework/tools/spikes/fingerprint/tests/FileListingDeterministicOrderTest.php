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

namespace Coretsia\Tools\Spikes\fingerprint\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\FixtureRoot;
use Coretsia\Tools\Spikes\fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - expected_paths.txt MUST contain paths relative to this fixture root and MUST match the lister output exactly.
 *  - Any text fixtures used as inputs to hashing (expected_paths.txt) MUST be LF-only and end with a final newline.
 */
final class FileListingDeterministicOrderTest extends TestCase
{
    public function testExpectedPathsFixtureMatchesDeterministicListerOutputExactly(): void
    {
        $repoRoot = FixtureRoot::path('repo_min/skeleton');
        $repoRootReal = realpath($repoRoot);

        self::assertIsString($repoRootReal);
        self::assertNotSame('', $repoRootReal);
        self::assertTrue(is_dir($repoRootReal));

        $lister = new DeterministicFileLister($repoRootReal);
        $actual = $lister->listAllFiles();

        // MUST: deterministic lexicographic order (byte-order; strcmp), no locale dependence.
        $sortedActual = $actual;
        usort($sortedActual, static fn (string $a, string $b): int => strcmp($a, $b));
        self::assertSame($sortedActual, $actual);

        $expectedPath = FixtureRoot::path('repo_min/expected_paths.txt');

        // MUST: use spikes deterministic IO wrapper (spikes io policy gate).
        $raw = DeterministicFile::readBytesExact($expectedPath);

        self::assertNotSame('', $raw);

        // MUST: LF-only and end with a final newline.
        self::assertFalse(str_contains($raw, "\r"));
        self::assertTrue(str_ends_with($raw, "\n"));

        // Parse lines (fixture guarantees final newline; tolerate multiple trailing newlines).
        $trimmed = rtrim($raw, "\n");
        $expected = $trimmed === '' ? [] : explode("\n", $trimmed);

        self::assertNotSame([], $expected);

        // Validate expected paths are safe repo-relative paths (forward slashes only).
        foreach ($expected as $p) {
            self::assertIsString($p);
            self::assertNotSame('', $p);

            self::assertFalse(str_contains($p, "\0"));
            self::assertFalse(str_contains($p, "\\"));
            self::assertFalse(str_starts_with($p, '/'));
            self::assertFalse((bool)preg_match('~(?i)\A[A-Z]:[\\\\/]~', $p)); // "C:/" or "C:\"
            self::assertFalse(str_contains($p, '/../'));
            self::assertFalse(str_contains($p, '/..'));
            self::assertNotSame('..', $p);
            self::assertNotSame('.', $p);
            self::assertFalse(str_starts_with($p, './'));
        }

        // MUST: deterministic expected fixture order and uniqueness (byte-order; strcmp).
        $expectedSorted = $expected;
        usort($expectedSorted, static fn (string $a, string $b): int => strcmp($a, $b));
        self::assertSame($expectedSorted, $expected);

        $unique = array_values(array_unique($expected));
        self::assertSame($expected, $unique);

        // MUST: exact match with lister output (content + order).
        self::assertSame($expected, $actual);
    }
}
