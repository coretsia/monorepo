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

final class CommandsDispatchViaSpikesBootstrapTest extends TestCase
{
    public function testAllCommandsUseSpikesBootstrapAndDoNotRequireToolsBootstrapDirectly(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $commandsDir = $packageRoot . '/src/Command';

        self::assertDirectoryExists($commandsDir, 'src/Command directory MUST exist.');

        $files = $this->collectPhpFiles($commandsDir);
        self::assertNotEmpty($files, 'src/Command/**/*.php MUST exist.');

        foreach ($files as $file) {
            $code = \file_get_contents($file);
            self::assertIsString($code, 'Read failed: ' . $this->displayPath($packageRoot, $file));

            $tokens = \token_get_all($code, \defined('TOKEN_PARSE') ? \TOKEN_PARSE : 0);

            $hasBootstrapCall = false;

            $count = \count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $t = $tokens[$i];

                if (!\is_array($t)) {
                    continue;
                }

                $id = $t[0];
                $text = (string)$t[1];
                $line = (int)($t[2] ?? 0);

                if ($id === \T_COMMENT || $id === \T_DOC_COMMENT) {
                    continue;
                }

                // Forbid direct reference to tools bootstrap path in command sources.
                if ($id === \T_CONSTANT_ENCAPSED_STRING) {
                    $lit = \strtolower(\trim($text, "\"'"));
                    if (\str_contains($lit, '_support/bootstrap.php')) {
                        self::fail(
                            'Commands MUST NOT require tools bootstrap directly; use SpikesBootstrap only.'
                            . ' File: ' . $this->displayPath($packageRoot, $file)
                            . ' Line: ' . (string)$line
                        );
                    }
                }

                // Detect: SpikesBootstrap::requireOnce(...) or ::requireOnceFromGlobals(...)
                if ($id === \T_STRING && $text === 'SpikesBootstrap') {
                    $j = $this->nextNonWhitespaceIndex($tokens, $i);
                    if ($j === null) {
                        continue;
                    }

                    $tj = $tokens[$j];
                    $isDoubleColon = (\is_array($tj) && $tj[0] === \T_DOUBLE_COLON) || ((string)$tj === '::');
                    if (!$isDoubleColon) {
                        continue;
                    }

                    $k = $this->nextNonWhitespaceIndex($tokens, $j);
                    if ($k === null || !\is_array($tokens[$k]) || $tokens[$k][0] !== \T_STRING) {
                        continue;
                    }

                    $method = (string)$tokens[$k][1];
                    if ($method === 'requireOnce' || $method === 'requireOnceFromGlobals') {
                        $hasBootstrapCall = true;
                        break;
                    }
                }
            }

            self::assertTrue(
                $hasBootstrapCall,
                'Each command MUST dispatch through SpikesBootstrap. File: ' . $this->displayPath($packageRoot, $file)
            );
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
            if (\strtolower((string)$item->getExtension()) !== 'php') {
                continue;
            }

            $out[] = $item->getPathname();
        }

        \sort($out);

        return $out;
    }

    /**
     * Find next non-whitespace/non-comment token index after $i.
     *
     * @param list<mixed> $tokens
     */
    private function nextNonWhitespaceIndex(array $tokens, int $i): ?int
    {
        $count = \count($tokens);

        for ($j = $i + 1; $j < $count; $j++) {
            $t = $tokens[$j];

            if (\is_array($t) && $t[0] === \T_WHITESPACE) {
                continue;
            }
            if (\is_array($t) && ($t[0] === \T_COMMENT || $t[0] === \T_DOC_COMMENT)) {
                continue;
            }

            return $j;
        }

        return null;
    }

    private function displayPath(string $packageRoot, string $absPath): string
    {
        $root = \rtrim(\str_replace('\\', '/', $packageRoot), '/');
        $path = \str_replace('\\', '/', $absPath);

        if ($root !== '' && \str_starts_with($path, $root . '/')) {
            return \substr($path, \strlen($root) + 1);
        }

        return $path;
    }
}
