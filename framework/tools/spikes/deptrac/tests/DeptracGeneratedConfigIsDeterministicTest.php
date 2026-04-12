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

namespace Coretsia\Tools\Spikes\deptrac\tests;

use Coretsia\Tools\Spikes\deptrac\DeptracGenerate;
use PHPUnit\Framework\TestCase;

final class DeptracGeneratedConfigIsDeterministicTest extends TestCase
{
    public function testYamlBytesAreDeterministicAndLfOnly(): void
    {
        $yaml1 = DeptracGenerate::generateYamlFromFixture('deptrac_min/package_index_ok.php');
        $yaml2 = DeptracGenerate::generateYamlFromFixture('deptrac_min/package_index_ok.php');

        self::assertSame($yaml1, $yaml2, 'rerun => no diff (in-memory)');

        $this->assertLfOnlyAndFinalNewline($yaml1);

        // Header MUST be stable and must not leak absolute paths.
        self::assertStringContainsString("# fixture: deptrac_min/package_index_ok.php\n", $yaml1);

        // Stable ordering (strcmp): demo/pkg-a before demo/pkg-b.
        $posA = strpos($yaml1, "name: 'demo/pkg-a'");
        $posB = strpos($yaml1, "name: 'demo/pkg-b'");
        self::assertIsInt($posA);
        self::assertIsInt($posB);
        self::assertLessThan($posB, $posA, 'layers must be emitted in sorted package_id order (a < b)');

        // Ruleset should include demo/pkg-a -> demo/pkg-b and demo/pkg-b -> [] deterministically.
        self::assertStringContainsString("  ruleset:\n", $yaml1);
        self::assertStringContainsString("    'demo/pkg-a':\n      - 'demo/pkg-b'\n", $yaml1);
        self::assertStringContainsString("    'demo/pkg-b': []\n", $yaml1);
    }

    private function assertLfOnlyAndFinalNewline(string $text): void
    {
        self::assertFalse(str_contains($text, "\r"), 'must be LF-only (no CR)');
        self::assertNotSame('', $text, 'must not be empty');
        self::assertSame("\n", substr($text, -1), 'must end with final newline');
    }
}
