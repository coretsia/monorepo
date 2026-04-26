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
                'CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT';
    $fallbackScanFailed = 'CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED';
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
        $name = $ErrorCodes . '::CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED';
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

        $resetInterfaceFile = $packagesRoot . '/core/contracts/src/Runtime/ResetInterface.php';
        $foundationTagsFile = $packagesRoot . '/core/foundation/src/Provider/Tags.php';

        if (
            !\is_file($resetInterfaceFile)
            || !\is_readable($resetInterfaceFile)
            || !\is_file($foundationTagsFile)
            || !\is_readable($foundationTagsFile)
        ) {
            exit(0);
        }

        $foundationTags = coretsia_cross_cutting_contract_gate_extract_string_constants($foundationTagsFile);

        if (
            ($foundationTags['KERNEL_STATEFUL'] ?? null) !== 'kernel.stateful'
            || ($foundationTags['KERNEL_RESET'] ?? null) !== 'kernel.reset'
        ) {
            $ConsoleOutput::codeWithDiagnostics($codeViolation, [
                'packages/core/foundation/src/Provider/Tags.php: kernel-tags-drift',
            ]);
            exit(1);
        }

        if (!\is_dir($packagesRoot)) {
            exit(0);
        }

        /**
         * @var array<string, array{
         *     kind:string,
         *     file:string,
         *     extends:list<string>,
         *     implements:list<string>
         * }> $declaredTypes
         */
        $declaredTypes = [];

        /** @var array<string, string> $classFiles */
        $classFiles = [];

        foreach (coretsia_cross_cutting_contract_gate_find_php_sources([$packagesRoot], true) as $absPath) {
            foreach (coretsia_cross_cutting_contract_gate_extract_declared_types($absPath) as $symbol => $info) {
                $declaredTypes[$symbol] = $info;

                if ($info['kind'] === 'class') {
                    $classFiles[$symbol] = $absPath;
                }
            }
        }

        /** @var array<string, true> $resettableClasses */
        $resettableClasses = [];

        /** @var array<string, bool> $resettableCache */
        $resettableCache = [];

        foreach (\array_keys($classFiles) as $className) {
            if (
                coretsia_cross_cutting_contract_gate_type_is_resettable(
                    $className,
                    $declaredTypes,
                    $resettableCache,
                    [],
                )
            ) {
                $resettableClasses[$className] = true;
            }
        }

        $contextSymbols = coretsia_cross_cutting_contract_gate_find_context_symbols($declaredTypes);

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_cross_cutting_contract_gate_candidate_wiring_sources($packagesRoot) as $absPath) {
            $frameworkRelPath = coretsia_cross_cutting_contract_gate_rel_from_framework($absPath, $frameworkRoot);

            foreach (
                coretsia_cross_cutting_contract_gate_extract_stateful_service_evidence(
                    $absPath,
                    $classFiles,
                ) as $evidence
            ) {
                if (!$evidence['has_reset_tag']) {
                    $violations[] = $frameworkRelPath . ': kernel-stateful-service-missing-reset-tag';
                }

                if ($evidence['service_classes'] === []) {
                    $violations[] = $frameworkRelPath . ': kernel-stateful-service-class-unresolved';
                    continue;
                }

                foreach ($evidence['service_classes'] as $serviceClass) {
                    if (!isset($resettableClasses[$serviceClass])) {
                        $violations[] = $frameworkRelPath . ': kernel-stateful-service-not-resettable';
                    }
                }
            }

            foreach (
                coretsia_cross_cutting_contract_gate_detect_forbidden_context_symbol_usage(
                    $absPath,
                    $contextSymbols,
                ) as $reason
            ) {
                $violations[] = $frameworkRelPath . ': ' . $reason;
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
 * @return array<string, string>
 */
function coretsia_cross_cutting_contract_gate_extract_string_constants(string $phpFile): array
{
    $source = coretsia_cross_cutting_contract_gate_read_file($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    /** @var array<string, string> $constants */
    $constants = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_CONST) {
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

        foreach (coretsia_cross_cutting_contract_gate_extract_string_constants_from_declaration($declarationTokens) as $name => $value) {
            $constants[$name] = $value;
        }

        $i = $j;
    }

    \ksort($constants, \SORT_STRING);

    return $constants;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $declarationTokens
 * @return array<string, string>
 */
function coretsia_cross_cutting_contract_gate_extract_string_constants_from_declaration(array $declarationTokens): array
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

    /** @var array<string, string> $constants */
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

            if (\is_array($token) && $token[0] === T_STRING) {
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
                $value = coretsia_cross_cutting_contract_gate_decode_php_string_literal($token[1]);
                break;
            }

            if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                break;
            }
        }

        if ($value !== null) {
            $constants[$name] = $value;
        }
    }

    \ksort($constants, \SORT_STRING);

    return $constants;
}

/**
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_candidate_wiring_sources(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    /** @var list<string> $roots */
    $roots = [];

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

            foreach ([$packageRoot . '/src', $packageRoot . '/config'] as $root) {
                if (\is_dir($root)) {
                    $roots[] = \rtrim(\str_replace('\\', '/', $root), '/');
                }
            }
        }
    }

    return coretsia_cross_cutting_contract_gate_find_php_sources($roots, false);
}

/**
 * @param list<string> $roots
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_find_php_sources(array $roots, bool $srcOnly): array
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
                \str_contains($absPath, '/tests/')
                || \str_contains($absPath, '/fixtures/')
                || \str_contains($absPath, '/vendor/')
            ) {
                continue;
            }

            if ($srcOnly && !\str_contains($absPath, '/src/')) {
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
 * @return array<string, array{kind:string, file:string, extends:list<string>, implements:list<string>}>
 */
function coretsia_cross_cutting_contract_gate_extract_declared_types(string $phpFile): array
{
    $source = coretsia_cross_cutting_contract_gate_read_file($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_cross_cutting_contract_gate_parse_php_context($source);
    $namespace = $context['namespace'];
    $imports = $context['imports'];

    /** @var array<string, array{kind:string, file:string, extends:list<string>, implements:list<string>}> $types */
    $types = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (
            !\is_array($token)
            || (
                $token[0] !== T_CLASS
                && $token[0] !== T_INTERFACE
            )
        ) {
            continue;
        }

        if ($token[0] === T_CLASS) {
            $previous = coretsia_cross_cutting_contract_gate_previous_meaningful_token($tokens, $i - 1);
            if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
                continue;
            }
        }

        $nameIndex = coretsia_cross_cutting_contract_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($nameIndex === null) {
            continue;
        }

        $nameToken = $tokens[$nameIndex] ?? null;
        if (!\is_array($nameToken) || $nameToken[0] !== T_STRING) {
            continue;
        }

        $fqcn = $namespace === '' ? $nameToken[1] : $namespace . '\\' . $nameToken[1];
        $kind = $token[0] === T_CLASS ? 'class' : 'interface';

        $extends = coretsia_cross_cutting_contract_gate_extract_type_list_after_keyword(
            $tokens,
            $nameIndex + 1,
            T_EXTENDS,
            $token[0] === T_CLASS ? [T_IMPLEMENTS] : [],
            $namespace,
            $imports,
        );

        $implements = $token[0] === T_CLASS
            ? coretsia_cross_cutting_contract_gate_extract_type_list_after_keyword(
                $tokens,
                $nameIndex + 1,
                T_IMPLEMENTS,
                [],
                $namespace,
                $imports,
            )
            : [];

        $types[$fqcn] = [
            'kind' => $kind,
            'file' => $phpFile,
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
function coretsia_cross_cutting_contract_gate_extract_type_list_after_keyword(
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
                $names[] = coretsia_cross_cutting_contract_gate_resolve_type_name($current, $namespace, $imports);
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
        $names[] = coretsia_cross_cutting_contract_gate_resolve_type_name($current, $namespace, $imports);
    }

    $names = \array_values(\array_unique($names));
    \sort($names, \SORT_STRING);

    return $names;
}

/**
 * @param array<string, array{kind:string, file:string, extends:list<string>, implements:list<string>}> $declaredTypes
 * @param array<string, bool> $cache
 * @param array<string, true> $visiting
 */
function coretsia_cross_cutting_contract_gate_type_is_resettable(
    string $typeName,
    array  $declaredTypes,
    array  &$cache,
    array  $visiting,
): bool {
    if ($typeName === 'Coretsia\\Contracts\\Runtime\\ResetInterface') {
        return true;
    }

    if (isset($cache[$typeName])) {
        return $cache[$typeName];
    }

    if (isset($visiting[$typeName])) {
        $cache[$typeName] = false;
        return false;
    }

    if (!isset($declaredTypes[$typeName])) {
        $cache[$typeName] = false;
        return false;
    }

    $visiting[$typeName] = true;
    $info = $declaredTypes[$typeName];

    foreach ($info['implements'] as $implemented) {
        if (coretsia_cross_cutting_contract_gate_type_is_resettable($implemented, $declaredTypes, $cache, $visiting)) {
            $cache[$typeName] = true;
            return true;
        }
    }

    foreach ($info['extends'] as $parent) {
        if (coretsia_cross_cutting_contract_gate_type_is_resettable($parent, $declaredTypes, $cache, $visiting)) {
            $cache[$typeName] = true;
            return true;
        }
    }

    $cache[$typeName] = false;

    return false;
}

/**
 * @param array<string, array{kind:string, file:string, extends:list<string>, implements:list<string>}> $declaredTypes
 * @return array{ContextStore:array<string, string>, ContextKeys:array<string, string>}
 */
function coretsia_cross_cutting_contract_gate_find_context_symbols(array $declaredTypes): array
{
    /** @var array<string, string> $contextStore */
    $contextStore = [];

    /** @var array<string, string> $contextKeys */
    $contextKeys = [];

    foreach ($declaredTypes as $symbol => $info) {
        $basename = \basename(\str_replace('\\', '/', $symbol));

        if ($basename === 'ContextStore') {
            $contextStore[$symbol] = $info['file'];
            continue;
        }

        if ($basename === 'ContextKeys') {
            $contextKeys[$symbol] = $info['file'];
        }
    }

    \ksort($contextStore, \SORT_STRING);
    \ksort($contextKeys, \SORT_STRING);

    return [
        'ContextStore' => $contextStore,
        'ContextKeys' => $contextKeys,
    ];
}

/**
 * @param array<string, string> $classFiles
 * @return list<array{service_classes:list<string>, has_reset_tag:bool}>
 */
function coretsia_cross_cutting_contract_gate_extract_stateful_service_evidence(
    string $phpFile,
    array  $classFiles,
): array {
    $source = coretsia_cross_cutting_contract_gate_read_file($phpFile);

    if (
        !\str_contains($source, 'kernel.stateful')
        && !\str_contains($source, 'KERNEL_STATEFUL')
    ) {
        return [];
    }

    if (coretsia_cross_cutting_contract_gate_is_tag_constant_definition_file($phpFile)) {
        return [];
    }

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_cross_cutting_contract_gate_parse_php_context($source);
    $namespace = $context['namespace'];
    $imports = $context['imports'];

    $declaredClassNames = coretsia_cross_cutting_contract_gate_extract_declared_class_names_from_source($source);

    /** @var list<array{service_classes:list<string>, has_reset_tag:bool}> $evidence */
    $evidence = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!coretsia_cross_cutting_contract_gate_is_stateful_tag_token($tokens[$i])) {
            continue;
        }

        $windowStart = \max(0, $i - 160);
        $windowEnd = \min($count - 1, $i + 160);

        $hasResetTag = false;
        for ($j = $windowStart; $j <= $windowEnd; $j++) {
            if (coretsia_cross_cutting_contract_gate_is_reset_tag_token($tokens[$j])) {
                $hasResetTag = true;
                break;
            }
        }

        $serviceClasses = coretsia_cross_cutting_contract_gate_extract_class_references_from_window(
            $tokens,
            $windowStart,
            $windowEnd,
            $namespace,
            $imports,
            $classFiles,
        );

        if ($serviceClasses === []) {
            $serviceClasses = coretsia_cross_cutting_contract_gate_declared_classes_in_window(
                $tokens,
                $windowStart,
                $windowEnd,
                $declaredClassNames,
            );
        }

        if ($serviceClasses === [] && \count($declaredClassNames) === 1) {
            $serviceClasses = $declaredClassNames;
        }

        $evidence[] = [
            'service_classes' => $serviceClasses,
            'has_reset_tag' => $hasResetTag,
        ];
    }

    return $evidence;
}

function coretsia_cross_cutting_contract_gate_is_tag_constant_definition_file(string $phpFile): bool
{
    $constants = coretsia_cross_cutting_contract_gate_extract_string_constants($phpFile);

    return ($constants['KERNEL_STATEFUL'] ?? null) === 'kernel.stateful'
        && ($constants['KERNEL_RESET'] ?? null) === 'kernel.reset';
}

/**
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_extract_declared_class_names_from_source(string $source): array
{
    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_cross_cutting_contract_gate_parse_php_context($source);
    $namespace = $context['namespace'];

    /** @var list<string> $classes */
    $classes = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_CLASS) {
            continue;
        }

        $previous = coretsia_cross_cutting_contract_gate_previous_meaningful_token($tokens, $i - 1);
        if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
            continue;
        }

        $nameIndex = coretsia_cross_cutting_contract_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($nameIndex === null) {
            continue;
        }

        $nameToken = $tokens[$nameIndex] ?? null;
        if (!\is_array($nameToken) || $nameToken[0] !== T_STRING) {
            continue;
        }

        $classes[] = $namespace === '' ? $nameToken[1] : $namespace . '\\' . $nameToken[1];
    }

    $classes = \array_values(\array_unique($classes));
    \sort($classes, \SORT_STRING);

    return $classes;
}

/**
 * @param array{0:int, 1:string, 2:int}|string $token
 */
function coretsia_cross_cutting_contract_gate_is_stateful_tag_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
        return coretsia_cross_cutting_contract_gate_decode_php_string_literal($token[1]) === 'kernel.stateful';
    }

    return $token[0] === T_STRING && $token[1] === 'KERNEL_STATEFUL';
}

/**
 * @param array{0:int, 1:string, 2:int}|string $token
 */
function coretsia_cross_cutting_contract_gate_is_reset_tag_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
        return coretsia_cross_cutting_contract_gate_decode_php_string_literal($token[1]) === 'kernel.reset';
    }

    return $token[0] === T_STRING && $token[1] === 'KERNEL_RESET';
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 * @param array<string, string> $imports
 * @param array<string, string> $classFiles
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_extract_class_references_from_window(
    array  $tokens,
    int    $windowStart,
    int    $windowEnd,
    string $namespace,
    array  $imports,
    array  $classFiles,
): array {
    /** @var array<string, true> $classes */
    $classes = [];

    for ($i = $windowStart; $i <= $windowEnd; $i++) {
        $token = $tokens[$i] ?? null;

        if (\is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literalValue = coretsia_cross_cutting_contract_gate_decode_php_string_literal($token[1]);
            $literalClass = \ltrim($literalValue, '\\');

            if (isset($classFiles[$literalClass])) {
                $classes[$literalClass] = true;
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

        $doubleColonIndex = coretsia_cross_cutting_contract_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($doubleColonIndex === null) {
            continue;
        }

        $doubleColonToken = $tokens[$doubleColonIndex] ?? null;
        if (!\is_array($doubleColonToken) || $doubleColonToken[0] !== T_DOUBLE_COLON) {
            continue;
        }

        $classKeywordIndex = coretsia_cross_cutting_contract_gate_next_meaningful_token_index($tokens, $doubleColonIndex + 1);
        if ($classKeywordIndex === null) {
            continue;
        }

        $classKeyword = $tokens[$classKeywordIndex] ?? null;
        if (!\is_array($classKeyword) || $classKeyword[0] !== T_CLASS) {
            continue;
        }

        $resolved = coretsia_cross_cutting_contract_gate_resolve_type_name($token[1], $namespace, $imports);

        if (isset($classFiles[$resolved])) {
            $classes[$resolved] = true;
        }
    }

    $result = \array_keys($classes);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 * @param list<string> $declaredClassNames
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_declared_classes_in_window(
    array $tokens,
    int   $windowStart,
    int   $windowEnd,
    array $declaredClassNames,
): array {
    if ($declaredClassNames === []) {
        return [];
    }

    /** @var array<string, true> $classes */
    $classes = [];

    for ($i = $windowStart; $i <= $windowEnd; $i++) {
        $token = $tokens[$i] ?? null;

        if (!\is_array($token) || $token[0] !== T_CLASS) {
            continue;
        }

        $previous = coretsia_cross_cutting_contract_gate_previous_meaningful_token($tokens, $i - 1);
        if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
            continue;
        }

        $nameIndex = coretsia_cross_cutting_contract_gate_next_meaningful_token_index($tokens, $i + 1);
        if ($nameIndex === null) {
            continue;
        }

        $nameToken = $tokens[$nameIndex] ?? null;
        if (!\is_array($nameToken) || $nameToken[0] !== T_STRING) {
            continue;
        }

        foreach ($declaredClassNames as $className) {
            if (\str_ends_with($className, '\\' . $nameToken[1]) || $className === $nameToken[1]) {
                $classes[$className] = true;
            }
        }
    }

    $result = \array_keys($classes);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @param array{ContextStore:array<string, string>, ContextKeys:array<string, string>} $contextSymbols
 * @return list<string>
 */
function coretsia_cross_cutting_contract_gate_detect_forbidden_context_symbol_usage(
    string $phpFile,
    array  $contextSymbols,
): array {
    if ($contextSymbols['ContextStore'] === [] && $contextSymbols['ContextKeys'] === []) {
        return [];
    }

    $source = coretsia_cross_cutting_contract_gate_read_file($phpFile);

    if (
        !\str_contains($source, 'ContextStore')
        && !\str_contains($source, 'ContextKeys')
    ) {
        return [];
    }

    $ownSymbols = [];
    foreach ($contextSymbols['ContextStore'] as $symbol => $ownerFile) {
        if ($ownerFile === $phpFile) {
            $ownSymbols[$symbol] = true;
        }
    }
    foreach ($contextSymbols['ContextKeys'] as $symbol => $ownerFile) {
        if ($ownerFile === $phpFile) {
            $ownSymbols[$symbol] = true;
        }
    }

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $context = coretsia_cross_cutting_contract_gate_parse_php_context($source);
    $namespace = $context['namespace'];
    $imports = $context['imports'];

    /** @var array<string, true> $violations */
    $violations = [];

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

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

        if ($token[1] !== 'ContextStore' && $token[1] !== 'ContextKeys' && !\str_ends_with($token[1], '\\ContextStore') && !\str_ends_with($token[1], '\\ContextKeys')) {
            continue;
        }

        $previous = coretsia_cross_cutting_contract_gate_previous_meaningful_token($tokens, $i - 1);
        if ($previous === T_CLASS || $previous === T_INTERFACE || $previous === T_TRAIT) {
            continue;
        }

        $resolved = coretsia_cross_cutting_contract_gate_resolve_type_name($token[1], $namespace, $imports);

        if (isset($ownSymbols[$resolved])) {
            continue;
        }

        if (isset($contextSymbols['ContextStore'][$resolved])) {
            $violations['forbidden-context-store-usage'] = true;
            continue;
        }

        if (isset($contextSymbols['ContextKeys'][$resolved])) {
            $violations['forbidden-context-keys-usage'] = true;
        }
    }

    $result = \array_keys($violations);
    \sort($result, \SORT_STRING);

    return $result;
}

/**
 * @return array{namespace:string, imports:array<string, string>}
 */
function coretsia_cross_cutting_contract_gate_parse_php_context(string $source): array
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

            foreach (coretsia_cross_cutting_contract_gate_extract_imports_from_use_statement($useStatement) as $alias => $fqcn) {
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
function coretsia_cross_cutting_contract_gate_extract_imports_from_use_statement(string $useStatement): array
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

        foreach (coretsia_cross_cutting_contract_gate_split_use_statement_parts($inside) as $part) {
            $parsed = coretsia_cross_cutting_contract_gate_parse_import_part($part, $prefix);

            if ($parsed === null) {
                continue;
            }

            $imports[$parsed['alias']] = $parsed['fqcn'];
        }

        \ksort($imports, \SORT_STRING);

        return $imports;
    }

    foreach (coretsia_cross_cutting_contract_gate_split_use_statement_parts($useStatement) as $part) {
        $parsed = coretsia_cross_cutting_contract_gate_parse_import_part($part, null);

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
function coretsia_cross_cutting_contract_gate_split_use_statement_parts(string $useStatement): array
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
function coretsia_cross_cutting_contract_gate_parse_import_part(string $part, ?string $prefix): ?array
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
function coretsia_cross_cutting_contract_gate_resolve_type_name(
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

    if ($namespace === '') {
        return $name;
    }

    return $namespace . '\\' . $name;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_cross_cutting_contract_gate_next_meaningful_token_index(array $tokens, int $start): ?int
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
function coretsia_cross_cutting_contract_gate_previous_meaningful_token(array $tokens, int $start): int|string|null
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

function coretsia_cross_cutting_contract_gate_decode_php_string_literal(string $literal): string
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

function coretsia_cross_cutting_contract_gate_read_file(string $path): string
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

function coretsia_cross_cutting_contract_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
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
