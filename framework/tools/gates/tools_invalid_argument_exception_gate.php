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

            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = \defined($ErrorCodes . '::CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED')
                    ? (string)\constant($ErrorCodes . '::CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED')
                    : 'CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED';

                $ConsoleOutput::codeWithDiagnostics($code, []);
            } else {
                $ConsoleOutput::codeWithDiagnostics('CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED', []);
            }
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
        $name = $ErrorCodes . '::CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_FORBIDDEN';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return 'CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_FORBIDDEN';
    })();

    $codeScanFailed = (static function () use ($ErrorCodes): string {
        $name = $ErrorCodes . '::CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return 'CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED';
    })();

    // Allowlist: ONLY these files may throw InvalidArgumentException directly.
    $allowlisted = [
        'build/sync_composer_repositories.php' => true,
        'spikes/_support/DeterministicException.php' => true,
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

        if (!\is_dir($scanRoot)) {
            throw new \RuntimeException('scan-root-invalid');
        }

        // Policy: scan root MUST be inside tools root (prevents path leaks + keeps rel paths stable).
        if (!coretsia_tools_iae_is_within_root($toolsRootRuntime, $scanRoot)) {
            throw new \RuntimeException('scan-root-outside-tools-root');
        }

        $violations = []; // list<array{path:string, reason:string}>
        $files = coretsia_tools_iae_list_php_files($toolsRootRuntime, $scanRoot);

        foreach ($files as $absFile => $relPath) {
            if (coretsia_tools_iae_is_excluded_path($relPath)) {
                continue;
            }

            // Allowlisted files: skip detection entirely (explicit contract).
            if (isset($allowlisted[$relPath])) {
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

            if (coretsia_tools_iae_detect_throw_new_invalid_argument_exception($tokens)) {
                $violations[] = [
                    'path' => $relPath,
                    'reason' => 'throw-new-InvalidArgumentException',
                ];
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
 * Normalize for prefix checks:
 * - "/" separators
 * - on Windows: case-fold to lower to avoid drive-letter case mismatch from realpath()
 */
function coretsia_tools_iae_normalize_for_prefix_check(string $path): string
{
    $path = \str_replace('\\', '/', $path);
    $path = \rtrim($path, '/');

    if (\PHP_OS_FAMILY === 'Windows') {
        return \strtolower($path);
    }

    return $path;
}

function coretsia_tools_iae_is_within_root(string $rootAbs, string $candidateAbs): bool
{
    $rootReal = \realpath($rootAbs);
    $candReal = \realpath($candidateAbs);

    if ($rootReal === false || $candReal === false) {
        return false;
    }

    $root = coretsia_tools_iae_normalize_for_prefix_check((string)$rootReal);
    $cand = coretsia_tools_iae_normalize_for_prefix_check((string)$candReal);

    if ($root === '' || $cand === '') {
        return false;
    }

    return $cand === $root || \str_starts_with($cand . '/', $root . '/');
}

/**
 * @return array<string,string> map absPath => tools-root-relative normalized path (forward slashes),
 *                             iteration order stable by rel path strcmp.
 */
function coretsia_tools_iae_list_php_files(string $toolsRootAbs, string $scanRootAbs): array
{
    $toolsRootReal = \realpath($toolsRootAbs);
    $scanRootReal = \realpath($scanRootAbs);

    if ($toolsRootReal === false || $scanRootReal === false) {
        throw new \RuntimeException('invalid-root');
    }

    $toolsNorm = \rtrim(\str_replace('\\', '/', (string)$toolsRootReal), '/');
    if ($toolsNorm === '') {
        throw new \RuntimeException('invalid-tools-root');
    }

    $toolsNormCmp = coretsia_tools_iae_normalize_for_prefix_check($toolsNorm);

    $out = [];

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator((string)$scanRootReal, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY,
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

        $absReal = \realpath($abs);
        if ($absReal === false) {
            throw new \RuntimeException('scan-failed');
        }

        $absNorm = \str_replace('\\', '/', (string)$absReal);
        $absNormCmp = coretsia_tools_iae_normalize_for_prefix_check($absNorm);

        if (!\str_starts_with($absNormCmp, $toolsNormCmp . '/')) {
            // Should never happen if scanRoot is within toolsRoot; still deterministic failure.
            throw new \RuntimeException('scan-failed');
        }

        $rel = \substr($absNorm, \strlen($toolsNorm) + 1);
        if (!\is_string($rel) || $rel === '') {
            continue;
        }

        $out[(string)$absReal] = $rel;
    }

    \uasort(
        $out,
        static fn(string $a, string $b): int => \strcmp($a, $b)
    );

    return $out;
}

function coretsia_tools_iae_is_excluded_path(string $relPath): bool
{
    // Exclude tests/fixtures anywhere under tools root.
    if (\preg_match('#(^|/)tests/#', $relPath) === 1) {
        return true;
    }
    if (\preg_match('#(^|/)fixtures/#', $relPath) === 1) {
        return true;
    }

    return false;
}

function coretsia_tools_iae_is_ignorable_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    return $token[0] === \T_WHITESPACE
        || $token[0] === \T_COMMENT
        || $token[0] === \T_DOC_COMMENT;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_tools_iae_next_non_ignorable_index(array $tokens, int $from): ?int
{
    $n = \count($tokens);
    for ($i = $from; $i < $n; $i++) {
        if (coretsia_tools_iae_is_ignorable_token($tokens[$i])) {
            continue;
        }
        return $i;
    }
    return null;
}

function coretsia_tools_iae_is_name_token(int $id): bool
{
    return $id === \T_STRING
        || $id === (\defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1)
        || $id === (\defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1)
        || $id === (\defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1);
}

function coretsia_tools_iae_last_name_segment(string $name): string
{
    $name = \ltrim($name, '\\');
    $parts = \explode('\\', $name);
    $last = $parts[\count($parts) - 1] ?? $name;
    return (string)$last;
}

/**
 * Detect pattern: `throw new \InvalidArgumentException(...)` (and unqualified variants).
 *
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_tools_iae_detect_throw_new_invalid_argument_exception(array $tokens): bool
{
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t) || $t[0] !== \T_THROW) {
            continue;
        }

        $j = coretsia_tools_iae_next_non_ignorable_index($tokens, $i + 1);
        if ($j === null) {
            continue;
        }

        // Allow optional "(" right after throw: `throw (new ...)`
        if ($tokens[$j] === '(') {
            $j = coretsia_tools_iae_next_non_ignorable_index($tokens, $j + 1);
            if ($j === null) {
                continue;
            }
        }

        $tok = $tokens[$j];
        if (!\is_array($tok) || $tok[0] !== \T_NEW) {
            continue;
        }

        $k = coretsia_tools_iae_next_non_ignorable_index($tokens, $j + 1);
        if ($k === null) {
            continue;
        }

        $nameTok = $tokens[$k];
        if (!\is_array($nameTok) || !coretsia_tools_iae_is_name_token((int)$nameTok[0])) {
            continue;
        }

        $raw = (string)($nameTok[1] ?? '');
        if ($raw === '') {
            continue;
        }

        $last = coretsia_tools_iae_last_name_segment($raw);

        if (\strcasecmp($last, 'InvalidArgumentException') === 0) {
            return true;
        }
    }

    return false;
}
