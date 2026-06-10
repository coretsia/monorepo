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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

final class SpikeFingerprintPathNormalizationCrossOsLockTest extends TestCase
{
    public function testRepoMinSkeletonPathsUseForwardSlashesAndStableSortOrder(): void
    {
        $paths = new DeterministicFileLister()->listFiles(self::repoMinSkeletonRoot());

        self::assertSame(self::expectedRepoMinPaths(), $paths);

        foreach ($paths as $path) {
            self::assertStringNotContainsString('\\', $path);
            self::assertFalse(
                \str_starts_with($path, '/'),
                'Fingerprint relative paths must not start with a slash.',
            );
            self::assertStringNotContainsString('//', $path);
        }
    }

    public function testBackslashDeclaredFileCandidateNormalizesToBasename(): void
    {
        $declaredFile = self::repoMinSkeletonRoot() . '\\config\\app.php';

        self::assertSame(
            [
                'app.php',
            ],
            new DeterministicFileLister()->listFileCandidate($declaredFile),
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private static function expectedRepoMinPaths(): array
    {
        $path = self::repoRoot() . '/framework/tools/spikes/fixtures/repo_min/expected_paths.txt';

        self::assertFileExists($path);

        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);

        $paths = [];

        foreach (\preg_split('/\r?\n/', \trim($bytes)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $paths[] = $line;
        }

        return $paths;
    }

    private static function repoMinSkeletonRoot(): string
    {
        return self::repoRoot() . '/framework/tools/spikes/fixtures/repo_min/skeleton';
    }

    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 6);
    }
}
