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
                'CORETSIA_DTO_GATE_SCAN_FAILED',
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

    $fallbackViolation = 'CORETSIA_DTO_MARKER_VIOLATION';
    $fallbackScanFailed = 'CORETSIA_DTO_GATE_SCAN_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_dto_marker_consistency_gate_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_DTO_GATE_SCAN_FAILED',
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

    $codeViolation = coretsia_dto_marker_consistency_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DTO_MARKER_VIOLATION',
        $fallbackViolation,
    );

    $codeScanFailed = coretsia_dto_marker_consistency_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DTO_GATE_SCAN_FAILED',
        $fallbackScanFailed,
    );

    try {
        $defaultScanRoot = $toolsRootRuntime . '/..';
        $scanRoot = coretsia_dto_marker_consistency_gate_resolve_scan_root($argv, $defaultScanRoot);

        $diagnostics = coretsia_dto_marker_consistency_gate_scan($scanRoot);
        $diagnostics = \array_values(\array_unique($diagnostics));
        \sort($diagnostics, \SORT_STRING);

        if ($diagnostics === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})(isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []);

/**
 * @param list<string> $argv
 */
function coretsia_dto_marker_consistency_gate_resolve_scan_root(array $argv, string $defaultScanRoot): string
{
    $scanRoot = $defaultScanRoot;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg)) {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $scanRoot = \substr($arg, \strlen('--path='));
            break;
        }
    }

    $realScanRoot = \realpath($scanRoot);

    if (!\is_string($realScanRoot) || !\is_dir($realScanRoot) || !\is_readable($realScanRoot)) {
        throw new \RuntimeException('scan-root-invalid');
    }

    return \str_replace('\\', '/', $realScanRoot);
}

/**
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_scan(string $scanRoot): array
{
    $files = coretsia_dto_marker_consistency_gate_collect_php_source_files($scanRoot);

    /** @var list<string> $diagnostics */
    $diagnostics = [];
    $hasCanonicalMarkerStrategy = false;

    /** @var list<string> $alternativeStrategyPaths */
    $alternativeStrategyPaths = [];

    foreach ($files as $file) {
        $relativePath = coretsia_dto_marker_consistency_gate_normalize_relative_path($scanRoot, $file);

        foreach (
            coretsia_dto_marker_consistency_gate_analyze_php_file(
                $file,
                $relativePath,
                $hasCanonicalMarkerStrategy,
                $alternativeStrategyPaths,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    if ($hasCanonicalMarkerStrategy && $alternativeStrategyPaths !== []) {
        $uniqueAlternativePaths = \array_values(\array_unique($alternativeStrategyPaths));
        \sort($uniqueAlternativePaths, \SORT_STRING);

        foreach ($uniqueAlternativePaths as $path) {
            $diagnostics[] = $path . ': multiple-dto-marker-strategies';
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_collect_php_source_files(string $scanRoot): array
{
    $root = \is_dir($scanRoot . '/packages')
        ? $scanRoot . '/packages'
        : $scanRoot;

    /** @var list<string> $files */
    $files = [];

    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
    } catch (\Throwable) {
        throw new \RuntimeException('source-root-iterator-failed');
    }

    foreach ($iterator as $entry) {
        if (!$entry instanceof \SplFileInfo || !$entry->isFile()) {
            continue;
        }

        $path = \str_replace('\\', '/', $entry->getPathname());

        if (!\str_ends_with($path, '.php')) {
            continue;
        }

        $relativePath = coretsia_dto_marker_consistency_gate_normalize_relative_path($scanRoot, $path);

        if (!\str_contains('/' . $relativePath, '/src/')) {
            continue;
        }

        if (coretsia_dto_marker_consistency_gate_contains_excluded_segment($relativePath)) {
            continue;
        }

        $files[] = $path;
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

function coretsia_dto_marker_consistency_gate_contains_excluded_segment(string $relativePath): bool
{
    $path = '/' . \trim($relativePath, '/') . '/';

    return \str_contains($path, '/tests/')
        || \str_contains($path, '/fixtures/')
        || \str_contains($path, '/vendor/');
}

/**
 * @param list<string> $alternativeStrategyPaths
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_analyze_php_file(
    string $path,
    string $relativePath,
    bool   &$hasCanonicalMarkerStrategy,
    array  &$alternativeStrategyPaths,
): array {
    $contents = coretsia_dto_marker_consistency_gate_read_file($path);

    try {
        $tokens = \token_get_all($contents);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $namespace = coretsia_dto_marker_consistency_gate_extract_namespace($contents);
    $uses = coretsia_dto_marker_consistency_gate_extract_use_aliases($contents);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (coretsia_dto_marker_consistency_gate_extract_attribute_names($tokens) as $rawAttributeName) {
        $resolvedAttributeName = coretsia_dto_marker_consistency_gate_resolve_class_name(
            $rawAttributeName,
            $namespace,
            $uses,
        );

        if ($resolvedAttributeName === 'Coretsia\\Dto\\Attribute\\Dto') {
            $hasCanonicalMarkerStrategy = true;
            continue;
        }

        if (coretsia_dto_marker_consistency_gate_is_dto_marker_like_name($resolvedAttributeName)) {
            $diagnostics[] = $relativePath . ': non-canonical-dto-marker';
            $alternativeStrategyPaths[] = $relativePath;
        }
    }

    foreach (coretsia_dto_marker_consistency_gate_extract_class_likes($tokens) as $classLike) {
        $shortName = $classLike['name'];
        $fqn = $namespace === '' ? $shortName : $namespace . '\\' . $shortName;

        if (
            $classLike['kind'] === 'class'
            && $fqn === 'Coretsia\\Dto\\Attribute\\Dto'
            && $relativePath === 'packages/core/dto-attribute/src/Attribute/Dto.php'
        ) {
            $hasCanonicalMarkerStrategy = true;
            continue;
        }

        if (
            $classLike['kind'] === 'class'
            && coretsia_dto_marker_consistency_gate_class_like_has_native_attribute(
                $classLike['attributes'],
                $namespace,
                $uses,
            )
            && coretsia_dto_marker_consistency_gate_is_dto_marker_class_name($shortName)
        ) {
            $diagnostics[] = $relativePath . ': custom-dto-marker-class';
            $alternativeStrategyPaths[] = $relativePath;
            continue;
        }

        if (
            $classLike['kind'] === 'interface'
            && coretsia_dto_marker_consistency_gate_is_legacy_dto_interface_name($shortName)
        ) {
            $diagnostics[] = $relativePath . ': legacy-dto-interface-marker';
            $alternativeStrategyPaths[] = $relativePath;
        }
    }

    return $diagnostics;
}

function coretsia_dto_marker_consistency_gate_extract_namespace(string $contents): string
{
    if (\preg_match('/^\s*namespace\s+([^;{]+)[;{]/m', $contents, $matches) !== 1) {
        return '';
    }

    return \trim($matches[1]);
}

/**
 * @return array<string,string>
 */
function coretsia_dto_marker_consistency_gate_extract_use_aliases(string $contents): array
{
    /** @var array<string,string> $aliases */
    $aliases = [];

    $matchCount = \preg_match_all('/^\s*use\s+(?!function\b|const\b)([^;]+);/mi', $contents, $matches);

    if ($matchCount === false || $matchCount === 0) {
        return $aliases;
    }

    foreach ($matches[1] as $useStatement) {
        if (\str_contains($useStatement, '{')) {
            continue;
        }

        foreach (\explode(',', $useStatement) as $part) {
            $part = \trim($part);

            if ($part === '') {
                continue;
            }

            if (\preg_match('/^(.+)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $part, $aliasMatches) === 1) {
                $fqn = \trim($aliasMatches[1], " \t\n\r\0\x0B\\");
                $alias = $aliasMatches[2];
            } else {
                $fqn = \trim($part, " \t\n\r\0\x0B\\");
                $segments = \explode('\\', $fqn);
                $alias = (string)\end($segments);
            }

            if ($alias !== '' && $fqn !== '') {
                $aliases[$alias] = $fqn;
            }
        }
    }

    \ksort($aliases, \SORT_STRING);

    return $aliases;
}

/**
 * @param list<array|string> $tokens
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_extract_attribute_names(array $tokens): array
{
    /** @var list<string> $names */
    $names = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!\is_array($tokens[$i]) || $tokens[$i][0] !== T_ATTRIBUTE) {
            continue;
        }

        [$attributeNames, $endIndex] = coretsia_dto_marker_consistency_gate_parse_attribute_group($tokens, $i);

        foreach ($attributeNames as $attributeName) {
            $names[] = $attributeName;
        }

        $i = $endIndex;
    }

    return $names;
}

/**
 * @param list<array|string> $tokens
 * @return array{0:list<string>,1:int}
 */
function coretsia_dto_marker_consistency_gate_parse_attribute_group(array $tokens, int $startIndex): array
{
    /** @var list<string> $names */
    $names = [];

    $count = \count($tokens);
    $parenDepth = 0;
    $expectName = true;

    for ($i = $startIndex + 1; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '(') {
            $parenDepth++;
            continue;
        }

        if ($token === ')') {
            $parenDepth = \max(0, $parenDepth - 1);
            continue;
        }

        if ($token === ']' && $parenDepth === 0) {
            return [$names, $i];
        }

        if ($token === ',' && $parenDepth === 0) {
            $expectName = true;
            continue;
        }

        if (!$expectName || $parenDepth !== 0) {
            continue;
        }

        if (!coretsia_dto_marker_consistency_gate_is_attribute_name_token($token)) {
            continue;
        }

        $name = '';

        while (
            $i < $count
            && coretsia_dto_marker_consistency_gate_is_attribute_name_token($tokens[$i])
        ) {
            $name .= \is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            $i++;
        }

        $i--;

        if ($name !== '') {
            $names[] = $name;
            $expectName = false;
        }
    }

    return [$names, $count - 1];
}

/**
 * @param array|string $token
 */
function coretsia_dto_marker_consistency_gate_is_attribute_name_token($token): bool
{
    if (\is_string($token)) {
        return $token === '\\';
    }

    return $token[0] === T_STRING
        || $token[0] === T_NAME_QUALIFIED
        || $token[0] === T_NAME_FULLY_QUALIFIED;
}

/**
 * @param list<array|string> $tokens
 * @return list<array{kind:string,name:string,attributes:list<string>}>
 */
function coretsia_dto_marker_consistency_gate_extract_class_likes(array $tokens): array
{
    /** @var list<array{kind:string,name:string,attributes:list<string>}> $classLikes */
    $classLikes = [];

    /** @var list<string> $pendingAttributes */
    $pendingAttributes = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_ATTRIBUTE) {
            [$attributeNames, $endIndex] = coretsia_dto_marker_consistency_gate_parse_attribute_group($tokens, $i);
            $pendingAttributes = \array_merge($pendingAttributes, $attributeNames);
            $i = $endIndex;
            continue;
        }

        if (coretsia_dto_marker_consistency_gate_is_ignorable_between_attribute_and_class($token)) {
            continue;
        }

        if (\is_array($token) && ($token[0] === T_CLASS || $token[0] === T_INTERFACE)) {
            if (
                $token[0] === T_CLASS
                && coretsia_dto_marker_consistency_gate_previous_significant_token_is_new($tokens, $i)
            ) {
                $pendingAttributes = [];
                continue;
            }

            $name = coretsia_dto_marker_consistency_gate_next_class_like_name($tokens, $i);

            if ($name !== null) {
                $classLikes[] = [
                    'kind' => $token[0] === T_CLASS ? 'class' : 'interface',
                    'name' => $name,
                    'attributes' => $pendingAttributes,
                ];
            }

            $pendingAttributes = [];
            continue;
        }

        if (coretsia_dto_marker_consistency_gate_is_significant_token($token)) {
            $pendingAttributes = [];
        }
    }

    return $classLikes;
}

/**
 * @param array|string $token
 */
function coretsia_dto_marker_consistency_gate_is_ignorable_between_attribute_and_class($token): bool
{
    if (\is_string($token)) {
        return false;
    }

    return $token[0] === T_WHITESPACE
        || $token[0] === T_COMMENT
        || $token[0] === T_DOC_COMMENT
        || $token[0] === T_FINAL
        || $token[0] === T_ABSTRACT
        || $token[0] === T_READONLY;
}

/**
 * @param array|string $token
 */
function coretsia_dto_marker_consistency_gate_is_significant_token($token): bool
{
    if (\is_string($token)) {
        return \trim($token) !== '';
    }

    return $token[0] !== T_WHITESPACE
        && $token[0] !== T_COMMENT
        && $token[0] !== T_DOC_COMMENT;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_marker_consistency_gate_previous_significant_token_is_new(array $tokens, int $index): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!coretsia_dto_marker_consistency_gate_is_significant_token($token)) {
            continue;
        }

        return \is_array($token) && $token[0] === T_NEW;
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_marker_consistency_gate_next_class_like_name(array $tokens, int $index): ?string
{
    $count = \count($tokens);

    for ($i = $index + 1; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }

        if (coretsia_dto_marker_consistency_gate_is_significant_token($token)) {
            return null;
        }
    }

    return null;
}

/**
 * @param array<string,string> $uses
 */
function coretsia_dto_marker_consistency_gate_resolve_class_name(
    string $rawName,
    string $namespace,
    array  $uses,
): string {
    $rawName = \trim($rawName);

    if ($rawName === '') {
        return '';
    }

    $isFullyQualified = \str_starts_with($rawName, '\\');
    $name = \trim($rawName, '\\');
    $segments = \explode('\\', $name);
    $firstSegment = $segments[0] ?? '';

    if (!$isFullyQualified && isset($uses[$firstSegment])) {
        $segments[0] = $uses[$firstSegment];

        return \implode('\\', $segments);
    }

    if ($isFullyQualified || $namespace === '') {
        return $name;
    }

    return $namespace . '\\' . $name;
}

/**
 * @param list<string> $attributeNames
 * @param array<string,string> $uses
 */
function coretsia_dto_marker_consistency_gate_class_like_has_native_attribute(
    array  $attributeNames,
    string $namespace,
    array  $uses,
): bool {
    foreach ($attributeNames as $attributeName) {
        if (
            coretsia_dto_marker_consistency_gate_resolve_class_name(
                $attributeName,
                $namespace,
                $uses,
            ) === 'Attribute'
        ) {
            return true;
        }
    }

    return false;
}

function coretsia_dto_marker_consistency_gate_is_dto_marker_like_name(string $fqn): bool
{
    if ($fqn === 'Coretsia\\Dto\\Attribute\\Dto') {
        return false;
    }

    $shortName = coretsia_dto_marker_consistency_gate_short_class_name($fqn);

    return $shortName === 'Dto'
        || \str_ends_with($shortName, 'Dto')
        || \str_ends_with($shortName, 'DtoAttribute')
        || \str_ends_with($shortName, 'DtoMarker')
        || \str_contains($shortName, 'DtoMarker')
        || \str_contains($shortName, 'DtoAttribute');
}

function coretsia_dto_marker_consistency_gate_is_dto_marker_class_name(string $shortName): bool
{
    return $shortName === 'Dto'
        || \str_ends_with($shortName, 'DtoAttribute')
        || \str_ends_with($shortName, 'DtoMarker')
        || \str_contains($shortName, 'DtoMarker')
        || \str_contains($shortName, 'DtoAttribute');
}

function coretsia_dto_marker_consistency_gate_is_legacy_dto_interface_name(string $shortName): bool
{
    return $shortName === 'DtoInterface'
        || \str_ends_with($shortName, 'DtoInterface');
}

function coretsia_dto_marker_consistency_gate_short_class_name(string $fqn): string
{
    $segments = \explode('\\', \trim($fqn, '\\'));

    return (string)\end($segments);
}

function coretsia_dto_marker_consistency_gate_normalize_relative_path(string $root, string $path): string
{
    $root = \rtrim(\str_replace('\\', '/', $root), '/');
    $path = \str_replace('\\', '/', $path);

    if (\str_starts_with($path, $root . '/')) {
        return \substr($path, \strlen($root) + 1);
    }

    return \ltrim($path, '/');
}

function coretsia_dto_marker_consistency_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $contents = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($contents)) {
        throw new \RuntimeException('php-file-read-failed');
    }

    return $contents;
}

function coretsia_dto_marker_consistency_gate_error_code_or_fallback(
    string $errorCodesFqcn,
    string $constantName,
    string $fallback,
): string {
    $name = $errorCodesFqcn . '::' . $constantName;

    if (\defined($name)) {
        /** @var string $code */
        $code = \constant($name);

        return $code;
    }

    return $fallback;
}
