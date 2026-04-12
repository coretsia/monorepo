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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\_support\FixtureRoot;
use Coretsia\Tools\Spikes\fingerprint\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - tracked_env allowlist MUST be unique; duplicates are forbidden and MUST fail deterministically.
 */
final class TrackedEnvAllowlistHasNoDuplicatesTest extends TestCase
{
    public function test_duplicate_allowlist_keys_fail_deterministically(): void
    {
        $allowlistPath = FixtureRoot::path('repo_min/tracked_env_allowlist.php');
        $original = DeterministicFile::readBytesExact($allowlistPath);

        $patched = $this->buildDuplicateAllowlistFile($original);

        try {
            DeterministicFile::writeTextLf($allowlistPath, $patched);
            $this->invalidatePhpCache($allowlistPath);

            try {
                new FingerprintCalculator()->calculate(false);
                self::fail('expected-deterministic-exception');
            } catch (DeterministicException $e) {
                self::assertSame(
                    ErrorCodes::CORETSIA_FINGERPRINT_TRACKED_ENV_ALLOWLIST_DUPLICATE,
                    $e->code(),
                );

                self::assertSame(
                    'fingerprint-tracked-env-allowlist-duplicate',
                    $e->getMessage(),
                );
            }
        } finally {
            DeterministicFile::writeBytesExact($allowlistPath, $original);
            $this->invalidatePhpCache($allowlistPath);
        }
    }

    private function buildDuplicateAllowlistFile(string $original): string
    {
        $needle = "return [";
        $pos = strpos($original, $needle);
        if ($pos === false) {
            self::fail('allowlist-fixture-format-unexpected');
        }

        $patchedList = "return [\n"
            . "    'CORETSIA_TRACKED_ENV_ALPHA',\n"
            . "    'CORETSIA_TRACKED_ENV_ALPHA',\n"
            . "    'CORETSIA_TRACKED_ENV_BETA',\n"
            . "];\n";

        $end = strpos($original, "];", $pos);
        if ($end === false) {
            self::fail('allowlist-fixture-format-unexpected');
        }

        $before = substr($original, 0, $pos);
        $after = substr($original, $end + 2);

        return $before . $patchedList . $after;
    }

    private function invalidatePhpCache(string $path): void
    {
        \clearstatcache(true, $path);

        if (\function_exists('opcache_invalidate')) {
            /** @psalm-suppress UndefinedFunction */
            @\opcache_invalidate($path, true);
        }
    }
}
