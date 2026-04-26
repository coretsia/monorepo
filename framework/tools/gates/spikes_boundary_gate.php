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

    // Bootstrap discovery/loading MUST be independent from --path (scan-only).
    if ($toolsRootRuntime === null) {
        // Best-effort local include (tools-only); do not leak absolute paths.
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED',
                [],
            );
        }

        exit(1);
    }

    /**
     * Parse --path=<dir> (scan root override only).
     */
    $scanRoot = $toolsRootRuntime;
    foreach ($argv as $arg) {
        if (!\is_string($arg)) {
            continue;
        }
        if (!\str_starts_with($arg, '--path=')) {
            continue;
        }

        $candidate = \substr($arg, \strlen('--path='));
        $resolved = $withSuppressedErrors(static function () use ($candidate): ?string {
            $p = \realpath($candidate);
            return \is_string($p) ? $p : null;
        });

        if ($resolved === null) {
            // Treat invalid scan root as scan failure (deterministic).
            $scanRoot = '';
        } else {
            $scanRoot = $resolved;
        }

        break;
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';

    // If bootstrap missing/unreadable: MUST print SCAN_FAILED using ConsoleOutput (tools-only include), exit 1.
    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        $consolePath = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
        if (\is_file($consolePath) && \is_readable($consolePath)) {
            require_once $consolePath;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED',
                [],
            );
        }

        exit(1);
    }

    // NOTE (cemented): if bootstrap exists but terminates (e.g. autoload missing), its output is authoritative.
    require_once $bootstrap;

    $consolePath = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesPath = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';

    if (
        !\is_file($consolePath)
        || !\is_readable($consolePath)
        || !\is_file($errorCodesPath)
        || !\is_readable($errorCodesPath)
    ) {
        if (\is_file($consolePath) && \is_readable($consolePath)) {
            require_once $consolePath;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED',
                [],
            );
        }

        exit(1);
    }

    require_once $consolePath;
    require_once $errorCodesPath;

    $ConsoleOutput = \Coretsia\Tools\Spikes\_support\ConsoleOutput::class;
    $ErrorCodes = \Coretsia\Tools\Spikes\_support\ErrorCodes::class;

    /**
     * Forbidden namespace roots (cemented).
     *
     * @var list<string> $forbiddenRoots
     */
    $forbiddenRoots = [
        'Coretsia\\Contracts',
        'Coretsia\\Foundation',
        'Coretsia\\Kernel',
        'Coretsia\\Core',
        'Coretsia\\Platform',
        'Coretsia\\Integrations',
        'Coretsia\\Devtools\\CliSpikes',
    ];

    /**
     * @return string scan-root-relative path with forward slashes
     */
    $toScanRootRelative = static function (string $absPath, string $scanRootAbs): string {
        $abs = \str_replace('\\', '/', $absPath);
        $root = \rtrim(\str_replace('\\', '/', $scanRootAbs), '/');

        if ($root === '') {
            return \str_replace('\\', '/', $absPath);
        }

        $prefix = $root . '/';
        if (\str_starts_with($abs, $prefix)) {
            return \substr($abs, \strlen($prefix));
        }

        // Fallback: return normalized basename-only (should not happen in normal scans).
        return \basename($abs);
    };

    /**
     * Excludes (single-choice; matched against scan-root-relative paths):
     * - spikes/tests/**
     * - spikes/fixtures/**
     * - gates/**\/tests/**
     * - gates/**\/fixtures/**
     * @param string $rel
     * @return bool
     */
    $isExcluded = static function (string $rel): bool {
        if (\str_starts_with($rel, 'spikes/tests/')) {
            return true;
        }
        if (\str_starts_with($rel, 'spikes/fixtures/')) {
            return true;
        }

        if (\str_starts_with($rel, 'gates/')) {
            if (\str_contains($rel, '/tests/')) {
                return true;
            }
            if (\str_contains($rel, '/fixtures/')) {
                return true;
            }
        }

        return false;
    };

    /**
     * Collect scanned PHP files under:
     * - $scanRoot/spikes/**\/*.php
     * - $scanRoot/gates/**\/*.php
     *
     * @param string $scanRootAbs
     * @return list<array{abs:string, rel:string}>
     */
    $collectFiles = static function (string $scanRootAbs) use ($toScanRootRelative, $isExcluded): array {
        $out = [];

        $roots = [
            $scanRootAbs . '/spikes',
            $scanRootAbs . '/gates',
        ];

        foreach ($roots as $base) {
            if (!\is_dir($base)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($it as $fi) {
                if (!$fi instanceof \SplFileInfo) {
                    continue;
                }
                if (!$fi->isFile()) {
                    continue;
                }

                $ext = $fi->getExtension();
                if (\strtolower((string)$ext) !== 'php') {
                    continue;
                }

                $abs = $fi->getPathname();
                if (!\is_string($abs) || $abs === '') {
                    continue;
                }

                $rel = $toScanRootRelative($abs, $scanRootAbs);
                $rel = \str_replace('\\', '/', $rel);

                if ($isExcluded($rel)) {
                    continue;
                }

                $out[] = ['abs' => $abs, 'rel' => $rel];
            }
        }

        // Deterministic ordering.
        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp((string)$a['rel'], (string)$b['rel']),
        );

        return $out;
    };

    /**
     * Extract content of T_CONSTANT_ENCAPSED_STRING (without surrounding quotes).
     * No unescaping; deterministic detection of path fragments only.
     */
    $extractStringLiteral = static function (string $tokenText): string {
        $len = \strlen($tokenText);
        if ($len >= 2) {
            $q1 = $tokenText[0];
            $q2 = $tokenText[$len - 1];
            if (($q1 === "'" && $q2 === "'") || ($q1 === '"' && $q2 === '"')) {
                return \substr($tokenText, 1, $len - 2);
            }
        }

        return $tokenText;
    };

    /**
     * Forbidden path literal means:
     * - normalize via str_replace('\\','/',$raw)
     * - forbidden if contains 'packages/' AND '/src/' in that order.
     */
    $isForbiddenPathLiteral = static function (string $raw): bool {
        $norm = \str_replace('\\', '/', $raw);

        $p1 = \strpos($norm, 'packages/');
        if ($p1 === false) {
            return false;
        }

        $p2 = \strpos($norm, '/src/');
        if ($p2 === false) {
            return false;
        }

        return $p2 > $p1;
    };

    /**
     * Token-based forbidden namespace detection (ALL roots, unique):
     * - only T_NAME_QUALIFIED | T_NAME_FULLY_QUALIFIED | T_NAME_RELATIVE
     * - normalize by stripping leading "\" if present
     * - match if token == root OR starts with root + "\"
     *
     * @return list<string> matched forbidden roots (unique, sorted by strcmp)
     */
    $detectForbiddenNamespaceRoots = static function (array $tokens) use ($forbiddenRoots): array {
        $nameTokenIds = [
            \defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1,
            \defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1,
            \defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1,
        ];

        $found = [];

        foreach ($tokens as $t) {
            if (!\is_array($t)) {
                continue;
            }

            $id = $t[0];
            if (!\in_array($id, $nameTokenIds, true)) {
                continue;
            }

            $val = (string)($t[1] ?? '');
            if ($val === '') {
                continue;
            }

            // Normalize: strip leading "\" if present.
            if (\str_starts_with($val, '\\')) {
                $val = \substr($val, 1);
            }

            foreach ($forbiddenRoots as $root) {
                if ($val === $root || \str_starts_with($val, $root . '\\')) {
                    $found[$root] = true;
                }
            }
        }

        $roots = \array_keys($found);
        \usort($roots, static fn (string $a, string $b): int => \strcmp($a, $b));

        /** @var list<string> $roots */
        return $roots;
    };

    /**
     * Token-based forbidden path include/require detection:
     * - inspect every require|require_once|include|include_once
     * - analyze argument tokens up to ';'
     * - extract all T_CONSTANT_ENCAPSED_STRING fragments
     * - candidate = concat fragments in source order
     * - violation if candidate OR ANY fragment matches forbidden-path definition
     */
    $detectForbiddenPathUsage = static function (array $tokens) use ($extractStringLiteral, $isForbiddenPathLiteral): bool {
        $includeIds = [
            \defined('T_REQUIRE') ? \T_REQUIRE : -1,
            \defined('T_REQUIRE_ONCE') ? \T_REQUIRE_ONCE : -1,
            \defined('T_INCLUDE') ? \T_INCLUDE : -1,
            \defined('T_INCLUDE_ONCE') ? \T_INCLUDE_ONCE : -1,
        ];

        $n = \count($tokens);

        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (!\is_array($t)) {
                continue;
            }

            if (!\in_array($t[0], $includeIds, true)) {
                continue;
            }

            $fragments = [];
            $j = $i + 1;

            for (; $j < $n; $j++) {
                $x = $tokens[$j];

                if ($x === ';') {
                    break;
                }

                if (\is_array($x) && $x[0] === \T_CONSTANT_ENCAPSED_STRING) {
                    $fragments[] = $extractStringLiteral((string)$x[1]);
                }
            }

            // If no semicolon found, treat as non-match and continue (still deterministic).
            if ($j >= $n) {
                continue;
            }

            if ($fragments !== []) {
                $candidate = \implode('', $fragments);

                if ($isForbiddenPathLiteral($candidate)) {
                    return true;
                }

                foreach ($fragments as $frag) {
                    if ($isForbiddenPathLiteral($frag)) {
                        return true;
                    }
                }
            }

            // Continue scanning after this statement terminator.
            $i = $j;
        }

        return false;
    };

    /**
     * Record violation (dedup by rel+reason).
     *
     * @param array<string, array{path:string, reason:string, i:int}> $acc
     */
    $recordViolation = static function (array &$acc, string $rel, string $reason, int &$seq): void {
        $key = $rel . "\n" . $reason;
        if (isset($acc[$key])) {
            return;
        }

        $acc[$key] = [
            'path' => $rel,
            'reason' => $reason,
            'i' => $seq,
        ];
        $seq++;
    };

    try {
        if ($scanRoot === '' || !\is_dir($scanRoot)) {
            throw new \RuntimeException('scan-root-invalid');
        }

        $files = $collectFiles($scanRoot);

        /** @var array<string, array{path:string, reason:string, i:int}> $violations */
        $violations = [];
        $seq = 0;

        $hasForbiddenImport = false;
        $hasForbiddenPath = false;

        foreach ($files as $f) {
            $abs = (string)$f['abs'];
            $rel = (string)$f['rel'];

            $code = $withSuppressedErrors(static function () use ($abs): ?string {
                if (!\is_file($abs) || !\is_readable($abs)) {
                    return null;
                }
                $c = \file_get_contents($abs);
                return \is_string($c) ? $c : null;
            });

            if ($code === null) {
                throw new \RuntimeException('scan-read-failed');
            }

            $tokens = $withSuppressedErrors(static function () use ($code): array {
                // TOKEN_PARSE keeps tokenization strict; still deterministic.
                return \token_get_all($code, \TOKEN_PARSE);
            });

            // Forbidden namespace usage (token-based, qualified-name tokens only) - ALL roots.
            $roots = $detectForbiddenNamespaceRoots($tokens);
            if ($roots !== []) {
                $hasForbiddenImport = true;

                foreach ($roots as $root) {
                    $recordViolation($violations, $rel, 'forbidden-import:' . $root, $seq);
                }
            }

            // Forbidden include/require path usage (token-based).
            if ($detectForbiddenPathUsage($tokens)) {
                $hasForbiddenPath = true;
                $recordViolation($violations, $rel, 'forbidden-path', $seq);
            }
        }

        if (!$hasForbiddenImport && !$hasForbiddenPath) {
            exit(0);
        }

        // Deterministic CODE selection (cemented).
        if ($hasForbiddenImport) {
            $codeOut = $ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT;
        } elseif ($hasForbiddenPath) {
            $codeOut = $ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH;
        } else {
            $codeOut = $ErrorCodes::CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED;
        }

        $list = \array_values($violations);

        // Stable sorting: path strcmp; tie-break reason strcmp; final tie-break by insertion seq.
        \usort(
            $list,
            static function (array $a, array $b): int {
                $c = \strcmp((string)$a['path'], (string)$b['path']);
                if ($c !== 0) {
                    return $c;
                }

                $r = \strcmp((string)$a['reason'], (string)$b['reason']);
                if ($r !== 0) {
                    return $r;
                }

                return ((int)$a['i']) <=> ((int)$b['i']);
            }
        );

        /** @var list<string> $diagnostics */
        $diagnostics = [];
        foreach ($list as $v) {
            $diagnostics[] = (string)$v['path'] . ': ' . (string)$v['reason'];
        }

        $ConsoleOutput::codeWithDiagnostics((string)$codeOut, $diagnostics);
        exit(1);
    } catch (\Throwable $e) {
        // Deterministic scan failure; no details, no absolute paths.
        $ConsoleOutput::codeWithDiagnostics(
            $ErrorCodes::CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED,
            [],
        );
        exit(1);
    }
})(isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []);
