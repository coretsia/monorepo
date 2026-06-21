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

        return \is_string($p) ? $p : null;
    });

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT';
    $fallbackScanFailed = 'CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED';
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

    // NOTE (cemented): if bootstrap terminates the process, its deterministic output is authoritative.
    require_once $bootstrap;

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }

    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);

            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED';
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

        foreach ($argv as $arg) {
            if (!\is_string($arg)) {
                continue;
            }

            if (!\str_starts_with($arg, '--root=')) {
                continue;
            }

            $candidate = \substr($arg, \strlen('--root='));
            if ($candidate === '') {
                throw new \RuntimeException('root-override-empty');
            }

            $resolved = $withSuppressedErrors(static function () use ($candidate): ?string {
                $p = \realpath($candidate);

                return \is_string($p) ? $p : null;
            });

            if ($resolved === null || $resolved === '') {
                throw new \RuntimeException('root-override-invalid');
            }

            $repoRoot = $resolved;
            break;
        }

        $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
        if ($repoRoot === '') {
            throw new \RuntimeException('repo-root-invalid');
        }

        $frameworkRoot = $repoRoot . '/framework';
        $packagesRoot = $frameworkRoot . '/packages';
        $tagsFile = $repoRoot . '/docs/ssot/tags.md';
        $reservedTagsFile = $frameworkRoot . '/packages/core/foundation/src/Tag/ReservedTags.php';

        if (!\is_file($tagsFile) || !\is_readable($tagsFile)) {
            throw new \RuntimeException('tags-ssot-missing');
        }

        if (!\is_dir($packagesRoot)) {
            throw new \RuntimeException('packages-root-missing');
        }

        /**
         * @var array<string, string> $expectedConstants
         */
        $expectedConstants = coretsia_reserved_tags_registry_gate_parse_tag_registry($tagsFile);

        if ($expectedConstants === []) {
            throw new \RuntimeException('tag-registry-empty');
        }

        /**
         * @var array<string, true> $knownReservedTags
         */
        $knownReservedTags = [];
        foreach (\array_keys($expectedConstants) as $tag) {
            $knownReservedTags[$tag] = true;
        }

        /** @var list<string> $violations */
        $violations = [];

        $reservedTagsRelPath = 'framework/packages/core/foundation/src/Tag/ReservedTags.php';

        if (!\is_file($reservedTagsFile)) {
            $violations[] = $reservedTagsRelPath . ': reserved-tags-registry-missing';
        } elseif (!\is_readable($reservedTagsFile)) {
            throw new \RuntimeException('reserved-tags-registry-not-readable');
        } else {
            $reservedConstants = coretsia_reserved_tags_registry_gate_extract_constant_declarations($reservedTagsFile);

            /**
             * @var array<string, list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}>> $reservedConstantsByName
             */
            $reservedConstantsByName = [];

            foreach ($reservedConstants as $constant) {
                $reservedConstantsByName[$constant['name']][] = $constant;
            }

            foreach ($expectedConstants as $tag => $constantName) {
                $matches = $reservedConstantsByName[$constantName] ?? [];

                if ($matches === []) {
                    $violations[] = $reservedTagsRelPath . ': reserved-tag-constant-missing:' . $constantName;
                    continue;
                }

                foreach ($matches as $constant) {
                    if ($constant['visibility'] !== 'public') {
                        $violations[] = $reservedTagsRelPath . ': reserved-tag-constant-not-public:' . $constantName;
                        continue;
                    }

                    if ($constant['value'] !== $tag) {
                        $violations[] = $reservedTagsRelPath . ': reserved-tag-constant-value-mismatch:' . $constantName;
                    }
                }
            }

            foreach ($reservedConstants as $constant) {
                if ($constant['visibility'] !== 'public') {
                    continue;
                }

                $value = $constant['value'];
                if ($value === null) {
                    continue;
                }

                if (!coretsia_reserved_tags_registry_gate_is_tag_like($value)) {
                    continue;
                }

                if (!isset($expectedConstants[$value])) {
                    $violations[] = $reservedTagsRelPath . ': reserved-tag-extra-public-constant:' . $constant['name'];
                    continue;
                }

                $expectedName = $expectedConstants[$value];

                if ($constant['name'] !== $expectedName) {
                    $violations[] = $reservedTagsRelPath . ': reserved-tag-extra-public-constant:' . $constant['name'];
                }
            }
        }

        foreach (coretsia_reserved_tags_registry_gate_find_provider_tags_files($packagesRoot, $repoRoot) as $relPath) {
            $violations[] = $relPath . ': provider-tags-file-forbidden';
        }

        $reservedTagsFileReal = \realpath($reservedTagsFile);
        $reservedTagsFileNorm = $reservedTagsFileReal === false
            ? null
            : \rtrim(\str_replace('\\', '/', $reservedTagsFileReal), '/');

        foreach (
            coretsia_reserved_tags_registry_gate_find_package_src_php_files(
                $packagesRoot,
                $repoRoot
            ) as $absPath => $relPath
        ) {
            $absNorm = \rtrim(\str_replace('\\', '/', $absPath), '/');

            if ($reservedTagsFileNorm !== null && $absNorm === $reservedTagsFileNorm) {
                continue;
            }

            foreach (coretsia_reserved_tags_registry_gate_extract_constant_declarations($absPath) as $constant) {
                $value = $constant['value'];

                if ($value !== null && isset($knownReservedTags[$value])) {
                    $violations[] = $relPath . ': reserved-tag-local-constant-forbidden:' . $constant['name'];
                    continue;
                }

                if ($constant['references_reserved_tags']) {
                    $violations[] = $relPath . ': reserved-tag-local-constant-forbidden:' . $constant['name'];
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
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @return array<string, string> tag => expected ReservedTags constant name
 */
function coretsia_reserved_tags_registry_gate_parse_tag_registry(string $tagsFile): array
{
    $content = coretsia_reserved_tags_registry_gate_read_file($tagsFile);
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

        if (!coretsia_reserved_tags_registry_gate_is_tag_like($tag)) {
            throw new \RuntimeException('tag-registry-tag-invalid');
        }

        if (!\preg_match('/\A[a-z][a-z0-9_]*\/[a-z][a-z0-9_-]*\z/', $ownerPackageId)) {
            throw new \RuntimeException('tag-registry-owner-invalid');
        }

        if (isset($tags[$tag])) {
            throw new \RuntimeException('tag-registry-duplicate-tag');
        }

        $tags[$tag] = coretsia_reserved_tags_registry_gate_constant_name_for_tag($tag);
    }

    return $tags;
}

function coretsia_reserved_tags_registry_gate_constant_name_for_tag(string $tag): string
{
    return \strtoupper(\str_replace('.', '_', $tag));
}

function coretsia_reserved_tags_registry_gate_is_tag_like(string $value): bool
{
    return \preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/', $value) === 1;
}

/**
 * @return list<string> repo-root-relative normalized paths
 */
function coretsia_reserved_tags_registry_gate_find_provider_tags_files(string $packagesRoot, string $repoRoot): array
{
    /** @var list<string> $out */
    $out = [];

    $iterator = coretsia_reserved_tags_registry_gate_recursive_files($packagesRoot);

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof \SplFileInfo) {
            continue;
        }

        if (!$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }

        $path = \str_replace('\\', '/', $fileInfo->getPathname());

        if (!\str_ends_with($path, '/src/Provider/Tags.php')) {
            continue;
        }

        $out[] = coretsia_reserved_tags_registry_gate_rel_from_root($path, $repoRoot);
    }

    \sort($out, \SORT_STRING);

    /** @var list<string> $out */
    return $out;
}

/**
 * @return array<string, string> map: absolute normalized path => repo-root-relative normalized path
 */
function coretsia_reserved_tags_registry_gate_find_package_src_php_files(string $packagesRoot, string $repoRoot): array
{
    /** @var array<string, string> $out */
    $out = [];

    $iterator = coretsia_reserved_tags_registry_gate_recursive_files($packagesRoot);

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof \SplFileInfo) {
            continue;
        }

        if (!$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }

        $filename = $fileInfo->getFilename();
        if (!\is_string($filename) || !\str_ends_with($filename, '.php')) {
            continue;
        }

        $path = \str_replace('\\', '/', $fileInfo->getPathname());

        if (!\str_contains($path, '/src/')) {
            continue;
        }

        $real = \realpath($path);
        if ($real === false) {
            throw new \RuntimeException('php-source-realpath-failed');
        }

        $absNorm = \rtrim(\str_replace('\\', '/', $real), '/');
        $out[$absNorm] = coretsia_reserved_tags_registry_gate_rel_from_root($absNorm, $repoRoot);
    }

    \uasort(
        $out,
        static fn (string $a, string $b): int => \strcmp($a, $b),
    );

    return $out;
}

function coretsia_reserved_tags_registry_gate_recursive_files(string $root): \RecursiveIteratorIterator
{
    try {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
    } catch (\Throwable) {
        throw new \RuntimeException('recursive-iterator-failed');
    }
}

function coretsia_reserved_tags_registry_gate_rel_from_root(string $path, string $repoRoot): string
{
    $path = \rtrim(\str_replace('\\', '/', $path), '/');
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    $pathCmp = coretsia_reserved_tags_registry_gate_normalize_for_prefix_check($path);
    $repoRootCmp = coretsia_reserved_tags_registry_gate_normalize_for_prefix_check($repoRoot);

    if ($pathCmp !== $repoRootCmp && !\str_starts_with($pathCmp, $repoRootCmp . '/')) {
        throw new \RuntimeException('path-outside-root');
    }

    if ($pathCmp === $repoRootCmp) {
        return '.';
    }

    $rel = \substr($path, \strlen($repoRoot) + 1);

    return coretsia_reserved_tags_registry_gate_normalize_relative_path($rel);
}

function coretsia_reserved_tags_registry_gate_normalize_for_prefix_check(string $path): string
{
    $path = \rtrim(\str_replace('\\', '/', $path), '/');

    if (\PHP_OS_FAMILY === 'Windows') {
        return \strtolower($path);
    }

    return $path;
}

function coretsia_reserved_tags_registry_gate_normalize_relative_path(string $path): string
{
    $path = \str_replace('\\', '/', $path);
    $parts = \explode('/', $path);

    $out = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            if ($out === []) {
                throw new \RuntimeException('relative-path-escapes-root');
            }

            \array_pop($out);
            continue;
        }

        $out[] = $part;
    }

    return \implode('/', $out);
}

/**
 * @return list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}>
 */
function coretsia_reserved_tags_registry_gate_extract_constant_declarations(string $phpFile): array
{
    $source = coretsia_reserved_tags_registry_gate_read_file($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    /** @var list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}> $constants */
    $constants = [];

    $pendingVisibility = 'public';
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            if ($token === ';' || $token === '{' || $token === '}') {
                $pendingVisibility = 'public';
            }

            continue;
        }

        if ($token[0] === \T_PUBLIC) {
            $pendingVisibility = 'public';
            continue;
        }

        if ($token[0] === \T_PROTECTED) {
            $pendingVisibility = 'protected';
            continue;
        }

        if ($token[0] === \T_PRIVATE) {
            $pendingVisibility = 'private';
            continue;
        }

        if ($token[0] !== \T_CONST) {
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

        foreach (
            coretsia_reserved_tags_registry_gate_extract_constants_from_declaration(
                $declarationTokens,
                $pendingVisibility,
            ) as $constant
        ) {
            $constants[] = $constant;
        }

        $pendingVisibility = 'public';
        $i = $j;
    }

    return $constants;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $declarationTokens
 * @return list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}>
 */
function coretsia_reserved_tags_registry_gate_extract_constants_from_declaration(
    array $declarationTokens,
    string $visibility,
): array {
    /** @var list<list<array{0:int, 1:string, 2:int}|string>> $segments */
    $segments = [];
    $current = [];
    $squareDepth = 0;
    $parenDepth = 0;
    $braceDepth = 0;

    foreach ($declarationTokens as $token) {
        if ($token === '[') {
            $squareDepth++;
        } elseif ($token === ']') {
            $squareDepth = \max(0, $squareDepth - 1);
        } elseif ($token === '(') {
            $parenDepth++;
        } elseif ($token === ')') {
            $parenDepth = \max(0, $parenDepth - 1);
        } elseif ($token === '{') {
            $braceDepth++;
        } elseif ($token === '}') {
            $braceDepth = \max(0, $braceDepth - 1);
        }

        if ($token === ',' && $squareDepth === 0 && $parenDepth === 0 && $braceDepth === 0) {
            $segments[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    if ($current !== []) {
        $segments[] = $current;
    }

    /** @var list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}> $constants */
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
        $nameIndex = null;

        for ($i = $equalsIndex - 1; $i >= 0; $i--) {
            $token = $segment[$i];

            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === \T_STRING) {
                $name = $token[1];
                $nameIndex = $i;
                break;
            }
        }

        if ($name === null || $name === '' || $nameIndex === null) {
            continue;
        }

        $declaredType = coretsia_reserved_tags_registry_gate_declared_const_type($segment, $nameIndex);
        $isStringLikeConstant = $declaredType === null || $declaredType === 'string';

        $value = null;
        $referencesReservedTags = false;
        $segmentCount = \count($segment);

        for ($i = $equalsIndex + 1; $i < $segmentCount; $i++) {
            $token = $segment[$i];

            if (\is_array($token)) {
                if (
                    $isStringLikeConstant
                    && $value === null
                    && $token[0] === \T_CONSTANT_ENCAPSED_STRING
                ) {
                    $value = coretsia_reserved_tags_registry_gate_decode_php_string_literal($token[1]);
                    continue;
                }

                if (
                    coretsia_reserved_tags_registry_gate_is_name_token($token[0])
                    && coretsia_reserved_tags_registry_gate_last_name_segment($token[1]) === 'ReservedTags'
                ) {
                    $referencesReservedTags = true;
                }
            }
        }

        $constants[] = [
            'name' => $name,
            'value' => $isStringLikeConstant ? $value : null,
            'visibility' => $visibility,
            'references_reserved_tags' => $referencesReservedTags,
        ];
    }

    return $constants;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $segment
 */
function coretsia_reserved_tags_registry_gate_declared_const_type(array $segment, int $nameIndex): ?string
{
    for ($i = $nameIndex - 1; $i >= 0; $i--) {
        $token = $segment[$i];

        if (coretsia_reserved_tags_registry_gate_is_ignorable_token($token)) {
            continue;
        }

        if (!\is_array($token)) {
            return null;
        }

        if ($token[0] !== \T_STRING) {
            return null;
        }

        $candidate = \strtolower($token[1]);

        return \in_array(
            $candidate,
            ['array', 'bool', 'false', 'float', 'int', 'mixed', 'null', 'string', 'true'],
            true,
        ) ? $candidate : null;
    }

    return null;
}

/**
 * @param int $id
 */
function coretsia_reserved_tags_registry_gate_is_name_token(int $id): bool
{
    return $id === \T_STRING
        || $id === (\defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1)
        || $id === (\defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1)
        || $id === (\defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1);
}

function coretsia_reserved_tags_registry_gate_last_name_segment(string $name): string
{
    $name = \ltrim($name, '\\');
    $parts = \explode('\\', $name);

    return (string)($parts[\count($parts) - 1] ?? $name);
}

/**
 * @param array{0:int,1:string,2?:int}|string $token
 */
function coretsia_reserved_tags_registry_gate_is_ignorable_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    return $token[0] === \T_WHITESPACE
        || $token[0] === \T_COMMENT
        || $token[0] === \T_DOC_COMMENT;
}

function coretsia_reserved_tags_registry_gate_decode_php_string_literal(string $literal): string
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

function coretsia_reserved_tags_registry_gate_read_file(string $file): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $content = \file_get_contents($file);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($content)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $content;
}
