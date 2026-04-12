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

namespace Coretsia\Tools\Spikes\config_merge\tests;

use Coretsia\Tools\Spikes\config_merge\ConfigMerger;
use PHPUnit\Framework\TestCase;

final class RerunNoDiffTest extends TestCase
{
    public function testMergeScenarioRerunNoDiffAcrossWholeFixtureMatrix(): void
    {
        $matrix = require __DIR__ . '/fixtures/scenarios.php';

        if (!is_array($matrix) || !isset($matrix['scenarios']) || !is_array($matrix['scenarios'])) {
            self::fail('config-merge-fixture-matrix-invalid');
        }

        $merger = new ConfigMerger();

        /** @var array<string, array{inputs: array, expect: array}> $scenarios */
        $scenarios = $matrix['scenarios'];

        $ids = array_keys($scenarios);
        usort($ids, static fn(string $a, string $b): int => strcmp($a, $b));

        foreach ($ids as $id) {
            $scenario = $scenarios[$id];

            if (!isset($scenario['inputs'], $scenario['expect']) || !is_array($scenario['inputs']) || !is_array($scenario['expect'])) {
                self::fail('config-merge-fixture-scenario-invalid: ' . $id);
            }

            $inputs = $scenario['inputs'];
            $expect = $scenario['expect'];

            if (isset($expect['error_code'])) {
                $code = (string)$expect['error_code'];

                $m1 = null;
                $m2 = null;

                try {
                    $merger->mergeScenario($inputs);
                    self::fail('Expected deterministic error (run #1): ' . $code . ' for ' . $id);
                } catch (\RuntimeException $e) {
                    $m1 = $e->getMessage();
                }

                try {
                    $merger->mergeScenario($inputs);
                    self::fail('Expected deterministic error (run #2): ' . $code . ' for ' . $id);
                } catch (\RuntimeException $e) {
                    $m2 = $e->getMessage();
                }

                self::assertSame($code, $m1, 'error code mismatch (run #1) for ' . $id);
                self::assertSame($code, $m2, 'error code mismatch (run #2) for ' . $id);

                continue;
            }

            $out1 = $merger->mergeScenario($inputs);
            $out2 = $merger->mergeScenario($inputs);

            self::assertSame($out1, $out2, 'merged output differs on rerun for ' . $id);

            // Stronger "no diff": deterministic JSON bytes given deterministic key ordering.
            $j1 = json_encode($out1, JSON_THROW_ON_ERROR);
            $j2 = json_encode($out2, JSON_THROW_ON_ERROR);

            self::assertSame($j1, $j2, 'json bytes differ on rerun for ' . $id);
        }
    }

    public function testMergeSourcesIsOrderIndependentWithinSameSet(): void
    {
        $merger = new ConfigMerger();

        $sourcesA = [
            [
                'sourceType' => 'app',
                'file' => 'z.php',
                'config' => [
                    'k' => 'app',
                ],
            ],
            [
                'sourceType' => 'defaults',
                'file' => 'b.php',
                'config' => [
                    'k' => 'defaults',
                    'x' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
            ],
            [
                'sourceType' => 'module',
                'file' => 'a.php',
                'config' => [
                    'x' => [
                        '@merge' => [
                            'b' => 9,
                            'c' => 3,
                        ],
                    ],
                ],
            ],
        ];

        // Same entries, different input order (must not affect final merged output).
        $sourcesB = [
            $sourcesA[1],
            $sourcesA[2],
            $sourcesA[0],
        ];

        $outA = $merger->mergeSources($sourcesA);
        $outB = $merger->mergeSources($sourcesB);

        self::assertSame($outA, $outB);
    }
}
