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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\_support\FixtureRoot;
use Coretsia\Tools\Spikes\payload\FloatPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.70.0 (MUST):
 * - MUST reject any float at any nesting depth
 * - MUST reject NaN / INF / -INF explicitly
 * - MUST throw DeterministicException with code CORETSIA_JSON_FLOAT_FORBIDDEN
 * - exception message MAY include only path-to-value; MUST NOT include value itself
 */
final class FloatPolicyTest extends TestCase
{
    public function test_ok_payloads_do_not_throw(): void
    {
        $fixtures = self::fixtures();

        $ok1 = $fixtures['ok_http_middleware_config'] ?? null;
        self::assertIsArray($ok1);
        FloatPolicy::enforce($ok1);
        self::assertTrue(true); // no exception

        $ok2 = $fixtures['ok_large_nested_mixed'] ?? null;
        self::assertIsArray($ok2);
        FloatPolicy::enforce($ok2);
        self::assertTrue(true); // no exception
    }

    public function test_forbidden_nested_float_rejected_with_path_only(): void
    {
        $fixtures = self::fixtures();
        $bad = $fixtures['forbidden_float_nested'] ?? null;

        self::assertIsArray($bad);

        try {
            FloatPolicy::enforce($bad);
            self::fail('expected DeterministicException');
        } catch (DeterministicException $e) {
            self::assertSame(ErrorCodes::CORETSIA_JSON_FLOAT_FORBIDDEN, $e->code());
            self::assertSame('a.b[3].c', $e->getMessage());
            self::assertFalse(str_contains($e->getMessage(), '='));
            self::assertFalse(str_contains($e->getMessage(), '\\'));
            self::assertFalse(str_contains($e->getMessage(), "\n"));
        }
    }

    public function test_forbidden_nan_rejected(): void
    {
        $this->assertForbiddenFixture('forbidden_float_nan', 'meta.value');
    }

    public function test_forbidden_inf_rejected(): void
    {
        $this->assertForbiddenFixture('forbidden_float_inf', 'meta.value');
    }

    public function test_forbidden_negative_inf_rejected(): void
    {
        $this->assertForbiddenFixture('forbidden_float_ninf', 'meta.value');
    }

    private function assertForbiddenFixture(string $fixtureKey, string $expectedPath): void
    {
        $fixtures = self::fixtures();
        $bad = $fixtures[$fixtureKey] ?? null;

        self::assertIsArray($bad);

        try {
            FloatPolicy::enforce($bad);
            self::fail('expected DeterministicException');
        } catch (DeterministicException $e) {
            self::assertSame(ErrorCodes::CORETSIA_JSON_FLOAT_FORBIDDEN, $e->code());
            self::assertSame($expectedPath, $e->getMessage());

            // Ensure the message is just a path carrier (no value).
            self::assertFalse(str_contains($e->getMessage(), '='));
            self::assertFalse(str_contains($e->getMessage(), '\\'));
            self::assertFalse(str_contains($e->getMessage(), "\n"));
            self::assertFalse(str_contains($e->getMessage(), "\r"));
            self::assertNotSame('', $e->getMessage());
        }
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
