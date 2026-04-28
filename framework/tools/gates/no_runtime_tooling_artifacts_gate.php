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
                'CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION';
    $fallbackGateFailed = 'CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED';
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

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeGateFailed = (static function () use ($ErrorCodes, $fallbackGateFailed): string {
        $name = $ErrorCodes . '::CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackGateFailed;
    })();

    try {
        $repoRoot = $withSuppressedErrors(static function (): ?string {
            $p = \realpath(__DIR__ . '/..' . '/..' . '/..');
            return \is_string($p) ? $p : null;
        });

        if ($repoRoot === null || $repoRoot === '') {
            throw new \RuntimeException('repo-root-invalid');
        }

        $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
        $frameworkRoot = $repoRoot . '/framework';
        $packagesRoot = $frameworkRoot . '/packages';

        $scanRoots = coretsia_no_runtime_tooling_artifacts_gate_collect_scan_roots($packagesRoot);

        if ($scanRoots === []) {
            exit(0);
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_no_runtime_tooling_artifacts_gate_find_scan_files($scanRoots) as $absPath) {
            $source = coretsia_no_runtime_tooling_artifacts_gate_read_file($absPath);
            $repoRelativePath = coretsia_no_runtime_tooling_artifacts_gate_rel_from_repo($absPath, $repoRoot);

            foreach (coretsia_no_runtime_tooling_artifacts_gate_detect_reasons($source) as $reason) {
                $violations[] = $repoRelativePath . ': ' . $reason;
            }
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
            $ConsoleOutput::codeWithDiagnostics($codeGateFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return list<string>
 */
function coretsia_no_runtime_tooling_artifacts_gate_collect_scan_roots(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    if (!\is_dir($packagesRoot)) {
        return [];
    }

    $runtimeLayers = [
        'core',
        'platform',
        'integrations',
        'presets',
        'enterprise',
    ];

    /** @var list<string> $roots */
    $roots = [];

    foreach ($runtimeLayers as $layer) {
        $layerDir = $packagesRoot . '/' . $layer;

        if (!\is_dir($layerDir)) {
            continue;
        }

        $slugs = \scandir($layerDir);
        if ($slugs === false) {
            throw new \RuntimeException('runtime-layer-scan-failed');
        }

        \sort($slugs, \SORT_STRING);

        foreach ($slugs as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }

            $packageRoot = $layerDir . '/' . $slug;

            if (!\is_dir($packageRoot)) {
                continue;
            }

            foreach (['src', 'config'] as $relativeRoot) {
                $root = $packageRoot . '/' . $relativeRoot;

                if (\is_dir($root)) {
                    $roots[] = \rtrim(\str_replace('\\', '/', $root), '/');
                }
            }
        }
    }

    $roots = \array_values(\array_unique($roots));
    \sort($roots, \SORT_STRING);

    return $roots;
}

/**
 * @param list<string> $scanRoots
 * @return list<string>
 */
function coretsia_no_runtime_tooling_artifacts_gate_find_scan_files(array $scanRoots): array
{
    /** @var list<string> $files */
    $files = [];

    foreach ($scanRoots as $scanRoot) {
        $scanRoot = \rtrim(\str_replace('\\', '/', $scanRoot), '/');

        if (!\is_dir($scanRoot)) {
            continue;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $scanRoot,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\Throwable) {
            throw new \RuntimeException('runtime-scan-root-iterator-failed');
        }

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $absPath = \rtrim(\str_replace('\\', '/', $fileInfo->getPathname()), '/');

            if (!\str_ends_with($absPath, '.php')) {
                continue;
            }

            if (
                \str_contains($absPath, '/tests/')
                || \str_contains($absPath, '/Tests/')
                || \str_contains($absPath, '/fixtures/')
                || \str_contains($absPath, '/Fixtures/')
                || \str_contains($absPath, '/vendor/')
            ) {
                continue;
            }

            $files[] = $absPath;
        }
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

/**
 * @return list<string>
 */
function coretsia_no_runtime_tooling_artifacts_gate_detect_reasons(string $source): array
{
    /** @var array<string, true> $reasons */
    $reasons = [];

    if (
        coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
            $source,
            'Coretsia\\Tools\\Spikes\\',
        )
    ) {
        $reasons['runtime-imports-tools-spikes'] = true;
    }

    if (
        coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
            $source,
            'Coretsia\\Devtools\\',
        )
    ) {
        $reasons['runtime-imports-devtools'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_devtools_package_reference($source)) {
        $reasons['runtime-references-devtools-package'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_architecture_artifact_path($source)) {
        $reasons['runtime-reads-architecture-artifact'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($source)) {
        $reasons['runtime-reads-framework-tools'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_executed_tooling_path($source)) {
        $reasons['runtime-executes-tooling-path'] = true;
    }

    $out = \array_keys($reasons);
    \sort($out, \SORT_STRING);

    return $out;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
    string $source,
    string $namespacePrefix,
): bool {
    $escapedPrefix = \str_replace('\\', '\\\\', $namespacePrefix);

    return \str_contains($source, $namespacePrefix)
        || \str_contains($source, $escapedPrefix);
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_devtools_package_reference(string $source): bool
{
    foreach (
        [
            'devtools/internal-toolkit',
            'devtools/cli-spikes',
            'coretsia/devtools-internal-toolkit',
            'coretsia/devtools-cli-spikes',
        ] as $needle
    ) {
        if (\str_contains($source, $needle)) {
            return true;
        }
    }

    return false;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_architecture_artifact_path(string $source): bool
{
    return \preg_match(
        '~(?<![A-Za-z0-9_.-])framework[\\\\/]+var[\\\\/]+arch(?:[\\\\/]+|\\b)~iu',
        $source,
    ) === 1;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path(string $source): bool
{
    foreach (
        [
            '~(?<![A-Za-z0-9_.-])framework[\\\\/]+tools[\\\\/]+~iu',
            '~(?<![A-Za-z0-9_.-])tools[\\\\/]+(?:spikes|build|gates)(?:[\\\\/]+|\\b)~iu',
        ] as $pattern
    ) {
        if (\preg_match($pattern, $source) === 1) {
            return true;
        }
    }

    return false;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_executed_tooling_path(string $source): bool
{
    if (!coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($source)) {
        return false;
    }

    $source = \str_replace(["\r\n", "\r"], "\n", $source);

    foreach (\explode("\n", $source) as $line) {
        if (!coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($line)) {
            continue;
        }

        if (
            \preg_match(
                '~\\b(?:exec|shell_exec|system|passthru|proc_open|popen)\\s*\\(~iu',
                $line,
            ) === 1
        ) {
            return true;
        }

        if (\str_contains($line, '`')) {
            return true;
        }

        if (
            \preg_match(
                '~(?:^|[^A-Za-z0-9_-])(?:php|composer|sh|bash)\\s+[^\\n]*(?:framework[\\\\/]+tools[\\\\/]+|tools[\\\\/]+(?:spikes|build|gates)(?:[\\\\/]+|\\b))~iu',
                $line,
            ) === 1
        ) {
            return true;
        }
    }

    return false;
}

function coretsia_no_runtime_tooling_artifacts_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $content = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($content)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $content;
}

function coretsia_no_runtime_tooling_artifacts_gate_rel_from_repo(string $absPath, string $repoRoot): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    if ($absPath === $repoRoot) {
        return '.';
    }

    if (!\str_starts_with($absPath, $repoRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($repoRoot) + 1);
}
