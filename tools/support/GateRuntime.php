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

namespace Coretsia\Tools\Support;

/**
 * Shared bootstrap/output runtime for repository gates.
 *
 * GateRuntime owns shared gate execution, output, and repository scan-path plumbing.
 * Individual gate policy and diagnostics remain in the gate scripts.
 */
final class GateRuntime
{
    private function __construct()
    {
    }

    /**
     * @param non-empty-string $violationCode Registered ErrorCodes value.
     * @param non-empty-string $scanFailedCode Registered ErrorCodes value.
     * @param callable(RepositoryContext):list<string> $scan
     */
    public static function execute(
        string $violationCode,
        string $scanFailedCode,
        callable $scan,
    ): int {
        return self::executeSelectedViolation(
            $scanFailedCode,
            static function (RepositoryContext $repository) use ($violationCode, $scan): array {
                self::validateErrorCode($violationCode);

                return [
                    'violationCode' => $violationCode,
                    'diagnostics' => $scan($repository),
                ];
            },
        );
    }

    /**
     * @param non-empty-string $scanFailedCode Registered ErrorCodes value.
     * @param callable(RepositoryContext):array{
     *     violationCode:non-empty-string,
     *     diagnostics:list<string>
     * } $scan
     */
    public static function executeSelectedViolation(
        string $scanFailedCode,
        callable $scan,
    ): int {
        $toolsRoot = self::runtimeToolsRoot();

        if ($toolsRoot === null) {
            self::emitFallback($scanFailedCode);
            return 1;
        }

        $bootstrap = $toolsRoot . '/support/bootstrap.php';
        if (!is_file($bootstrap) || !is_readable($bootstrap)) {
            self::emitFallback($scanFailedCode);
            return 1;
        }

        $validatedScanFailedCode = null;

        try {
            // If bootstrap terminates, its deterministic output is authoritative.
            @require_once $bootstrap;
            self::ensureSupportClass(ConsoleOutput::class, __DIR__ . '/ConsoleOutput.php');
            self::ensureSupportClass(ErrorCodes::class, __DIR__ . '/ErrorCodes.php');

            $validatedScanFailedCode = self::validateErrorCode($scanFailedCode);

            self::ensureSupportClass(RepositoryContext::class, __DIR__ . '/RepositoryContext.php');

            $repository = RepositoryContext::fromToolsRoot($toolsRoot);

            $outcome = $scan($repository);
            $violationCode = $outcome['violationCode'] ?? null;
            $diagnostics = $outcome['diagnostics'] ?? null;

            if (!is_string($violationCode) || !is_array($diagnostics)) {
                throw new \RuntimeException('gate-outcome-invalid');
            }

            $validatedViolationCode = self::validateErrorCode($violationCode);
            $diagnostics = self::normalizeDiagnostics($diagnostics);

            if ($diagnostics === []) {
                return 0;
            }

            ConsoleOutput::codeWithDiagnostics($validatedViolationCode, $diagnostics);

            return 1;
        } catch (\Throwable) {
            self::emitFallback($validatedScanFailedCode ?? $scanFailedCode);

            return 1;
        }
    }

    /**
     * Resolve the sole optional --path=<dir> argument for tools-source gates.
     * Relative paths are repository-root-relative and the resolved directory
     * must remain inside the canonical tools root.
     *
     * @param list<mixed> $argv
     */
    public static function resolveToolsScanRoot(
        RepositoryContext $repository,
        array $argv,
    ): string {
        $path = null;

        foreach ($argv as $index => $arg) {
            if ($index === 0) {
                continue;
            }

            if (!is_string($arg) || !str_starts_with($arg, '--path=')) {
                throw new \RuntimeException('gate-tools-scan-argument-invalid');
            }

            if ($path !== null) {
                throw new \RuntimeException('gate-tools-scan-argument-duplicate');
            }

            $path = substr($arg, strlen('--path='));

            if ($path === '') {
                throw new \RuntimeException('gate-tools-scan-argument-invalid');
            }
        }

        $scanRoot = $path === null
            ? $repository->toolsRoot()
            : $repository->resolveExistingDirectory($path);

        if (!RepositoryContext::containsPath($repository->toolsRoot(), $scanRoot)) {
            throw new \RuntimeException('gate-tools-scan-root-outside-tools');
        }

        return $scanRoot;
    }

    /**
     * Resolve the sole optional --path=<dir> argument for repository gates.
     *
     * Relative paths are repository-root-relative. The resolved directory must
     * remain inside the canonical repository root. Null means that the caller
     * should use its canonical default scan roots.
     *
     * @param list<mixed> $argv
     */
    public static function resolveOptionalRepositoryScanRoot(
        RepositoryContext $repository,
        array $argv,
    ): ?string {
        $path = null;

        foreach ($argv as $index => $arg) {
            if ($index === 0) {
                continue;
            }

            if (!is_string($arg) || !str_starts_with($arg, '--path=')) {
                throw new \RuntimeException('gate-repository-scan-argument-invalid');
            }

            if ($path !== null) {
                throw new \RuntimeException('gate-repository-scan-argument-duplicate');
            }

            $path = substr($arg, strlen('--path='));

            if ($path === '') {
                throw new \RuntimeException('gate-repository-scan-argument-invalid');
            }
        }

        if ($path === null) {
            return null;
        }

        return $repository->resolveExistingDirectory($path);
    }

    /**
     * Restrict an optional scan root to canonical policy roots.
     *
     * A scan root may be an ancestor of canonical roots or a descendant of one
     * canonical root. Unrelated repository directories are intentionally ignored.
     *
     * @param list<string> $canonicalRoots
     * @param list<string> $excludedSegments Exact descendant directory names that
     *     cannot become selected roots.
     *
     * @return list<string>
     */
    public static function selectScanRoots(
        ?string $scanRoot,
        array $canonicalRoots,
        array $excludedSegments = [],
    ): array {
        $roots = [];
        $excluded = [];

        foreach ($excludedSegments as $segment) {
            if (
                !is_string($segment)
                || $segment === ''
                || $segment === '.'
                || $segment === '..'
                || str_contains($segment, '/')
                || str_contains($segment, '\\')
                || str_contains($segment, "\0")
            ) {
                throw new \RuntimeException('gate-excluded-scan-segment-invalid');
            }

            $excluded[$segment] = true;
        }

        foreach ($canonicalRoots as $canonicalRoot) {
            if (!is_string($canonicalRoot) || $canonicalRoot === '') {
                throw new \RuntimeException('gate-canonical-scan-root-invalid');
            }

            if ($scanRoot === null) {
                $roots[] = $canonicalRoot;
                continue;
            }

            if (RepositoryContext::containsPath($scanRoot, $canonicalRoot)) {
                $roots[] = $canonicalRoot;
                continue;
            }

            if (RepositoryContext::containsPath($canonicalRoot, $scanRoot)) {
                $relativeScanRoot = substr(
                    $scanRoot,
                    strlen(rtrim($canonicalRoot, '/')) + 1,
                );
                $segments = $relativeScanRoot === ''
                    ? []
                    : explode('/', str_replace('\\', '/', $relativeScanRoot));
                $isExcluded = false;

                foreach ($segments as $segment) {
                    if (isset($excluded[$segment])) {
                        $isExcluded = true;
                        break;
                    }
                }

                if (!$isExcluded) {
                    $roots[] = $scanRoot;
                }
            }
        }

        $roots = array_values(array_unique($roots));
        sort($roots, SORT_STRING);

        return $roots;
    }

    public static function relativeToTools(
        RepositoryContext $repository,
        string $path,
    ): string {
        $relativePath = $repository->relativeToRepo($path);

        if ($relativePath === 'tools') {
            return '.';
        }

        $prefix = 'tools/';

        if (!str_starts_with($relativePath, $prefix)) {
            throw new \RuntimeException('gate-path-outside-tools');
        }

        return substr($relativePath, strlen($prefix));
    }

    /**
     * Execute callable with warnings/notices suppressed.
     *
     * @template T
     * @param callable():T $fn
     *
     * @return T
     */
    public static function withSuppressedErrors(callable $fn): mixed
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    private static function runtimeToolsRoot(): ?string
    {
        $resolved = self::withSuppressedErrors(static function (): string|false {
            return realpath(__DIR__ . '/..');
        });

        if (!is_string($resolved) || $resolved === '') {
            return null;
        }

        return rtrim(str_replace('\\', '/', $resolved), '/');
    }

    /**
     * @param class-string $className
     */
    private static function ensureSupportClass(string $className, string $path): void
    {
        if (class_exists($className, false)) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('gate-support-class-missing');
        }

        @require_once $path;

        if (!class_exists($className, false)) {
            throw new \RuntimeException('gate-support-class-missing');
        }
    }

    private static function validateErrorCode(string $code): string
    {
        if ($code === '' || !ErrorCodes::has($code)) {
            throw new \RuntimeException('gate-error-code-invalid');
        }

        return $code;
    }

    /**
     * @param array<mixed> $diagnostics
     *
     * @return list<string>
     */
    private static function normalizeDiagnostics(array $diagnostics): array
    {
        $out = [];

        foreach ($diagnostics as $diagnostic) {
            if (!is_string($diagnostic) || $diagnostic === '') {
                throw new \RuntimeException('gate-diagnostic-invalid');
            }

            $out[] = $diagnostic;
        }

        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);

        return $out;
    }

    private static function emitFallback(string $scanFailedCode): void
    {
        $errorCodesPath = __DIR__ . '/ErrorCodes.php';
        $consolePath = __DIR__ . '/ConsoleOutput.php';

        if (
            !is_file($errorCodesPath)
            || !is_readable($errorCodesPath)
            || !is_file($consolePath)
            || !is_readable($consolePath)
        ) {
            return;
        }

        try {
            @require_once $errorCodesPath;
            @require_once $consolePath;

            if (
                !class_exists(ErrorCodes::class, false)
                || !class_exists(ConsoleOutput::class, false)
                || !ErrorCodes::has($scanFailedCode)
            ) {
                return;
            }

            ConsoleOutput::codeWithDiagnostics($scanFailedCode, []);
        } catch (\Throwable) {
            // No safe output channel remains.
        }
    }
}
