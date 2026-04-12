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

final class SpikeModulesDoNotUseConsoleOutputTest extends TestCase
{
    public function testSpikeModulesDoNotReferenceConsoleOutputNorWriteToStdoutStderr(): void
    {
        $spikesRoot = realpath(__DIR__ . '/..');
        $this->assertIsString($spikesRoot);
        $this->assertNotSame('', $spikesRoot);

        $violations = self::scanSpikeModules($spikesRoot);

        if ($violations !== []) {
            sort($violations, SORT_STRING);
        }

        self::assertSame(
            [],
            $violations,
            "Spike module output policy violations detected:\n" . implode("\n", $violations)
        );
    }

    /**
     * Token-based scan of framework/tools/spikes/** excluding:
     *  - framework/tools/spikes/_support/**
     *  - framework/tools/spikes/tests/**
     *  - framework/tools/spikes/fixtures/**
     *
     * MUST fail if any spike module:
     *  - references ConsoleOutput (class name or FQCN)
     *  - writes to stdout/stderr (direct bypass constructs)
     *
     * @return list<string> stable lines "<rel>: <reason>"
     */
    private static function scanSpikeModules(string $spikesRoot): array
    {
        $spikesRootNorm = rtrim(str_replace('\\', '/', $spikesRoot), '/');

        $excludedPrefixes = [
            '_support/',
            'tests/',
            'fixtures/',
        ];

        $directSinks = [
            'php://stdout',
            'php://stderr',
            'php://output',
            'php://fd/1',
            'php://fd/2',
        ];

        $callBypass = [
            'var_dump' => true,
            'print_r' => true,
            'printf' => true,
            'vprintf' => true,
            'error_log' => true,
        ];

        $streamFns = [
            'fwrite' => true,
            'fputs' => true,
            'fprintf' => true,
        ];

        $violations = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($spikesRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $name = $file->getFilename();
            if (!is_string($name) || !str_ends_with($name, '.php')) {
                continue;
            }

            $abs = $file->getPathname();
            $absReal = realpath($abs);
            if ($absReal === false) {
                // Deterministic test failure (environmental).
                self::fail('scan-failed: realpath');
            }

            $absNorm = str_replace('\\', '/', $absReal);
            if (!str_starts_with($absNorm, $spikesRootNorm . '/')) {
                self::fail('scan-failed: outside-root');
            }

            $rel = substr($absNorm, strlen($spikesRootNorm) + 1);
            if (!is_string($rel) || $rel === '') {
                continue;
            }

            $rel = self::normalizeRelativePath($rel);

            // Exclude contract-specified directories.
            $excluded = false;
            foreach ($excludedPrefixes as $prefix) {
                if ($rel === rtrim($prefix, '/') || str_starts_with($rel, $prefix)) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            $src = file_get_contents($absReal);
            if (!is_string($src)) {
                self::fail('scan-failed: io');
            }

            $tokens = token_get_all($src);
            $n = count($tokens);

            for ($i = 0; $i < $n; $i++) {
                $t = $tokens[$i];

                if (!is_array($t)) {
                    continue;
                }

                $id = $t[0];
                $text = $t[1];

                // Output constructs (language-level).
                if ($id === T_ECHO || $id === T_PRINT) {
                    $violations[] = $rel . ': output-bypass';
                    break;
                }

                // Direct sinks as exact string literal values.
                if ($id === T_CONSTANT_ENCAPSED_STRING) {
                    $value = self::decodeConstantStringLiteral($text);
                    if ($value !== null && in_array($value, $directSinks, true)) {
                        $violations[] = $rel . ': output-bypass';
                        break;
                    }
                    continue;
                }

                // Names: ConsoleOutput reference OR bypass function calls.
                if (!self::isNameToken($id)) {
                    continue;
                }

                $last = self::lastNameSegment($text);

                // MUST fail on any reference to ConsoleOutput (class name or FQCN).
                if ($last === 'ConsoleOutput') {
                    $violations[] = $rel . ': console-output-reference';
                    break;
                }

                $fnLower = strtolower($last);

                // Global bypass calls (var_dump/print_r/printf/vprintf/error_log) if it is a call site.
                if (isset($callBypass[$fnLower])) {
                    if (self::isCallSite($tokens, $i)) {
                        $violations[] = $rel . ': output-bypass';
                        break;
                    }
                    continue;
                }

                // fwrite/fputs/fprintf -> only bypass if args contain STDOUT/STDERR.
                if (isset($streamFns[$fnLower])) {
                    if (!self::isCallSite($tokens, $i)) {
                        continue;
                    }

                    $openIdx = self::nextNonIgnorableIndex($tokens, $i + 1);
                    if ($openIdx === null || $tokens[$openIdx] !== '(') {
                        continue;
                    }

                    if (self::argsContainStdStream($tokens, $openIdx)) {
                        $violations[] = $rel . ': output-bypass';
                        break;
                    }
                }
            }
        }

        // Stable ordering (rel already normalized).
        sort($violations, SORT_STRING);

        return array_values(array_unique($violations));
    }

    private static function normalizeRelativePath(string $path): string
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

    private static function isNameToken(int $id): bool
    {
        // PHP 8.4: T_NAME_* exist; keep guarded for safety.
        return $id === T_STRING
            || $id === (defined('T_NAME_QUALIFIED') ? T_NAME_QUALIFIED : -1)
            || $id === (defined('T_NAME_FULLY_QUALIFIED') ? T_NAME_FULLY_QUALIFIED : -1)
            || $id === (defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : -1);
    }

    private static function lastNameSegment(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $last = end($parts);

        return ($last === false) ? $name : (string)$last;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     */
    private static function isCallSite(array $tokens, int $nameIndex): bool
    {
        $prev = self::prevNonIgnorableIndex($tokens, $nameIndex - 1);
        if ($prev !== null && is_array($tokens[$prev])) {
            $pid = $tokens[$prev][0];
            if ($pid === T_OBJECT_OPERATOR || $pid === T_DOUBLE_COLON || $pid === T_FUNCTION) {
                return false;
            }
        }

        $next = self::nextNonIgnorableIndex($tokens, $nameIndex + 1);
        if ($next === null) {
            return false;
        }

        return $tokens[$next] === '(';
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     */
    private static function prevNonIgnorableIndex(array $tokens, int $from): ?int
    {
        for ($i = $from; $i >= 0; $i--) {
            $t = $tokens[$i];
            if (self::isIgnorableToken($t)) {
                continue;
            }
            return $i;
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     */
    private static function nextNonIgnorableIndex(array $tokens, int $from): ?int
    {
        $n = count($tokens);
        for ($i = $from; $i < $n; $i++) {
            $t = $tokens[$i];
            if (self::isIgnorableToken($t)) {
                continue;
            }
            return $i;
        }

        return null;
    }

    /**
     * @param array{0:int,1:string,2?:int}|string $token
     */
    private static function isIgnorableToken(array|string $token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        return $token[0] === T_WHITESPACE
            || $token[0] === T_COMMENT
            || $token[0] === T_DOC_COMMENT;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     */
    private static function argsContainStdStream(array $tokens, int $openParenIndex): bool
    {
        $n = count($tokens);
        $depth = 0;

        for ($i = $openParenIndex + 1; $i < $n; $i++) {
            $t = $tokens[$i];

            if ($t === '(') {
                $depth++;
                continue;
            }
            if ($t === ')') {
                if ($depth === 0) {
                    return false;
                }
                $depth--;
                continue;
            }

            if (!is_array($t)) {
                continue;
            }

            $id = $t[0];
            $text = $t[1];

            if ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                continue;
            }
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if (self::isNameToken($id)) {
                $name = self::lastNameSegment($text);
                $u = strtoupper($name);
                if ($u === 'STDOUT' || $u === 'STDERR') {
                    return true;
                }
            }
        }

        self::fail('scan-failed: unterminated-call');
    }

    /**
     * @param string $tokenText
     * @return string|null
     */
    private static function decodeConstantStringLiteral(string $tokenText): ?string
    {
        $len = strlen($tokenText);
        if ($len < 2) {
            return null;
        }

        $q = $tokenText[0];
        if (($q !== '\'' && $q !== '"') || $tokenText[$len - 1] !== $q) {
            return null;
        }

        $inner = substr($tokenText, 1, $len - 2);
        if (!is_string($inner)) {
            return null;
        }

        if ($q === '\'') {
            // In single quotes, only \\ and \' are escaped.
            return str_replace(['\\\\', '\\\''], ['\\', '\''], $inner);
        }

        return stripcslashes($inner);
    }
}
