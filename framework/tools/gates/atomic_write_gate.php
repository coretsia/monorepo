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

(static function (array $argv): void {
    /**
     * Execute callable with warnings/notices suppressed (no output pollution).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    $withSuppressedErrors = static function (callable $fn) {
        \set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $fn();
        } finally {
            \restore_error_handler();
        }
    };

    $toolsRootRuntime = $withSuppressedErrors(static function (): ?string {
        $p = \realpath(__DIR__ . '/..');

        return \is_string($p) ? $p : null;
    });

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_ATOMIC_WRITE_GATE_FAILED',
                [],
            );
        }

        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';

    /** @var class-string $ConsoleOutput */
    $ConsoleOutput = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';

    /** @var class-string $ErrorCodes */
    $ErrorCodes = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

    $fallbackViolation = 'CORETSIA_ATOMIC_WRITE_VIOLATION';
    $fallbackGateFailed = 'CORETSIA_ATOMIC_WRITE_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_ATOMIC_WRITE_GATE_FAILED';
                if (\defined($name)) {
                    /** @var string $v */
                    $v = \constant($name);
                    $code = $v;
                }
            }

            $ConsoleOutput::codeWithDiagnostics($code, []);
        }

        exit(1);
    }

    require_once $bootstrap;

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }

    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = coretsia_atomic_write_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_ATOMIC_WRITE_VIOLATION',
        $fallbackViolation,
    );

    $codeGateFailed = coretsia_atomic_write_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_ATOMIC_WRITE_GATE_FAILED',
        $fallbackGateFailed,
    );

    try {
        $frameworkRoot = $withSuppressedErrors(static function () use ($toolsRootRuntime): ?string {
            $p = \realpath($toolsRootRuntime . '/..');

            return \is_string($p) ? \rtrim(\str_replace('\\', '/', $p), '/') : null;
        });

        if ($frameworkRoot === null || $frameworkRoot === '') {
            throw new \RuntimeException('framework-root-invalid');
        }
        $scanRoot = coretsia_atomic_write_gate_resolve_scan_root($argv, $toolsRootRuntime, $frameworkRoot);
        $allowlistPath = coretsia_atomic_write_gate_resolve_allowlist_path(
            $argv,
            $frameworkRoot . '/tools/config/atomic_write_allowlist.php',
            $frameworkRoot,
        );

        $allowlist = coretsia_atomic_write_gate_load_allowlist($allowlistPath);

        /** @var list<string> $diagnostics */
        $diagnostics = [];

        foreach (coretsia_atomic_write_gate_collect_php_files($scanRoot) as $file) {
            $file = \str_replace('\\', '/', $file);
            $frameworkRelative = coretsia_atomic_write_gate_display_path($file, $scanRoot, $frameworkRoot);

            if (coretsia_atomic_write_gate_should_skip_file($file, $frameworkRelative, $frameworkRoot, $allowlist)) {
                continue;
            }

            $source = coretsia_atomic_write_gate_read_file($file);
            $tokens = \token_get_all($source, \defined('TOKEN_PARSE') ? \TOKEN_PARSE : 0);

            foreach (coretsia_atomic_write_gate_scan_tokens($tokens) as $line) {
                $diagnostics[] = $frameworkRelative . ':' . (string)$line;
            }
        }

        $diagnostics = \array_values(\array_unique($diagnostics));
        \usort($diagnostics, static fn (string $a, string $b): int => \strcmp($a, $b));

        if ($diagnostics === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeGateFailed, []);
        }

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @param list<string> $argv
 */
function coretsia_atomic_write_gate_resolve_scan_root(
    array $argv,
    string $defaultScanRoot,
    string $frameworkRoot
): string {
    $scanRoot = $defaultScanRoot;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '') {
            continue;
        }

        if ($arg === '--') {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $scanRoot = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '--allowlist=')) {
            continue;
        }

        if (\str_starts_with($arg, '-')) {
            continue;
        }

        $scanRoot = $arg;
    }

    return coretsia_atomic_write_gate_resolve_existing_dir($scanRoot, $frameworkRoot);
}

function coretsia_atomic_write_gate_resolve_existing_dir(string $path, string $frameworkRoot): string
{
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        throw new \RuntimeException('scan-root-invalid');
    }

    /** @var list<string> $candidates */
    $candidates = [];

    if (coretsia_atomic_write_gate_is_absolute_path($path)) {
        $candidates[] = $path;
    } else {
        $cwd = \getcwd();
        if (\is_string($cwd)) {
            $candidates[] = \rtrim(\str_replace('\\', '/', $cwd), '/') . '/' . \ltrim($path, '/');
        }

        $candidates[] = \rtrim($frameworkRoot, '/') . '/' . \ltrim($path, '/');
    }

    foreach (\array_values(\array_unique($candidates)) as $candidate) {
        $real = \realpath($candidate);

        if (\is_string($real) && \is_dir($real) && \is_readable($real)) {
            return \rtrim(\str_replace('\\', '/', $real), '/');
        }
    }

    throw new \RuntimeException('scan-root-invalid');
}

/**
 * @param list<string> $argv
 */
function coretsia_atomic_write_gate_resolve_allowlist_path(
    array $argv,
    string $defaultAllowlistPath,
    string $frameworkRoot
): string {
    $allowlistPath = $defaultAllowlistPath;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg)) {
            continue;
        }

        if (\str_starts_with($arg, '--allowlist=')) {
            $allowlistPath = \substr($arg, \strlen('--allowlist='));
            break;
        }
    }

    if (!coretsia_atomic_write_gate_is_absolute_path($allowlistPath)) {
        $allowlistPath = \rtrim($frameworkRoot, '/') . '/' . \ltrim($allowlistPath, '/');
    }

    $real = \realpath($allowlistPath);

    if (!\is_string($real) || !\is_file($real) || !\is_readable($real)) {
        throw new \RuntimeException('atomic-write-allowlist-path-invalid');
    }

    return \str_replace('\\', '/', $real);
}

/**
 * @return array<string, true>
 */
function coretsia_atomic_write_gate_load_allowlist(string $allowlistPath): array
{
    $value = require $allowlistPath;

    if (!\is_array($value) || !\array_is_list($value)) {
        throw new \RuntimeException('atomic-write-allowlist-invalid');
    }

    /** @var list<string> $paths */
    $paths = [];

    foreach ($value as $entry) {
        if (!\is_array($entry) || \array_is_list($entry)) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        $keys = \array_keys($entry);
        \sort($keys, \SORT_STRING);

        if ($keys !== ['path', 'reason']) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        $path = $entry['path'] ?? null;
        $reason = $entry['reason'] ?? null;

        if (!\is_string($path) || !\is_string($reason)) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        if (!coretsia_atomic_write_gate_is_valid_framework_relative_path($path)) {
            throw new \RuntimeException('atomic-write-allowlist-path-invalid');
        }

        if (\preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/', $reason) !== 1) {
            throw new \RuntimeException('atomic-write-allowlist-reason-invalid');
        }

        $paths[] = $path;
    }

    $sorted = $paths;
    \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));

    if ($paths !== $sorted) {
        throw new \RuntimeException('atomic-write-allowlist-not-sorted');
    }

    if (\count(\array_unique($paths)) !== \count($paths)) {
        throw new \RuntimeException('atomic-write-allowlist-duplicate');
    }

    /** @var array<string, true> $lookup */
    $lookup = [];
    foreach ($paths as $path) {
        $lookup[$path] = true;
    }

    \ksort($lookup, \SORT_STRING);

    return $lookup;
}

/**
 * @return list<string>
 */
function coretsia_atomic_write_gate_collect_php_files(string $scanRoot): array
{
    if (!\is_dir($scanRoot)) {
        return [];
    }

    /** @var list<string> $out */
    $out = [];

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($scanRoot, \FilesystemIterator::SKIP_DOTS),
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

        $out[] = \str_replace('\\', '/', $item->getPathname());
    }

    \sort($out, \SORT_STRING);

    return $out;
}

/**
 * @param array<string, true> $allowlist
 */
function coretsia_atomic_write_gate_should_skip_file(
    string $absPath,
    string $displayPath,
    string $frameworkRoot,
    array $allowlist,
): bool {
    $absPath = \str_replace('\\', '/', $absPath);
    $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');

    if (isset($allowlist[$displayPath])) {
        return true;
    }

    $frameworkRelative = coretsia_atomic_write_gate_rel_from_framework_or_null($absPath, $frameworkRoot);
    if ($frameworkRelative === null) {
        return false;
    }

    if (isset($allowlist[$frameworkRelative])) {
        return true;
    }

    if ($frameworkRelative === 'tools/spikes/_support/DeterministicFile.php') {
        return true;
    }

    if (\str_starts_with($frameworkRelative, 'tools/tests/') || \str_contains($frameworkRelative, '/tests/')) {
        return true;
    }

    if (\str_contains($frameworkRelative, '/fixtures/')) {
        return true;
    }

    if (\str_starts_with($frameworkRelative, 'tools/spikes/fixtures/')) {
        return true;
    }

    return false;
}

/**
 * @param list<mixed> $tokens
 * @return list<int>
 */
function coretsia_atomic_write_gate_scan_tokens(array $tokens): array
{
    /** @var list<int> $lines */
    $lines = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            continue;
        }

        $id = $token[0];
        $text = (string)$token[1];
        $line = (int)($token[2] ?? 0);

        if ($id === \T_COMMENT || $id === \T_DOC_COMMENT) {
            continue;
        }

        $call = coretsia_atomic_write_gate_function_call_name($tokens, $i);
        if ($call !== null) {
            if (\in_array($call, ['file_put_contents', 'fwrite', 'rename', 'copy'], true)) {
                $lines[] = $line;
                continue;
            }

            if ($call === 'fopen' && coretsia_atomic_write_gate_fopen_mode_is_write_or_unknown($tokens, $i)) {
                $lines[] = $line;
                continue;
            }
        }

        if ($id === \T_NEW && coretsia_atomic_write_gate_new_splfileobject_mode_is_write_or_unknown($tokens, $i)) {
            $lines[] = $line;
            continue;
        }
    }

    $lines = \array_values(\array_unique($lines));
    \sort($lines, \SORT_NUMERIC);

    return $lines;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_function_call_name(array $tokens, int $i): ?string
{
    $token = $tokens[$i];

    if (!\is_array($token)) {
        return null;
    }

    $id = $token[0];
    $text = (string)$token[1];

    $allowedNameTokens = [\T_STRING];

    if (\defined('T_NAME_FULLY_QUALIFIED')) {
        $allowedNameTokens[] = \T_NAME_FULLY_QUALIFIED;
    }
    if (\defined('T_NAME_QUALIFIED')) {
        $allowedNameTokens[] = \T_NAME_QUALIFIED;
    }

    if (!\in_array($id, $allowedNameTokens, true)) {
        return null;
    }

    $name = \strtolower(\ltrim($text, '\\'));

    if (\str_contains($name, '\\')) {
        return null;
    }

    if (!coretsia_atomic_write_gate_next_non_ws_token_is($tokens, $i, '(')) {
        return null;
    }

    $prev = coretsia_atomic_write_gate_prev_non_ws_index($tokens, $i);

    if ($prev !== null) {
        $prevToken = $tokens[$prev];

        if (\is_array($prevToken)) {
            if (\in_array($prevToken[0], [\T_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION, \T_NEW], true)) {
                return null;
            }
        } elseif ($prevToken === '\\') {
            // \fopen(...): allowed global function call.
        }
    }

    return $name;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_fopen_mode_is_write_or_unknown(array $tokens, int $callIndex): bool
{
    $open = coretsia_atomic_write_gate_next_non_ws_index($tokens, $callIndex);
    if ($open === null || coretsia_atomic_write_gate_token_text($tokens[$open]) !== '(') {
        return true;
    }

    $comma = coretsia_atomic_write_gate_find_next_top_level_comma($tokens, $open);
    if ($comma === null) {
        return true;
    }

    $modeIndex = coretsia_atomic_write_gate_next_non_ws_index($tokens, $comma);
    if ($modeIndex === null) {
        return true;
    }

    $mode = coretsia_atomic_write_gate_string_literal_value($tokens[$modeIndex] ?? null);

    if ($mode === null) {
        return true;
    }

    return !coretsia_atomic_write_gate_is_read_only_fopen_mode($mode);
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_new_splfileobject_mode_is_write_or_unknown(array $tokens, int $newIndex): bool
{
    $classIndex = coretsia_atomic_write_gate_next_non_ws_index($tokens, $newIndex);
    if ($classIndex === null) {
        return false;
    }

    $className = coretsia_atomic_write_gate_read_class_name($tokens, $classIndex);
    if ($className === null || \strtolower(\ltrim($className, '\\')) !== 'splfileobject') {
        return false;
    }

    $open = coretsia_atomic_write_gate_next_non_ws_index($tokens, $classIndex);
    if ($open === null || coretsia_atomic_write_gate_token_text($tokens[$open]) !== '(') {
        return true;
    }

    $comma = coretsia_atomic_write_gate_find_next_top_level_comma($tokens, $open);
    if ($comma === null) {
        // Default SplFileObject mode is read-only.
        return false;
    }

    $modeIndex = coretsia_atomic_write_gate_next_non_ws_index($tokens, $comma);
    if ($modeIndex === null) {
        return true;
    }

    $mode = coretsia_atomic_write_gate_string_literal_value($tokens[$modeIndex] ?? null);

    if ($mode === null) {
        return true;
    }

    return !coretsia_atomic_write_gate_is_read_only_fopen_mode($mode);
}

function coretsia_atomic_write_gate_is_read_only_fopen_mode(string $mode): bool
{
    $mode = \strtolower(\trim($mode));

    return \in_array($mode, ['r', 'rb', 'rt'], true);
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_read_class_name(array $tokens, int $start): ?string
{
    $out = '';
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        $t = $tokens[$i];

        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }

        if (\is_array($t)) {
            $id = $t[0];
            $text = (string)$t[1];

            if ($id === \T_STRING) {
                $out .= $text;
                continue;
            }

            if (\defined('T_NAME_FULLY_QUALIFIED') && $id === \T_NAME_FULLY_QUALIFIED) {
                return $text;
            }

            if (\defined('T_NAME_QUALIFIED') && $id === \T_NAME_QUALIFIED) {
                return $text;
            }
        }

        if ((string)$t === '\\') {
            $out .= '\\';
            continue;
        }

        break;
    }

    return $out !== '' ? $out : null;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_find_next_top_level_comma(array $tokens, int $openIndex): ?int
{
    $depth = 0;
    $count = \count($tokens);

    for ($i = $openIndex; $i < $count; $i++) {
        $text = coretsia_atomic_write_gate_token_text($tokens[$i]);

        if ($text === '(' || $text === '[' || $text === '{') {
            $depth++;
            continue;
        }

        if ($text === ')' || $text === ']' || $text === '}') {
            $depth--;

            if ($depth <= 0) {
                return null;
            }

            continue;
        }

        if ($text === ',' && $depth === 1) {
            return $i;
        }
    }

    return null;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_next_non_ws_index(array $tokens, int $i): ?int
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
function coretsia_atomic_write_gate_prev_non_ws_index(array $tokens, int $i): ?int
{
    for ($j = $i - 1; $j >= 0; $j--) {
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
function coretsia_atomic_write_gate_next_non_ws_token_is(array $tokens, int $i, string $expected): bool
{
    $j = coretsia_atomic_write_gate_next_non_ws_index($tokens, $i);

    return $j !== null && coretsia_atomic_write_gate_token_text($tokens[$j]) === $expected;
}

function coretsia_atomic_write_gate_token_text(mixed $token): string
{
    if (\is_array($token)) {
        return (string)$token[1];
    }

    return (string)$token;
}

function coretsia_atomic_write_gate_string_literal_value(mixed $token): ?string
{
    if (!\is_array($token) || $token[0] !== \T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }

    $literal = (string)$token[1];

    if (\strlen($literal) < 2) {
        return null;
    }

    $quote = $literal[0];
    $inner = \substr($literal, 1, -1);

    if ($quote === "'") {
        return \str_replace(["\\\\", "\\'"], ['\\', "'"], $inner);
    }

    if ($quote === '"') {
        return \stripcslashes($inner);
    }

    return null;
}

function coretsia_atomic_write_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $contents = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($contents)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $contents;
}

function coretsia_atomic_write_gate_is_absolute_path(string $path): bool
{
    $path = \trim($path);

    if ($path === '') {
        return false;
    }

    if (\str_starts_with($path, '/') || \str_starts_with($path, '\\')) {
        return true;
    }

    return \preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) === 1;
}

function coretsia_atomic_write_gate_is_valid_framework_relative_path(string $path): bool
{
    if ($path === '' || coretsia_atomic_write_gate_is_absolute_path($path)) {
        return false;
    }

    if (\str_contains($path, '\\') || \str_contains($path, "\0")) {
        return false;
    }

    if (\str_contains($path, '*') || \str_contains($path, '?') || \str_contains($path, '[') || \str_contains(
        $path,
        ']'
    )) {
        return false;
    }

    if (
        $path === '.'
        || $path === '..'
        || \str_starts_with($path, './')
        || \str_starts_with($path, '../')
        || \str_contains($path, '/./')
        || \str_contains($path, '/../')
        || \str_ends_with($path, '/.')
        || \str_ends_with($path, '/..')
        || \str_contains($path, '//')
    ) {
        return false;
    }

    return \preg_match('/\A[A-Za-z0-9._\/-]+\z/', $path) === 1;
}

function coretsia_atomic_write_gate_display_path(string $absPath, string $scanRoot, string $frameworkRoot): string
{
    $frameworkRelative = coretsia_atomic_write_gate_rel_from_framework_or_null($absPath, $frameworkRoot);
    if ($frameworkRelative !== null) {
        return $frameworkRelative;
    }

    $scanRoot = \rtrim(\str_replace('\\', '/', $scanRoot), '/');
    $absPath = \str_replace('\\', '/', $absPath);

    if ($scanRoot !== '' && \str_starts_with($absPath, $scanRoot . '/')) {
        $relative = \substr($absPath, \strlen($scanRoot) + 1);
        $relative = \ltrim($relative, '/');

        if (coretsia_atomic_write_gate_is_valid_framework_relative_path($relative)) {
            return $relative;
        }
    }

    throw new \RuntimeException('display-path-invalid');
}

function coretsia_atomic_write_gate_rel_from_framework_or_null(string $absPath, string $frameworkRoot): ?string
{
    $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');
    $absPath = \str_replace('\\', '/', $absPath);

    if ($frameworkRoot !== '' && \str_starts_with($absPath, $frameworkRoot . '/')) {
        $relative = \substr($absPath, \strlen($frameworkRoot) + 1);

        if (coretsia_atomic_write_gate_is_valid_framework_relative_path($relative)) {
            return $relative;
        }
    }

    return null;
}

function coretsia_atomic_write_gate_error_code_or_fallback(
    string $errorCodesFqcn,
    string $constantName,
    string $fallback,
): string {
    $name = $errorCodesFqcn . '::' . $constantName;

    if (\defined($name)) {
        /** @var string $code */
        $code = \constant($name);

        return $code;
    }

    return $fallback;
}
