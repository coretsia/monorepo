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
                'CORETSIA_TAG_CONSTANT_MIRROR_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_TAG_CONSTANT_MIRROR_DRIFT';
    $fallbackScanFailed = 'CORETSIA_TAG_CONSTANT_MIRROR_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_TAG_CONSTANT_MIRROR_GATE_FAILED';
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
        $name = $ErrorCodes . '::CORETSIA_TAG_CONSTANT_MIRROR_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_TAG_CONSTANT_MIRROR_GATE_FAILED';
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
        $frameworkRoot = $repoRoot . '/framework';
        $packagesRoot = $frameworkRoot . '/packages';
        $tagsFile = $repoRoot . '/docs/ssot/tags.md';
        $dependencyTableFile = $repoRoot . '/docs/roadmap/phase0/00_2-dependency-table.md';
        $tagOwnerConstantsPolicyFile = $frameworkRoot . '/tools/policies/tag_owner_constants.php';

        if (!\is_file($tagsFile) || !\is_readable($tagsFile)) {
            throw new \RuntimeException('tags-ssot-missing');
        }

        if (!\is_file($dependencyTableFile) || !\is_readable($dependencyTableFile)) {
            throw new \RuntimeException('dependency-table-missing');
        }

        if (!\is_file($tagOwnerConstantsPolicyFile) || !\is_readable($tagOwnerConstantsPolicyFile)) {
            throw new \RuntimeException('tag-owner-constants-policy-missing');
        }

        /** @var array<string, string> $tagOwners */
        $tagOwners = coretsia_tag_constant_mirror_gate_parse_tag_registry($tagsFile);

        if ($tagOwners === []) {
            throw new \RuntimeException('tag-registry-empty');
        }

        /** @var array<string, array<string, true>> $dependencyTable */
        $dependencyTable = coretsia_tag_constant_mirror_gate_parse_dependency_table($dependencyTableFile);

        /** @var array<string, array{owner_package_id:string, constant_required:bool, constant_path:string, constant_name:string}> $tagOwnerConstantsPolicy */
        $tagOwnerConstantsPolicy = coretsia_tag_constant_mirror_gate_load_tag_owner_constants_policy(
            $tagOwnerConstantsPolicyFile,
            $tagOwners,
        );

        /** @var array<string, string> $expectedConstantNames */
        $expectedConstantNames = [];
        foreach (\array_keys($tagOwners) as $tag) {
            $policy = $tagOwnerConstantsPolicy[$tag] ?? null;
            if ($policy !== null) {
                $expectedConstantNames[$policy['constant_name']] = $tag;
                continue;
            }

            $expectedConstantNames[coretsia_tag_constant_mirror_gate_constant_name_for_tag($tag)] = $tag;
        }

        /** @var array<string, true> $knownTagValues */
        $knownTagValues = [];
        foreach (\array_keys($tagOwners) as $tag) {
            $knownTagValues[$tag] = true;
        }

        /** @var array<string, array{id:string, root:string, src:string}> $packages */
        $packages = \is_dir($packagesRoot)
            ? coretsia_tag_constant_mirror_gate_discover_packages($packagesRoot)
            : [];

        /** @var list<string> $violations */
        $violations = [];

        foreach ($tagOwnerConstantsPolicy as $tag => $policy) {
            $constantPath = $policy['constant_path'];
            $constantPathAbs = $repoRoot . '/' . $constantPath;
            $constantPathFrameworkRel = coretsia_tag_constant_mirror_gate_rel_from_framework(
                $constantPathAbs,
                $frameworkRoot,
            );

            if (!\is_file($constantPathAbs)) {
                if ($policy['constant_required']) {
                    $violations[] = $constantPathFrameworkRel . ': owner-tag-constant-missing';
                }

                continue;
            }

            if (!\is_readable($constantPathAbs)) {
                throw new \RuntimeException('owner-tag-constant-file-not-readable');
            }

            $ownerConstants = coretsia_tag_constant_mirror_gate_extract_string_constants($constantPathAbs);
            $matches = coretsia_tag_constant_mirror_gate_find_constants_by_name(
                $ownerConstants,
                $policy['constant_name'],
            );

            if ($matches === []) {
                if ($policy['constant_required']) {
                    $violations[] = $constantPathFrameworkRel . ': owner-tag-constant-missing';
                }

                continue;
            }

            foreach ($matches as $constant) {
                if ($constant['visibility'] !== 'public') {
                    $violations[] = $constantPathFrameworkRel . ': owner-tag-constant-not-public';
                    continue;
                }

                if ($constant['value'] !== $tag) {
                    $violations[] = $constantPathFrameworkRel . ': tag-constant-value-mismatch';
                }
            }
        }

        foreach ($packages as $package) {
            foreach (coretsia_tag_constant_mirror_gate_find_php_sources($package['src']) as $absPath) {
                $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
                $frameworkRelPath = coretsia_tag_constant_mirror_gate_rel_from_framework($absPath, $frameworkRoot);
                $packageRelPath = coretsia_tag_constant_mirror_gate_rel_from_package($absPath, $package['root']);

                foreach (coretsia_tag_constant_mirror_gate_extract_string_constants($absPath) as $constant) {
                    $constantName = $constant['name'];
                    $constantValue = $constant['value'];

                    $tagByName = $expectedConstantNames[$constantName] ?? null;
                    $tagByValue = isset($knownTagValues[$constantValue]) ? $constantValue : null;

                    if ($tagByName === null && $tagByValue === null) {
                        continue;
                    }

                    if ($tagByName !== null && $constantValue !== $tagByName) {
                        $violations[] = $frameworkRelPath . ': tag-constant-value-mismatch';
                        continue;
                    }

                    $tag = $tagByName ?? $tagByValue;
                    if ($tag === null) {
                        continue;
                    }

                    $ownerPackageId = $tagOwners[$tag] ?? null;
                    if ($ownerPackageId === null) {
                        throw new \RuntimeException('tag-owner-missing');
                    }

                    if ($package['id'] === $ownerPackageId) {
                        if ($tagByName !== null && $constant['visibility'] !== 'public') {
                            $violations[] = $frameworkRelPath . ': owner-tag-constant-not-public';
                        }

                        continue;
                    }

                    if (coretsia_tag_constant_mirror_gate_is_competing_owner_api($packageRelPath, $constant['visibility'])) {
                        $violations[] = $frameworkRelPath . ': competing-owner-tag-api';
                        continue;
                    }

                    if (!coretsia_tag_constant_mirror_gate_is_internal_mirror_scope($packageRelPath, $constant['visibility'])) {
                        $violations[] = $frameworkRelPath . ': tag-mirror-public-api';
                        continue;
                    }

                    if (
                        coretsia_tag_constant_mirror_gate_is_allowed_compile_time_dependency(
                            $package['id'],
                            $ownerPackageId,
                            $dependencyTable,
                        )
                    ) {
                        $violations[] = $frameworkRelPath . ': tag-mirror-owner-dependency-allowed';
                    }
                }
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
 * @return array<string, string> tag => owner package_id
 */
function coretsia_tag_constant_mirror_gate_parse_tag_registry(string $tagsFile): array
{
    $content = coretsia_tag_constant_mirror_gate_read_file($tagsFile);
    $lines = \preg_split('/\R/u', $content);
    if (!\is_array($lines)) {
        throw new \RuntimeException('tag-registry-lines-invalid');
    }

    /** @var array<string, string> $tags */
    $tags = [];

    $inRegistry = false;
    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if ($trimmed === '## Reserved Tag Registry (MUST)') {
            $inRegistry = true;
            continue;
        }

        if ($inRegistry && \str_starts_with($trimmed, '## ')) {
            break;
        }

        if (!$inRegistry) {
            continue;
        }

        if (!\str_starts_with($trimmed, '|')) {
            continue;
        }

        if (\str_contains($trimmed, '| tag ') || \str_contains($trimmed, '|---')) {
            continue;
        }

        if (!\preg_match('/^\|\s*`([^`]+)`\s*\|\s*`([^`]+)`\s*\|/u', $trimmed, $m)) {
            continue;
        }

        $tag = $m[1];
        $ownerPackageId = $m[2];

        if (!\preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $tag)) {
            throw new \RuntimeException('tag-registry-tag-invalid');
        }

        if (!\preg_match('/^[a-z][a-z0-9_]*\/[a-z][a-z0-9_-]*$/', $ownerPackageId)) {
            throw new \RuntimeException('tag-registry-owner-invalid');
        }

        if (isset($tags[$tag])) {
            throw new \RuntimeException('tag-registry-duplicate-tag');
        }

        $tags[$tag] = $ownerPackageId;
    }

    \ksort($tags, \SORT_STRING);

    return $tags;
}

/**
 * @return array<string, array<string, true>>
 */
function coretsia_tag_constant_mirror_gate_parse_dependency_table(string $dependencyTableFile): array
{
    $content = coretsia_tag_constant_mirror_gate_read_file($dependencyTableFile);
    $lines = \preg_split('/\R/u', $content);
    if (!\is_array($lines)) {
        throw new \RuntimeException('dependency-table-lines-invalid');
    }

    /** @var array<string, array<string, true>> $table */
    $table = [];

    $inTable = false;
    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if ($trimmed === '## 4) Phase 0 baseline dependency table (MUST)') {
            $inTable = true;
            continue;
        }

        if ($inTable && \str_starts_with($trimmed, '## ')) {
            break;
        }

        if (!$inTable) {
            continue;
        }

        if (!\str_starts_with($trimmed, '|')) {
            continue;
        }

        if (\str_contains($trimmed, '| package_id ') || \str_contains($trimmed, '|---')) {
            continue;
        }

        $cells = \array_map(
            static fn(string $cell): string => \trim($cell),
            \explode('|', \trim($trimmed, '|')),
        );

        if (\count($cells) < 2) {
            continue;
        }

        $packageId = $cells[0];
        $dependsOn = $cells[1];

        if (!\preg_match('/^[a-z][a-z0-9_]*\/[a-z][a-z0-9_-]*$/', $packageId)) {
            throw new \RuntimeException('dependency-table-package-invalid');
        }

        if (isset($table[$packageId])) {
            throw new \RuntimeException('dependency-table-duplicate-package');
        }

        /** @var array<string, true> $deps */
        $deps = [];

        if ($dependsOn !== '—') {
            foreach (\explode(',', $dependsOn) as $dep) {
                $dep = \trim($dep);

                if (!\preg_match('/^[a-z][a-z0-9_]*\/[a-z][a-z0-9_-]*$/', $dep)) {
                    throw new \RuntimeException('dependency-table-dependency-invalid');
                }

                $deps[$dep] = true;
            }
        }

        \ksort($deps, \SORT_STRING);
        $table[$packageId] = $deps;
    }

    if ($table === []) {
        throw new \RuntimeException('dependency-table-empty');
    }

    \ksort($table, \SORT_STRING);

    return $table;
}

/**
 * @param array<string, string> $tagOwners
 * @return array<string, array{owner_package_id:string, constant_required:bool, constant_path:string, constant_name:string}>
 */
function coretsia_tag_constant_mirror_gate_load_tag_owner_constants_policy(
    string $policyFile,
    array $tagOwners,
): array
{
    $bufferStarted = \ob_start();

    try {
        $policy = require $policyFile;
    } finally {
        if ($bufferStarted) {
            $buffer = \ob_get_clean();
            if ($buffer !== '') {
                throw new \RuntimeException('tag-owner-constants-policy-output-forbidden');
            }
        }
    }

    if (!\is_array($policy)) {
        throw new \RuntimeException('tag-owner-constants-policy-invalid');
    }

    /** @var array<string, array{owner_package_id:string, constant_required:bool, constant_path:string, constant_name:string}> $normalized */
    $normalized = [];

    foreach (\array_keys($tagOwners) as $tag) {
        if (!\array_key_exists($tag, $policy)) {
            throw new \RuntimeException('tag-owner-constants-policy-missing-tag');
        }
    }

    foreach ($policy as $tag => $entry) {
        if (!\is_string($tag)) {
            throw new \RuntimeException('tag-owner-constants-policy-tag-invalid');
        }

        if (!isset($tagOwners[$tag])) {
            throw new \RuntimeException('tag-owner-constants-policy-unknown-tag');
        }

        if (!\is_array($entry)) {
            throw new \RuntimeException('tag-owner-constants-policy-entry-invalid');
        }

        $ownerPackageId = $entry['owner_package_id'] ?? null;
        $constantRequired = $entry['constant_required'] ?? null;
        $constantPath = $entry['constant_path'] ?? null;
        $constantName = $entry['constant_name'] ?? null;

        if (!\is_string($ownerPackageId) || $ownerPackageId !== $tagOwners[$tag]) {
            throw new \RuntimeException('tag-owner-constants-policy-owner-invalid');
        }

        if (!\is_bool($constantRequired)) {
            throw new \RuntimeException('tag-owner-constants-policy-required-invalid');
        }

        if (!\is_string($constantPath) || $constantPath === '') {
            throw new \RuntimeException('tag-owner-constants-policy-path-invalid');
        }

        if (!\is_string($constantName) || !\preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName)) {
            throw new \RuntimeException('tag-owner-constants-policy-constant-name-invalid');
        }

        $constantPath = coretsia_tag_constant_mirror_gate_normalize_declared_relative_path($constantPath);
        $expectedPrefix = 'framework/packages/' . $ownerPackageId . '/src/';

        if (!\str_starts_with($constantPath, $expectedPrefix) || !\str_ends_with($constantPath, '.php')) {
            throw new \RuntimeException('tag-owner-constants-policy-path-owner-mismatch');
        }

        $normalized[$tag] = [
            'owner_package_id' => $ownerPackageId,
            'constant_required' => $constantRequired,
            'constant_path' => $constantPath,
            'constant_name' => $constantName,
        ];
    }

    \ksort($normalized, \SORT_STRING);

    return $normalized;
}

function coretsia_tag_constant_mirror_gate_normalize_declared_relative_path(string $path): string
{
    $path = \trim(\str_replace('\\', '/', $path));

    if ($path === '' || \str_starts_with($path, '/') || \str_contains($path, "\0")) {
        throw new \RuntimeException('declared-relative-path-invalid');
    }

    $segments = \explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new \RuntimeException('declared-relative-path-segment-invalid');
        }
    }

    return $path;
}

function coretsia_tag_constant_mirror_gate_constant_name_for_tag(string $tag): string
{
    return \strtoupper(\str_replace('.', '_', $tag));
}

/**
 * @return array<string, array{id:string, root:string, src:string}>
 */
function coretsia_tag_constant_mirror_gate_discover_packages(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

    /** @var array<string, array{id:string, root:string, src:string}> $packages */
    $packages = [];

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

            $srcRoot = $packageRoot . '/src';
            if (!\is_dir($srcRoot)) {
                continue;
            }

            $packageId = $layer . '/' . $slug;

            $packages[$packageId] = [
                'id' => $packageId,
                'root' => \rtrim(\str_replace('\\', '/', $packageRoot), '/'),
                'src' => \rtrim(\str_replace('\\', '/', $srcRoot), '/'),
            ];
        }
    }

    \ksort($packages, \SORT_STRING);

    return $packages;
}

/**
 * @return list<string>
 */
function coretsia_tag_constant_mirror_gate_find_php_sources(string $srcRoot): array
{
    $srcRoot = \rtrim(\str_replace('\\', '/', $srcRoot), '/');

    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $srcRoot,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
    } catch (\Throwable) {
        throw new \RuntimeException('src-root-iterator-failed');
    }

    /** @var list<string> $phpFiles */
    $phpFiles = [];

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof \SplFileInfo) {
            continue;
        }

        if (!$fileInfo->isFile()) {
            continue;
        }

        $absPath = \rtrim(\str_replace('\\', '/', $fileInfo->getPathname()), '/');
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

        $phpFiles[] = $absPath;
    }

    $phpFiles = \array_values(\array_unique($phpFiles));
    \sort($phpFiles, \SORT_STRING);

    return $phpFiles;
}

/**
 * @return list<array{name:string, value:string, visibility:string}>
 */
function coretsia_tag_constant_mirror_gate_extract_string_constants(string $phpFile): array
{
    $source = coretsia_tag_constant_mirror_gate_read_file($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    /** @var list<array{name:string, value:string, visibility:string}> $constants */
    $constants = [];

    $pendingVisibility = 'public';
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token)) {
            if ($token[0] === T_PUBLIC) {
                $pendingVisibility = 'public';
                continue;
            }

            if ($token[0] === T_PROTECTED) {
                $pendingVisibility = 'protected';
                continue;
            }

            if ($token[0] === T_PRIVATE) {
                $pendingVisibility = 'private';
                continue;
            }

            if ($token[0] !== T_CONST) {
                continue;
            }

            $declarationTokens = [];
            $j = $i + 1;
            while ($j < $count) {
                $next = $tokens[$j];

                if ($next === ';') {
                    break;
                }

                $declarationTokens[] = $next;
                $j++;
            }

            foreach (coretsia_tag_constant_mirror_gate_extract_constants_from_declaration($declarationTokens, $pendingVisibility) as $constant) {
                $constants[] = $constant;
            }

            $pendingVisibility = 'public';
            $i = $j;
            continue;
        }

        if ($token === ';' || $token === '{' || $token === '}') {
            $pendingVisibility = 'public';
        }
    }

    return $constants;
}

/**
 * @param list<array{name:string, value:string, visibility:string}> $constants
 * @return list<array{name:string, value:string, visibility:string}>
 */
function coretsia_tag_constant_mirror_gate_find_constants_by_name(array $constants, string $name): array
{
    /** @var list<array{name:string, value:string, visibility:string}> $matches */
    $matches = [];

    foreach ($constants as $constant) {
        if ($constant['name'] === $name) {
            $matches[] = $constant;
        }
    }

    return $matches;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $declarationTokens
 * @return list<array{name:string, value:string, visibility:string}>
 */
function coretsia_tag_constant_mirror_gate_extract_constants_from_declaration(array $declarationTokens, string $visibility): array
{
    /** @var list<list<array{0:int, 1:string, 2:int}|string>> $segments */
    $segments = [];
    $current = [];

    foreach ($declarationTokens as $token) {
        if ($token === ',') {
            $segments[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    if ($current !== []) {
        $segments[] = $current;
    }

    /** @var list<array{name:string, value:string, visibility:string}> $constants */
    $constants = [];

    foreach ($segments as $segment) {
        $equalsIndex = null;
        foreach ($segment as $index => $token) {
            if ($token === '=') {
                $equalsIndex = $index;
                break;
            }
        }

        if ($equalsIndex === null) {
            continue;
        }

        $name = null;
        for ($i = $equalsIndex - 1; $i >= 0; $i--) {
            $token = $segment[$i];

            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_STRING) {
                $name = $token[1];
                break;
            }
        }

        if ($name === null || $name === '') {
            continue;
        }

        $value = null;
        $segmentCount = \count($segment);
        for ($i = $equalsIndex + 1; $i < $segmentCount; $i++) {
            $token = $segment[$i];

            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $value = coretsia_tag_constant_mirror_gate_decode_php_string_literal($token[1]);
                break;
            }

            if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                break;
            }
        }

        if ($value === null) {
            continue;
        }

        $constants[] = [
            'name' => $name,
            'value' => $value,
            'visibility' => $visibility,
        ];
    }

    return $constants;
}

function coretsia_tag_constant_mirror_gate_decode_php_string_literal(string $literal): string
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
 * @param array<string, array<string, true>> $dependencyTable
 */
function coretsia_tag_constant_mirror_gate_is_allowed_compile_time_dependency(
    string $packageId,
    string $ownerPackageId,
    array $dependencyTable,
): bool
{
    if ($packageId === $ownerPackageId) {
        return true;
    }

    return isset($dependencyTable[$packageId][$ownerPackageId]);
}

function coretsia_tag_constant_mirror_gate_is_internal_mirror_scope(string $packageRelPath, string $visibility): bool
{
    $packageRelPath = \ltrim(\str_replace('\\', '/', $packageRelPath), '/');

    if ($visibility === 'private') {
        return true;
    }

    return \str_contains('/' . $packageRelPath, '/src/Internal/')
        || \str_contains('/' . $packageRelPath, '/src/_internal/')
        || \str_contains('/' . $packageRelPath, '/src/Private/');
}

function coretsia_tag_constant_mirror_gate_is_competing_owner_api(string $packageRelPath, string $visibility): bool
{
    $packageRelPath = \ltrim(\str_replace('\\', '/', $packageRelPath), '/');

    if ($visibility === 'private') {
        return false;
    }

    if (
        \str_contains('/' . $packageRelPath, '/src/Internal/')
        || \str_contains('/' . $packageRelPath, '/src/_internal/')
        || \str_contains('/' . $packageRelPath, '/src/Private/')
    ) {
        return false;
    }

    return $packageRelPath === 'src/Tags.php'
        || \str_ends_with($packageRelPath, '/Tags.php')
        || \str_contains('/' . $packageRelPath, '/src/Provider/Tags.php');
}

function coretsia_tag_constant_mirror_gate_read_file(string $path): string
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

function coretsia_tag_constant_mirror_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
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

function coretsia_tag_constant_mirror_gate_rel_from_package(string $absPath, string $packageRoot): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $packageRoot = \rtrim(\str_replace('\\', '/', $packageRoot), '/');

    if ($absPath === $packageRoot) {
        return '.';
    }

    if (!\str_starts_with($absPath, $packageRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($packageRoot) + 1);
}
