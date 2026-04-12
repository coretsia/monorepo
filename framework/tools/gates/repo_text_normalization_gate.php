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

    $repoRootRuntime = realpath($toolsRootRuntime . '/..' . '/..');
    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleOutputFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';
    $scannerFile = $toolsRootRuntime . '/spikes/_support/RepoTextNormalizationScanner.php';

    if ($repoRootRuntime === false) {
        safeEmitScanFailed($consoleOutputFile, ['repo_root_unresolvable']);
        exit(1);
    }

    $scanRoot = resolveScanRoot($repoRootRuntime, $argv);
    if ($scanRoot === null) {
        safeEmitScanFailed($consoleOutputFile, ['scan_root_invalid']);
        exit(1);
    }

    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        safeEmitScanFailed($consoleOutputFile, ['bootstrap_missing']);
        exit(1);
    }

    // NOTE (cemented): if bootstrap terminates the process, its deterministic output is authoritative.
    require_once $bootstrap;

    // Output MUST be emitted only via runtime ConsoleOutput (tools-root based).
    require_once $consoleOutputFile;

    // Canonical codes registry (tools-root based).
    require_once $errorCodesFile;

    // Scanner (tools-root based).
    require_once $scannerFile;

    try {
        /** @var class-string $errorCodesFqcn */
        $errorCodesFqcn = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

        /** @var class-string $scannerFqcn */
        $scannerFqcn = 'Coretsia\\Tools\\Spikes\\_support\\RepoTextNormalizationScanner';

        /** @var list<string> $diagnostics */
        $diagnostics = $scannerFqcn::scan($repoRootRuntime, $scanRoot);

        if ($diagnostics === []) {
            // Gate pass: silent.
            exit(0);
        }

        $out = [];
        $out[] = $errorCodesFqcn::CORETSIA_REPO_TEXT_POLICY_VIOLATION;
        foreach ($diagnostics as $line) {
            $out[] = $line;
        }

        safeEmitLines($out);
        exit(1);
    } catch (Throwable) {
        safeEmitLines(['CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED', 'scan_failed']);
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

function resolveScanRoot(string $repoRoot, array $argv): ?string
{
    $override = parseScanRootOverride($argv);

    $scanRoot = $repoRoot;
    if ($override !== null) {
        $scanRoot = resolvePathAgainstRepoRoot($repoRoot, $override);
    }

    $real = realpath($scanRoot);
    if (!is_string($real) || $real === '') {
        return null;
    }

    $repoReal = realpath($repoRoot);
    if (!is_string($repoReal) || $repoReal === '') {
        return null;
    }

    $repoReal = rtrim(str_replace('\\', '/', $repoReal), '/');
    $realNorm = rtrim(str_replace('\\', '/', $real), '/');

    // MUST be inside repo root.
    if ($realNorm !== $repoReal && !str_starts_with($realNorm . '/', $repoReal . '/')) {
        return null;
    }

    return $real;
}

function resolvePathAgainstRepoRoot(string $repoRoot, string $path): string
{
    $p = str_replace('\\', '/', $path);

    // Absolute (POSIX)
    if (str_starts_with($p, '/')) {
        return $path;
    }

    // Absolute (Windows drive / UNC)
    if (preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1 || str_starts_with($p, '//')) {
        return $path;
    }

    return $repoRoot . '/' . $p;
}

/**
 * @param string $consoleOutputFile absolute runtime tools path
 * @param list<string> $reasons
 */
function safeEmitScanFailed(string $consoleOutputFile, array $reasons = []): void
{
    if (is_file($consoleOutputFile) && is_readable($consoleOutputFile)) {
        require_once $consoleOutputFile;

        $lines = ['CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED'];
        foreach ($reasons as $r) {
            if (is_string($r) && $r !== '') {
                $lines[] = $r;
            }
        }

        safeEmitLines($lines);
        return;
    }

    // No safe output channel available.
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
