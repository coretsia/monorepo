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

use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_DOC_VERSION_DRIFT',
    'CORETSIA_DOC_VERSION_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        ) ?? $repository->repoRoot();

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
                    $repository,
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

        return $diagnostics;
    },
));

/**
 * @return list<string>
 */
function coretsia_doc_version_gate_validate_index(
    RepositoryContext $repository,
    string $scanRoot,
    string $indexRelPath,
    string $versionKey,
): array {
    $indexCandidate = $scanRoot . '/' . $indexRelPath;

    if (!\is_file($indexCandidate) || !\is_readable($indexCandidate)) {
        throw new \RuntimeException('index-missing');
    }

    $indexPath = $repository->resolveExistingFile($indexCandidate);
    if (!RepositoryContext::containsPath($scanRoot, $indexPath)) {
        throw new \RuntimeException('index-outside-scan-root');
    }

    $indexContent = DeterministicFile::readBytesExact($indexPath);
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
        $targetCandidate = $scanRoot . '/' . $targetRelPath;

        if (!\is_file($targetCandidate) || !\is_readable($targetCandidate)) {
            $diagnostics[] = $targetRelPath . ': document-missing';
            continue;
        }

        $targetAbsPath = $repository->resolveExistingFile($targetCandidate);
        if (!RepositoryContext::containsPath($scanRoot, $targetAbsPath)) {
            throw new \RuntimeException('document-outside-scan-root');
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
                . (string) $entry['version']
                . ' file-version-'
                . (string) $fileVersion['version'];
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
            $diagnostics[] = $indexRelPath . ': index-entry-format-invalid:line-' . (string) $lineNumber;
            continue;
        }

        $targetRelPath = coretsia_doc_version_gate_normalize_relative_path(
            $indexDir . '/' . \substr($m[1], 2),
        );

        if ($targetRelPath === null) {
            $diagnostics[] = $indexRelPath . ': index-entry-target-invalid:line-' . (string) $lineNumber;
            continue;
        }

        $entries[] = [
            'target_rel_path' => $targetRelPath,
            'version' => (int) $m[2],
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
    $content = DeterministicFile::readBytesExact($documentAbsPath);
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
        'version' => (int) $rawVersion,
        'reason' => null,
    ];
}
