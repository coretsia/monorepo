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

use PHPUnit\Framework\TestCase;

final class SpikeModulesDoNotCallExitOrDieTest extends TestCase
{
    public function testSpikeModulesDoNotCallExitOrDie(): void
    {
        $spikesRoot = realpath(__DIR__ . '/..');
        if ($spikesRoot === false) {
            $this->fail('spikes-root-unresolvable');
        }

        $phpFiles = $this->collectPhpFiles($spikesRoot);

        /** @var array<string, array<string,true>> $violations map: relPath => set{exit|die} */
        $violations = [];

        foreach ($phpFiles as $abs => $rel) {
            // Exclude contract-defined subtrees.
            if ($this->isExcluded($rel)) {
                continue;
            }

            $found = $this->findProcessTerminationConstructs($abs);
            if ($found !== []) {
                foreach ($found as $kw) {
                    $violations[$rel][$kw] = true;
                }
            }
        }

        if ($violations !== []) {
            $paths = array_keys($violations);
            usort($paths, static fn(string $a, string $b): int => strcmp($a, $b));

            $lines = [];
            $lines[] = 'Spike modules MUST NOT use process-termination constructs (exit/die).';
            $lines[] = 'Found:';
            foreach ($paths as $p) {
                $kws = array_keys($violations[$p]);
                usort($kws, static fn(string $a, string $b): int => strcmp($a, $b));

                $lines[] = '- ' . $p . ': ' . implode(', ', $kws);
            }

            $this->fail(implode("\n", $lines));
        }

        $this->assertTrue(true);
    }

    /**
     * Token-based detection (comments/strings ignored by tokenization):
     * - MUST fail on any occurrence of `exit` or `die` language constructs.
     *
     * @return list<string> found keywords (subset of {"exit","die"}) in stable order of first appearance
     */
    private function findProcessTerminationConstructs(string $absPath): array
    {
        $src = file_get_contents($absPath);
        if (!is_string($src)) {
            $this->fail('scan-failed');
        }

        $tokens = token_get_all($src);
        $n = count($tokens);

        $found = [];
        $seen = [];

        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];

            if (!is_array($t)) {
                continue;
            }

            $id = $t[0];
            $text = $t[1];

            // In PHP, both "exit" and "die" are tokenized as T_EXIT.
            if ($id === T_EXIT) {
                $kw = strtolower($text);
                if ($kw !== 'exit' && $kw !== 'die') {
                    $kw = 'exit';
                }

                if (!isset($seen[$kw])) {
                    $seen[$kw] = true;
                    $found[] = $kw;
                }
            }
        }

        return $found;
    }

    /**
     * @param string $spikesRoot absolute
     * @return array<string,string> map: absPath => spikes-root-relative normalized path (forward slashes)
     */
    private function collectPhpFiles(string $spikesRoot): array
    {
        $rootNorm = rtrim(str_replace('\\', '/', $spikesRoot), '/');

        $it = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($spikesRoot, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current): bool {
                    if ($current->isLink()) {
                        return false;
                    }

                    if ($current->isDir()) {
                        $name = $current->getFilename();
                        if ($name === '_support' || $name === 'tests' || $name === 'fixtures') {
                            return false;
                        }
                        return true;
                    }

                    $name = $current->getFilename();
                    return is_string($name) && str_ends_with($name, '.php');
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $out = [];

        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $abs = $file->getPathname();
            $absReal = realpath($abs);
            if ($absReal === false) {
                $this->fail('scan-failed');
            }

            $absNorm = str_replace('\\', '/', $absReal);
            if (!str_starts_with($absNorm, $rootNorm . '/')) {
                $this->fail('scan-failed');
            }

            $rel = substr($absNorm, strlen($rootNorm) + 1);
            if (!is_string($rel) || $rel === '') {
                $this->fail('scan-failed');
            }

            $relNorm = $this->normalizeRelativePath($rel);

            $out[$absReal] = $relNorm;
        }

        ksort($out, SORT_STRING);

        return $out;
    }

    private function isExcluded(string $rel): bool
    {
        $rel = $this->normalizeRelativePath($rel);

        if ($rel === '_support' || str_starts_with($rel, '_support/')) {
            return true;
        }
        if ($rel === 'tests' || str_starts_with($rel, 'tests/')) {
            return true;
        }
        if ($rel === 'fixtures' || str_starts_with($rel, 'fixtures/')) {
            return true;
        }

        return false;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);

        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                if ($out !== []) {
                    array_pop($out);
                }
                continue;
            }
            $out[] = $p;
        }

        return implode('/', $out);
    }
}
