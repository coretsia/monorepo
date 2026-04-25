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

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\deptrac\DeptracGenerate;
use PHPUnit\Framework\TestCase;

final class RerunGeneratorNoDiffTest extends TestCase
{
    public function testGenerateToFileIsRerunNoDiffAndLfOnly(): void
    {
        $dir = self::createTempDir('coretsia_spikes_deptrac_yaml_');
        try {
            $out = $dir . '/deptrac.yaml';

            DeptracGenerate::generateToFile('deptrac_min/package_index_ok.php', $out);
            $first = DeterministicFile::readBytesExact($out);

            DeptracGenerate::generateToFile('deptrac_min/package_index_ok.php', $out);
            $second = DeterministicFile::readBytesExact($out);

            self::assertSame($first, $second, 'rerun => no diff (file bytes)');

            $this->assertLfOnlyAndFinalNewline($first);
        } finally {
            self::rmDir($dir);
        }
    }

    private static function createTempDir(string $prefix): string
    {
        $base = rtrim(sys_get_temp_dir(), '/\\');
        $dir = $base . '/' . $prefix . bin2hex(random_bytes(6));

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('failed_to_create_temp_dir');
        }

        return $dir;
    }

    private static function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir . '/' . $name;

            if (is_dir($path)) {
                self::rmDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    private function assertLfOnlyAndFinalNewline(string $text): void
    {
        self::assertFalse(str_contains($text, "\r"), 'must be LF-only (no CR)');
        self::assertNotSame('', $text, 'must not be empty');
        self::assertSame("\n", substr($text, -1), 'must end with final newline');
    }
}
