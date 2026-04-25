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
                'CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT';
    $fallbackScanFailed = 'CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED';
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
        $name = $ErrorCodes . '::CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackScanFailed;
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
        $artifactsFile = $repoRoot . '/docs/ssot/artifacts.md';

        if (!\is_file($artifactsFile) || !\is_readable($artifactsFile)) {
            throw new \RuntimeException('artifacts-ssot-missing');
        }

        /**
         * @var array<string, array{name:string, schema_version:int}> $registry
         */
        $registry = coretsia_artifact_header_schema_gate_parse_artifact_registry($artifactsFile);

        if ($registry === []) {
            throw new \RuntimeException('artifact-registry-empty');
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_artifact_header_schema_gate_find_generated_artifact_candidates($repoRoot, $registry) as $absPath) {
            foreach (
                coretsia_artifact_header_schema_gate_validate_artifact_file(
                    $absPath,
                    $repoRoot,
                    $registry,
                ) as $violation
            ) {
                $violations[] = $violation;
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
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return array<string, array{name:string, schema_version:int}>
 */
function coretsia_artifact_header_schema_gate_parse_artifact_registry(string $artifactsFile): array
{
    $content = coretsia_artifact_header_schema_gate_read_file($artifactsFile);

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
            $schemaVersion = (int)$match[2];

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

        if (!\preg_match('/^\|\s*`([a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*)`\s*\|\s*`?([1-9][0-9]*)`?\s*\|/u', $trimmed, $m)) {
            continue;
        }

        $name = $m[1];
        $schemaVersion = (int)$m[2];

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
function coretsia_artifact_header_schema_gate_find_generated_artifact_candidates(string $repoRoot, array $registry): array
{
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    /** @var list<string> $scanRoots */
    $scanRoots = [];

    foreach (coretsia_artifact_header_schema_gate_candidate_generated_roots($repoRoot) as $root) {
        if (\is_dir($root)) {
            $scanRoots[] = \rtrim(\str_replace('\\', '/', $root), '/');
        }
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
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $scanRoot,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\Throwable) {
            throw new \RuntimeException('artifact-generated-root-iterator-failed');
        }

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if (!$fileInfo->isFile()) {
                continue;
            }

            $absPath = \rtrim(\str_replace('\\', '/', $fileInfo->getPathname()), '/');

            if (coretsia_artifact_header_schema_gate_is_excluded_path($absPath)) {
                continue;
            }

            $basename = \basename($absPath);
            $ext = \strtolower((string)\pathinfo($basename, \PATHINFO_EXTENSION));

            if ($ext !== 'json' && $ext !== 'php') {
                continue;
            }

            if (isset($knownBasenames[$basename])) {
                $files[] = $absPath;
                continue;
            }

            $content = coretsia_artifact_header_schema_gate_read_file($absPath);
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
function coretsia_artifact_header_schema_gate_candidate_generated_roots(string $repoRoot): array
{
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    /** @var list<string> $roots */
    $roots = [
        $repoRoot . '/var',
        $repoRoot . '/generated',
        $repoRoot . '/artifacts',
        $repoRoot . '/.generated',
        $repoRoot . '/.artifacts',
        $repoRoot . '/framework/var',
        $repoRoot . '/framework/generated',
        $repoRoot . '/framework/artifacts',
        $repoRoot . '/framework/.generated',
        $repoRoot . '/framework/.artifacts',
        $repoRoot . '/framework/build/generated',
        $repoRoot . '/framework/build/artifacts',
        $repoRoot . '/skeleton/var',
        $repoRoot . '/skeleton/generated',
        $repoRoot . '/skeleton/artifacts',
        $repoRoot . '/skeleton/.generated',
        $repoRoot . '/skeleton/.artifacts',
        $repoRoot . '/skeleton/bootstrap/cache',
    ];

    $packagesRoot = $repoRoot . '/framework/packages';
    if (!\is_dir($packagesRoot)) {
        return $roots;
    }

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

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
            throw new \RuntimeException('package-layer-scan-failed');
        }

        foreach ($slugs as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }

            $packageRoot = $layerDir . '/' . $slug;
            if (!\is_dir($packageRoot)) {
                continue;
            }

            $roots[] = $packageRoot . '/var';
            $roots[] = $packageRoot . '/generated';
            $roots[] = $packageRoot . '/artifacts';
            $roots[] = $packageRoot . '/.generated';
            $roots[] = $packageRoot . '/.artifacts';
            $roots[] = $packageRoot . '/build/generated';
            $roots[] = $packageRoot . '/build/artifacts';
        }
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

function coretsia_artifact_header_schema_gate_is_excluded_path(string $absPath): bool
{
    $absPath = \str_replace('\\', '/', $absPath);

    return \str_contains($absPath, '/vendor/')
        || \str_contains($absPath, '/node_modules/')
        || \str_contains($absPath, '/tests/')
        || \str_contains($absPath, '/fixtures/')
        || \str_contains($absPath, '/.git/')
        || \str_contains($absPath, '/.cache/')
        || \str_contains($absPath, '/framework/.cache/')
        || \str_contains($absPath, '/framework/var/phpstan/')
        || \str_contains($absPath, '/framework/var/phpunit/')
        || \str_contains($absPath, '/framework/var/cache/')
        || \str_contains($absPath, '/framework/var/backups/')
        || \str_contains($absPath, '/framework/tools/build/');
}

/**
 * @param array<string, array{name:string, schema_version:int}> $registry
 * @return list<string>
 */
function coretsia_artifact_header_schema_gate_validate_artifact_file(
    string $absPath,
    string $repoRoot,
    array  $registry,
): array
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    $repoRelPath = coretsia_artifact_header_schema_gate_rel_from_repo($absPath, $repoRoot);
    $content = coretsia_artifact_header_schema_gate_read_file($absPath);

    /** @var list<string> $violations */
    $violations = [];

    foreach (coretsia_artifact_header_schema_gate_detect_forbidden_bytes($content, $repoRelPath) as $violation) {
        $violations[] = $violation;
    }

    $ext = \strtolower((string)\pathinfo($absPath, \PATHINFO_EXTENSION));

    if ($ext === 'json') {
        foreach (coretsia_artifact_header_schema_gate_validate_json_artifact($content, $repoRelPath, $registry) as $violation) {
            $violations[] = $violation;
        }

        return coretsia_artifact_header_schema_gate_unique_sorted($violations);
    }

    if ($ext === 'php') {
        foreach (coretsia_artifact_header_schema_gate_validate_php_artifact($content, $repoRelPath, $registry) as $violation) {
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
    array  $registry,
): array
{
    /** @var list<string> $violations */
    $violations = [];

    $decoded = \json_decode($content, true);

    if (!\is_array($decoded) || \array_is_list($decoded)) {
        return [$repoRelPath . ': artifact-envelope-invalid'];
    }

    foreach (coretsia_artifact_header_schema_gate_validate_decoded_envelope($decoded, $repoRelPath, $registry) as $violation) {
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
    array  $registry,
): array
{
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
    array  $decoded,
    string $repoRelPath,
    array  $registry,
): array
{
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
        || \preg_match('#/(?:home|Users|mnt|tmp|private/var|var/folders|workspace|workspaces|runner|builds)/#u', $content) === 1
    ) {
        $violations[] = $repoRelPath . ': artifact-forbidden-absolute-path';
    }

    if (
        \preg_match('/\b(?:GITHUB_|RUNNER_|CI_|USERPROFILE|COMPUTERNAME|HOSTNAME|USERNAME|HOME|PWD|TMPDIR|TEMP|TMP)\b/u', $content) === 1
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

    $matchText = $m[1][0] ?? null;
    $matchOffset = $m[1][1] ?? null;

    if (!\is_string($matchText) || !\is_int($matchOffset)) {
        return null;
    }

    if (\str_ends_with($matchText, '[')) {
        $openPos = $matchOffset + \strlen($matchText) - 1;
    } else {
        $openPos = \strpos($content, '(', $matchOffset);
    }

    if (!\is_int($openPos)) {
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

    $matchText = $m[2][0] ?? null;
    $matchOffset = $m[2][1] ?? null;

    if (!\is_string($matchText) || !\is_int($matchOffset)) {
        return null;
    }

    if (\str_ends_with($matchText, '[')) {
        $openPos = $matchOffset + \strlen($matchText) - 1;
    } else {
        $openPos = \strpos($returnArrayBlock, '(', $matchOffset);
    }

    if (!\is_int($openPos)) {
        return null;
    }

    $open = $returnArrayBlock[$openPos];
    $close = $open === '[' ? ']' : ')';

    return coretsia_artifact_header_schema_gate_extract_balanced_block($returnArrayBlock, $openPos, $open, $close);
}

function coretsia_artifact_header_schema_gate_extract_balanced_block(
    string $source,
    int    $openPos,
    string $open,
    string $close,
): ?string
{
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
                    $keys[coretsia_artifact_header_schema_gate_decode_php_string_literal($literal)] = true;
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
            $values[$field] = coretsia_artifact_header_schema_gate_decode_php_string_literal($m[2] . $m[3] . $m[2]);
        }
    }

    if (
        \preg_match(
            '/([\'"])schemaVersion\1\s*=>\s*([0-9]+)\s*(?=[,\x5D)])/u',
            $metaBlock,
            $m,
        ) === 1
    ) {
        $values['schemaVersion'] = (int)$m[2];
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

function coretsia_artifact_header_schema_gate_decode_php_string_literal(string $literal): string
{
    if (\strlen($literal) < 2) {
        throw new \RuntimeException('php-string-literal-invalid');
    }

    $quote = $literal[0];
    $inner = \substr($literal, 1, -1);

    if ($quote === "'") {
        return \str_replace(
            ["\\\\", "\\'"],
            ["\\", "'"],
            $inner,
        );
    }

    if ($quote === '"') {
        return \stripcslashes($inner);
    }

    throw new \RuntimeException('php-string-literal-quote-invalid');
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

function coretsia_artifact_header_schema_gate_read_file(string $path): string
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

function coretsia_artifact_header_schema_gate_rel_from_repo(string $absPath, string $repoRoot): string
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
