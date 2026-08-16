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

        return \is_string($p) ? \rtrim(\str_replace('\\', '/', $p), '/') : null;
    });

    $fallbackViolation = 'CORETSIA_LICENSE_HEADER_VIOLATION';
    $fallbackGateFailed = 'CORETSIA_LICENSE_HEADER_GATE_FAILED';

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                $fallbackGateFailed,
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

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_license_header_gate_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_LICENSE_HEADER_GATE_FAILED',
                    $code,
                );
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

    $codeViolation = coretsia_license_header_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_LICENSE_HEADER_VIOLATION',
        $fallbackViolation,
    );

    $codeGateFailed = coretsia_license_header_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_LICENSE_HEADER_GATE_FAILED',
        $fallbackGateFailed,
    );

    try {
        $repoRoot = coretsia_license_header_gate_resolve_repo_root($toolsRootRuntime);
        $scanRoot = coretsia_license_header_gate_parse_scan_root($argv, $repoRoot);

        $diagnostics = coretsia_license_header_gate_scan($repoRoot, $scanRoot);
        $diagnostics = \array_values(\array_unique($diagnostics));
        \sort($diagnostics, \SORT_STRING);

        if ($diagnostics === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeGateFailed, []);
        }

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @param class-string $errorCodesClass
 */
function coretsia_license_header_gate_error_code_or_fallback(
    string $errorCodesClass,
    string $constant,
    string $fallback,
): string {
    $name = $errorCodesClass . '::' . $constant;
    if (\defined($name)) {
        /** @var string $value */
        $value = \constant($name);

        return $value;
    }

    return $fallback;
}

function coretsia_license_header_gate_resolve_repo_root(string $toolsRootRuntime): string
{
    $repoRoot = \realpath($toolsRootRuntime . '/../..');

    if (!\is_string($repoRoot) || !\is_dir($repoRoot) || !\is_readable($repoRoot)) {
        throw new \RuntimeException('repo-root-invalid');
    }

    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    foreach (['LICENSE', 'NOTICE'] as $file) {
        $path = $repoRoot . '/' . $file;
        if (!\is_file($path) || !\is_readable($path)) {
            throw new \RuntimeException('repo-legal-file-missing');
        }
    }

    return $repoRoot;
}

/**
 * @param list<string> $argv
 */
function coretsia_license_header_gate_parse_scan_root(array $argv, string $repoRoot): string
{
    $path = null;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '' || $arg === '--') {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $path = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '-')) {
            continue;
        }
    }

    return $path === null
        ? $repoRoot
        : coretsia_license_header_gate_resolve_existing_dir($path, $repoRoot);
}

function coretsia_license_header_gate_resolve_existing_dir(string $path, string $repoRoot): string
{
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        throw new \RuntimeException('path-empty');
    }

    $candidate = coretsia_license_header_gate_is_absolute_path($path)
        ? $path
        : \rtrim($repoRoot, '/') . '/' . \ltrim($path, '/');

    $real = \realpath($candidate);

    if (!\is_string($real) || !\is_dir($real) || !\is_readable($real)) {
        throw new \RuntimeException('path-invalid');
    }

    $real = \rtrim(\str_replace('\\', '/', $real), '/');
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    if ($real !== $repoRoot && !\str_starts_with($real . '/', $repoRoot . '/')) {
        throw new \RuntimeException('path-outside-repo');
    }

    return $real;
}

function coretsia_license_header_gate_is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if ($path[0] === '/' || \str_starts_with($path, '//')) {
        return true;
    }

    return \preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

/**
 * @return list<string>
 */
function coretsia_license_header_gate_scan(string $repoRoot, string $scanRoot): array
{
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
    $scanRoot = \rtrim(\str_replace('\\', '/', $scanRoot), '/');

    $skipDirectories = [
        '.git' => true,
        '.idea' => true,
        '.vscode' => true,
        '.fleet' => true,
        '.osp' => true,
        'vendor' => true,
        'node_modules' => true,
        'var' => true,
        'tmp' => true,
        'coverage' => true,
        '.phpunit.cache' => true,
        '.phpstan.cache' => true,
        '.phpstan-cache' => true,
        '.rector.cache' => true,
        '.psalm' => true,
        '.infection' => true,
    ];

    $directory = new \RecursiveDirectoryIterator(
        $scanRoot,
        \FilesystemIterator::SKIP_DOTS,
    );

    $filter = new \RecursiveCallbackFilterIterator(
        $directory,
        static function (\SplFileInfo $entry) use ($skipDirectories): bool {
            if ($entry->isLink()) {
                return false;
            }

            if ($entry->isDir()) {
                return !isset($skipDirectories[$entry->getFilename()]);
            }

            return true;
        },
    );

    $iterator = new \RecursiveIteratorIterator(
        $filter,
        \RecursiveIteratorIterator::LEAVES_ONLY,
    );

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($iterator as $entry) {
        if (!$entry instanceof \SplFileInfo || !$entry->isFile() || $entry->isLink()) {
            continue;
        }

        $absolutePath = $entry->getRealPath();
        if (!\is_string($absolutePath)) {
            throw new \RuntimeException('file-realpath-invalid');
        }

        $absolutePath = \str_replace('\\', '/', $absolutePath);
        if (!\str_starts_with($absolutePath, $repoRoot . '/')) {
            throw new \RuntimeException('file-outside-repo');
        }

        $relativePath = \substr($absolutePath, \strlen($repoRoot) + 1);
        if ($relativePath === '') {
            throw new \RuntimeException('file-relative-path-invalid');
        }

        if (coretsia_license_header_gate_is_intrinsically_exempt($relativePath)) {
            continue;
        }

        $prefix = coretsia_license_header_gate_read_prefix($absolutePath);
        $profile = coretsia_license_header_gate_profile($relativePath, $prefix);

        if ($profile === null) {
            continue;
        }

        $result = coretsia_license_header_gate_validate_header($profile, $prefix);
        if ($result === null) {
            continue;
        }

        $diagnostics[] = coretsia_license_header_gate_safe_diagnostic_path($relativePath)
            . ':'
            . $result;
    }

    return $diagnostics;
}

function coretsia_license_header_gate_is_intrinsically_exempt(string $relativePath): bool
{
    $base = \basename(\str_replace('\\', '/', $relativePath));

    return $base === 'LICENSE'
        || $base === 'NOTICE';
}

function coretsia_license_header_gate_read_prefix(string $path): string
{
    $handle = @\fopen($path, 'rb');
    if (!\is_resource($handle)) {
        throw new \RuntimeException('file-open-failed');
    }

    try {
        $bytes = @\fread($handle, 8192);
        if (!\is_string($bytes)) {
            throw new \RuntimeException('file-read-failed');
        }

        return $bytes;
    } finally {
        @\fclose($handle);
    }
}

function coretsia_license_header_gate_profile(string $relativePath, string $prefix): ?string
{
    $path = \strtolower(\str_replace('\\', '/', $relativePath));
    $base = \basename($path);

    if (\str_ends_with($path, '.php')) {
        return 'c-block';
    }

    if (
        \str_ends_with($path, '.md')
        || \str_ends_with($path, '.markdown')
        || \str_ends_with($path, '.html')
        || \str_ends_with($path, '.htm')
        || \str_ends_with($path, '.xml')
        || \str_ends_with($path, '.xml.dist')
        || \str_ends_with($path, '.svg')
    ) {
        return 'html';
    }

    if (
        \str_ends_with($path, '.yml')
        || \str_ends_with($path, '.yaml')
        || \str_ends_with($path, '.toml')
        || \str_ends_with($path, '.neon')
        || \str_ends_with($path, '.ini')
        || \str_ends_with($path, '.sh')
        || \str_ends_with($path, '.bash')
        || \str_ends_with($path, '.zsh')
        || \str_ends_with($path, '.ps1')
        || \str_starts_with($base, '.env')
        || \in_array(
            $base,
            [
                '.editorconfig',
                '.gitattributes',
                '.gitignore',
                '.gitleaks.toml',
            ],
            true,
        )
    ) {
        return 'hash';
    }

    if (\str_ends_with($path, '.dot')) {
        return 'slash-line';
    }

    if (
        \str_ends_with($path, '.js')
        || \str_ends_with($path, '.ts')
        || \str_ends_with($path, '.tsx')
        || \str_ends_with($path, '.css')
        || \str_ends_with($path, '.scss')
    ) {
        return 'c-block';
    }

    if (\str_starts_with($prefix, '#!')) {
        if (\str_contains(\substr($prefix, 0, 256), '<?php')) {
            return 'c-block';
        }

        return 'hash';
    }

    return null;
}

function coretsia_license_header_gate_validate_header(string $profile, string $prefix): ?string
{
    $normalized = \str_replace(["\r\n", "\r"], "\n", $prefix);

    $pattern = match ($profile) {
        'c-block' => coretsia_license_header_gate_c_block_pattern(),
        'html' => coretsia_license_header_gate_html_pattern(),
        'hash' => coretsia_license_header_gate_hash_pattern(),
        'slash-line' => coretsia_license_header_gate_slash_line_pattern(),
        default => throw new \LogicException('license-header-profile-invalid'),
    };

    $matches = [];
    $matched = \preg_match($pattern, $normalized, $matches);

    if ($matched !== 1) {
        if (!\str_contains($normalized, 'SPDX-License-Identifier:')) {
            return 'license-header-missing';
        }

        return 'license-header-invalid';
    }

    $copyright = isset($matches['copyright']) && \is_string($matches['copyright'])
        ? \trim($matches['copyright'])
        : '';

    $spdxCopyright = isset($matches['spdx_copyright']) && \is_string($matches['spdx_copyright'])
        ? \trim($matches['spdx_copyright'])
        : '';

    if ($copyright === '' || $spdxCopyright === '' || $copyright !== $spdxCopyright) {
        return 'license-header-copyright-mismatch';
    }

    $authors = isset($matches['authors']) && \is_string($matches['authors'])
        ? \trim($matches['authors'])
        : '';

    if ($authors === '') {
        return 'license-header-invalid';
    }

    return null;
}

function coretsia_license_header_gate_c_block_pattern(): string
{
    return '~(?:\A|\n)/\*\n'
        . ' \* Coretsia Framework \(Monorepo\)\n'
        . ' \*\n'
        . ' \* Project: Coretsia Framework \(Monorepo\)\n'
        . ' \* Authors: (?<authors>[^\n]+)\n'
        . ' \* Copyright \(c\) (?<copyright>[^\n]+)\n'
        . ' \*\n'
        . ' \* SPDX-FileCopyrightText: (?<spdx_copyright>[^\n]+)\n'
        . ' \* SPDX-License-Identifier: Apache-2\.0\n'
        . ' \*\n'
        . ' \* For contributors list, see git history\.\n'
        . ' \* See LICENSE and NOTICE in the project root for full license information\.\n'
        . ' \*/(?:\n|\z)~';
}

function coretsia_license_header_gate_html_pattern(): string
{
    return '~(?:\A|\n)<!--\n'
        . '  Coretsia Framework \(Monorepo\)\n'
        . '\n'
        . '  Project: Coretsia Framework \(Monorepo\)\n'
        . '  Authors: (?<authors>[^\n]+)\n'
        . '  Copyright \(c\) (?<copyright>[^\n]+)\n'
        . '\n'
        . '  SPDX-FileCopyrightText: (?<spdx_copyright>[^\n]+)\n'
        . '  SPDX-License-Identifier: Apache-2\.0\n'
        . '\n'
        . '  For contributors list, see git history\.\n'
        . '  See LICENSE and NOTICE in the project root for full license information\.\n'
        . '-->(?:\n|\z)~';
}

function coretsia_license_header_gate_hash_pattern(): string
{
    return '~(?:\A|\n)# Coretsia Framework \(Monorepo\)\n'
        . '#\n'
        . '# Project: Coretsia Framework \(Monorepo\)\n'
        . '# Authors: (?<authors>[^\n]+)\n'
        . '# Copyright \(c\) (?<copyright>[^\n]+)\n'
        . '#\n'
        . '# SPDX-FileCopyrightText: (?<spdx_copyright>[^\n]+)\n'
        . '# SPDX-License-Identifier: Apache-2\.0\n'
        . '#\n'
        . '# For contributors list, see git history\.\n'
        . '# See LICENSE and NOTICE in the project root for full license information\.(?:\n|\z)~';
}

function coretsia_license_header_gate_slash_line_pattern(): string
{
    return '~(?:\A|\n)// Coretsia Framework \(Monorepo\)\n'
        . '//\n'
        . '// Project: Coretsia Framework \(Monorepo\)\n'
        . '// Authors: (?<authors>[^\n]+)\n'
        . '// Copyright \(c\) (?<copyright>[^\n]+)\n'
        . '//\n'
        . '// SPDX-FileCopyrightText: (?<spdx_copyright>[^\n]+)\n'
        . '// SPDX-License-Identifier: Apache-2\.0\n'
        . '//\n'
        . '// For contributors list, see git history\.\n'
        . '// See LICENSE and NOTICE in the project root for full license information\.(?:\n|\z)~';
}

function coretsia_license_header_gate_safe_diagnostic_path(string $path): string
{
    $path = \str_replace('\\', '/', $path);
    $safe = '';
    $length = \strlen($path);

    for ($i = 0; $i < $length; $i++) {
        $byte = \ord($path[$i]);

        if (
            ($byte >= 0x41 && $byte <= 0x5A)
            || ($byte >= 0x61 && $byte <= 0x7A)
            || ($byte >= 0x30 && $byte <= 0x39)
            || \str_contains('._/-', $path[$i])
        ) {
            $safe .= $path[$i];
            continue;
        }

        $safe .= '%' . \strtoupper(\str_pad(\dechex($byte), 2, '0', \STR_PAD_LEFT));
    }

    return $safe;
}
