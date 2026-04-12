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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class ConsoleOutputReservedForGatesOnlyTest extends TestCase
{
    public function testToolsSpikesDoNotReferenceConsoleOutputOutsideReservedImplementationFile(): void
    {
        $repoRoot = \dirname(__DIR__, 6);
        $spikesRoot = $repoRoot . '/framework/tools/spikes';

        self::assertDirectoryExists($spikesRoot, 'framework/tools/spikes directory MUST exist.');

        $files = $this->collectPhpFiles($spikesRoot);
        self::assertNotEmpty($files, 'framework/tools/spikes/**/*.php MUST exist.');

        foreach ($files as $file) {
            if ($this->shouldSkipFile($file)) {
                continue;
            }

            $code = \file_get_contents($file);
            self::assertIsString($code, 'Read failed: ' . $this->displayPath($repoRoot, $file));

            $tokens = \token_get_all($code, \defined('TOKEN_PARSE') ? \TOKEN_PARSE : 0);

            foreach ($tokens as $token) {
                if (!\is_array($token)) {
                    continue;
                }

                $id = $token[0];
                $text = (string) $token[1];
                $line = (int) ($token[2] ?? 0);

                if ($id === \T_COMMENT || $id === \T_DOC_COMMENT) {
                    continue;
                }

                if ($id === \T_STRING && $text === 'ConsoleOutput') {
                    self::fail(
                        'ConsoleOutput is reserved for gates/runner diagnostics only and MUST NOT be used in spike business logic.'
                        . ' File: ' . $this->displayPath($repoRoot, $file)
                        . ' Line: ' . (string) $line
                    );
                }

                if (
                    \defined('T_NAME_QUALIFIED')
                    && $id === \constant('T_NAME_QUALIFIED')
                    && \str_contains($text, 'ConsoleOutput')
                ) {
                    self::fail(
                        'ConsoleOutput is reserved for gates/runner diagnostics only and MUST NOT be used in spike business logic.'
                        . ' File: ' . $this->displayPath($repoRoot, $file)
                        . ' Line: ' . (string) $line
                    );
                }

                if (
                    \defined('T_NAME_FULLY_QUALIFIED')
                    && $id === \constant('T_NAME_FULLY_QUALIFIED')
                    && \str_contains($text, 'ConsoleOutput')
                ) {
                    self::fail(
                        'ConsoleOutput is reserved for gates/runner diagnostics only and MUST NOT be used in spike business logic.'
                        . ' File: ' . $this->displayPath($repoRoot, $file)
                        . ' Line: ' . (string) $line
                    );
                }
            }
        }

        self::addToAssertionCount(1);
    }

    /**
     * @return list<string>
     */
    private function collectPhpFiles(string $dir): array
    {
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }
            if (\strtolower((string) $item->getExtension()) !== 'php') {
                continue;
            }

            $out[] = $item->getPathname();
        }

        \sort($out);

        return $out;
    }

    private function shouldSkipFile(string $absPath): bool
    {
        $path = \str_replace('\\', '/', $absPath);

        if (\str_ends_with($path, '/framework/tools/spikes/_support/ConsoleOutput.php')) {
            return true;
        }

        if (\str_ends_with($path, '/framework/tools/spikes/_support/DeterminismRunner.php')) {
            return true;
        }

        if (\str_ends_with($path, '/framework/tools/spikes/_support/bootstrap.php')) {
            return true;
        }

        if (\str_contains($path, '/framework/tools/spikes/tests/')) {
            return true;
        }

        if (\str_contains($path, '/framework/tools/spikes/fixtures/')) {
            return true;
        }

        return false;
    }

    private function displayPath(string $repoRoot, string $absPath): string
    {
        $root = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
        $path = \str_replace('\\', '/', $absPath);

        if ($root !== '' && \str_starts_with($path, $root . '/')) {
            return \substr($path, \strlen($root) + 1);
        }

        return $path;
    }
}
