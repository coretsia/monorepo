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

    $fallbackViolation = 'CORETSIA_DOC_VERSION_DRIFT';
    $fallbackGateFailed = 'CORETSIA_DOC_VERSION_GATE_FAILED';

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Support\ConsoleOutput::codeWithDiagnostics(
                $fallbackGateFailed,
                [],
            );
        }

        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/support/bootstrap.php';
    $consoleFile = $toolsRootRuntime . '/support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/support/ErrorCodes.php';

    /** @var class-string $ConsoleOutput */
    $ConsoleOutput = 'Coretsia\\Tools\\Support\\ConsoleOutput';

    /** @var class-string $ErrorCodes */
    $ErrorCodes = 'Coretsia\\Tools\\Support\\ErrorCodes';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_doc_version_gate_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_DOC_VERSION_GATE_FAILED',
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

    $codeViolation = coretsia_doc_version_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DOC_VERSION_DRIFT',
        $fallbackViolation,
    );

    $codeGateFailed = coretsia_doc_version_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DOC_VERSION_GATE_FAILED',
        $fallbackGateFailed,
    );

    try {
        $repoRoot = coretsia_doc_version_gate_resolve_repo_root($toolsRootRuntime);
        $scanRoot = coretsia_doc_version_gate_parse_scan_root($argv, $repoRoot);

        /** @var list<string> $diagnostics */
        $diagnostics = [];

        foreach (
            [
                [
                    'index_rel_path' => 'docs/ssot/INDEX.md',
                    'version_key' => 'ssotVersion',
                ],
                [
                    'index_rel_path' => 'docs/adr/INDEX.md',
                    'version_key' => 'adrVersion',
                ],
            ] as $spec
        ) {
            foreach (
                coretsia_doc_version_gate_validate_index(
                    $scanRoot,
                    $spec['index_rel_path'],
                    $spec['version_key'],
                ) as $diagnostic
            ) {
                $diagnostics[] = $diagnostic;
            }
        }

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
function coretsia_doc_version_gate_error_code_or_fallback(
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

function coretsia_doc_version_gate_resolve_repo_root(string $toolsRootRuntime): string
{
    $repoRoot = \realpath($toolsRootRuntime . '/..' . '/..');

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
function coretsia_doc_version_gate_parse_scan_root(array $argv, string $repoRoot): string
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
        : coretsia_doc_version_gate_resolve_existing_dir($path, $repoRoot);
}

function coretsia_doc_version_gate_resolve_existing_dir(string $path, string $baseRoot): string
{
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        throw new \RuntimeException('path-empty');
    }

    $candidate = coretsia_doc_version_gate_is_absolute_path($path)
        ? $path
        : \rtrim($baseRoot, '/') . '/' . \ltrim($path, '/');

    $real = \realpath($candidate);

    if (!\is_string($real) || !\is_dir($real) || !\is_readable($real)) {
        throw new \RuntimeException('path-invalid');
    }

    return \rtrim(\str_replace('\\', '/', $real), '/');
}

function coretsia_doc_version_gate_is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if ($path[0] === '/') {
        return true;
    }

    return \preg_match('/\A[A-Za-z]:\//', $path) === 1;
}

/**
 * @return list<string>
 */
function coretsia_doc_version_gate_validate_index(
    string $repoRoot,
    string $indexRelPath,
    string $versionKey,
): array {
    $indexPath = $repoRoot . '/' . $indexRelPath;

    if (!\is_file($indexPath) || !\is_readable($indexPath)) {
        throw new \RuntimeException('index-missing');
    }

    $indexContent = coretsia_doc_version_gate_read_file($indexPath);
    $parsed = coretsia_doc_version_gate_parse_index_entries(
        $indexContent,
        $indexRelPath,
        $versionKey,
    );

    $entries = $parsed['entries'];

    /** @var list<string> $diagnostics */
    $diagnostics = $parsed['diagnostics'];

    if ($entries === []) {
        throw new \RuntimeException('index-empty');
    }

    foreach ($entries as $entry) {
        $targetRelPath = $entry['target_rel_path'];
        $targetAbsPath = $repoRoot . '/' . $targetRelPath;

        if (!\is_file($targetAbsPath) || !\is_readable($targetAbsPath)) {
            $diagnostics[] = $targetRelPath . ': document-missing';
            continue;
        }

        $fileVersion = coretsia_doc_version_gate_parse_document_version(
            $targetAbsPath,
            $versionKey,
        );

        if ($fileVersion['reason'] !== null) {
            $diagnostics[] = $targetRelPath . ': ' . $fileVersion['reason'];
            continue;
        }

        if ($fileVersion['version'] !== $entry['version']) {
            $diagnostics[] = $targetRelPath
                . ': '
                . $versionKey
                . '-drift index-version-'
                . (string)$entry['version']
                . ' file-version-'
                . (string)$fileVersion['version'];
        }
    }

    $diagnostics = \array_values(\array_unique($diagnostics));
    \sort($diagnostics, \SORT_STRING);

    return $diagnostics;
}

/**
 * @return array{
 *     entries:list<array{target_rel_path:string, version:int}>,
 *     diagnostics:list<string>
 * }
 */
function coretsia_doc_version_gate_parse_index_entries(
    string $indexContent,
    string $indexRelPath,
    string $versionKey,
): array {
    $lines = \preg_split('/\R/u', $indexContent);
    if (!\is_array($lines)) {
        throw new \RuntimeException('index-lines-invalid');
    }

    /** @var list<array{target_rel_path:string, version:int}> $entries */
    $entries = [];

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    $indexDir = \dirname($indexRelPath);

    foreach ($lines as $offset => $line) {
        if (!\is_string($line)) {
            continue;
        }

        $lineNumber = $offset + 1;
        $trimmed = \trim($line);

        if (!\str_starts_with($trimmed, '- [')) {
            continue;
        }

        if (!\str_contains($trimmed, '](')) {
            continue;
        }

        if (!\str_contains($trimmed, '— owner:')) {
            continue;
        }

        /*
         * Only current-folder documents are version-governed here.
         * Cross-references such as ../roadmap/ROADMAP.md are navigation links.
         */
        if (\preg_match('/^- \[[^]]+]\((\.\/[^)]+\.md)\)/u', $trimmed, $linkMatch) !== 1) {
            continue;
        }

        if (
            \preg_match(
                '/^- \[[^]]+]\((\.\/[^)]+\.md)\) — owner: [^ ]+ — '
                . \preg_quote($versionKey, '/')
                . ': ([1-9][0-9]*) — scope: [a-z0-9][a-z0-9,-]*$/u',
                $trimmed,
                $m,
            ) !== 1
        ) {
            $diagnostics[] = $indexRelPath
                . ': index-entry-format-invalid:line-'
                . (string)$lineNumber;
            continue;
        }

        $targetRelPath = coretsia_doc_version_gate_normalize_relative_path(
            $indexDir . '/' . \substr($m[1], 2),
        );

        if ($targetRelPath === null) {
            $diagnostics[] = $indexRelPath
                . ': index-entry-target-invalid:line-'
                . (string)$lineNumber;
            continue;
        }

        $entries[] = [
            'target_rel_path' => $targetRelPath,
            'version' => (int)$m[2],
        ];
    }

    \usort(
        $entries,
        static fn (array $a, array $b): int => \strcmp($a['target_rel_path'], $b['target_rel_path']),
    );

    $diagnostics = \array_values(\array_unique($diagnostics));
    \sort($diagnostics, \SORT_STRING);

    return [
        'entries' => $entries,
        'diagnostics' => $diagnostics,
    ];
}

function coretsia_doc_version_gate_normalize_relative_path(string $path): ?string
{
    $path = \str_replace('\\', '/', $path);

    if ($path === '' || \str_starts_with($path, '/') || \preg_match('/\A[A-Za-z]:\//', $path) === 1) {
        return null;
    }

    $parts = \explode('/', $path);
    $normalized = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            return null;
        }

        $normalized[] = $part;
    }

    if ($normalized === []) {
        return null;
    }

    return \implode('/', $normalized);
}

/**
 * @return array{version:int|null, reason:string|null}
 */
function coretsia_doc_version_gate_parse_document_version(string $documentAbsPath, string $versionKey): array
{
    $content = coretsia_doc_version_gate_read_file($documentAbsPath);
    $lines = \preg_split('/\R/u', $content);

    if (!\is_array($lines)) {
        throw new \RuntimeException('document-lines-invalid');
    }

    $h1Line = null;
    foreach ($lines as $i => $line) {
        if (!\is_string($line)) {
            continue;
        }

        if (\preg_match('/\A# [^\r\n]+\z/u', $line) === 1) {
            $h1Line = $i;
            break;
        }
    }

    if ($h1Line === null) {
        return [
            'version' => null,
            'reason' => 'h1-missing',
        ];
    }

    $i = $h1Line + 1;
    $count = \count($lines);

    while ($i < $count && \is_string($lines[$i]) && \trim($lines[$i]) === '') {
        $i++;
    }

    if ($i >= $count || !\is_string($lines[$i]) || \trim($lines[$i]) !== '```yaml') {
        return [
            'version' => null,
            'reason' => 'metadata-block-missing',
        ];
    }

    $i++;

    /** @var array<string, string> $metadata */
    $metadata = [];

    for (; $i < $count; $i++) {
        $line = $lines[$i];

        if (!\is_string($line)) {
            continue;
        }

        if (\trim($line) === '```') {
            break;
        }

        if (\preg_match('/\A([A-Za-z][A-Za-z0-9]*): ([^\s#]+)\z/u', $line, $m) === 1) {
            $metadata[$m[1]] = $m[2];
        }
    }

    if ($i >= $count) {
        return [
            'version' => null,
            'reason' => 'metadata-block-unclosed',
        ];
    }

    if (!isset($metadata[$versionKey])) {
        return [
            'version' => null,
            'reason' => $versionKey . '-missing',
        ];
    }

    $rawVersion = $metadata[$versionKey];

    if (\preg_match('/\A[1-9][0-9]*\z/', $rawVersion) !== 1) {
        return [
            'version' => null,
            'reason' => $versionKey . '-invalid',
        ];
    }

    return [
        'version' => (int)$rawVersion,
        'reason' => null,
    ];
}

function coretsia_doc_version_gate_read_file(string $path): string
{
    $content = \file_get_contents($path);

    if (!\is_string($content)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $content;
}
