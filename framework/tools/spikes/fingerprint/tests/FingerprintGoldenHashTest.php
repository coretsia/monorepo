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

/**.
 *  - If you intentionally modify the fixtures or hashing scheme, you MUST update these constants.
 */
final class FingerprintGoldenHashTest extends TestCase
{
    private const string EXPECTED_FINGERPRINT = '86f0fcf13ce12c2eb896b25a0e8aa3904d9900b783b5ccd3da823526f5af1a37';

    private const string EXPECTED_BUCKET_CODE = '43f19692c1217d4b953d831b0d0bf6cf8e1dcd1dfab5a7211b3153bd3af8cb15';
    private const string EXPECTED_BUCKET_CONFIG = 'fb4589a848553874752819b4e831d60190f9d6714906c0ad0969b49ac86bb72d';
    private const string EXPECTED_BUCKET_DOTENV = '5b2c08dab13b610a84337ffcc7ec98e8d7865b4d7e80baefb8a8218b1c1a229b';
    private const string EXPECTED_BUCKET_SCHEMA_VERSIONS = '11336a09d6edb13b91a284e3096a9f9d46e16263910b5a81241e6ec23707f6ef';
    private const string EXPECTED_BUCKET_TRACKED_ENV = '56c682c412b1f12eb847e6d210424b11bf606d8d27d2a0314b274762bfb7ff65';

    /**
     * @var array<string,string|false>
     */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $keys = [
            'CORETSIA_TRACKED_ENV_ALPHA',
            'CORETSIA_TRACKED_ENV_BETA',
            'CORETSIA_TRACKED_ENV_GAMMA',
        ];

        foreach ($keys as $k) {
            $this->envBackup[$k] = \getenv($k);
        }

        \putenv('CORETSIA_TRACKED_ENV_ALPHA=alpha');
        \putenv('CORETSIA_TRACKED_ENV_BETA=beta');
        \putenv('CORETSIA_TRACKED_ENV_GAMMA=gamma');

        self::assertSame('alpha', \getenv('CORETSIA_TRACKED_ENV_ALPHA'));
        self::assertSame('beta', \getenv('CORETSIA_TRACKED_ENV_BETA'));
        self::assertSame('gamma', \getenv('CORETSIA_TRACKED_ENV_GAMMA'));
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === false) {
                $this->unsetEnv($k);
                continue;
            }

            $this->setEnv($k, (string)$v);
        }

        $this->envBackup = [];
    }

    /**
     * @throws \JsonException
     */
    public function test_golden_fingerprint_and_bucket_digests_match_expected_cross_os(): void
    {
        $out = new FingerprintCalculator()->calculate(true);

        self::assertSame(
            [
                'code' => self::EXPECTED_BUCKET_CODE,
                'config' => self::EXPECTED_BUCKET_CONFIG,
                'dotenv' => self::EXPECTED_BUCKET_DOTENV,
                'schema_versions' => self::EXPECTED_BUCKET_SCHEMA_VERSIONS,
                'tracked_env' => self::EXPECTED_BUCKET_TRACKED_ENV,
            ],
            $out['bucket_digests']
        );

        self::assertSame(self::EXPECTED_FINGERPRINT, $out['fingerprint']);

        // Sanity: snapshots are safe and stable (no values for dotenv/tracked_env).
        $snapshots = $out['snapshots'] ?? null;
        self::assertIsArray($snapshots);

        $code = $snapshots['code'] ?? null;
        self::assertIsArray($code);
        self::assertSame([], $code);

        $config = $snapshots['config'] ?? null;
        self::assertIsArray($config);

        $dotenv = $snapshots['dotenv'] ?? null;
        self::assertIsArray($dotenv);

        $tracked = $snapshots['tracked_env'] ?? null;
        self::assertIsArray($tracked);

        // Config snapshot must cover config/** and apps/*/config/**.
        self::assertSame(
            [
                'apps/web/config/app.php',
                'apps/web/config/http.php',
                'config/app.php',
                'config/http.php',
                'config/modules.php',
            ],
            array_keys($config)
        );

        // Dotenv snapshot meta only.
        self::assertSame(
            [
                '.env',
                '.env.local',
                '.env.local.local',
            ],
            array_keys($dotenv)
        );

        foreach ($dotenv as $path => $meta) {
            self::assertIsString($path);
            self::assertIsArray($meta);
            self::assertArrayHasKey('sha256', $meta);
            self::assertArrayHasKey('len', $meta);
            self::assertIsString($meta['sha256']);
            self::assertIsInt($meta['len']);
        }

        // tracked_env snapshot tokens only; values are not exposed.
        self::assertSame(
            [
                'CORETSIA_TRACKED_ENV_ALPHA',
                'CORETSIA_TRACKED_ENV_BETA',
                'CORETSIA_TRACKED_ENV_GAMMA',
            ],
            array_keys($tracked)
        );

        foreach ($tracked as $k => $token) {
            self::assertIsString($k);
            self::assertIsString($token);
            self::assertNotSame('', $token);
            self::assertStringNotContainsString('alpha', $token);
            self::assertStringNotContainsString('beta', $token);
            self::assertStringNotContainsString('gamma', $token);
        }
    }

    private function setEnv(string $key, string $value): void
    {
        \putenv($key . '=' . $value);
        self::assertSame($value, \getenv($key));
    }

    private function unsetEnv(string $key): void
    {
        \putenv($key);
        self::assertFalse(\getenv($key));
    }
}
