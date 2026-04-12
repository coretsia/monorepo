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

(function (array $argv): void {
    $toolsRootRuntime = realpath(__DIR__ . '/..');
    if ($toolsRootRuntime === false) {
        // No safe output channel available before we can resolve runtime tools root.
        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleOutputFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';

    $scanRoot = $toolsRootRuntime;
    $override = parseScanRootOverride($argv);
    if ($override !== null) {
        $resolved = realpath($override);
        if ($resolved === false) {
            safeEmitScanFailed($consoleOutputFile);
            exit(1);
        }
        $scanRoot = $resolved;
    }

    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        safeEmitScanFailed($consoleOutputFile);
        exit(1);
    }

    // NOTE (cemented): if bootstrap terminates the process, its deterministic output is authoritative.
    require_once $bootstrap;

    // Output MUST be emitted only via runtime ConsoleOutput (tools-root based, not scan-root based).
    require_once $consoleOutputFile;

    try {
        $scanRootNorm = rtrim(str_replace('\\', '/', $scanRoot), '/');

        $includeDirs = [
            $scanRoot . DIRECTORY_SEPARATOR . 'spikes',
            $scanRoot . DIRECTORY_SEPARATOR . 'gates',
        ];

        foreach ($includeDirs as $dir) {
            if (!is_dir($dir)) {
                safeEmitScanFailed($consoleOutputFile);
                exit(1);
            }
        }

        $allowlistedRel = 'spikes/_support/ConsoleOutput.php';

        $directSinks = buildDirectOutputSinks();
        $bypassFiles = [];

        $files = collectPhpFiles($scanRoot, $scanRootNorm);
        foreach ($files as $abs => $rel) {
            if (isExcluded($rel)) {
                continue;
            }
            if ($rel === $allowlistedRel) {
                continue;
            }

            if (fileHasOutputBypass($abs, $directSinks)) {
                $bypassFiles[$rel] = true;
            }
        }

        if ($bypassFiles !== []) {
            $paths = array_keys($bypassFiles);
            usort($paths, static fn(string $a, string $b): int => strcmp($a, $b));

            $lines = [];
            $lines[] = 'CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED';
            foreach ($paths as $p) {
                $lines[] = $p . ': output-bypass';
            }

            safeEmitLines($lines);
            exit(1);
        }

        exit(0);
    } catch (Throwable) {
        safeEmitLines(['CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED']);
        exit(1);
    }
})($argv ?? []);

/**
 * @param array $argv
 * @return string|null
 */
function parseScanRootOverride(array $argv): ?string
{
    foreach ($argv as $arg) {
        if (!is_string($arg)) {
            continue;
        }
        if (str_starts_with($arg, '--path=')) {
            $value = substr($arg, 7);
            if ($value === false || $value === '') {
                return null;
            }
            return $value;
        }
    }

    return null;
}

/**
 * @param string $consoleOutputFile absolute runtime tools path
 */
function safeEmitScanFailed(string $consoleOutputFile): void
{
    if (is_file($consoleOutputFile) && is_readable($consoleOutputFile)) {
        require_once $consoleOutputFile;
        safeEmitLines(['CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED']);
        return;
    }

    // No safe output channel available.
}

/**
 * @return list<string>
 */
function buildDirectOutputSinks(): array
{
    $p = 'php' . '://';

    return [
        $p . 'stdout',
        $p . 'stderr',
        $p . 'output',
        $p . 'fd/1',
        $p . 'fd/2',
    ];
}

/**
 * Normalize for prefix checks:
 * - "/" separators
 * - on Windows: case-fold to lower to avoid drive-letter case mismatch from realpath()
 */
function normalizeForPrefixCheck(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = rtrim($path, '/');

    if (\PHP_OS_FAMILY === 'Windows') {
        return strtolower($path);
    }

    return $path;
}

/**
 * @param string $scanRoot absolute
 * @param string $scanRootNorm absolute with "/" separators and no trailing "/"
 * @return array<string, string> map: absPath => scan-root-relative normalized path
 */
function collectPhpFiles(string $scanRoot, string $scanRootNorm): array
{
    $out = [];

    $scanRootNormCmp = normalizeForPrefixCheck($scanRootNorm);

    foreach (['spikes', 'gates'] as $sub) {
        $dir = $scanRoot . DIRECTORY_SEPARATOR . $sub;

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();
            if (!is_string($name) || !str_ends_with($name, '.php')) {
                continue;
            }

            $abs = $file->getPathname();
            $absReal = realpath($abs);
            if ($absReal === false) {
                throw new RuntimeException('scan-failed');
            }

            $absNorm = str_replace('\\', '/', $absReal);
            $absNormCmp = normalizeForPrefixCheck($absNorm);

            // On Windows: compare case-insensitively (drive-letter casing can differ).
            if (!str_starts_with($absNormCmp, $scanRootNormCmp . '/')) {
                throw new RuntimeException('scan-failed');
            }

            $rel = substr($absNorm, strlen($scanRootNorm) + 1);
            if (!is_string($rel)) {
                throw new RuntimeException('scan-failed');
            }

            $relNorm = normalizeRelativePath($rel);
            $out[$absReal] = $relNorm;
        }
    }

    ksort($out, SORT_STRING);

    return $out;
}

function normalizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $parts = explode('/', $path);

    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') {
            continue;
        }
        if ($p === '..') {
            if ($out === []) {
                continue;
            }
            array_pop($out);
            continue;
        }
        $out[] = $p;
    }

    return implode('/', $out);
}

function isExcluded(string $rel): bool
{
    if ($rel === 'spikes/tests' || str_starts_with($rel, 'spikes/tests/')) {
        return true;
    }
    if ($rel === 'spikes/fixtures' || str_starts_with($rel, 'spikes/fixtures/')) {
        return true;
    }

    if (str_starts_with($rel, 'gates/')) {
        if ($rel === 'gates/tests' || str_contains($rel, '/tests/') || str_ends_with($rel, '/tests')) {
            return true;
        }
        if ($rel === 'gates/fixtures' || str_contains($rel, '/fixtures/') || str_ends_with($rel, '/fixtures')) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $directSinks
 */
function fileHasOutputBypass(string $absPath, array $directSinks): bool
{
    $src = file_get_contents($absPath);
    if (!is_string($src)) {
        throw new RuntimeException('scan-failed');
    }

    $tokens = token_get_all($src);
    $n = count($tokens);

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

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_array($t)) {
            $id = $t[0];
            $text = $t[1];

            if ($id === T_ECHO || $id === T_PRINT) {
                return true;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $value = decodeConstantStringLiteral($text);
                if ($value !== null && in_array($value, $directSinks, true)) {
                    return true;
                }
                continue;
            }

            if (!isCallableNameToken($id)) {
                continue;
            }

            $fn = lastNameSegment($text);
            $fnLower = strtolower($fn);

            if (!isset($callBypass[$fnLower]) && !isset($streamFns[$fnLower])) {
                continue;
            }

            if (!isCallSite($tokens, $i)) {
                continue;
            }

            if (isset($callBypass[$fnLower])) {
                return true;
            }

            // fwrite/fputs/fprintf: bypass only if args contain STDOUT/STDERR.
            $openIdx = nextNonIgnorableIndex($tokens, $i + 1);
            if ($openIdx === null || $tokens[$openIdx] !== '(') {
                continue;
            }

            if (argsContainStdStream($tokens, $openIdx)) {
                return true;
            }

            continue;
        }
    }

    return false;
}

function isCallableNameToken(int $id): bool
{
    return $id === T_STRING
        || $id === (defined('T_NAME_QUALIFIED') ? T_NAME_QUALIFIED : -1)
        || $id === (defined('T_NAME_FULLY_QUALIFIED') ? T_NAME_FULLY_QUALIFIED : -1)
        || $id === (defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : -1);
}

function lastNameSegment(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $parts = explode('/', $name);
    $last = end($parts);
    if ($last === false) {
        return $name;
    }
    return (string)$last;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function isCallSite(array $tokens, int $nameIndex): bool
{
    $prev = prevNonIgnorableIndex($tokens, $nameIndex - 1);
    if ($prev !== null && is_array($tokens[$prev])) {
        $pid = $tokens[$prev][0];
        if ($pid === T_OBJECT_OPERATOR || $pid === T_DOUBLE_COLON || $pid === T_FUNCTION) {
            return false;
        }
    }

    $next = nextNonIgnorableIndex($tokens, $nameIndex + 1);
    if ($next === null) {
        return false;
    }

    return $tokens[$next] === '(';
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function prevNonIgnorableIndex(array $tokens, int $from): ?int
{
    for ($i = $from; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (isIgnorableToken($t)) {
            continue;
        }
        return $i;
    }

    return null;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function nextNonIgnorableIndex(array $tokens, int $from): ?int
{
    $n = count($tokens);
    for ($i = $from; $i < $n; $i++) {
        $t = $tokens[$i];
        if (isIgnorableToken($t)) {
            continue;
        }
        return $i;
    }

    return null;
}

/**
 * @param array{0:int,1:string,2?:int}|string $token
 */
function isIgnorableToken(array|string $token): bool
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
function argsContainStdStream(array $tokens, int $openParenIndex): bool
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

        if ($id === T_STRING || isCallableNameToken($id)) {
            $name = lastNameSegment($text);
            $u = strtoupper($name);
            if ($u === 'STDOUT' || $u === 'STDERR') {
                return true;
            }
        }
    }

    throw new RuntimeException('scan-failed');
}

/**
 * @param string $tokenText
 * @return string|null
 */
function decodeConstantStringLiteral(string $tokenText): ?string
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
        $inner = str_replace(['\\\\', '\\\''], ['\\', '\''], $inner);
        return $inner;
    }

    return stripcslashes($inner);
}

/**
 * @param list<string> $lines
 */
function safeEmitLines(array $lines): void
{
    $fqcn = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';
    $candidates = [$fqcn, 'ConsoleOutput'];

    foreach ($candidates as $class) {
        if (!class_exists($class)) {
            continue;
        }

        // Prefer batch APIs if available.
        foreach (['writeLines', 'lines', 'emitLines', 'outLines'] as $m) {
            if (method_exists($class, $m)) {
                $class::$m($lines);
                return;
            }
        }

        // Fallback: single-line APIs.
        foreach (['writeLine', 'line', 'writeln', 'emit', 'out'] as $m) {
            if (!method_exists($class, $m)) {
                continue;
            }
            foreach ($lines as $line) {
                $class::$m($line);
            }
            return;
        }
    }

    // No safe output channel available.
}
