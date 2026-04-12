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
 *  - Same fixed inputs => same fingerprint deterministically (rerun-no-diff).
 */
final class RerunSameInputsSameHashTest extends TestCase
{
    /**
     * @var array<string,string>
     */
    private const array FIXED_ENV = [
        'CORETSIA_TRACKED_ENV_ALPHA' => 'alpha',
        'CORETSIA_TRACKED_ENV_BETA' => 'beta',
        'CORETSIA_TRACKED_ENV_GAMMA' => 'gamma',
    ];

    /**
     * @var array<string,string|false>
     */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (self::FIXED_ENV as $k => $_v) {
            $this->envBackup[$k] = \getenv($k);
        }

        foreach (self::FIXED_ENV as $k => $v) {
            \putenv($k . '=' . $v);
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

    /**
     * @throws \JsonException
     */
    public function test_rerun_same_inputs_same_fingerprint_and_bucket_digests(): void
    {
        $a = new FingerprintCalculator()->calculate(true);
        $b = new FingerprintCalculator()->calculate(true);
        $c = new FingerprintCalculator()->calculate(true);

        self::assertSame($a['fingerprint'], $b['fingerprint']);
        self::assertSame($b['fingerprint'], $c['fingerprint']);

        self::assertSame($a['bucket_digests'], $b['bucket_digests']);
        self::assertSame($b['bucket_digests'], $c['bucket_digests']);

        // Snapshots are optional but when requested must be deterministic too.
        self::assertSame($a['snapshots'], $b['snapshots']);
        self::assertSame($b['snapshots'], $c['snapshots']);
    }
}
