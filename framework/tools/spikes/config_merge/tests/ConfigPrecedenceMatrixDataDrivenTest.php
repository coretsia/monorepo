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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigPrecedenceMatrixDataDrivenTest extends TestCase
{
    /**
     * @return iterable<string, array{inputs: array, expect: array}>
     */
    public static function provideScenarios(): iterable
    {
        $matrix = require __DIR__ . '/fixtures/scenarios.php';

        if (!is_array($matrix) || !isset($matrix['schema_version'], $matrix['precedence'], $matrix['scenarios'])) {
            throw new \LogicException('config-merge-fixture-matrix-invalid');
        }

        if ($matrix['precedence'] !== ConfigMerger::PRECEDENCE) {
            throw new \LogicException('config-merge-fixture-precedence-mismatch');
        }

        /** @var array<string, array{inputs: array, expect: array}> $scenarios */
        $scenarios = $matrix['scenarios'];

        $keys = array_keys($scenarios);
        usort($keys, static fn (string $a, string $b): int => strcmp($a, $b));

        foreach ($keys as $id) {
            $scenario = $scenarios[$id];

            if (!isset($scenario['inputs'], $scenario['expect']) || !is_array($scenario['inputs']) || !is_array($scenario['expect'])) {
                throw new \LogicException('config-merge-fixture-scenario-invalid: ' . $id);
            }

            yield $id => [
                'inputs' => $scenario['inputs'],
                'expect' => $scenario['expect'],
            ];
        }
    }

    #[DataProvider('provideScenarios')]
    public function testScenarioMatrix(array $inputs, array $expect): void
    {
        $merger = new ConfigMerger();

        if (isset($expect['error_code'])) {
            $expectedCode = (string)$expect['error_code'];

            try {
                $merger->mergeScenario($inputs);
                self::fail('Expected a deterministic error code: ' . $expectedCode);
            } catch (\RuntimeException $e) {
                self::assertSame($expectedCode, $e->getMessage());
            }

            return;
        }

        if (!isset($expect['merged']) || !is_array($expect['merged'])) {
            self::fail('Fixture scenario expects either merged or error_code.');
        }

        $merged = $merger->mergeScenario($inputs);

        self::assertSame($expect['merged'], $merged);
    }
}
