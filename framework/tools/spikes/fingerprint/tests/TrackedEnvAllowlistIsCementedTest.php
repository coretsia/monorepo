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
use Coretsia\Tools\Spikes\fingerprint\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - tracked_env allowlist is a single source of truth: fixtures/repo_min/tracked_env_allowlist.php
 *  - removing/bypassing that file MUST break tests.
 *
 * This test ensures the calculator actually reads that file (not a hardcoded list).
 */
final class TrackedEnvAllowlistIsCementedTest extends TestCase
{
    /**
     * @var array<string,string|false>
     */
    private array $envBackup = [];

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === false) {
                \putenv($k);
                continue;
            }
            \putenv($k . '=' . (string)$v);
        }

        $this->envBackup = [];
    }

    public function test_calculator_uses_allowlist_file_as_single_source_of_truth(): void
    {
        $allowlistPath = FixtureRoot::path('repo_min/tracked_env_allowlist.php');
        $original = DeterministicFile::readBytesExact($allowlistPath);

        // Patch: insert a new allowlisted key "CORETSIA_TRACKED_ENV_DELTA" in sorted order.
        $patched = $this->buildPatchedAllowlistFile($original);

        try {
            DeterministicFile::writeTextLf($allowlistPath, $patched);
            $this->invalidatePhpCache($allowlistPath);

            $this->backupAndSetEnv('CORETSIA_TRACKED_ENV_ALPHA', 'alpha');
            $this->backupAndSetEnv('CORETSIA_TRACKED_ENV_BETA', 'beta');
            $this->backupAndSetEnv('CORETSIA_TRACKED_ENV_DELTA', 'delta');
            $this->backupAndSetEnv('CORETSIA_TRACKED_ENV_GAMMA', 'gamma');

            $calc = new FingerprintCalculator();
            $out = $calc->calculate(true);

            $snapshots = $out['snapshots'] ?? null;
            self::assertIsArray($snapshots);

            $tracked = $snapshots['tracked_env'] ?? null;
            self::assertIsArray($tracked);

            // MUST include the patched key (proof that file is used).
            self::assertArrayHasKey('CORETSIA_TRACKED_ENV_DELTA', $tracked);
        } finally {
            // Restore original file no matter what.
            DeterministicFile::writeBytesExact($allowlistPath, $original);
            $this->invalidatePhpCache($allowlistPath);
        }
    }

    private function buildPatchedAllowlistFile(string $original): string
    {
        // Keep it simple and deterministic: replace only the return list block.
        // The fixture format is cemented; this manipulation is for testing only.
        $needle = "return [";
        $pos = strpos($original, $needle);
        if ($pos === false) {
            self::fail('allowlist-fixture-format-unexpected');
        }

        // Replace the list with a patched list in strict strcmp order.
        // (ALPHA < BETA < DELTA < GAMMA).
        $patchedList = "return [\n"
            . "    'CORETSIA_TRACKED_ENV_ALPHA',\n"
            . "    'CORETSIA_TRACKED_ENV_BETA',\n"
            . "    'CORETSIA_TRACKED_ENV_DELTA',\n"
            . "    'CORETSIA_TRACKED_ENV_GAMMA',\n"
            . "];\n";

        // Replace from "return [" up to the closing "];" following it.
        $end = strpos($original, "];", $pos);
        if ($end === false) {
            self::fail('allowlist-fixture-format-unexpected');
        }

        $before = substr($original, 0, $pos);
        $after = substr($original, $end + 2); // keep trailing content after the list

        return $before . $patchedList . $after;
    }

    private function backupAndSetEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->envBackup)) {
            $this->envBackup[$key] = \getenv($key);
        }

        \putenv($key . '=' . $value);
        self::assertSame($value, \getenv($key));
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
