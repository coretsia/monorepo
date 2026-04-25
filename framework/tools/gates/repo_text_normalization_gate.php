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
        coretsia_repo_text_safe_emit_scan_failed($consoleOutputFile);
        exit(1);
    }

    $scanRoot = coretsia_repo_text_resolve_scan_root($repoRootRuntime, $argv);
    if ($scanRoot === null) {
        coretsia_repo_text_safe_emit_scan_failed($consoleOutputFile);
        exit(1);
    }

    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        coretsia_repo_text_safe_emit_scan_failed($consoleOutputFile);
        exit(1);
    }

    // NOTE (cemented): if bootstrap terminates the process, its deterministic output is authoritative.
    require_once $bootstrap;

    if (!is_file($consoleOutputFile) || !is_readable($consoleOutputFile)) {
        exit(1);
    }

    // Output MUST be emitted only via runtime ConsoleOutput (tools-root based).
    require_once $consoleOutputFile;

    if (!is_file($errorCodesFile) || !is_readable($errorCodesFile)) {
        coretsia_repo_text_safe_emit_scan_failed($consoleOutputFile);
        exit(1);
    }

    // Canonical codes registry (tools-root based).
    require_once $errorCodesFile;

    if (!is_file($scannerFile) || !is_readable($scannerFile)) {
        coretsia_repo_text_safe_emit_scan_failed($consoleOutputFile);
        exit(1);
    }

    // Scanner (tools-root based).
    require_once $scannerFile;

    try {
        /** @var class-string $errorCodesFqcn */
        $errorCodesFqcn = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

        /** @var class-string $scannerFqcn */
        $scannerFqcn = 'Coretsia\\Tools\\Spikes\\_support\\RepoTextNormalizationScanner';

        /** @var list<string> $diagnostics */
        $diagnostics = $scannerFqcn::scan($repoRootRuntime, $scanRoot);

        $diagnostics = \array_values(\array_unique($diagnostics));
        \sort($diagnostics, \SORT_STRING);

        if ($diagnostics === []) {
            // Gate pass: silent.
            exit(0);
        }

        $out = [];
        $out[] = $errorCodesFqcn::CORETSIA_REPO_TEXT_POLICY_VIOLATION;
        foreach ($diagnostics as $line) {
            $out[] = $line;
        }

        coretsia_repo_text_safe_emit_lines($out);
        exit(1);
    } catch (Throwable) {
        coretsia_repo_text_safe_emit_lines(['CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED']);
        exit(1);
    }
})($argv ?? []);

/**
 * @param array $argv
 */
function coretsia_repo_text_parse_scan_root_override(array $argv): ?string
{
    $prefix = '--path=';

    foreach ($argv as $arg) {
        if (!is_string($arg)) {
            continue;
        }

        if (!str_starts_with($arg, $prefix)) {
            continue;
        }

        if (strlen($arg) === strlen($prefix)) {
            return null;
        }

        return substr($arg, strlen($prefix));
    }

    return null;
}

function coretsia_repo_text_resolve_scan_root(string $repoRoot, array $argv): ?string
{
    $override = coretsia_repo_text_parse_scan_root_override($argv);

    $scanRoot = $repoRoot;
    if ($override !== null) {
        $scanRoot = coretsia_repo_text_resolve_path_against_repo_root($repoRoot, $override);
    }

    $real = realpath($scanRoot);
    if ($real === false) {
        return null;
    }

    $repoReal = realpath($repoRoot);
    if ($repoReal === false) {
        return null;
    }

    $repoReal = rtrim(str_replace('\\', '/', $repoReal), '/');
    $realNorm = rtrim(str_replace('\\', '/', $real), '/');

    if ($realNorm !== $repoReal && !str_starts_with($realNorm . '/', $repoReal . '/')) {
        return null;
    }

    return $real;
}

function coretsia_repo_text_resolve_path_against_repo_root(string $repoRoot, string $path): string
{
    $p = str_replace('\\', '/', $path);

    if (str_starts_with($p, '/')) {
        return $path;
    }

    if (preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1 || str_starts_with($p, '//')) {
        return $path;
    }

    return $repoRoot . '/' . $p;
}

/**
 * @param string $consoleOutputFile absolute runtime tools path
 */
function coretsia_repo_text_safe_emit_scan_failed(string $consoleOutputFile): void
{
    if (!is_file($consoleOutputFile) || !is_readable($consoleOutputFile)) {
        return;
    }

    require_once $consoleOutputFile;

    coretsia_repo_text_safe_emit_lines([
        'CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED',
    ]);
}

/**
 * @param list<string> $lines
 */
function coretsia_repo_text_safe_emit_lines(array $lines): void
{
    if ($lines === []) {
        return;
    }

    /** @var class-string<\Coretsia\Tools\Spikes\_support\ConsoleOutput> $fqcn */
    $fqcn = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';

    if (!class_exists($fqcn)) {
        return;
    }

    $code = array_shift($lines);

    if (!is_string($code) || $code === '') {
        return;
    }

    $fqcn::codeWithDiagnostics($code, $lines);
}
