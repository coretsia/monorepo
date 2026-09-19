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

namespace Coretsia\Platform\Cli\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class CommandsDoNotWriteToStdoutTest extends TestCase
{
    public function testCommandsDoNotUseDirectStdoutStderrSinks(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $commandsDir = $packageRoot . '/src/Command';

        self::assertDirectoryExists($commandsDir, 'src/Command directory MUST exist.');

        $files = $this->collectPhpFiles($commandsDir);
        self::assertNotEmpty($files, 'src/Command/**/*.php MUST exist.');

        foreach ($files as $file) {
            $code = (string)\file_get_contents($file);

            // Token-based scan:
            // - MUST ignore comments
            // - MUST ignore strings for keyword detection, except controlled checks (php://stdout/stderr/output)
            $tokens = \token_get_all($code, \defined('TOKEN_PARSE') ? \TOKEN_PARSE : 0);

            $this->scanTokens($file, $tokens);
        }

        self::addToAssertionCount(1);
    }

    /**
     * @param list<mixed> $tokens
     */
    private function scanTokens(string $file, array $tokens): void
    {
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            if (!\is_array($t)) {
                continue;
            }

            $id = $t[0];
            $text = $t[1];
            $line = (int)($t[2] ?? 0);

            // Ignore comments.
            if ($id === \T_COMMENT || $id === \T_DOC_COMMENT) {
                continue;
            }

            // Direct language constructs.
            if ($id === \T_ECHO) {
                $this->failToken($file, $line, 'echo is forbidden in commands (must use OutputInterface).');
            }
            if ($id === \T_PRINT) {
                $this->failToken($file, $line, 'print is forbidden in commands (must use OutputInterface).');
            }

            if ($id !== \T_STRING) {
                continue;
            }

            $name = \strtolower($text);

            // Function calls: var_dump, print_r, printf, vprintf, fprintf, error_log
            if (\in_array($name, ['var_dump', 'print_r', 'printf', 'vprintf', 'fprintf', 'error_log'], true)) {
                if ($this->nextNonWhitespaceTokenIs($tokens, $i, '(')) {
                    $this->failToken($file, $line, $name . '() is forbidden in commands.');
                }
            }

            // fwrite/fputs with STDOUT|STDERR (including \STDOUT, \STDERR).
            if (\in_array($name, ['fwrite', 'fputs'], true)) {
                $open = $this->nextNonWhitespaceIndex($tokens, $i);
                if ($open !== null && $this->tokenText($tokens[$open]) === '(') {
                    $argIdx = $this->nextNonWhitespaceIndex($tokens, $open);
                    if ($argIdx !== null) {
                        $const = $this->readPossibleFqConstant($tokens, $argIdx);
                        if ($const === 'STDOUT' || $const === 'STDERR') {
                            $this->failToken(
                                $file,
                                $line,
                                $name . '(' . $const . ', ...) is forbidden in commands.'
                            );
                        }
                    }
                }
            }

            // php://stdout|stderr|output sinks via file_put_contents/fopen/popen (controlled string literal check).
            if (\in_array($name, ['file_put_contents', 'fopen', 'popen'], true)) {
                $open = $this->nextNonWhitespaceIndex($tokens, $i);
                if ($open !== null && $this->tokenText($tokens[$open]) === '(') {
                    $argIdx = $this->nextNonWhitespaceIndex($tokens, $open);
                    if ($argIdx !== null && \is_array($tokens[$argIdx]) && $tokens[$argIdx][0] === \T_CONSTANT_ENCAPSED_STRING) {
                        $lit = \strtolower((string)$tokens[$argIdx][1]);
                        if (
                            \str_contains($lit, 'php://stdout') ||
                            \str_contains($lit, 'php://stderr') ||
                            \str_contains($lit, 'php://output')
                        ) {
                            $this->failToken(
                                $file,
                                $line,
                                $name . '("php://stdout|stderr|output", ...) is forbidden in commands.'
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Read a possible fully-qualified constant like \STDOUT.
     *
     * @param list<mixed> $tokens
     */
    private function readPossibleFqConstant(array $tokens, int $idx): string
    {
        $t = $tokens[$idx];

        // \STDOUT => T_NS_SEPARATOR "\" then T_STRING "STDOUT"
        if (\is_array($t) && $t[0] === \T_NS_SEPARATOR) {
            $next = $this->nextNonWhitespaceIndex($tokens, $idx);
            if ($next !== null) {
                return $this->readPossibleFqConstant($tokens, $next);
            }

            return '';
        }

        $raw = $this->tokenText($t);
        $raw = \ltrim($raw, '\\');

        return $raw;
    }

    private function failToken(string $file, int $line, string $message): void
    {
        self::fail($message . ' File: ' . $file . ' Line: ' . (string)$line);
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

    /**
     * @param list<mixed> $tokens
     */
    private function nextNonWhitespaceTokenIs(array $tokens, int $i, string $expected): bool
    {
        $j = $this->nextNonWhitespaceIndex($tokens, $i);
        if ($j === null) {
            return false;
        }

        return $this->tokenText($tokens[$j]) === $expected;
    }

    private function tokenText(mixed $token): string
    {
        return \is_array($token) ? (string)$token[1] : (string)$token;
    }
}
