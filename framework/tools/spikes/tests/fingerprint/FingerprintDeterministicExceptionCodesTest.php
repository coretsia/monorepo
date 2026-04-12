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

namespace Coretsia\Tools\Spikes\tests\fingerprint;

use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\fingerprint\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

final class FingerprintDeterministicExceptionCodesTest extends TestCase
{
    public function testAllDeterministicExceptionCodesInFingerprintAreRegistered(): void
    {
        $dir = $this->fingerprintDir();

        $files = $this->listPhpFilesRecursive($dir);
        self::assertNotSame([], $files, 'No PHP files found in fingerprint directory (test is misconfigured).');

        $occurrences = 0;

        foreach ($files as $file) {
            $src = file_get_contents($file);
            self::assertIsString($src, 'Failed to read file: ' . $file);

            foreach ($this->extractDeterministicExceptionCodeExprs($src) as $expr) {
                $occurrences++;

                $constName = $this->extractErrorCodesConstantName($expr);
                self::assertNotNull(
                    $constName,
                    'DeterministicException code MUST be ErrorCodes::<CONST> (no ad-hoc expressions). '
                    . 'File: ' . $file . ' Expr: ' . $expr,
                );

                $fq = ErrorCodes::class . '::' . $constName;

                self::assertTrue(
                    defined($fq),
                    'ErrorCodes constant is not defined: ' . $fq . ' (File: ' . $file . ')',
                );

                /** @var string $code */
                $code = constant($fq);

                self::assertIsString($code, 'ErrorCodes constant must be string: ' . $fq);
                self::assertNotSame('', $code, 'ErrorCodes constant must be non-empty: ' . $fq);

                self::assertTrue(
                    ErrorCodes::has($code),
                    'ErrorCodes::has() returned false => constant exists but is NOT in registry. '
                    . 'Constant: ' . $fq . ' Value: ' . $code,
                );
            }
        }

        self::assertGreaterThan(
            0,
            $occurrences,
            'No DeterministicException occurrences found in fingerprint/*.php (test is likely broken).',
        );
    }

    private function fingerprintDir(): string
    {
        $ref = new \ReflectionClass(FingerprintCalculator::class);
        $file = $ref->getFileName();

        self::assertIsString($file, 'Reflection failed for FingerprintCalculator.');
        self::assertNotSame('', $file, 'Reflection returned empty filename for FingerprintCalculator.');

        return \dirname($file);
    }

    /**
     * @return list<string>
     */
    private function listPhpFilesRecursive(string $dir): array
    {
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
            ),
        );

        foreach ($it as $info) {
            if (!$info instanceof \SplFileInfo) {
                continue;
            }

            if (!$info->isFile()) {
                continue;
            }

            if (\strtolower($info->getExtension()) !== 'php') {
                continue;
            }

            $path = $info->getPathname();
            if ($path !== '') {
                $out[] = $path;
            }
        }

        \sort($out);

        /** @var list<string> $out */
        return $out;
    }

    /**
     * Extracts first-argument expressions from:
     *   new DeterministicException(<expr>, ...)
     *
     * @return list<string> list of raw expressions (trimmed)
     */
    private function extractDeterministicExceptionCodeExprs(string $src): array
    {
        $matches = [];

        // Match both:
        //   new DeterministicException(...)
        //   new \Coretsia\Tools\Spikes\_support\DeterministicException(...)
        //
        // Capture ONLY the first argument before the first comma.
        preg_match_all(
            '~new\s+(?:\\\\?Coretsia\\\\Tools\\\\Spikes\\\\_support\\\\)?DeterministicException\s*\(\s*(.+?)\s*,~s',
            $src,
            $matches,
        );

        $out = [];

        /** @var list<string> $exprs */
        $exprs = $matches[1] ?? [];
        foreach ($exprs as $expr) {
            $expr = \trim($expr);
            if ($expr === '') {
                continue;
            }
            $out[] = $expr;
        }

        return $out;
    }

    /**
     * Accepts expressions like:
     *   ErrorCodes::FOO
     *   \Coretsia\Tools\Spikes\_support\ErrorCodes::FOO
     *
     * Returns "FOO" or null.
     */
    private function extractErrorCodesConstantName(string $expr): ?string
    {
        if (\preg_match('~(?:\\\\?Coretsia\\\\Tools\\\\Spikes\\\\_support\\\\)?ErrorCodes::([A-Z0-9_]+)\z~', $expr, $m) === 1) {
            return (string)$m[1];
        }

        return null;
    }
}
