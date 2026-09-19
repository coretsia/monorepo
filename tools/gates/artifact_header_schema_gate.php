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
use Coretsia\Tools\Support\PhpTokenStream;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT',
    'CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $artifactsFile = $repository->resolveExistingFile('docs/ssot/artifacts.md');
        $registry = coretsia_artifact_header_schema_gate_parse_artifact_registry($artifactsFile);

        if ($registry === []) {
            throw new \RuntimeException('artifact-registry-empty');
        }

        $catalog = WorkspacePackageCatalog::discover($repository);
        $violations = [];

        foreach (
            coretsia_artifact_header_schema_gate_find_generated_artifact_candidates(
                $repository,
                $catalog,
                $registry,
            ) as $absPath
        ) {
            foreach (
                coretsia_artifact_header_schema_gate_validate_artifact_file(
                    $absPath,
                    $repository,
                    $registry,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }

        return $violations;
    },
));

/**
 * @return array<string, array{name:string, schema_version:int}>
 */
function coretsia_artifact_header_schema_gate_parse_artifact_registry(string $artifactsFile): array
{
    $content = DeterministicFile::readTextNormalizedEol($artifactsFile);

    /**
     * Canonical registry entries are expected to be referenced as backticked names
     * such as `container@1`, `config@1`, `module-manifest@1`, `routes@1`.
     *
     * The parser intentionally accepts all such SSoT mentions and de-duplicates
     * them by artifact name/version.
     */
    if (
        \preg_match_all(
            '/`([a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*)@([1-9][0-9]*)`/u',
            $content,
            $matches,
            \PREG_SET_ORDER,
        ) !== false
    ) {
        /** @var array<string, array{name:string, schema_version:int}> $registry */
        $registry = [];

        foreach ($matches as $match) {
            $name = $match[1];
            $schemaVersion = (int) $match[2];

            if (isset($registry[$name]) && $registry[$name]['schema_version'] !== $schemaVersion) {
                throw new \RuntimeException('artifact-registry-conflicting-version');
            }

            $registry[$name] = [
                'name' => $name,
                'schema_version' => $schemaVersion,
            ];
        }

        \ksort($registry, \SORT_STRING);

        if ($registry !== []) {
            return $registry;
        }
    }

    /**
     * Fallback for a table shape where name and schema version are stored in
     * separate backticked columns.
     */
    $lines = \preg_split('/\R/u', $content);
    if (!\is_array($lines)) {
        throw new \RuntimeException('artifact-registry-lines-invalid');
    }

    /** @var array<string, array{name:string, schema_version:int}> $registry */
    $registry = [];

    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if (!\str_starts_with($trimmed, '|')) {
            continue;
        }

        if (\str_contains($trimmed, '|---') || \str_contains($trimmed, '| name ')) {
            continue;
        }

        if (!\preg_match(
            '/^\|\s*`([a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*)`\s*\|\s*`?([1-9][0-9]*)`?\s*\|/u',
            $trimmed,
            $m,
        )) {
            continue;
        }

        $name = $m[1];
        $schemaVersion = (int) $m[2];

        if (isset($registry[$name]) && $registry[$name]['schema_version'] !== $schemaVersion) {
            throw new \RuntimeException('artifact-registry-conflicting-version');
        }

        $registry[$name] = [
            'name' => $name,
            'schema_version' => $schemaVersion,
        ];
    }

    \ksort($registry, \SORT_STRING);

    return $registry;
}

/**
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_find_generated_artifact_candidates(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    array $registry,
): array {
    /** @var list<string> $scanRoots */
    $scanRoots = [];

    foreach (coretsia_artifact_header_schema_gate_candidate_generated_roots($repository, $catalog) as $root) {
        if (\is_link($root)) {
            throw new \RuntimeException('artifact-generated-root-symlink');
        }

        if (!\is_dir($root)) {
            continue;
        }

        $scanRoots[] = $repository->resolveExistingDirectory($root);
    }

    if ($scanRoots === []) {
        return [];
    }

    /** @var array<string, true> $knownBasenames */
    $knownBasenames = [];

    foreach (\array_keys($registry) as $artifactName) {
        foreach (coretsia_artifact_header_schema_gate_artifact_basename_variants($artifactName) as $variant) {
            $knownBasenames[$variant . '.json'] = true;
            $knownBasenames[$variant . '.php'] = true;
        }
    }

    /** @var list<string> $files */
    $files = [];

    foreach (\array_values(\array_unique($scanRoots)) as $scanRoot) {
        try {
            $iterator = coretsia_artifact_header_schema_gate_iterate_files($scanRoot);
        } catch (\Throwable) {
            throw new \RuntimeException('artifact-generated-root-iterator-failed');
        }

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $absPath = \rtrim(\str_replace('\\', '/', $fileInfo->getPathname()), '/');
            $repoRelative = $repository->relativeToRepo($absPath);

            if (coretsia_artifact_header_schema_gate_is_excluded_path($repoRelative)) {
                continue;
            }

            $basename = \basename($absPath);
            $ext = \strtolower((string) \pathinfo($basename, \PATHINFO_EXTENSION));

            if ($ext !== 'json' && $ext !== 'php') {
                continue;
            }

            if (isset($knownBasenames[$basename])) {
                $files[] = $absPath;
                continue;
            }

            $content = DeterministicFile::readBytesExact($absPath);
            if (\str_contains($content, '_meta') && \str_contains($content, 'payload')) {
                $files[] = $absPath;
            }
        }
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

/**
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_candidate_generated_roots(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
): array {
    $roots = [
        $repository->resolve('var'),
        $repository->resolve('generated'),
        $repository->resolve('artifacts'),
        $repository->resolve('.generated'),
        $repository->resolve('.artifacts'),
    ];

    foreach ($catalog->all() as $product) {
        $packageRoot = $product['absolutePath'];

        $roots[] = $packageRoot . '/var';
        $roots[] = $packageRoot . '/generated';
        $roots[] = $packageRoot . '/artifacts';
        $roots[] = $packageRoot . '/.generated';
        $roots[] = $packageRoot . '/.artifacts';
        $roots[] = $packageRoot . '/build/generated';
        $roots[] = $packageRoot . '/build/artifacts';
    }

    $roots = \array_values(\array_unique($roots));
    \sort($roots, \SORT_STRING);

    return $roots;
}

/**
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_artifact_basename_variants(string $artifactName): array
{
    $variants = [
        $artifactName,
        \str_replace('-', '_', $artifactName),
        \str_replace('.', '-', $artifactName),
        \str_replace('.', '_', $artifactName),
    ];

    $variants = \array_values(\array_unique($variants));
    \sort($variants, \SORT_STRING);

    return $variants;
}

/**
 * @return \Traversable<int, \SplFileInfo>
 */
function coretsia_artifact_header_schema_gate_iterate_files(string $scanRoot): \Traversable
{
    return new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(
            $scanRoot,
            \FilesystemIterator::SKIP_DOTS,
        ),
        \RecursiveIteratorIterator::LEAVES_ONLY,
    );
}

function coretsia_artifact_header_schema_gate_is_excluded_path(string $relativePath): bool
{
    $relativePath = \trim(\str_replace('\\', '/', $relativePath), '/');
    $withBoundaries = '/' . $relativePath . '/';

    return \str_contains($withBoundaries, '/vendor/')
        || \str_contains($withBoundaries, '/node_modules/')
        || \str_contains($withBoundaries, '/tests/')
        || \str_contains($withBoundaries, '/fixtures/')
        || \str_contains($withBoundaries, '/.git/')
        || \str_contains($withBoundaries, '/.cache/')
        || \str_contains($withBoundaries, '/var/phpstan/')
        || \str_contains($withBoundaries, '/var/phpunit/')
        || \str_contains($withBoundaries, '/var/cache/')
        || \str_contains($withBoundaries, '/var/backups/')
        || \str_contains($withBoundaries, '/tools/build/');
}

/**
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_validate_artifact_file(
    string $absPath,
    RepositoryContext $repository,
    array $registry,
): array {
    $repoRelPath = $repository->relativeToRepo($absPath);
    $content = DeterministicFile::readBytesExact($absPath);

    /** @var list<string> $violations */
    $violations = [];

    foreach (coretsia_artifact_header_schema_gate_detect_forbidden_bytes($content, $repoRelPath) as $violation) {
        $violations[] = $violation;
    }

    $ext = \strtolower((string) \pathinfo($absPath, \PATHINFO_EXTENSION));

    if ($ext === 'json') {
        foreach (
            coretsia_artifact_header_schema_gate_validate_json_artifact(
                $content,
                $repoRelPath,
                $registry,
            ) as $violation
        ) {
            $violations[] = $violation;
        }

        return coretsia_artifact_header_schema_gate_unique_sorted($violations);
    }

    if ($ext === 'php') {
        foreach (
            coretsia_artifact_header_schema_gate_validate_php_artifact(
                $content,
                $repoRelPath,
                $registry,
            ) as $violation
        ) {
            $violations[] = $violation;
        }

        return coretsia_artifact_header_schema_gate_unique_sorted($violations);
    }

    return [];
}

/**
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_validate_json_artifact(
    string $content,
    string $repoRelPath,
    array $registry,
): array {
    /** @var list<string> $violations */
    $violations = [];

    $decoded = \json_decode($content, true);

    if (!\is_array($decoded) || \array_is_list($decoded)) {
        return [$repoRelPath . ': artifact-envelope-invalid'];
    }

    foreach (
        coretsia_artifact_header_schema_gate_validate_decoded_envelope(
            $decoded,
            $repoRelPath,
            $registry,
        ) as $violation
    ) {
        $violations[] = $violation;
    }

    return coretsia_artifact_header_schema_gate_unique_sorted($violations);
}

/**
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_validate_php_artifact(
    string $content,
    string $repoRelPath,
    array $registry,
): array {
    /** @var list<string> $violations */
    $violations = [];

    if (!coretsia_artifact_header_schema_gate_php_source_has_return_array($content)) {
        return [$repoRelPath . ': artifact-envelope-invalid'];
    }

    $returnArrayBlock = coretsia_artifact_header_schema_gate_extract_php_return_array_block($content);
    if ($returnArrayBlock === null) {
        return [$repoRelPath . ': artifact-envelope-invalid'];
    }

    $topKeys = coretsia_artifact_header_schema_gate_extract_php_array_string_keys($returnArrayBlock);

    if (!isset($topKeys['_meta']) || !isset($topKeys['payload']) || \count($topKeys) !== 2) {
        $violations[] = $repoRelPath . ': artifact-envelope-invalid';
    }

    $metaBlock = coretsia_artifact_header_schema_gate_extract_php_meta_array_block($returnArrayBlock);
    if ($metaBlock === null) {
        $violations[] = $repoRelPath . ': artifact-meta-invalid';
        return coretsia_artifact_header_schema_gate_unique_sorted($violations);
    }

    $meta = coretsia_artifact_header_schema_gate_extract_php_meta_scalar_values($metaBlock);

    foreach (['name', 'schemaVersion', 'fingerprint', 'generator'] as $field) {
        if (!\array_key_exists($field, $meta)) {
            $violations[] = $repoRelPath . ': artifact-meta-field-missing';
        }
    }

    if (isset($meta['name']) && (!\is_string($meta['name']) || $meta['name'] === '')) {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (isset($meta['schemaVersion']) && !\is_int($meta['schemaVersion'])) {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (isset($meta['fingerprint']) && (!\is_string($meta['fingerprint']) || $meta['fingerprint'] === '')) {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (isset($meta['generator']) && (!\is_string($meta['generator']) || $meta['generator'] === '')) {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (isset($meta['name']) && \is_string($meta['name'])) {
        $artifactName = $meta['name'];

        if (!isset($registry[$artifactName])) {
            $violations[] = $repoRelPath . ': artifact-name-unregistered';
        } elseif (isset($meta['schemaVersion']) && \is_int($meta['schemaVersion'])) {
            if ($meta['schemaVersion'] !== $registry[$artifactName]['schema_version']) {
                $violations[] = $repoRelPath . ': artifact-schema-version-mismatch';
            }
        }
    }

    return coretsia_artifact_header_schema_gate_unique_sorted($violations);
}

/**
 * @param array<mixed> $decoded
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_validate_decoded_envelope(
    array $decoded,
    string $repoRelPath,
    array $registry,
): array {
    /** @var list<string> $violations */
    $violations = [];

    if (!isset($decoded['_meta']) || !\array_key_exists('payload', $decoded) || \count($decoded) !== 2) {
        $violations[] = $repoRelPath . ': artifact-envelope-invalid';
    }

    $meta = $decoded['_meta'] ?? null;
    if (!\is_array($meta) || \array_is_list($meta)) {
        $violations[] = $repoRelPath . ': artifact-meta-invalid';
        return coretsia_artifact_header_schema_gate_unique_sorted($violations);
    }

    foreach (['name', 'schemaVersion', 'fingerprint', 'generator'] as $field) {
        if (!\array_key_exists($field, $meta)) {
            $violations[] = $repoRelPath . ': artifact-meta-field-missing';
        }
    }

    $name = $meta['name'] ?? null;
    $schemaVersion = $meta['schemaVersion'] ?? null;
    $fingerprint = $meta['fingerprint'] ?? null;
    $generator = $meta['generator'] ?? null;

    if (!\is_string($name) || $name === '') {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (!\is_int($schemaVersion)) {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (!\is_string($fingerprint) || $fingerprint === '') {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (!\is_string($generator) || $generator === '') {
        $violations[] = $repoRelPath . ': artifact-meta-field-invalid';
    }

    if (\is_string($name)) {
        if (!isset($registry[$name])) {
            $violations[] = $repoRelPath . ': artifact-name-unregistered';
        } elseif (\is_int($schemaVersion) && $schemaVersion !== $registry[$name]['schema_version']) {
            $violations[] = $repoRelPath . ': artifact-schema-version-mismatch';
        }
    }

    return coretsia_artifact_header_schema_gate_unique_sorted($violations);
}

/**
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_detect_forbidden_bytes(string $content, string $repoRelPath): array
{
    /** @var list<string> $violations */
    $violations = [];

    if (
        \preg_match('/\b(?:generated_at|created_at|updated_at|timestamp|datetime|dateTime)\b/u', $content) === 1
        || \preg_match('/\b20[0-9]{2}-[0-9]{2}-[0-9]{2}[T ][0-9]{2}:[0-9]{2}:[0-9]{2}/u', $content) === 1
    ) {
        $violations[] = $repoRelPath . ': artifact-forbidden-timestamp';
    }

    if (
        \preg_match('/[A-Za-z]:[\\\\\/][^\'"\s]+/u', $content) === 1
        || \preg_match('/\\\\\\\\[A-Za-z0-9_.-]+\\\\[A-Za-z0-9_.-]+/u', $content) === 1
        || \preg_match(
            '#/(?:home|Users|mnt|tmp|private/var|var/folders|workspace|workspaces|runner|builds)/#u',
            $content,
        ) === 1
    ) {
        $violations[] = $repoRelPath . ': artifact-forbidden-absolute-path';
    }

    if (
        \preg_match(
            '/\b(?:GITHUB_|RUNNER_|CI_|USERPROFILE|COMPUTERNAME|HOSTNAME|USERNAME|HOME|PWD|TMPDIR|TEMP|TMP)\b/u',
            $content,
        ) === 1
        || \preg_match('/%\w+%/u', $content) === 1
        || \preg_match('/\$_(?:ENV|SERVER)\b/u', $content) === 1
        || \preg_match('/\bgetenv\s*\(/u', $content) === 1
    ) {
        $violations[] = $repoRelPath . ': artifact-env-specific-bytes';
    }

    return coretsia_artifact_header_schema_gate_unique_sorted($violations);
}

function coretsia_artifact_header_schema_gate_php_source_has_return_array(string $content): bool
{
    return \preg_match('/\breturn\s*(?:array\s*\(|\[)/u', $content) === 1;
}

function coretsia_artifact_header_schema_gate_extract_php_return_array_block(string $content): ?string
{
    if (
        \preg_match(
            '/\breturn\s*(array\s*\(|\[)/u',
            $content,
            $m,
            \PREG_OFFSET_CAPTURE,
        ) !== 1
    ) {
        return null;
    }

    $matchText = (string) $m[1][0];
    $matchOffset = (int) $m[1][1];

    if (\str_ends_with($matchText, '[')) {
        $openPos = $matchOffset + \strlen($matchText) - 1;
    } else {
        $openPos = \strpos($content, '(', $matchOffset);
    }

    if ($openPos === false) {
        return null;
    }

    $open = $content[$openPos];
    $close = $open === '[' ? ']' : ')';

    return coretsia_artifact_header_schema_gate_extract_balanced_block($content, $openPos, $open, $close);
}

function coretsia_artifact_header_schema_gate_extract_php_meta_array_block(string $returnArrayBlock): ?string
{
    if (
        \preg_match(
            '/([\'"])_meta\1\s*=>\s*(array\s*\(|\[)/u',
            $returnArrayBlock,
            $m,
            \PREG_OFFSET_CAPTURE,
        ) !== 1
    ) {
        return null;
    }

    $matchText = (string) $m[2][0];
    $matchOffset = (int) $m[2][1];

    if (\str_ends_with($matchText, '[')) {
        $openPos = $matchOffset + \strlen($matchText) - 1;
    } else {
        $openPos = \strpos($returnArrayBlock, '(', $matchOffset);
    }

    if ($openPos === false) {
        return null;
    }

    $open = $returnArrayBlock[$openPos];
    $close = $open === '[' ? ']' : ')';

    return coretsia_artifact_header_schema_gate_extract_balanced_block($returnArrayBlock, $openPos, $open, $close);
}

function coretsia_artifact_header_schema_gate_extract_balanced_block(
    string $source,
    int $openPos,
    string $open,
    string $close,
): ?string {
    $len = \strlen($source);
    $depth = 0;

    for ($i = $openPos; $i < $len; $i++) {
        $char = $source[$i];

        if ($char === "'" || $char === '"') {
            $i = coretsia_artifact_header_schema_gate_skip_php_string($source, $i);
            continue;
        }

        if ($char === '/' && ($source[$i + 1] ?? '') === '/') {
            $next = \strpos($source, "\n", $i + 2);
            if ($next === false) {
                return null;
            }

            $i = $next;
            continue;
        }

        if ($char === '#') {
            $next = \strpos($source, "\n", $i + 1);
            if ($next === false) {
                return null;
            }

            $i = $next;
            continue;
        }

        if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
            $next = \strpos($source, '*/', $i + 2);
            if ($next === false) {
                return null;
            }

            $i = $next + 1;
            continue;
        }

        if ($char === $open) {
            $depth++;
            continue;
        }

        if ($char === $close) {
            $depth--;

            if ($depth === 0) {
                return \substr($source, $openPos, $i - $openPos + 1);
            }
        }
    }

    return null;
}

function coretsia_artifact_header_schema_gate_skip_php_string(string $source, int $start): int
{
    $quote = $source[$start];
    $len = \strlen($source);

    for ($i = $start + 1; $i < $len; $i++) {
        if ($source[$i] === '\\') {
            $i++;
            continue;
        }

        if ($source[$i] === $quote) {
            return $i;
        }
    }

    return $len - 1;
}

/**
 * @return array<string, true>
 */
function coretsia_artifact_header_schema_gate_extract_php_array_string_keys(string $arrayBlock): array
{
    /** @var array<string, true> $keys */
    $keys = [];

    $len = \strlen($arrayBlock);
    $depth = 0;

    for ($i = 0; $i < $len; $i++) {
        $char = $arrayBlock[$i];

        if ($char === "'" || $char === '"') {
            $end = coretsia_artifact_header_schema_gate_skip_php_string($arrayBlock, $i);
            $literal = \substr($arrayBlock, $i, $end - $i + 1);

            if ($depth === 1) {
                $after = coretsia_artifact_header_schema_gate_next_non_ws_offset($arrayBlock, $end + 1);
                if ($after !== null && \substr($arrayBlock, $after, 2) === '=>') {
                    $keys[PhpTokenStream::decodeStringLiteral($literal)] = true;
                }
            }

            $i = $end;
            continue;
        }

        if ($char === '[' || $char === '(') {
            $depth++;
            continue;
        }

        if ($char === ']' || $char === ')') {
            $depth--;
        }
    }

    \ksort($keys, \SORT_STRING);

    return $keys;
}

/**
 * @return array<string, string|int>
 */
function coretsia_artifact_header_schema_gate_extract_php_meta_scalar_values(string $metaBlock): array
{
    /** @var array<string, string|int> $values */
    $values = [];

    foreach (['name', 'fingerprint', 'generator'] as $field) {
        if (
            \preg_match(
                '/([\'"])' . \preg_quote($field, '/') . '\1\s*=>\s*([\'"])(.*?)\2/u',
                $metaBlock,
                $m,
            ) === 1
        ) {
            $values[$field] = PhpTokenStream::decodeStringLiteral(
                $m[2] . $m[3] . $m[2],
            );
        }
    }

    if (
        \preg_match(
            '/([\'"])schemaVersion\1\s*=>\s*([0-9]+)\s*(?=[,\x5D)])/u',
            $metaBlock,
            $m,
        ) === 1
    ) {
        $values['schemaVersion'] = (int) $m[2];
    }

    return $values;
}

function coretsia_artifact_header_schema_gate_next_non_ws_offset(string $source, int $start): ?int
{
    $len = \strlen($source);

    for ($i = $start; $i < $len; $i++) {
        if (!\ctype_space($source[$i])) {
            return $i;
        }
    }

    return null;
}

/**
 * @param list<string> $values
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}
