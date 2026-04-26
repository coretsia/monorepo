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

    $fallbackViolation = 'CORETSIA_DTO_SHAPE_VIOLATION';
    $fallbackScanFailed = 'CORETSIA_DTO_GATE_SCAN_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_dto_shape_gate_error_code_or_fallback(
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

    $codeViolation = coretsia_dto_shape_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DTO_SHAPE_VIOLATION',
        $fallbackViolation,
    );

    $codeScanFailed = coretsia_dto_shape_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DTO_GATE_SCAN_FAILED',
        $fallbackScanFailed,
    );

    try {
        $defaultScanRoot = $toolsRootRuntime . '/..';
        $scanRoot = coretsia_dto_shape_gate_resolve_scan_root($argv, $defaultScanRoot);

        $diagnostics = coretsia_dto_shape_gate_scan($scanRoot);
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
function coretsia_dto_shape_gate_resolve_scan_root(array $argv, string $defaultScanRoot): string
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
function coretsia_dto_shape_gate_scan(string $scanRoot): array
{
    $files = coretsia_dto_shape_gate_collect_php_source_files($scanRoot);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($files as $file) {
        $relativePath = coretsia_dto_shape_gate_normalize_relative_path($scanRoot, $file);

        foreach (coretsia_dto_shape_gate_analyze_php_file($file, $relativePath) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_dto_shape_gate_collect_php_source_files(string $scanRoot): array
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

        $relativePath = coretsia_dto_shape_gate_normalize_relative_path($scanRoot, $path);

        if (!\str_contains('/' . $relativePath, '/src/')) {
            continue;
        }

        if (coretsia_dto_shape_gate_contains_excluded_segment($relativePath)) {
            continue;
        }

        $files[] = $path;
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

function coretsia_dto_shape_gate_contains_excluded_segment(string $relativePath): bool
{
    $path = '/' . \trim($relativePath, '/') . '/';

    return \str_contains($path, '/tests/')
        || \str_contains($path, '/fixtures/')
        || \str_contains($path, '/vendor/');
}

/**
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_php_file(string $path, string $relativePath): array
{
    $contents = coretsia_dto_shape_gate_read_file($path);

    try {
        $tokens = \token_get_all($contents);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $namespace = coretsia_dto_shape_gate_extract_namespace($contents);
    $uses = coretsia_dto_shape_gate_extract_use_aliases($contents);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (coretsia_dto_shape_gate_extract_marked_class_likes($tokens, $namespace, $uses) as $classInfo) {
        foreach (coretsia_dto_shape_gate_analyze_marked_class_like($tokens, $classInfo) as $reason) {
            $diagnostics[] = $relativePath . ': ' . $reason;
        }
    }

    return $diagnostics;
}

function coretsia_dto_shape_gate_extract_namespace(string $contents): string
{
    if (\preg_match('/^\s*namespace\s+([^;{]+)[;{]/m', $contents, $matches) !== 1) {
        return '';
    }

    return \trim($matches[1]);
}

/**
 * @return array<string,string>
 */
function coretsia_dto_shape_gate_extract_use_aliases(string $contents): array
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
 * @param array<string,string> $uses
 * @return list<array{
 *     kind:string,
 *     name:string,
 *     token_index:int,
 *     body_start:int,
 *     body_end:int,
 *     is_final:bool,
 *     is_abstract:bool,
 *     has_extends:bool,
 *     has_implements:bool
 * }>
 */
function coretsia_dto_shape_gate_extract_marked_class_likes(array $tokens, string $namespace, array $uses): array
{
    /** @var list<array{kind:string,name:string,token_index:int,body_start:int,body_end:int,is_final:bool,is_abstract:bool,has_extends:bool,has_implements:bool}> $classLikes */
    $classLikes = [];

    /** @var list<string> $pendingAttributes */
    $pendingAttributes = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_ATTRIBUTE) {
            [$attributeNames, $endIndex] = coretsia_dto_shape_gate_parse_attribute_group($tokens, $i);
            $pendingAttributes = \array_merge($pendingAttributes, $attributeNames);
            $i = $endIndex;
            continue;
        }

        if (coretsia_dto_shape_gate_is_ignorable_between_attribute_and_class_like($token)) {
            continue;
        }

        if (\is_array($token) && coretsia_dto_shape_gate_is_class_like_token($token)) {
            if (
                $token[0] === T_CLASS
                && coretsia_dto_shape_gate_previous_significant_token_is_new($tokens, $i)
            ) {
                $pendingAttributes = [];
                continue;
            }

            $name = coretsia_dto_shape_gate_next_class_like_name($tokens, $i);
            $bodyStart = coretsia_dto_shape_gate_find_next_string_token_index($tokens, $i, '{');

            if ($name !== null && $bodyStart !== null) {
                $bodyEnd = coretsia_dto_shape_gate_find_matching_pair($tokens, $bodyStart, '{', '}');

                if (
                    $bodyEnd !== null
                    && coretsia_dto_shape_gate_attributes_contain_canonical_dto_marker(
                        $pendingAttributes,
                        $namespace,
                        $uses,
                    )
                ) {
                    $classLikes[] = [
                        'kind' => coretsia_dto_shape_gate_class_like_kind($token),
                        'name' => $name,
                        'token_index' => $i,
                        'body_start' => $bodyStart,
                        'body_end' => $bodyEnd,
                        'is_final' => coretsia_dto_shape_gate_class_declaration_has_modifier($tokens, $i, T_FINAL),
                        'is_abstract' => coretsia_dto_shape_gate_class_declaration_has_modifier($tokens, $i, T_ABSTRACT),
                        'has_extends' => coretsia_dto_shape_gate_declaration_contains_token(
                            $tokens,
                            $i,
                            $bodyStart,
                            T_EXTENDS,
                        ),
                        'has_implements' => coretsia_dto_shape_gate_declaration_contains_token(
                            $tokens,
                            $i,
                            $bodyStart,
                            T_IMPLEMENTS,
                        ),
                    ];
                }

                if ($bodyEnd !== null) {
                    $i = $bodyEnd;
                }
            }

            $pendingAttributes = [];
            continue;
        }

        if (coretsia_dto_shape_gate_is_significant_token($token)) {
            $pendingAttributes = [];
        }
    }

    return $classLikes;
}

/**
 * @param list<string> $attributeNames
 * @param array<string,string> $uses
 */
function coretsia_dto_shape_gate_attributes_contain_canonical_dto_marker(
    array  $attributeNames,
    string $namespace,
    array  $uses,
): bool {
    foreach ($attributeNames as $attributeName) {
        if (
            coretsia_dto_shape_gate_resolve_class_name(
                $attributeName,
                $namespace,
                $uses,
            ) === 'Coretsia\\Dto\\Attribute\\Dto'
        ) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 * @return array{0:list<string>,1:int}
 */
function coretsia_dto_shape_gate_parse_attribute_group(array $tokens, int $startIndex): array
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

        if (!coretsia_dto_shape_gate_is_attribute_name_token($token)) {
            continue;
        }

        $name = '';

        while (
            $i < $count
            && coretsia_dto_shape_gate_is_attribute_name_token($tokens[$i])
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
function coretsia_dto_shape_gate_is_attribute_name_token($token): bool
{
    if (\is_string($token)) {
        return $token === '\\';
    }

    return $token[0] === T_STRING
        || $token[0] === T_NAME_QUALIFIED
        || $token[0] === T_NAME_FULLY_QUALIFIED
        || $token[0] === T_NAME_RELATIVE;
}

/**
 * @param array|string $token
 */
function coretsia_dto_shape_gate_is_ignorable_between_attribute_and_class_like($token): bool
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
function coretsia_dto_shape_gate_is_significant_token($token): bool
{
    if (\is_string($token)) {
        return \trim($token) !== '';
    }

    return $token[0] !== T_WHITESPACE
        && $token[0] !== T_COMMENT
        && $token[0] !== T_DOC_COMMENT;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_shape_gate_is_class_like_token(array $token): bool
{
    return $token[0] === T_CLASS
        || $token[0] === T_INTERFACE
        || $token[0] === T_TRAIT
        || $token[0] === T_ENUM;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_shape_gate_class_like_kind(array $token): string
{
    if ($token[0] === T_INTERFACE) {
        return 'interface';
    }

    if ($token[0] === T_TRAIT) {
        return 'trait';
    }

    if ($token[0] === T_ENUM) {
        return 'enum';
    }

    return 'class';
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_previous_significant_token_is_new(array $tokens, int $index): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!coretsia_dto_shape_gate_is_significant_token($token)) {
            continue;
        }

        return \is_array($token) && $token[0] === T_NEW;
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_next_class_like_name(array $tokens, int $index): ?string
{
    $count = \count($tokens);

    for ($i = $index + 1; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }

        if (coretsia_dto_shape_gate_is_significant_token($token)) {
            return null;
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_class_declaration_has_modifier(array $tokens, int $classIndex, int $modifier): bool
{
    for ($i = $classIndex - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!coretsia_dto_shape_gate_is_significant_token($token)) {
            continue;
        }

        if (!\is_array($token)) {
            return false;
        }

        if ($token[0] === $modifier) {
            return true;
        }

        if (
            $token[0] === T_FINAL
            || $token[0] === T_ABSTRACT
            || $token[0] === T_READONLY
        ) {
            continue;
        }

        return false;
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_declaration_contains_token(array $tokens, int $start, int $end, int $tokenId): bool
{
    for ($i = $start; $i < $end; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === $tokenId) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 * @param array{
 *     kind:string,
 *     name:string,
 *     token_index:int,
 *     body_start:int,
 *     body_end:int,
 *     is_final:bool,
 *     is_abstract:bool,
 *     has_extends:bool,
 *     has_implements:bool
 * } $classInfo
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_marked_class_like(array $tokens, array $classInfo): array
{
    /** @var list<string> $violations */
    $violations = [];

    if ($classInfo['kind'] !== 'class') {
        $violations[] = 'not-final';

        return \array_values(\array_unique($violations));
    }

    if ($classInfo['is_abstract']) {
        $violations[] = 'abstract-class';
    } elseif (!$classInfo['is_final']) {
        $violations[] = 'not-final';
    }

    if ($classInfo['has_extends']) {
        $violations[] = 'extends-class';
    }

    if ($classInfo['has_implements']) {
        $violations[] = 'implements-interface';
    }

    if (coretsia_dto_shape_gate_class_body_uses_traits($tokens, $classInfo['body_start'], $classInfo['body_end'])) {
        $violations[] = 'uses-trait';
    }

    foreach (
        coretsia_dto_shape_gate_analyze_declared_properties(
            $tokens,
            $classInfo['body_start'],
            $classInfo['body_end'],
        ) as $reason
    ) {
        $violations[] = $reason;
    }

    foreach (
        coretsia_dto_shape_gate_analyze_promoted_properties(
            $tokens,
            $classInfo['body_start'],
            $classInfo['body_end'],
        ) as $reason
    ) {
        $violations[] = $reason;
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_class_body_uses_traits(array $tokens, int $bodyStart, int $bodyEnd): bool
{
    $depth = 0;

    /** @var list<array|string> $statement */
    $statement = [];

    for ($i = $bodyStart + 1; $i < $bodyEnd; $i++) {
        $token = $tokens[$i];

        if ($depth === 0 && !coretsia_dto_shape_gate_is_trivia_token($token)) {
            $statement[] = $token;
        }

        if ($token === '{') {
            if (
                $depth === 0
                && coretsia_dto_shape_gate_class_body_statement_is_trait_use($statement)
            ) {
                return true;
            }

            $depth++;
            continue;
        }

        if ($token === '}') {
            if ($depth > 0) {
                $depth--;

                if ($depth === 0) {
                    $statement = [];
                }
            }

            continue;
        }

        if ($depth !== 0) {
            continue;
        }

        if ($token === ';') {
            if (coretsia_dto_shape_gate_class_body_statement_is_trait_use($statement)) {
                return true;
            }

            $statement = [];
        }
    }

    return false;
}

/**
 * @param list<array|string> $statement
 */
function coretsia_dto_shape_gate_class_body_statement_is_trait_use(array $statement): bool
{
    foreach ($statement as $token) {
        if (coretsia_dto_shape_gate_is_trivia_token($token)) {
            continue;
        }

        return \is_array($token) && $token[0] === T_USE;
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_declared_properties(array $tokens, int $bodyStart, int $bodyEnd): array
{
    /** @var list<string> $violations */
    $violations = [];

    $depth = 0;
    $segmentStart = $bodyStart + 1;

    for ($i = $bodyStart + 1; $i < $bodyEnd; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;
            continue;
        }

        if ($token === '}') {
            if ($depth > 0) {
                $depth--;

                if ($depth === 0) {
                    $segmentStart = $i + 1;
                }
            }

            continue;
        }

        if ($depth !== 0) {
            continue;
        }

        if ($token !== ';') {
            continue;
        }

        $segment = \array_slice($tokens, $segmentStart, $i - $segmentStart + 1);
        $segmentStart = $i + 1;

        if (!coretsia_dto_shape_gate_segment_is_property_declaration($segment)) {
            continue;
        }

        if (coretsia_dto_shape_gate_segment_contains_token($segment, T_STATIC)) {
            $violations[] = 'static-property';
        }

        if (!coretsia_dto_shape_gate_property_segment_is_public($segment)) {
            $violations[] = 'non-public-property';
        }

        if (!coretsia_dto_shape_gate_property_segment_is_typed($segment)) {
            $violations[] = 'untyped-property';
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_segment_is_property_declaration(array $segment): bool
{
    if (coretsia_dto_shape_gate_segment_contains_token($segment, T_FUNCTION)) {
        return false;
    }

    if (coretsia_dto_shape_gate_segment_contains_token($segment, T_CONST)) {
        return false;
    }

    foreach ($segment as $token) {
        if (\is_array($token) && $token[0] === T_VARIABLE) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_property_segment_is_public(array $segment): bool
{
    if (
        coretsia_dto_shape_gate_segment_contains_token($segment, T_PRIVATE)
        || coretsia_dto_shape_gate_segment_contains_token($segment, T_PROTECTED)
        || coretsia_dto_shape_gate_segment_contains_token($segment, T_VAR)
    ) {
        return false;
    }

    return coretsia_dto_shape_gate_segment_contains_token($segment, T_PUBLIC);
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_property_segment_is_typed(array $segment): bool
{
    $variableIndex = coretsia_dto_shape_gate_first_token_index($segment, T_VARIABLE);

    if ($variableIndex === null) {
        return true;
    }

    $start = 0;

    for ($i = 0; $i < $variableIndex; $i++) {
        $token = $segment[$i];

        if (
            \is_array($token)
            && (
                $token[0] === T_PUBLIC
                || $token[0] === T_PROTECTED
                || $token[0] === T_PRIVATE
                || $token[0] === T_VAR
                || $token[0] === T_STATIC
                || $token[0] === T_READONLY
            )
        ) {
            $start = $i + 1;
        }
    }

    for ($i = $start; $i < $variableIndex; $i++) {
        $token = $segment[$i];

        if (coretsia_dto_shape_gate_is_trivia_token($token)) {
            continue;
        }

        if (\is_array($token) && coretsia_dto_shape_gate_is_type_token($token)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_segment_contains_token(array $segment, int $tokenId): bool
{
    foreach ($segment as $token) {
        if (\is_array($token) && $token[0] === $tokenId) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_first_token_index(array $tokens, int $tokenId): ?int
{
    foreach ($tokens as $index => $token) {
        if (\is_array($token) && $token[0] === $tokenId) {
            return $index;
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_promoted_properties(array $tokens, int $bodyStart, int $bodyEnd): array
{
    /** @var list<string> $violations */
    $violations = [];

    foreach (coretsia_dto_shape_gate_extract_methods($tokens, $bodyStart, $bodyEnd) as $method) {
        if ($method['name'] !== '__construct') {
            continue;
        }

        if ($method['param_start'] === null || $method['param_end'] === null) {
            continue;
        }

        foreach (
            coretsia_dto_shape_gate_extract_parameter_segments(
                $tokens,
                $method['param_start'],
                $method['param_end'],
            ) as $segment
        ) {
            if (!coretsia_dto_shape_gate_parameter_segment_is_promoted_property($segment)) {
                continue;
            }

            if (!coretsia_dto_shape_gate_promoted_property_segment_is_public($segment)) {
                $violations[] = 'non-public-property';
            }

            if (!coretsia_dto_shape_gate_promoted_property_segment_is_typed($segment)) {
                $violations[] = 'untyped-property';
            }
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array|string> $tokens
 * @return list<array{name:string|null,param_start:int|null,param_end:int|null,body_start:int|null,body_end:int|null}>
 */
function coretsia_dto_shape_gate_extract_methods(array $tokens, int $classBodyStart, int $classBodyEnd): array
{
    /** @var list<array{name:string|null,param_start:int|null,param_end:int|null,body_start:int|null,body_end:int|null}> $methods */
    $methods = [];

    $depth = 0;

    for ($i = $classBodyStart + 1; $i < $classBodyEnd; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;
            continue;
        }

        if ($token === '}') {
            $depth = \max(0, $depth - 1);
            continue;
        }

        if ($depth !== 0) {
            continue;
        }

        if (!\is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $name = coretsia_dto_shape_gate_method_name_after_function_token($tokens, $i);
        $paramStart = coretsia_dto_shape_gate_find_next_string_token_index($tokens, $i, '(');
        $paramEnd = $paramStart === null
            ? null
            : coretsia_dto_shape_gate_find_matching_pair($tokens, $paramStart, '(', ')');

        $bodyStart = $paramEnd === null
            ? null
            : coretsia_dto_shape_gate_find_method_body_start($tokens, $paramEnd, $classBodyEnd);

        $bodyEnd = $bodyStart === null
            ? null
            : coretsia_dto_shape_gate_find_matching_pair($tokens, $bodyStart, '{', '}');

        $methods[] = [
            'name' => $name,
            'param_start' => $paramStart,
            'param_end' => $paramEnd,
            'body_start' => $bodyStart,
            'body_end' => $bodyEnd,
        ];

        if ($bodyEnd !== null) {
            $i = $bodyEnd;
        }
    }

    return $methods;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_method_name_after_function_token(array $tokens, int $functionIndex): ?string
{
    $count = \count($tokens);
    $i = coretsia_dto_shape_gate_skip_trivia($tokens, $functionIndex + 1);

    if ($i < $count && $tokens[$i] === '&') {
        $i = coretsia_dto_shape_gate_skip_trivia($tokens, $i + 1);
    }

    if ($i < $count && \is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
        return $tokens[$i][1];
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_find_method_body_start(array $tokens, int $paramEnd, int $classBodyEnd): ?int
{
    for ($i = $paramEnd + 1; $i < $classBodyEnd; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            return $i;
        }

        if ($token === ';') {
            return null;
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 * @return list<list<array|string>>
 */
function coretsia_dto_shape_gate_extract_parameter_segments(array $tokens, int $paramStart, int $paramEnd): array
{
    /** @var list<list<array|string>> $segments */
    $segments = [];

    $segmentStart = $paramStart + 1;
    $depth = 0;

    for ($i = $paramStart + 1; $i < $paramEnd; $i++) {
        $token = $tokens[$i];

        if ($token === '(' || $token === '[' || $token === '{') {
            $depth++;
            continue;
        }

        if ($token === ')' || $token === ']' || $token === '}') {
            $depth = \max(0, $depth - 1);
            continue;
        }

        if ($token !== ',' || $depth !== 0) {
            continue;
        }

        $segments[] = \array_slice($tokens, $segmentStart, $i - $segmentStart);
        $segmentStart = $i + 1;
    }

    $segments[] = \array_slice($tokens, $segmentStart, $paramEnd - $segmentStart);

    return $segments;
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_parameter_segment_is_promoted_property(array $segment): bool
{
    return coretsia_dto_shape_gate_segment_contains_token($segment, T_PUBLIC)
        || coretsia_dto_shape_gate_segment_contains_token($segment, T_PRIVATE)
        || coretsia_dto_shape_gate_segment_contains_token($segment, T_PROTECTED);
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_promoted_property_segment_is_public(array $segment): bool
{
    return coretsia_dto_shape_gate_segment_contains_token($segment, T_PUBLIC)
        && !coretsia_dto_shape_gate_segment_contains_token($segment, T_PRIVATE)
        && !coretsia_dto_shape_gate_segment_contains_token($segment, T_PROTECTED);
}

/**
 * @param list<array|string> $segment
 */
function coretsia_dto_shape_gate_promoted_property_segment_is_typed(array $segment): bool
{
    $variableIndex = coretsia_dto_shape_gate_first_token_index($segment, T_VARIABLE);

    if ($variableIndex === null) {
        return true;
    }

    $start = 0;

    for ($i = 0; $i < $variableIndex; $i++) {
        $token = $segment[$i];

        if (
            \is_array($token)
            && (
                $token[0] === T_PUBLIC
                || $token[0] === T_PROTECTED
                || $token[0] === T_PRIVATE
                || $token[0] === T_READONLY
            )
        ) {
            $start = $i + 1;
        }
    }

    for ($i = $start; $i < $variableIndex; $i++) {
        $token = $segment[$i];

        if (coretsia_dto_shape_gate_is_trivia_token($token)) {
            continue;
        }

        if (\is_array($token) && coretsia_dto_shape_gate_is_type_token($token)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_shape_gate_is_type_token(array $token): bool
{
    return $token[0] === T_STRING
        || $token[0] === T_NAME_QUALIFIED
        || $token[0] === T_NAME_FULLY_QUALIFIED
        || $token[0] === T_NAME_RELATIVE
        || $token[0] === T_ARRAY
        || $token[0] === T_CALLABLE;
}

/**
 * @param array<string,string> $uses
 */
function coretsia_dto_shape_gate_resolve_class_name(
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
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_find_next_string_token_index(array $tokens, int $start, string $needle): ?int
{
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        if ($tokens[$i] === $needle) {
            return $i;
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_find_matching_pair(
    array  $tokens,
    int    $openIndex,
    string $open,
    string $close,
): ?int {
    $depth = 0;
    $count = \count($tokens);

    for ($i = $openIndex; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === $open) {
            $depth++;
            continue;
        }

        if ($token === $close) {
            $depth--;

            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_shape_gate_skip_trivia(array $tokens, int $index): int
{
    $count = \count($tokens);

    while ($index < $count && coretsia_dto_shape_gate_is_trivia_token($tokens[$index])) {
        $index++;
    }

    return $index;
}

/**
 * @param array|string $token
 */
function coretsia_dto_shape_gate_is_trivia_token($token): bool
{
    return \is_array($token)
        && (
            $token[0] === T_WHITESPACE
            || $token[0] === T_COMMENT
            || $token[0] === T_DOC_COMMENT
        );
}

function coretsia_dto_shape_gate_normalize_relative_path(string $root, string $path): string
{
    $root = \rtrim(\str_replace('\\', '/', $root), '/');
    $path = \str_replace('\\', '/', $path);

    if (\str_starts_with($path, $root . '/')) {
        return \substr($path, \strlen($root) + 1);
    }

    return \ltrim($path, '/');
}

function coretsia_dto_shape_gate_read_file(string $path): string
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

function coretsia_dto_shape_gate_error_code_or_fallback(
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
