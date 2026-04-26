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

    $fallbackScanFailed = 'CORETSIA_SPIKES_CANONICAL_PATHS_GATE_SCAN_FAILED';
    $fallbackViolation = 'CORETSIA_SPIKES_CANONICAL_PATHS_VIOLATION';

    // Bootstrap MUST be loaded before scanning.
    // If bootstrap missing/unreadable -> must use local ConsoleOutput include (if available), print SCAN_FAILED and exit 1.
    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_SPIKES_CANONICAL_PATHS_GATE_SCAN_FAILED';
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

    // NOTE (cemented): if bootstrap exists but terminates the process, its output is authoritative.
    require_once $bootstrap;

    // Output MUST be emitted only via runtime ConsoleOutput (tools-root based).
    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }
    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_SPIKES_CANONICAL_PATHS_VIOLATION';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_SPIKES_CANONICAL_PATHS_GATE_SCAN_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }
        return $fallbackScanFailed;
    })();

    try {
        // --path=<dir> overrides tools root scan root only (does NOT affect bootstrap discovery/loading).
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

        // Policy: scan root MUST be within tools root (keeps rel paths stable; prevents leaks).
        if (!coretsia_spikes_paths_is_within_root($toolsRootRuntime, $scanRoot)) {
            throw new \RuntimeException('scan-root-outside-tools-root');
        }

        $violations = []; // list<array{path:string, reason:string}>

        /** @var array<string, true> $reservedTopLevelDirs */
        $reservedTopLevelDirs = [
            '_support' => true,
            '_artifacts' => true,
        ];

        // 0) Detect tools-root case variants for "spikes" itself: e.g. tools/Spikes
        foreach (coretsia_spikes_paths_list_child_dirs($scanRoot) as $child) {
            $name = $child['name'];
            if (\strtolower($name) === 'spikes' && $name !== 'spikes') {
                $violations[] = ['path' => 'spikes', 'reason' => 'non-canonical-tools-dir:' . $name];
            }
        }

        $spikesRoot = $scanRoot . DIRECTORY_SEPARATOR . 'spikes';
        if (!\is_dir($spikesRoot)) {
            $violations[] = ['path' => 'spikes', 'reason' => 'spikes-root-missing'];
        } else {
            // 1) Required canonical spike exists.
            if (!\is_dir($spikesRoot . DIRECTORY_SEPARATOR . 'config_merge')) {
                $violations[] = ['path' => 'spikes/config_merge', 'reason' => 'missing-required-spike'];
            }

            // 2) Scan ALL directories under spikes/** and enforce:
            //    - no uppercase letters in directory names
            //    - top-level spike IDs (except reserved infrastructure dirs) must be snake_case [a-z0-9_]+ (no hyphens)
            foreach (coretsia_spikes_paths_list_dirs_recursive($scanRoot, $spikesRoot) as $relDir) {
                // relDir is scan-root-relative, normalized with forward slashes, includes "spikes/..."
                if ($relDir === 'spikes') {
                    continue;
                }

                $after = \substr($relDir, \strlen('spikes/'));
                $segments = $after === '' ? [] : \explode('/', $after);

                // Defensive (should not happen)
                if ($segments === []) {
                    continue;
                }

                // Per-segment: forbid uppercase in directory segment name.
                foreach ($segments as $seg) {
                    if ($seg === '') {
                        continue;
                    }
                    if (\preg_match('/[A-Z]/', $seg) === 1) {
                        $violations[] = ['path' => $relDir, 'reason' => 'uppercase-dir-segment:' . $seg];
                        break; // one reason per dir is enough; keeps diagnostics shorter and deterministic
                    }
                }

                // Top-level spike id rules.
                $spikeId = (string)$segments[0];

                if (!isset($reservedTopLevelDirs[$spikeId])) {
                    if (\preg_match('/\A[a-z0-9][a-z0-9_]*\z/', $spikeId) !== 1) {
                        $violations[] = ['path' => 'spikes/' . $spikeId, 'reason' => 'non-canonical-spike-id'];
                    }
                }
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
function coretsia_spikes_paths_normalize_for_prefix_check(string $path): string
{
    $path = \str_replace('\\', '/', $path);
    $path = \rtrim($path, '/');

    if (\PHP_OS_FAMILY === 'Windows') {
        return \strtolower($path);
    }

    return $path;
}

function coretsia_spikes_paths_is_within_root(string $rootAbs, string $candidateAbs): bool
{
    $rootReal = \realpath($rootAbs);
    $candReal = \realpath($candidateAbs);

    if ($rootReal === false || $candReal === false) {
        return false;
    }

    $root = coretsia_spikes_paths_normalize_for_prefix_check((string)$rootReal);
    $cand = coretsia_spikes_paths_normalize_for_prefix_check((string)$candReal);

    if ($root === '' || $cand === '') {
        return false;
    }

    return $cand === $root || \str_starts_with($cand . '/', $root . '/');
}

/**
 * @return list<array{name:string, abs:string}>
 */
function coretsia_spikes_paths_list_child_dirs(string $rootAbs): array
{
    $out = [];

    $it = new \DirectoryIterator($rootAbs);
    foreach ($it as $fi) {
        if ($fi->isDot()) {
            continue;
        }

        if (!$fi->isDir()) {
            continue;
        }

        if ($fi->isLink()) {
            continue;
        }

        $name = (string)$fi->getFilename();
        $abs = (string)$fi->getPathname();

        $out[] = ['name' => $name, 'abs' => $abs];
    }

    \usort(
        $out,
        static fn (array $a, array $b): int => \strcmp((string)$a['name'], (string)$b['name'])
    );

    return $out;
}

/**
 * @return list<string> scan-root-relative directory paths with forward slashes (includes "spikes/...")
 */
function coretsia_spikes_paths_list_dirs_recursive(string $scanRootAbs, string $spikesRootAbs): array
{
    $scanRootReal = \realpath($scanRootAbs);
    $spikesRootReal = \realpath($spikesRootAbs);

    if ($scanRootReal === false || $spikesRootReal === false) {
        throw new \RuntimeException('invalid-root');
    }

    $scanRootNorm = \rtrim(\str_replace('\\', '/', (string)$scanRootReal), '/');
    $scanRootCmp = coretsia_spikes_paths_normalize_for_prefix_check($scanRootNorm);

    $out = [];

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator((string)$spikesRootReal, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $fi) {
        if (!$fi instanceof \SplFileInfo) {
            continue;
        }
        if (!$fi->isDir()) {
            continue;
        }
        if ($fi->isLink()) {
            continue;
        }

        $abs = (string)$fi->getPathname();
        $absReal = \realpath($abs);
        if ($absReal === false) {
            throw new \RuntimeException('scan-failed');
        }

        $absNorm = \rtrim(\str_replace('\\', '/', (string)$absReal), '/');
        $absCmp = coretsia_spikes_paths_normalize_for_prefix_check($absNorm);

        if (!\str_starts_with($absCmp, $scanRootCmp . '/')) {
            throw new \RuntimeException('scan-failed');
        }

        $rel = \substr($absNorm, \strlen($scanRootNorm) + 1);
        if ($rel === '') {
            continue;
        }

        $rel = \str_replace('\\', '/', $rel);
        $out[$rel] = true;
    }

    $dirs = \array_keys($out);
    \usort($dirs, static fn (string $a, string $b): int => \strcmp($a, $b));

    /** @var list<string> $dirs */
    return $dirs;
}
