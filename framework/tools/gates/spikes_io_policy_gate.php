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
        // Cannot locate tools root deterministically; no safe output channel guaranteed.
        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';

    /** @var class-string $ConsoleOutput */
    $ConsoleOutput = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';

    /** @var class-string $ErrorCodes */
    $ErrorCodes = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

    // Bootstrap MUST be loaded before scanning.
    // If bootstrap is missing/unreadable -> must use local ConsoleOutput include, print SCAN_FAILED and exit 1.
    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $scanFailed = \is_file($errorCodesFile) && \is_readable($errorCodesFile)
                ? (static function () use ($errorCodesFile, $ErrorCodes): string {
                    require_once $errorCodesFile;

                    $name = $ErrorCodes . '::CORETSIA_SPIKES_IO_POLICY_GATE_SCAN_FAILED';
                    if (\defined($name)) {
                        /** @var string $v */
                        $v = \constant($name);
                        return $v;
                    }

                    return 'CORETSIA_SPIKES_IO_POLICY_GATE_SCAN_FAILED';
                })()
                : 'CORETSIA_SPIKES_IO_POLICY_GATE_SCAN_FAILED';

            $ConsoleOutput::codeWithDiagnostics($scanFailed, []);
        }

        exit(1);
    }

    // NOTE (cemented): if bootstrap exists but terminates the process, its output is authoritative.
    require_once $bootstrap;

    // Output MUST be emitted only via runtime ConsoleOutput (tools-root based).
    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }
    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes): string {
        $name = $ErrorCodes . '::CORETSIA_SPIKES_IO_POLICY_VIOLATION';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return 'CORETSIA_SPIKES_IO_POLICY_VIOLATION';
    })();

    $codeScanFailed = (static function () use ($ErrorCodes): string {
        $name = $ErrorCodes . '::CORETSIA_SPIKES_IO_POLICY_GATE_SCAN_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return 'CORETSIA_SPIKES_IO_POLICY_GATE_SCAN_FAILED';
    })();

    try {
        // --path=<dir> overrides scan root only (does NOT affect bootstrap discovery/loading).
        $scanRoot = $toolsRootRuntime;

        foreach ($argv as $arg) {
            if (!\is_string($arg)) {
                continue;
            }
            if (!\str_starts_with($arg, '--path=')) {
                continue;
            }

            $candidate = \substr($arg, \strlen('--path='));
            $rp = $withSuppressedErrors(static function () use ($candidate): ?string {
                $p = \realpath($candidate);
                return \is_string($p) ? $p : null;
            });

            if ($rp === null) {
                throw new \RuntimeException('invalid-scan-root');
            }

            $scanRoot = $rp;
            break;
        }

        if (!\is_dir($scanRoot)) {
            throw new \RuntimeException('scan-root-invalid');
        }

        $spikesDir = $scanRoot . DIRECTORY_SEPARATOR . 'spikes';
        if (!\is_dir($spikesDir)) {
            throw new \RuntimeException('spikes-dir-missing');
        }

        $violations = []; // list<array{path:string, reason:string}>
        $files = coretsia_spikes_io_list_php_files($scanRoot);

        foreach ($files as $absFile => $relPath) {
            if (coretsia_spikes_io_is_excluded_path($relPath)) {
                continue;
            }

            $code = $withSuppressedErrors(static function () use ($absFile): ?string {
                if (!\is_file($absFile) || !\is_readable($absFile)) {
                    return null;
                }
                $c = \file_get_contents($absFile);
                return \is_string($c) ? $c : null;
            });

            if ($code === null) {
                throw new \RuntimeException('read-failed');
            }

            $tokens = $withSuppressedErrors(static function () use ($code): array {
                return \token_get_all($code, \TOKEN_PARSE);
            });

            $hits = coretsia_spikes_io_detect_policy_violations($tokens);

            foreach ($hits as $reason) {
                $violations[] = ['path' => $relPath, 'reason' => $reason];
            }
        }

        if ($violations === []) {
            exit(0);
        }

        // Diagnostics must be exhaustive, sorted by path strcmp; tie-break by reason strcmp.
        \usort(
            $violations,
            static function (array $a, array $b): int {
                $c = \strcmp((string)$a['path'], (string)$b['path']);
                if ($c !== 0) {
                    return $c;
                }
                return \strcmp((string)$a['reason'], (string)$b['reason']);
            }
        );

        /** @var list<string> $diagnostics */
        $diagnostics = [];
        foreach ($violations as $v) {
            $diagnostics[] = (string)$v['path'] . ': ' . (string)$v['reason'];
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        // Deterministic scan failure: no leaks.
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }
        exit(1);
    }
})(isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []);

/**
 * @return array<string,string> map absPath => scan-root-relative normalized path (forward slashes),
 *                             iteration order stable by rel path strcmp.
 */
function coretsia_spikes_io_list_php_files(string $scanRoot): array
{
    $scanRootReal = \realpath($scanRoot);
    if ($scanRootReal === false) {
        throw new \RuntimeException('invalid-scan-root');
    }

    $scanRootNorm = \rtrim(\str_replace('\\', '/', $scanRootReal), '/');
    if ($scanRootNorm === '') {
        throw new \RuntimeException('invalid-scan-root');
    }

    $out = [];

    $base = $scanRootReal . DIRECTORY_SEPARATOR . 'spikes';
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $info) {
        if (!$info instanceof \SplFileInfo) {
            continue;
        }
        if (!$info->isFile()) {
            continue;
        }
        if ($info->isLink()) {
            continue;
        }

        $name = $info->getFilename();
        if (!\is_string($name) || !\str_ends_with($name, '.php')) {
            continue;
        }

        $abs = $info->getPathname();
        if (!\is_string($abs) || $abs === '') {
            continue;
        }

        $absNorm = \str_replace('\\', '/', $abs);

        if (!\str_starts_with($absNorm, $scanRootNorm)) {
            continue;
        }

        $rel = \substr($absNorm, \strlen($scanRootNorm));
        $rel = \ltrim((string)$rel, '/');

        $out[$abs] = $rel;
    }

    \uasort(
        $out,
        static fn(string $a, string $b): int => \strcmp($a, $b)
    );

    return $out;
}

function coretsia_spikes_io_is_excluded_path(string $relPath): bool
{
    // Single-choice excludes:
    // - spikes/tests/**
    // - spikes/fixtures/**
    // - spikes/_support/**
    if (\str_starts_with($relPath, 'spikes/tests/')) {
        return true;
    }
    if (\str_starts_with($relPath, 'spikes/fixtures/')) {
        return true;
    }
    if (\str_starts_with($relPath, 'spikes/_support/')) {
        return true;
    }

    return false;
}

/**
 * Detect forbidden IO micro-implementations (token-based).
 *
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 * @return list<string> reasons (unique, sorted by strcmp)
 */
function coretsia_spikes_io_detect_policy_violations(array $tokens): array
{
    $forbiddenCalls = [
        'file_get_contents' => true,
        'file_put_contents' => true,
        'fopen' => true,
        'fread' => true,
        'fwrite' => true,
        'fputs' => true,
        'fprintf' => true,
        'stream_get_contents' => true,
        'readfile' => true,
        'file' => true,
        'hash_file' => true,
        'md5_file' => true,
        'sha1_file' => true,
    ];

    $out = [];
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t)) {
            continue;
        }

        $id = (int)$t[0];
        if (!coretsia_spikes_io_is_callable_name_token($id)) {
            continue;
        }

        $rawName = (string)$t[1];
        $fn = coretsia_spikes_io_last_name_segment($rawName);
        $fnLower = \strtolower($fn);

        if (!coretsia_spikes_io_is_call_site($tokens, $i)) {
            continue;
        }

        if (isset($forbiddenCalls[$fnLower])) {
            $out['forbidden-io:' . $fnLower] = true;
            continue;
        }

        if ($fnLower === 'str_replace' || $fnLower === 'preg_replace') {
            $openIdx = coretsia_spikes_io_next_non_ignorable_index($tokens, $i + 1);
            if ($openIdx === null || $tokens[$openIdx] !== '(') {
                continue;
            }

            $lits = coretsia_spikes_io_collect_string_literals_in_call_args($tokens, $openIdx);

            $hasCr = false;
            $hasLf = false;

            foreach ($lits as $s) {
                if (\str_contains($s, "\r")) {
                    $hasCr = true;
                }
                if (\str_contains($s, "\n")) {
                    $hasLf = true;
                }
                if ($hasCr && $hasLf) {
                    break;
                }
            }

            if ($hasCr && $hasLf) {
                $out['forbidden-eol-normalization:' . $fnLower] = true;
            }
        }
    }

    $reasons = \array_keys($out);
    \usort($reasons, static fn(string $a, string $b): int => \strcmp($a, $b));

    /** @var list<string> $reasons */
    return $reasons;
}

function coretsia_spikes_io_is_callable_name_token(int $id): bool
{
    return $id === \T_STRING
        || $id === (\defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1)
        || $id === (\defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1)
        || $id === (\defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1);
}

function coretsia_spikes_io_last_name_segment(string $name): string
{
    $name = \ltrim($name, '\\');
    $parts = \explode('\\', $name);
    $last = $parts[\count($parts) - 1] ?? $name;
    return (string)$last;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_spikes_io_is_call_site(array $tokens, int $nameIndex): bool
{
    $prev = coretsia_spikes_io_prev_non_ignorable_index($tokens, $nameIndex - 1);
    if ($prev !== null && \is_array($tokens[$prev])) {
        $pid = (int)$tokens[$prev][0];
        if ($pid === \T_OBJECT_OPERATOR || $pid === \T_DOUBLE_COLON || $pid === \T_FUNCTION) {
            return false;
        }
    }

    $next = coretsia_spikes_io_next_non_ignorable_index($tokens, $nameIndex + 1);
    if ($next === null) {
        return false;
    }

    return $tokens[$next] === '(';
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_spikes_io_prev_non_ignorable_index(array $tokens, int $from): ?int
{
    for ($i = $from; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (coretsia_spikes_io_is_ignorable_token($t)) {
            continue;
        }
        return $i;
    }

    return null;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_spikes_io_next_non_ignorable_index(array $tokens, int $from): ?int
{
    $n = \count($tokens);
    for ($i = $from; $i < $n; $i++) {
        $t = $tokens[$i];
        if (coretsia_spikes_io_is_ignorable_token($t)) {
            continue;
        }
        return $i;
    }

    return null;
}

/**
 * @param array{0:int,1:string,2?:int}|string $token
 */
function coretsia_spikes_io_is_ignorable_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    return $token[0] === \T_WHITESPACE
        || $token[0] === \T_COMMENT
        || $token[0] === \T_DOC_COMMENT;
}

/**
 * Collect decoded string literals from a call argument list.
 *
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 * @return list<string>
 */
function coretsia_spikes_io_collect_string_literals_in_call_args(array $tokens, int $openParenIndex): array
{
    $n = \count($tokens);
    $depth = 0;
    $out = [];

    for ($i = $openParenIndex + 1; $i < $n; $i++) {
        $t = $tokens[$i];

        if ($t === '(') {
            $depth++;
            continue;
        }
        if ($t === ')') {
            if ($depth === 0) {
                break;
            }
            $depth--;
            continue;
        }

        if (!\is_array($t)) {
            continue;
        }

        if ($t[0] !== \T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $decoded = coretsia_spikes_io_decode_constant_string_literal((string)$t[1]);
        if ($decoded !== null) {
            $out[] = $decoded;
        }
    }

    return $out;
}

/**
 * @param string $tokenText
 * @return string|null
 */
function coretsia_spikes_io_decode_constant_string_literal(string $tokenText): ?string
{
    $len = \strlen($tokenText);
    if ($len < 2) {
        return null;
    }

    $q = $tokenText[0];
    if (($q !== '\'' && $q !== '"') || $tokenText[$len - 1] !== $q) {
        return null;
    }

    $inner = \substr($tokenText, 1, $len - 2);
    if (!\is_string($inner)) {
        return null;
    }

    if ($q === '\'') {
        // In single quotes, only \\ and \' are escaped.
        $inner = \str_replace(['\\\\', '\\\''], ['\\', '\''], $inner);
        return $inner;
    }

    return \stripcslashes($inner);
}
