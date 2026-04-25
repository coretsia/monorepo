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
                'CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_KERNEL_PUBLIC_API_DRIFT';
    $fallbackScanFailed = 'CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED';
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
        $name = $ErrorCodes . '::CORETSIA_KERNEL_PUBLIC_API_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED';
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
        $kernelRoot = $frameworkRoot . '/packages/core/kernel';
        $kernelSrc = $kernelRoot . '/src';

        if (!\is_dir($kernelSrc)) {
            exit(0);
        }

        $evidenceFiles = coretsia_kernel_public_api_gate_find_public_api_evidence_files($repoRoot, $kernelRoot);

        if ($evidenceFiles === []) {
            exit(0);
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

        foreach (coretsia_kernel_public_api_gate_find_php_sources([$kernelSrc]) as $absPath) {
            foreach (coretsia_kernel_public_api_gate_extract_declared_types($absPath) as $symbol => $info) {
                $declaredTypes[$symbol] = $info;
            }
        }

        if ($declaredTypes === []) {
            exit(0);
        }

        $publicApiSymbols = coretsia_kernel_public_api_gate_extract_public_api_symbols_from_evidence(
            $evidenceFiles,
            $declaredTypes,
        );

        /** @var list<string> $violations */
        $violations = [];

        if ($publicApiSymbols === []) {
            foreach ($evidenceFiles as $evidenceFile) {
                $violations[] = coretsia_kernel_public_api_gate_rel_from_repo(
                    $evidenceFile,
                    $repoRoot,
                ) . ': kernel-public-api-evidence-empty';
            }
        }

        foreach ($declaredTypes as $symbol => $info) {
            $frameworkRelPath = coretsia_kernel_public_api_gate_rel_from_framework($info['file'], $frameworkRoot);
            $isListed = isset($publicApiSymbols[$symbol]);
            $isInternal = coretsia_kernel_public_api_gate_type_is_internal($symbol, $info);

            if ($isListed && $isInternal) {
                $violations[] = $frameworkRelPath . ': kernel-public-api-symbol-internal-listed';
                continue;
            }

            if (!$isListed && !$isInternal) {
                $violations[] = $frameworkRelPath . ': kernel-public-api-symbol-unlisted';
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
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_find_public_api_evidence_files(string $repoRoot, string $kernelRoot): array
{
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
    $kernelRoot = \rtrim(\str_replace('\\', '/', $kernelRoot), '/');

    /** @var list<string> $candidates */
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
        $repoRoot . '/docs/ssot/kernel-public-api.md',
        $repoRoot . '/docs/ssot/kernel_public_api.md',
        $repoRoot . '/docs/architecture/kernel-public-api.md',
        $repoRoot . '/docs/architecture/kernel_public_api.md',
    ];

    /** @var list<string> $files */
    $files = [];

    foreach ($candidates as $candidate) {
        if (\is_file($candidate) && \is_readable($candidate)) {
            $files[] = \rtrim(\str_replace('\\', '/', $candidate), '/');
        }
    }

    $testsRoot = $kernelRoot . '/tests';
    if (\is_dir($testsRoot)) {
        foreach (coretsia_kernel_public_api_gate_find_php_sources([$testsRoot]) as $absPath) {
            $basename = \basename($absPath);

            if (
                \str_contains($basename, 'PublicApi')
                || \str_contains($basename, 'PublicAPI')
                || \str_contains($basename, 'PublicSurface')
                || \str_contains($basename, 'ApiSurface')
                || \str_contains($basename, 'KernelPublic')
            ) {
                $files[] = $absPath;
            }
        }
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

/**
 * @param list<string> $roots
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_find_php_sources(array $roots): array
{
    /** @var list<string> $files */
    $files = [];

    foreach (\array_values(\array_unique($roots)) as $root) {
        $root = \rtrim(\str_replace('\\', '/', $root), '/');

        if (!\is_dir($root)) {
            continue;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\Throwable) {
            throw new \RuntimeException('source-root-iterator-failed');
        }

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
                \str_contains($absPath, '/fixtures/')
                || \str_contains($absPath, '/vendor/')
                || \str_contains($absPath, '/node_modules/')
            ) {
                continue;
            }

            $files[] = $absPath;
        }
    }

    $files = \array_values(\array_unique($files));
    \sort($files, \SORT_STRING);

    return $files;
}

/**
 * @return array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>}>
 */
function coretsia_kernel_public_api_gate_extract_declared_types(string $phpFile): array
{
    $source = coretsia_kernel_public_api_gate_read_file($phpFile);

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
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_type_list_after_keyword(
    array  $tokens,
    int    $start,
    int    $keywordId,
    array  $stopKeywordIds,
    string $namespace,
    array  $imports,
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
 * @param array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>}> $declaredTypes
 * @return array<string, true>
 */
function coretsia_kernel_public_api_gate_extract_public_api_symbols_from_evidence(
    array $evidenceFiles,
    array $declaredTypes,
): array {
    /** @var array<string, true> $symbols */
    $symbols = [];

    foreach ($evidenceFiles as $evidenceFile) {
        $ext = \strtolower((string)\pathinfo($evidenceFile, \PATHINFO_EXTENSION));

        $fileSymbols = $ext === 'php'
            ? coretsia_kernel_public_api_gate_extract_php_evidence_symbols($evidenceFile, $declaredTypes)
            : coretsia_kernel_public_api_gate_extract_text_evidence_symbols($evidenceFile, $declaredTypes);

        foreach ($fileSymbols as $symbol) {
            $symbols[$symbol] = true;
        }
    }

    \ksort($symbols, \SORT_STRING);

    return $symbols;
}

/**
 * @param array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>}> $declaredTypes
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_text_evidence_symbols(string $evidenceFile, array $declaredTypes): array
{
    $content = coretsia_kernel_public_api_gate_read_file($evidenceFile);

    /** @var array<string, true> $symbols */
    $symbols = [];

    foreach (\array_keys($declaredTypes) as $symbol) {
        if (
            \str_contains($content, $symbol)
            || \str_contains($content, \str_replace('\\', '\\\\', $symbol))
        ) {
            $symbols[$symbol] = true;
        }
    }

    $result = \array_keys($symbols);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @param array<string, array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>}> $declaredTypes
 * @return list<string>
 */
function coretsia_kernel_public_api_gate_extract_php_evidence_symbols(string $evidenceFile, array $declaredTypes): array
{
    $source = coretsia_kernel_public_api_gate_read_file($evidenceFile);

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

        $classKeywordIndex = coretsia_kernel_public_api_gate_next_meaningful_token_index($tokens, $doubleColonIndex + 1);
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

    foreach (coretsia_kernel_public_api_gate_extract_text_evidence_symbols($evidenceFile, $declaredTypes) as $symbol) {
        $symbols[$symbol] = true;
    }

    $result = \array_keys($symbols);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @param array{kind:string, file:string, docblock:string|null, extends:list<string>, implements:list<string>} $info
 */
function coretsia_kernel_public_api_gate_type_is_internal(string $symbol, array $info): bool
{
    if ($info['docblock'] !== null && \preg_match('/@internal\b/u', $info['docblock']) === 1) {
        return true;
    }

    $normalizedFile = \str_replace('\\', '/', $info['file']);

    return \str_contains($symbol, '\\Internal\\')
        || \str_contains($normalizedFile, '/Internal/')
        || \str_contains($normalizedFile, '/internal/');
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
        $declarationOffset = (int)$m[0][1];
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

            foreach (coretsia_kernel_public_api_gate_extract_imports_from_use_statement($useStatement) as $alias => $fqcn) {
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
    array  $imports,
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
    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_WHITESPACE) {
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

function coretsia_kernel_public_api_gate_read_file(string $path): string
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

function coretsia_kernel_public_api_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
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

function coretsia_kernel_public_api_gate_rel_from_repo(string $absPath, string $repoRoot): string
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
