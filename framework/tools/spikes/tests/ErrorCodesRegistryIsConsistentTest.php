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

namespace Coretsia\Tools\Spikes\tests;

use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\TestCase;

final class ErrorCodesRegistryIsConsistentTest extends TestCase
{
    private const string CODE_PATTERN = '/\A[A-Z0-9_]+\z/';

    public function testRegistryIsConsistentWithPublicConstants(): void
    {
        $ref = new \ReflectionClass(ErrorCodes::class);

        /** @var array<string, string> $codesByName */
        $codesByName = [];

        foreach ($ref->getReflectionConstants() as $const) {
            if (!$const->isPublic()) {
                continue;
            }

            $name = $const->getName();
            $value = $const->getValue();

            // Only public string codes are part of the registry contract.
            if (!is_string($value)) {
                continue;
            }

            // Cemented convention: only CORETSIA_* are registry codes.
            if (!str_starts_with($name, 'CORETSIA_')) {
                continue;
            }

            $codesByName[$name] = $value;
        }

        $values = array_values($codesByName);

        // All codes must be UPPER_SNAKE (A–Z, 0–9, _).
        foreach ($values as $code) {
            self::assertMatchesRegularExpression(
                self::CODE_PATTERN,
                $code,
                'Error code must be UPPER_SNAKE (A–Z, 0–9, _).',
            );
        }

        // Ensure public constants do not contain duplicate values.
        $unique = array_values(array_unique($values));
        self::assertCount(
            count($values),
            $unique,
            'ErrorCodes public constants contain duplicate string values.',
        );

        // Expected registry is the sorted unique set of public CORETSIA_* const values.
        $expected = $unique;
        usort($expected, static fn (string $a, string $b): int => strcmp($a, $b));

        $actual = ErrorCodes::all();

        // MUST assert ErrorCodes::all() is a list<string>.
        self::assertTrue(
            array_is_list($actual),
            'ErrorCodes::all() MUST return list<string> (array_is_list === true).',
        );

        // MUST assert ErrorCodes::all() contains no duplicates.
        self::assertSame(
            array_values(array_unique($actual)),
            $actual,
            'ErrorCodes::all() MUST contain no duplicates.',
        );

        // MUST assert ErrorCodes::all() is sorted by byte-order (strcmp).
        $sorted = $actual;
        usort($sorted, static fn (string $a, string $b): int => strcmp($a, $b));
        self::assertSame(
            $sorted,
            $actual,
            'ErrorCodes::all() MUST be sorted ascending by byte-order (strcmp).',
        );

        // Strongest invariant: registry must match public CORETSIA_* string constants exactly (strcmp-sorted).
        self::assertSame(
            $expected,
            $actual,
            'ErrorCodes registry mismatch: REGISTRY must match public CORETSIA_* string constants (strcmp-sorted).',
        );

        foreach ($expected as $code) {
            self::assertTrue(
                ErrorCodes::has($code),
                'ErrorCodes::has() must return true for every registry code.',
            );
        }
    }

    public function testDeterminismRunnerCodesExistInRegistry(): void
    {
        // MUST assert that DeterminismRunner codes exist in registry (hard fail if removed).
        $mustExist = [
            ErrorCodes::CORETSIA_DETERMINISM_GIT_REQUIRED,
            ErrorCodes::CORETSIA_DETERMINISM_WORKTREE_DIRTY,
            ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
        ];

        $all = ErrorCodes::all();

        foreach ($mustExist as $code) {
            self::assertTrue(
                ErrorCodes::has($code),
                'DeterminismRunner required code is missing from ErrorCodes registry: ' . $code,
            );

            self::assertContains(
                $code,
                $all,
                'DeterminismRunner required code MUST be present in ErrorCodes::all(): ' . $code,
            );
        }
    }

    public function testCodesReferencedByRunnerAndGatesExistInRegistry(): void
    {
        // (optional but recommended) Codes used by DeterminismRunner/Gates should exist in registry.
        // Best-effort scan: only enforce prefixes owned by Phase 0 rails.
        $toolsRoot = realpath(__DIR__ . '/../..'); // framework/tools
        if (!is_string($toolsRoot) || $toolsRoot === '') {
            self::markTestSkipped('tools-root-unresolvable');
        }

        $candidates = [
            $toolsRoot . '/spikes/_support/DeterminismRunner.php',
            $toolsRoot . '/spikes/_support/bootstrap.php',
            $toolsRoot . '/gates/spikes_output_gate.php',
            $toolsRoot . '/gates/repo_text_normalization_gate.php',
        ];

        // IMPORTANT: keep this list scoped to actual ErrorCodes families.
        // Do NOT include broad "CORETSIA_SPIKES_" because it also matches env vars like CORETSIA_SPIKES_TMP.
        $prefixes = [
            'CORETSIA_DETERMINISM_',
            'CORETSIA_SPIKES_BOOTSTRAP_',
            'CORETSIA_SPIKES_FIXTURE_',
            'CORETSIA_SPIKES_OUTPUT_',
            'CORETSIA_REPO_TEXT_',
        ];

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($candidates as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                continue;
            }

            $count = preg_match_all('/\bCORETSIA_[A-Z0-9_]+\b/', $contents, $m);
            if ($count === false || $count < 1) {
                continue;
            }

            foreach (($m[0] ?? []) as $match) {
                if (!is_string($match) || $match === '') {
                    continue;
                }

                $enforce = false;
                foreach ($prefixes as $p) {
                    if (str_starts_with($match, $p)) {
                        $enforce = true;
                        break;
                    }
                }

                if (!$enforce) {
                    continue;
                }

                $seen[$match] = true;
            }
        }

        $codes = array_keys($seen);
        usort($codes, static fn (string $a, string $b): int => strcmp($a, $b));

        foreach ($codes as $code) {
            self::assertTrue(
                ErrorCodes::has($code),
                'Runner/Gate references code that is missing from ErrorCodes registry: ' . $code,
            );
        }
    }
}
