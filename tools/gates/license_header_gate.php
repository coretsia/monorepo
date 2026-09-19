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

use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_LICENSE_HEADER_VIOLATION',
    'CORETSIA_LICENSE_HEADER_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        ) ?? $repository->repoRoot();

        return coretsia_license_header_gate_scan(
            $repository,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_license_header_gate_scan(
    RepositoryContext $repository,
    string $scanRoot,
): array {
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

        $absolutePath = RepositoryContext::normalizePath($absolutePath);
        $relativePath = $repository->relativeToRepo($absolutePath);

        if (coretsia_license_header_gate_is_intrinsically_exempt($relativePath)) {
            continue;
        }

        $prefix = coretsia_license_header_gate_read_prefix($absolutePath);
        $profile = coretsia_license_header_gate_profile($relativePath, $prefix);

        if ($profile === null) {
            continue;
        }

        $result = coretsia_license_header_gate_validate_header(
            $profile,
            $prefix,
            coretsia_license_header_gate_expected_project($relativePath),
        );
        if ($result === null) {
            continue;
        }

        $diagnostics[] = coretsia_license_header_gate_safe_diagnostic_path($relativePath) . ':' . $result;
    }

    return $diagnostics;
}

function coretsia_license_header_gate_is_intrinsically_exempt(string $relativePath): bool
{
    $base = \basename(\str_replace('\\', '/', $relativePath));

    return $base === 'LICENSE' || $base === 'NOTICE';
}

function coretsia_license_header_gate_expected_project(string $relativePath): string
{
    $path = \str_replace('\\', '/', $relativePath);

    if (\str_starts_with($path, 'packages/applications/skeleton/')) {
        return 'Coretsia Skeleton';
    }

    return 'Coretsia Framework (Monorepo)';
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

function coretsia_license_header_gate_validate_header(
    string $profile,
    string $prefix,
    string $expectedProject,
): ?string {
    $normalized = \str_replace(["\r\n", "\r"], "\n", $prefix);

    $pattern = match ($profile) {
        'c-block' => coretsia_license_header_gate_c_block_pattern($expectedProject),
        'html' => coretsia_license_header_gate_html_pattern($expectedProject),
        'hash' => coretsia_license_header_gate_hash_pattern($expectedProject),
        'slash-line' => coretsia_license_header_gate_slash_line_pattern($expectedProject),
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

function coretsia_license_header_gate_c_block_pattern(string $project): string
{
    $project = \preg_quote($project, '~');

    return '~(?:\A|\n)/\*\n'
        . ' \* ' . $project . '\n'
        . ' \*\n'
        . ' \* Project: ' . $project . '\n'
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

function coretsia_license_header_gate_html_pattern(string $project): string
{
    $project = \preg_quote($project, '~');

    return '~(?:\A|\n)<!--\n'
        . '  ' . $project . '\n'
        . '\n'
        . '  Project: ' . $project . '\n'
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

function coretsia_license_header_gate_hash_pattern(string $project): string
{
    $project = \preg_quote($project, '~');

    return '~(?:\A|\n)# ' . $project . '\n'
        . '#\n'
        . '# Project: ' . $project . '\n'
        . '# Authors: (?<authors>[^\n]+)\n'
        . '# Copyright \(c\) (?<copyright>[^\n]+)\n'
        . '#\n'
        . '# SPDX-FileCopyrightText: (?<spdx_copyright>[^\n]+)\n'
        . '# SPDX-License-Identifier: Apache-2\.0\n'
        . '#\n'
        . '# For contributors list, see git history\.\n'
        . '# See LICENSE and NOTICE in the project root for full license information\.(?:\n|\z)~';
}

function coretsia_license_header_gate_slash_line_pattern(string $project): string
{
    $project = \preg_quote($project, '~');

    return '~(?:\A|\n)// ' . $project . '\n'
        . '//\n'
        . '// Project: ' . $project . '\n'
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
