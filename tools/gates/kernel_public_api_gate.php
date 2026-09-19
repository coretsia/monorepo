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
use Coretsia\Tools\Support\PhpSourceFinder;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_KERNEL_PUBLIC_API_DRIFT',
    'CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $kernel = $catalog->byPackageId('core/kernel');

        if ($kernel === null) {
            return [];
        }

        $kernelRoot = $kernel['absolutePath'];
        $kernelSrc = $kernelRoot . '/src';

        if (!is_dir($kernelSrc)) {
            return [
                $kernel['relativePath'] . ': kernel-public-api-source-missing',
            ];
        }

        $kernelSrc = $repository->resolveExistingDirectory($kernelSrc);

        $evidenceFiles = coretsia_kernel_public_api_gate_find_public_api_evidence_files(
            $repository,
            $kernelRoot,
        );

        if ($evidenceFiles === []) {
            return [
                $kernel['relativePath'] . ': kernel-public-api-evidence-missing',
            ];
        }

        /**
         * @var array<string, array{
         *     kind:string,
         *     file:string,
         *     docblock:string|null,
         *     extends:list<string>,
         *     implements:list<string>
         * }> $declaredTypes
         */
        $declaredTypes = [];

        foreach (
            PhpSourceFinder::find(
                $kernelSrc,
                ['fixtures', 'vendor', 'node_modules'],
            ) as $absPath
        ) {
            foreach (coretsia_kernel_public_api_gate_extract_declared_types($absPath) as $symbol => $info) {
                $declaredTypes[$symbol] = $info;
            }
        }

        if ($declaredTypes === []) {
            return [
                $kernel['relativePath'] . ': kernel-public-api-source-empty',
            ];
        }

        $publicApiSymbols = coretsia_kernel_public_api_gate_extract_public_api_symbols_from_evidence(
            $evidenceFiles,
            $declaredTypes,
        );

        $violations = [];

        if ($publicApiSymbols === []) {
            foreach ($evidenceFiles as $evidenceFile) {
                $violations[] = $repository->relativeToRepo($evidenceFile) . ': kernel-public-api-evidence-empty';
            }
        }

        foreach (\array_keys($publicApiSymbols) as $symbol) {
            if (!isset($declaredTypes[$symbol])) {
                $violations[] = $kernel['relativePath']
                    . ': kernel-public-api-symbol-missing:'
                    . \str_replace('\\', '/', $symbol);
            }
        }

        foreach ($declaredTypes as $symbol => $info) {
            $repoRelPath = $repository->relativeToRepo($info['file']);
            $isListed = isset($publicApiSymbols[$symbol]);
            $isInternal = coretsia_kernel_public_api_gate_type_is_internal(
                $symbol,
                $info,
                $repoRelPath,
            );

            if ($isListed && $isInternal) {
                $violations[] = $repoRelPath . ': kernel-public-api-symbol-internal-listed';
                continue;
            }

            if (!$isListed && !$isInternal) {
                $violations[] = $repoRelPath . ': kernel-public-api-symbol-unlisted';
            }
        }

        return $violations;
    },
));

/**
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_find_public_api_evidence_files(
    RepositoryContext $repository,
    string $kernelRoot,
): array {
    $candidates = [
        $kernelRoot . '/PUBLIC_API.md',
        $kernelRoot . '/public-api.md',
        $kernelRoot . '/public_api.md',
        $kernelRoot . '/public-api.json',
        $kernelRoot . '/public_api.json',
        $kernelRoot . '/public-api.php',
        $kernelRoot . '/public_api.php',
        $kernelRoot . '/docs/PUBLIC_API.md',
        $kernelRoot . '/docs/public-api.md',
        $kernelRoot . '/docs/public_api.md',
        $repository->resolve('docs/ssot/kernel-public-api.md'),
        $repository->resolve('docs/ssot/kernel_public_api.md'),
        $repository->resolve('docs/architecture/kernel-public-api.md'),
        $repository->resolve('docs/architecture/kernel_public_api.md'),
    ];

    $files = [];

    foreach ($candidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }

        $files[] = $repository->resolveExistingFile($candidate);
    }

    $testsRoot = $kernelRoot . '/tests';

    if (is_dir($testsRoot)) {
        $testsRoot = $repository->resolveExistingDirectory($testsRoot);

        foreach (
            PhpSourceFinder::find(
                $testsRoot,
                ['fixtures', 'vendor', 'node_modules'],
            ) as $absPath
        ) {
            $basename = basename($absPath);

            if (
                str_contains($basename, 'PublicApi')
                || str_contains($basename, 'PublicAPI')
                || str_contains($basename, 'PublicSurface')
                || str_contains($basename, 'ApiSurface')
                || str_contains($basename, 'KernelPublic')
            ) {
                $files[] = $absPath;
            }
        }
    }

    $files = array_values(array_unique($files));
    sort($files, SORT_STRING);

    return $files;
}

/**
 * @return array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>,
 *     implements:list<string>}>
 */
function coretsia_kernel_public_api_gate_extract_declared_types(string $phpFile): array
{
    $source = DeterministicFile::readTextNormalizedEol($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_kernel_public_api_gate_parse_php_context($source);
    $namespace = $context['namespace'];
    $imports = $context['imports'];

    $enumTokenId = \defined('T_ENUM') ? \constant('T_ENUM') : -1;

    /** @var array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>}> $types */
    $types = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (
            !\is_array($token)
            || (
                $token[0] !== T_CLASS
                && $token[0] !== T_INTERFACE
                && $token[0] !== T_TRAIT
                && $token[0] !== $enumTokenId
            )
        ) {
            continue;
        }

        if ($token[0] === T_CLASS) {
            $previous = coretsia_kernel_public_api_gate_previous_meaningful_token($tokens, $i - 1);
            if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
                continue;
            }
        }

        $nameIndex = coretsia_kernel_public_api_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($nameIndex === null) {
            continue;
        }

        $nameToken = $tokens[$nameIndex] ?? null;
        if (!\is_array($nameToken) || $nameToken[0] !== T_STRING) {
            continue;
        }

        $kind = match ($token[0]) {
            T_CLASS => 'class',
            T_INTERFACE => 'interface',
            T_TRAIT => 'trait',
            default => 'enum',
        };

        $fqcn = $namespace === '' ? $nameToken[1] : $namespace . '\\' . $nameToken[1];

        $extends = [];
        if ($token[0] === T_CLASS || $token[0] === T_INTERFACE) {
            $extends = coretsia_kernel_public_api_gate_extract_type_list_after_keyword(
                $tokens,
                $nameIndex + 1,
                T_EXTENDS,
                $token[0] === T_CLASS ? [T_IMPLEMENTS] : [],
                $namespace,
                $imports,
            );
        }

        $implements = [];
        if ($token[0] === T_CLASS || $token[0] === $enumTokenId) {
            $implements = coretsia_kernel_public_api_gate_extract_type_list_after_keyword(
                $tokens,
                $nameIndex + 1,
                T_IMPLEMENTS,
                [],
                $namespace,
                $imports,
            );
        }

        $types[$fqcn] = [
            'kind' => $kind,
            'file' => $phpFile,
            'docblock' => coretsia_kernel_public_api_gate_previous_doc_comment($tokens, $i - 1),
            'extends' => $extends,
            'implements' => $implements,
        ];
    }

    \ksort($types, \SORT_STRING);

    return $types;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 * @param list<int> $stopKeywordIds
 * @param array<string, string> $imports
 *
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_type_list_after_keyword(
    array $tokens,
    int $start,
    int $keywordId,
    array $stopKeywordIds,
    string $namespace,
    array $imports,
): array {
    /** @var list<string> $names */
    $names = [];

    $count = \count($tokens);
    $collecting = false;
    $current = '';

    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            break;
        }

        if (\is_array($token) && \in_array($token[0], $stopKeywordIds, true)) {
            break;
        }

        if (\is_array($token) && $token[0] === $keywordId) {
            $collecting = true;
            continue;
        }

        if (!$collecting) {
            continue;
        }

        if ($token === ',') {
            if ($current !== '') {
                $names[] = coretsia_kernel_public_api_gate_resolve_type_name($current, $namespace, $imports);
                $current = '';
            }

            continue;
        }

        if (!\is_array($token)) {
            continue;
        }

        if (
            $token[0] === T_STRING
            || $token[0] === T_NAME_QUALIFIED
            || $token[0] === T_NAME_FULLY_QUALIFIED
        ) {
            $current .= $token[1];
            continue;
        }

        if ($token[0] === T_NS_SEPARATOR) {
            $current .= '\\';
        }
    }

    if ($collecting && $current !== '') {
        $names[] = coretsia_kernel_public_api_gate_resolve_type_name($current, $namespace, $imports);
    }

    $names = \array_values(\array_unique($names));
    \sort($names, \SORT_STRING);

    return $names;
}

/**
 * @param list<string> $evidenceFiles
 * @param array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>,
 *     implements:list<string>}> $declaredTypes
 *
 * @return array<string, true>
 */
function coretsia_kernel_public_api_gate_extract_public_api_symbols_from_evidence(
    array $evidenceFiles,
    array $declaredTypes,
): array {
    /** @var array<string, true> $symbols */
    $symbols = [];

    foreach ($evidenceFiles as $evidenceFile) {
        $ext = \strtolower((string) \pathinfo($evidenceFile, \PATHINFO_EXTENSION));

        $fileSymbols = $ext === 'php'
            ? coretsia_kernel_public_api_gate_extract_php_evidence_symbols($evidenceFile, $declaredTypes)
            : coretsia_kernel_public_api_gate_extract_text_evidence_symbols($evidenceFile);

        foreach ($fileSymbols as $symbol) {
            $symbols[$symbol] = true;
        }
    }

    \ksort($symbols, \SORT_STRING);

    return $symbols;
}

/**
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_text_evidence_symbols(string $evidenceFile): array
{
    $content = DeterministicFile::readTextNormalizedEol($evidenceFile);
    $content = \str_replace('\\\\', '\\', $content);

    if (
        \preg_match_all(
            '/(?<![A-Za-z0-9_\\\\])Coretsia\\\\Kernel(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/u',
            $content,
            $matches,
        ) === false
    ) {
        throw new \RuntimeException('kernel-public-api-evidence-scan-failed');
    }

    $symbols = \array_values(\array_unique($matches[0]));
    \sort($symbols, \SORT_STRING);

    return $symbols;
}

/**
 * @param array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>,
 *     implements:list<string>}> $declaredTypes
 *
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_php_evidence_symbols(string $evidenceFile, array $declaredTypes): array
{
    $source = DeterministicFile::readTextNormalizedEol($evidenceFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_kernel_public_api_gate_parse_php_context($source);
    $namespace = $context['namespace'];
    $imports = $context['imports'];

    /** @var array<string, true> $symbols */
    $symbols = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literalValue = \ltrim(coretsia_kernel_public_api_gate_decode_php_string_literal($token[1]), '\\');

            if (isset($declaredTypes[$literalValue])) {
                $symbols[$literalValue] = true;
            }

            continue;
        }

        if (
            !\is_array($token)
            || (
                $token[0] !== T_STRING
                && $token[0] !== T_NAME_QUALIFIED
                && $token[0] !== T_NAME_FULLY_QUALIFIED
            )
        ) {
            continue;
        }

        if (
            $token[1] === 'self'
            || $token[1] === 'static'
            || $token[1] === 'parent'
        ) {
            continue;
        }

        $doubleColonIndex = coretsia_kernel_public_api_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($doubleColonIndex === null) {
            continue;
        }

        $doubleColonToken = $tokens[$doubleColonIndex] ?? null;
        if (!\is_array($doubleColonToken) || $doubleColonToken[0] !== T_DOUBLE_COLON) {
            continue;
        }

        $classKeywordIndex = coretsia_kernel_public_api_gate_next_meaningful_token_index(
            $tokens,
            $doubleColonIndex + 1
        );
        if ($classKeywordIndex === null) {
            continue;
        }

        $classKeyword = $tokens[$classKeywordIndex] ?? null;
        if (!\is_array($classKeyword) || $classKeyword[0] !== T_CLASS) {
            continue;
        }

        $resolved = coretsia_kernel_public_api_gate_resolve_type_name($token[1], $namespace, $imports);

        if (isset($declaredTypes[$resolved])) {
            $symbols[$resolved] = true;
        }
    }

    foreach (coretsia_kernel_public_api_gate_extract_text_evidence_symbols($evidenceFile) as $symbol) {
        if (isset($declaredTypes[$symbol])) {
            $symbols[$symbol] = true;
        }
    }

    $result = \array_keys($symbols);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @param array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>} $info
 */
function coretsia_kernel_public_api_gate_type_is_internal(
    string $symbol,
    array $info,
    string $repoRelPath,
): bool {
    if ($info['docblock'] !== null && \preg_match('/@internal\b/u', $info['docblock']) === 1) {
        return true;
    }

    $repoRelPath = \str_replace('\\', '/', $repoRelPath);

    return \str_contains($symbol, '\\Internal\\')
        || \str_contains('/' . $repoRelPath, '/Internal/')
        || \str_contains('/' . $repoRelPath, '/internal/');
}

/**
 * @return array{namespace:string, imports:array<string, string>}
 */
function coretsia_kernel_public_api_gate_parse_php_context(string $source): array
{
    $header = $source;
    if (
        \preg_match(
            '/\b(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+/iu',
            $source,
            $m,
            \PREG_OFFSET_CAPTURE,
        ) === 1
    ) {
        $declarationOffset = (int) $m[0][1];
        $header = \substr($source, 0, $declarationOffset);
    }

    $namespace = '';
    if (\preg_match('/^\s*namespace\s+([^;{]+)[;{]/mi', $header, $m) === 1) {
        $namespace = \trim($m[1]);
        $namespace = \trim($namespace, '\\');
    }

    /** @var array<string, string> $imports */
    $imports = [];

    if (
        \preg_match_all(
            '/^\s*use\s+(?!function\b|const\b)([^;]+);/mi',
            $header,
            $matches,
            \PREG_SET_ORDER,
        ) !== false
    ) {
        foreach ($matches as $match) {
            $useStatement = \trim($match[1]);

            foreach (
                coretsia_kernel_public_api_gate_extract_imports_from_use_statement(
                    $useStatement
                ) as $alias => $fqcn
            ) {
                $imports[$alias] = $fqcn;
            }
        }
    }

    \ksort($imports, \SORT_STRING);

    return [
        'namespace' => $namespace,
        'imports' => $imports,
    ];
}

/**
 * @return array<string, string>
 */
function coretsia_kernel_public_api_gate_extract_imports_from_use_statement(string $useStatement): array
{
    $useStatement = \trim($useStatement);

    if ($useStatement === '') {
        return [];
    }

    /** @var array<string, string> $imports */
    $imports = [];

    $openBrace = \strpos($useStatement, '{');
    $closeBrace = \strrpos($useStatement, '}');

    if ($openBrace !== false && $closeBrace !== false && $closeBrace > $openBrace) {
        $prefix = \trim(\substr($useStatement, 0, $openBrace));
        $inside = \substr($useStatement, $openBrace + 1, $closeBrace - $openBrace - 1);

        if (!\str_ends_with($prefix, '\\')) {
            return [];
        }

        $prefix = \rtrim($prefix, "\\ \t\n\r\0\x0B");

        foreach (coretsia_kernel_public_api_gate_split_use_statement_parts($inside) as $part) {
            $parsed = coretsia_kernel_public_api_gate_parse_import_part($part, $prefix);

            if ($parsed === null) {
                continue;
            }

            $imports[$parsed['alias']] = $parsed['fqcn'];
        }

        \ksort($imports, \SORT_STRING);

        return $imports;
    }

    foreach (coretsia_kernel_public_api_gate_split_use_statement_parts($useStatement) as $part) {
        $parsed = coretsia_kernel_public_api_gate_parse_import_part($part, null);

        if ($parsed === null) {
            continue;
        }

        $imports[$parsed['alias']] = $parsed['fqcn'];
    }

    \ksort($imports, \SORT_STRING);

    return $imports;
}

/**
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_split_use_statement_parts(string $useStatement): array
{
    /** @var list<string> $parts */
    $parts = [];

    $current = '';
    $braceDepth = 0;
    $len = \strlen($useStatement);

    for ($i = 0; $i < $len; $i++) {
        $char = $useStatement[$i];

        if ($char === '{') {
            $braceDepth++;
            $current .= $char;
            continue;
        }

        if ($char === '}') {
            if ($braceDepth > 0) {
                $braceDepth--;
            }

            $current .= $char;
            continue;
        }

        if ($char === ',' && $braceDepth === 0) {
            $part = \trim($current);
            if ($part !== '') {
                $parts[] = $part;
            }

            $current = '';
            continue;
        }

        $current .= $char;
    }

    $part = \trim($current);
    if ($part !== '') {
        $parts[] = $part;
    }

    return $parts;
}

/**
 * @return array{alias:string, fqcn:string}|null
 */
function coretsia_kernel_public_api_gate_parse_import_part(string $part, ?string $prefix): ?array
{
    $part = \trim($part);

    if ($part === '') {
        return null;
    }

    $alias = null;
    if (\preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $part, $aliasMatch) === 1) {
        $fqcnPart = \trim($aliasMatch[1]);
        $alias = $aliasMatch[2];
    } else {
        $fqcnPart = $part;
    }

    $fqcnPart = \trim($fqcnPart, "\\ \t\n\r\0\x0B");

    if ($fqcnPart === '') {
        return null;
    }

    $fqcn = $prefix === null || $prefix === ''
        ? $fqcnPart
        : $prefix . '\\' . $fqcnPart;

    $fqcn = \trim($fqcn, "\\ \t\n\r\0\x0B");

    if ($fqcn === '') {
        return null;
    }

    if ($alias === null) {
        $segments = \explode('\\', $fqcn);
        $last = \end($segments);

        if (!\is_string($last) || $last === '') {
            return null;
        }

        $alias = $last;
    }

    return [
        'alias' => $alias,
        'fqcn' => $fqcn,
    ];
}

/**
 * @param array<string, string> $imports
 */
function coretsia_kernel_public_api_gate_resolve_type_name(
    string $name,
    string $namespace,
    array $imports,
): string {
    $name = \trim($name);
    if ($name === '') {
        return $name;
    }

    if (\str_starts_with($name, '\\')) {
        return \ltrim($name, '\\');
    }

    $parts = \explode('\\', $name);
    $head = $parts[0];

    if (isset($imports[$head])) {
        $parts[0] = $imports[$head];
        return \implode('\\', $parts);
    }

    if (\count($parts) > 1) {
        return $name;
    }

    if ($namespace === '') {
        return $name;
    }

    return $namespace . '\\' . $name;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_kernel_public_api_gate_next_meaningful_token_index(array $tokens, int $start): ?int
{
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            return $i;
        }

        if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            return $i;
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 *
 * @return int|string|null
 */
function coretsia_kernel_public_api_gate_previous_meaningful_token(array $tokens, int $start): int|string|null
{
    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            return $token;
        }

        if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            return $token[0];
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_kernel_public_api_gate_previous_doc_comment(array $tokens, int $start): ?string
{
    $modifierTokenIds = [
        T_ABSTRACT,
        T_FINAL,
    ];

    if (\defined('T_READONLY')) {
        $modifierTokenIds[] = \constant('T_READONLY');
    }

    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }

        if (\is_array($token) && \in_array($token[0], $modifierTokenIds, true)) {
            continue;
        }

        if ($token === ']') {
            $attributeDepth = 1;

            for ($i--; $i >= 0; $i--) {
                $attributeToken = $tokens[$i];

                if ($attributeToken === ']') {
                    $attributeDepth++;
                    continue;
                }

                if ($attributeToken === '[') {
                    $attributeDepth--;

                    if ($attributeDepth === 0) {
                        break;
                    }

                    continue;
                }

                if (
                    \is_array($attributeToken)
                    && $attributeToken[0] === T_ATTRIBUTE
                ) {
                    $attributeDepth--;

                    if ($attributeDepth === 0) {
                        break;
                    }
                }
            }

            if ($attributeDepth !== 0) {
                return null;
            }

            continue;
        }

        if (\is_array($token) && $token[0] === T_DOC_COMMENT) {
            return $token[1];
        }

        if (\is_array($token) && $token[0] === T_COMMENT) {
            continue;
        }

        return null;
    }

    return null;
}

function coretsia_kernel_public_api_gate_decode_php_string_literal(string $literal): string
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
