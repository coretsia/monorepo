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
use Coretsia\Tools\Spikes\payload\FloatPolicy;
use Coretsia\Tools\Spikes\payload\PayloadNormalizer;
use Coretsia\Tools\Spikes\payload\StableJsonEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.70.0 (MUST evidence):
 * - RerunNoDiffTest proves byte-identical output for same payload
 *
 * This test:
 * - runs the "pipeline" twice for each ok fixture and compares SHA256 of bytes
 * - ensures different fixtures produce different bytes (sanity)
 *
 * Security/Redaction:
 * - compare hashes + key-lists only (no raw payload or full JSON dumps on failure)
 */
final class RerunNoDiffTest extends TestCase
{
    public function test_pipeline_is_byte_identical_for_same_payload_across_reruns(): void
    {
        $fixtures = self::fixtures();

        $okHttp = $fixtures['ok_http_middleware_config'] ?? null;
        self::assertIsArray($okHttp);

        $okLarge = $fixtures['ok_large_nested_mixed'] ?? null;
        self::assertIsArray($okLarge);

        [$httpA, $httpHashA] = $this->runPipeline($okHttp);
        [$httpB, $httpHashB] = $this->runPipeline($okHttp);

        self::assertSame($httpHashA, $httpHashB);

        [$largeA, $largeHashA] = $this->runPipeline($okLarge);
        [$largeB, $largeHashB] = $this->runPipeline($okLarge);

        self::assertSame($largeHashA, $largeHashB);

        // Sanity: distinct inputs should not collapse to identical bytes.
        self::assertNotSame($httpHashA, $largeHashA);

        // Validate stable top-level key order for one representative output (keys only).
        $decoded = json_decode($largeA, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(['a', 'empty', 'flags', 'matrix', 'strings', 'tree', 'z'], array_keys($decoded));

        $decodedHttp = json_decode($httpA, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decodedHttp);
        self::assertSame(['meta', 'middleware', 'opt_in', 'schema_version'], array_keys($decodedHttp));
    }

    /**
     * Pipeline:
     * - FloatPolicy::enforce (reject early)
     * - PayloadNormalizer::normalize (deterministic)
     * - StableJsonEncoder::encode (stable bytes)
     *
     * @return array{0: string, 1: string} [json, sha256(json)]
     * @throws \JsonException
     */
    private function runPipeline(array $payload): array
    {
        // MUST: reject floats anywhere (no output; deterministic exception)
        FloatPolicy::enforce($payload);

        $normalized = PayloadNormalizer::normalize($payload);

        // Double-check: enforcing again on normalized payload stays safe (no-op for ok fixtures).
        FloatPolicy::enforce($normalized);

        $json = StableJsonEncoder::encode($normalized);

        self::assertNotSame('', $json);
        self::assertFalse(str_contains($json, "\r"));

        return [$json, hash('sha256', $json)];
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
}
