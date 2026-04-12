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

use Coretsia\Tools\Spikes\fingerprint\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - tracked_env missing vs empty MUST be distinguishable and deterministic.
 *  - if getenv($key) === false => missing
 *  - if getenv($key) === ''    => present-empty
 *
 * This test:
 *  - sets an allowlisted key to missing and to empty string in two runs and asserts:
 *      - hashes differ (missing != empty)
 *      - each scenario is deterministic across reruns/OS
 */
final class TrackedEnvBucketMissingVsEmptyIsDeterministicTest extends TestCase
{
    private const string KEY = 'CORETSIA_TRACKED_ENV_ALPHA';

    /**
     * Keep other allowlisted keys fixed, so only KEY affects the diff.
     *
     * @var array<string,string>
     */
    private const array OTHER_FIXED_ENV = [
        'CORETSIA_TRACKED_ENV_BETA' => 'beta',
        'CORETSIA_TRACKED_ENV_GAMMA' => 'gamma',
    ];

    /**
     * @var array<string,string|false>
     */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $keys = array_merge([self::KEY], array_keys(self::OTHER_FIXED_ENV));

        foreach ($keys as $k) {
            $this->envBackup[$k] = \getenv($k);
        }

        foreach (self::OTHER_FIXED_ENV as $k => $v) {
            \putenv($k . '=' . $v);
            self::assertSame($v, \getenv($k));
        }
    }

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

    public function test_missing_vs_empty_are_different_and_each_is_deterministic(): void
    {
        $missing = $this->runMissingScenario();
        $empty = $this->runEmptyScenario();

        self::assertNotSame($missing, $empty, 'missing and empty MUST yield different fingerprints');
    }

    private function runMissingScenario(): string
    {
        // Missing: getenv(KEY) === false
        \putenv(self::KEY);
        self::assertFalse(\getenv(self::KEY));

        $a = new FingerprintCalculator()->calculate(false)['fingerprint'] ?? null;
        $b = new FingerprintCalculator()->calculate(false)['fingerprint'] ?? null;

        self::assertIsString($a);
        self::assertIsString($b);
        self::assertSame($a, $b, 'missing scenario MUST be deterministic across reruns');

        return $a;
    }

    private function runEmptyScenario(): string
    {
        // Present-empty: getenv(KEY) === ''
        \putenv(self::KEY . '=');
        self::assertSame('', \getenv(self::KEY));

        $a = new FingerprintCalculator()->calculate(false)['fingerprint'] ?? null;
        $b = new FingerprintCalculator()->calculate(false)['fingerprint'] ?? null;

        self::assertIsString($a);
        self::assertIsString($b);
        self::assertSame($a, $b, 'empty scenario MUST be deterministic across reruns');

        return $a;
    }
}
