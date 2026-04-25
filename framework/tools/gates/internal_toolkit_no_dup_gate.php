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

            $code = 'CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED';

            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_tools_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED',
                    $code,
                );
            }

            $ConsoleOutput::codeWithDiagnostics($code, []);
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

    // Policy: owned determinism symbol names must not be declared under framework/tools/**
    $forbiddenNames = [
        'toStudly',
        'toSnake',
        'normalizeRelative',
        'encodeStable',
    ];

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

        $violations = []; // list<array{path: string, symbol: string}>
        $hasJsonEncode = false;
        $hasDuplication = false;

        $files = coretsia_tools_list_php_files($scanRoot);

        foreach ($files as $absFile => $relPath) {
            if (coretsia_tools_is_excluded_path($relPath)) {
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
                return \token_get_all($code);
            });

            // Wrapper allowlist is evaluated on scan-root-relative path.
            $isWrapperAllowlisted = coretsia_tools_is_wrapper_allowlisted($relPath);
            $isJsonEncodeAllowlisted = coretsia_tools_is_json_encode_allowlisted($relPath);

            // 1) Forbidden json_encode(...) calls (path-bound allowlist exception exists)
            if (coretsia_tools_detect_json_encode_call($tokens)) {
                if (!$isJsonEncodeAllowlisted) {
                    $hasJsonEncode = true;
                    $violations[] = ['path' => $relPath, 'symbol' => 'json_encode'];
                }
            }

            // 2) Forbidden symbol declarations (function/method names)
            $declared = coretsia_tools_find_declared_function_like_names($tokens);

            foreach ($declared as $name) {
                if (!\in_array($name, $forbiddenNames, true)) {
                    continue;
                }

                // Thin wrapper exception: encodeStable is allowed only in allowlisted wrapper file.
                if ($isWrapperAllowlisted && $name === 'encodeStable') {
                    continue;
                }

                $hasDuplication = true;
                $violations[] = ['path' => $relPath, 'symbol' => $name];
            }

            // 3) Wrapper delegation rule (token-pattern presence only; no body heuristics)
            if ($isWrapperAllowlisted && \in_array('encodeStable', $declared, true)) {
                if (!coretsia_tools_detect_internal_toolkit_encode_stable_call($tokens)) {
                    $hasDuplication = true;
                    $violations[] = ['path' => $relPath, 'symbol' => 'encodeStable-wrapper-must-delegate'];
                }
            }
        }

        if ($violations === []) {
            exit(0);
        }

        // Deterministic precedence-based CODE selection:
        // scan-failed handled by catch; else json-forbidden > duplication
        $codeOut = $hasJsonEncode
            ? $ErrorCodes::CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN
            : $ErrorCodes::CORETSIA_TOOLKIT_DUPLICATION_DETECTED;

        // Diagnostics must be exhaustive, sorted by path strcmp; tie-break by symbol strcmp.
        \usort(
            $violations,
            static function (array $a, array $b): int {
                $c = \strcmp($a['path'], $b['path']);
                if ($c !== 0) {
                    return $c;
                }
                return \strcmp($a['symbol'], $b['symbol']);
            }
        );

        /** @var list<string> $diagnostics */
        $diagnostics = [];
        foreach ($violations as $v) {
            $diagnostics[] = $v['path'] . ': ' . $v['symbol'];
        }

        $ConsoleOutput::codeWithDiagnostics((string)$codeOut, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        // Deterministic failure: no leaks.
        if (\class_exists($ConsoleOutput)) {
            $codeOut = coretsia_tools_error_code_or_fallback(
                $ErrorCodes,
                'CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED',
                'CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED',
            );

            $ConsoleOutput::codeWithDiagnostics($codeOut, []);
        }

        exit(1);
    }
})(isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []);

/**
 * @return array<string,string> map absPath => scan-root-relative normalized path (forward slashes),
 *                             iteration order stable by rel path strcmp.
 */
function coretsia_tools_list_php_files(string $scanRoot): array
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

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($scanRootReal, \FilesystemIterator::SKIP_DOTS)
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
        static fn (string $a, string $b): int => \strcmp($a, $b)
    );

    return $out;
}

function coretsia_tools_is_excluded_path(string $relPath): bool
{
    if (\preg_match('#(^|/)tests/#', $relPath) === 1) {
        return true;
    }
    if (\preg_match('#(^|/)fixtures/#', $relPath) === 1) {
        return true;
    }

    return false;
}

function coretsia_tools_is_wrapper_allowlisted(string $relPath): bool
{
    // Allowlist pattern: spikes/*/StableJsonEncoder.php (scan-root-relative)
    return \preg_match('#\Aspikes/[^/]+/StableJsonEncoder\.php\z#', $relPath) === 1;
}

function coretsia_tools_is_json_encode_allowlisted(string $relPath): bool
{
    // Allow exactly one exception file that MAY call json_encode(...).
    // Path is evaluated as scan-root-relative (tools-root-relative in real repo).
    // MUST NOT be generalized.
    return $relPath === 'spikes/workspace/ComposerJsonCanonicalizer.php';
}

/**
 * @return list<string> declared function-like names from `function <name>(...)` (covers functions and methods).
 */
function coretsia_tools_find_declared_function_like_names(array $tokens): array
{
    $names = [];
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t) || $t[0] !== \T_FUNCTION) {
            continue;
        }

        $j = $i + 1;
        $j = coretsia_tools_skip_trivia($tokens, $j);

        if ($j < $n && $tokens[$j] === '&') {
            $j++;
            $j = coretsia_tools_skip_trivia($tokens, $j);
        }

        if ($j < $n && \is_array($tokens[$j]) && $tokens[$j][0] === \T_STRING) {
            $names[] = (string)$tokens[$j][1];
        }
    }

    return $names;
}

function coretsia_tools_skip_trivia(array $tokens, int $i): int
{
    $n = \count($tokens);
    while ($i < $n) {
        $t = $tokens[$i];
        if (\is_array($t) && ($t[0] === \T_WHITESPACE || $t[0] === \T_COMMENT || $t[0] === \T_DOC_COMMENT)) {
            $i++;
            continue;
        }
        break;
    }
    return $i;
}

/**
 * Token-based detection of forbidden json_encode(...) calls:
 * - token type: T_STRING | T_NAME_QUALIFIED | T_NAME_FULLY_QUALIFIED
 * - last segment equals "json_encode" (case-insensitive)
 * - next non-whitespace token is "("
 * - ignore if immediately preceded (ignoring whitespace) by T_OBJECT_OPERATOR or T_DOUBLE_COLON
 */
function coretsia_tools_detect_json_encode_call(array $tokens): bool
{
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t)) {
            continue;
        }

        $type = $t[0];
        if ($type !== \T_STRING && $type !== \T_NAME_QUALIFIED && $type !== \T_NAME_FULLY_QUALIFIED) {
            continue;
        }

        $raw = \ltrim((string)$t[1], '\\');
        $parts = \explode('\\', $raw);
        $last = $parts[\count($parts) - 1];

        if (\strtolower((string)$last) !== 'json_encode') {
            continue;
        }

        $prev = coretsia_tools_prev_non_ws_token($tokens, $i);
        if ($prev !== null && \is_array($prev) && ($prev[0] === \T_OBJECT_OPERATOR || $prev[0] === \T_DOUBLE_COLON)) {
            continue;
        }

        $next = coretsia_tools_next_non_ws_token($tokens, $i);
        if ($next === '(') {
            return true;
        }
    }

    return false;
}

function coretsia_tools_prev_non_ws_token(array $tokens, int $i): array|string|null
{
    for ($j = $i - 1; $j >= 0; $j--) {
        $t = $tokens[$j];
        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }
        return $t;
    }
    return null;
}

function coretsia_tools_next_non_ws_token(array $tokens, int $i): array|string|null
{
    $n = \count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }
        return $t;
    }
    return null;
}

/**
 * Wrapper delegation check (token-pattern only):
 * detect: \Coretsia\Devtools\InternalToolkit\Json::encodeStable(
 */
function coretsia_tools_detect_internal_toolkit_encode_stable_call(array $tokens): bool
{
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t)) {
            continue;
        }

        if ($t[0] !== \T_NAME_QUALIFIED && $t[0] !== \T_NAME_FULLY_QUALIFIED) {
            continue;
        }

        $name = \ltrim((string)$t[1], '\\');
        if ($name !== 'Coretsia\\Devtools\\InternalToolkit\\Json') {
            continue;
        }

        $j = $i + 1;
        $j = coretsia_tools_skip_ws_only($tokens, $j);

        if ($j >= $n) {
            continue;
        }

        $tok = $tokens[$j];
        if (!(\is_array($tok) && $tok[0] === \T_DOUBLE_COLON) && $tok !== '::') {
            continue;
        }

        $j++;
        $j = coretsia_tools_skip_ws_only($tokens, $j);

        if ($j >= $n || !\is_array($tokens[$j]) || $tokens[$j][0] !== \T_STRING) {
            continue;
        }

        if ((string)$tokens[$j][1] !== 'encodeStable') {
            continue;
        }

        $j++;
        $j = coretsia_tools_skip_ws_only($tokens, $j);

        if ($j < $n && $tokens[$j] === '(') {
            return true;
        }
    }

    return false;
}

function coretsia_tools_skip_ws_only(array $tokens, int $i): int
{
    $n = \count($tokens);
    while ($i < $n) {
        $t = $tokens[$i];
        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            $i++;
            continue;
        }
        break;
    }
    return $i;
}

function coretsia_tools_error_code_or_fallback(string $errorCodesFqcn, string $constantName, string $fallback): string
{
    $name = $errorCodesFqcn . '::' . $constantName;

    if (\defined($name)) {
        /** @var string $code */
        $code = \constant($name);

        return $code;
    }

    return $fallback;
}
