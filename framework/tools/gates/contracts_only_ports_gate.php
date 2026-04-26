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

(static function (): void {
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
                'CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN';
    $fallbackScanFailed = 'CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED';
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

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }
    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackScanFailed;
    })();

    try {
        $frameworkRoot = $withSuppressedErrors(static function (): ?string {
            $p = \realpath(__DIR__ . '/..' . '/..');
            return \is_string($p) ? $p : null;
        });

        if ($frameworkRoot === null || $frameworkRoot === '') {
            throw new \RuntimeException('framework-root-invalid');
        }

        $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');
        $packagesRoot = $frameworkRoot . '/packages';
        $contractsOwnerRoot = $frameworkRoot . '/packages/core/contracts/src';

        if (!\is_dir($packagesRoot)) {
            exit(0);
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_contracts_only_ports_gate_find_php_sources($packagesRoot) as $absPath) {
            $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');

            if (coretsia_contracts_only_ports_gate_is_allowed_contracts_owner_path($absPath, $contractsOwnerRoot)) {
                continue;
            }

            $reason = coretsia_contracts_only_ports_gate_detect_violation_reason($absPath);
            if ($reason === null) {
                continue;
            }

            $violations[] = coretsia_contracts_only_ports_gate_rel_from_framework($absPath, $frameworkRoot)
                . ': '
                . $reason;
        }

        $violations = \array_values(\array_unique($violations));
        \sort($violations, \SORT_STRING);

        if ($violations === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $violations);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return list<string>
 */
function coretsia_contracts_only_ports_gate_find_php_sources(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

    /** @var list<string> $phpFiles */
    $phpFiles = [];

    foreach ($layers as $layer) {
        if ($layer === '.' || $layer === '..') {
            continue;
        }

        $layerDir = $packagesRoot . '/' . $layer;
        if (!\is_dir($layerDir)) {
            continue;
        }

        $slugs = \scandir($layerDir);
        if ($slugs === false) {
            throw new \RuntimeException('layer-dir-scan-failed');
        }

        foreach ($slugs as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }

            $packageDir = $layerDir . '/' . $slug;
            if (!\is_dir($packageDir)) {
                continue;
            }

            $srcDir = $packageDir . '/src';
            if (!\is_dir($srcDir)) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $srcDir,
                        \FilesystemIterator::SKIP_DOTS,
                    ),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );
            } catch (\Throwable) {
                throw new \RuntimeException('src-dir-iterator-failed');
            }

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo) {
                    continue;
                }

                if (!$fileInfo->isFile()) {
                    continue;
                }

                $absPath = \str_replace('\\', '/', $fileInfo->getPathname());
                if (!\str_ends_with($absPath, '.php')) {
                    continue;
                }

                if (
                    \str_contains($absPath, '/tests/')
                    || \str_contains($absPath, '/fixtures/')
                    || \str_contains($absPath, '/vendor/')
                ) {
                    continue;
                }

                $phpFiles[] = \rtrim($absPath, '/');
            }
        }
    }

    $phpFiles = \array_values(\array_unique($phpFiles));
    \sort($phpFiles, \SORT_STRING);

    return $phpFiles;
}

function coretsia_contracts_only_ports_gate_is_allowed_contracts_owner_path(string $absPath, string $contractsOwnerRoot): bool
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $contractsOwnerRoot = \rtrim(\str_replace('\\', '/', $contractsOwnerRoot), '/');

    return $absPath === $contractsOwnerRoot
        || \str_starts_with($absPath, $contractsOwnerRoot . '/');
}

function coretsia_contracts_only_ports_gate_detect_violation_reason(string $absPath): ?string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $basename = \basename($absPath);

    if (\str_ends_with($basename, 'PortInterface.php')) {
        return 'forbidden-public-port-interface';
    }

    $srcPos = \strpos($absPath, '/src/');
    if ($srcPos === false) {
        return null;
    }

    $insideSrc = \substr($absPath, $srcPos + 5);
    $insideSrc = \ltrim(\str_replace('\\', '/', $insideSrc), '/');

    if (\str_contains('/' . $insideSrc, '/Port/')) {
        return 'forbidden-public-port-namespace';
    }

    return null;
}

function coretsia_contracts_only_ports_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');

    if ($absPath === $frameworkRoot) {
        return '.';
    }

    if (!\str_starts_with($absPath, $frameworkRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($frameworkRoot) + 1);
}
