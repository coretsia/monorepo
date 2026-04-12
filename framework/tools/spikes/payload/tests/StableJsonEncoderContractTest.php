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

namespace Coretsia\Tools\Spikes\payload\tests;

use Coretsia\Tools\Spikes\_support\FixtureRoot;
use Coretsia\Tools\Spikes\payload\StableJsonEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.70.0 (MUST):
 * - StableJsonEncoder is a thin wrapper over InternalToolkit\Json::encodeStable(...)
 * - output MUST be stable for semantically identical payloads (key-order differences allowed)
 *
 * Security/Redaction:
 * - Compare hashes, not raw JSON.
 */
final class StableJsonEncoderContractTest extends TestCase
{
    public function test_encode_is_deterministic_for_same_payload(): void
    {
        $fixtures = self::fixtures();
        $payload = $fixtures['ok_large_nested_mixed'] ?? null;

        self::assertIsArray($payload);

        $a = StableJsonEncoder::encode($payload);
        $b = StableJsonEncoder::encode($payload);

        self::assertSame(self::sha256($a), self::sha256($b));
        self::assertNotSame('', $a);
        self::assertFalse(str_contains($a, "\r"));

        // Sanity: valid JSON.
        $decoded = json_decode($a, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
    }

    public function test_encode_is_stable_for_semantically_identical_payloads_with_different_key_order(): void
    {
        $fixtures = self::fixtures();
        $src = $fixtures['ok_large_nested_mixed'] ?? null;

        self::assertIsArray($src);

        // Build a semantically identical payload but with different top-level insertion order.
        $reordered = [
            'z' => $src['z'] ?? null,
            'tree' => $src['tree'] ?? null,
            'strings' => $src['strings'] ?? null,
            'matrix' => $src['matrix'] ?? null,
            'flags' => $src['flags'] ?? null,
            'empty' => $src['empty'] ?? null,
            'a' => $src['a'] ?? null,
        ];

        $a = StableJsonEncoder::encode($src);
        $b = StableJsonEncoder::encode($reordered);

        // MUST: stable bytes for the same semantic payload (stable encoder normalizes maps).
        self::assertSame(self::sha256($a), self::sha256($b));

        // Also validate decoded top-level key order is stable (keys only, no raw values).
        $decoded = json_decode($a, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertSame(['a', 'empty', 'flags', 'matrix', 'strings', 'tree', 'z'], array_keys($decoded));
    }

    /**
     * @return array<string, array>
     */
    private static function fixtures(): array
    {
        $path = FixtureRoot::path('payloads_min/payloads.php');

        $fixtures = require $path;

        self::assertIsArray($fixtures);

        /** @var array<string, array> $fixtures */
        return $fixtures;
    }

    private static function sha256(string $s): string
    {
        return hash('sha256', $s);
    }
}
