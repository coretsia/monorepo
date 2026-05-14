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
                'CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT';
    $fallbackScanFailed = 'CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED';
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
        $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED';
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
        $observabilityFile = $repoRoot . '/docs/ssot/observability.md';

        if (!\is_file($observabilityFile) || !\is_readable($observabilityFile)) {
            throw new \RuntimeException('observability-ssot-missing');
        }

        $markdown = coretsia_observability_span_naming_gate_read_file($observabilityFile);
        if ($markdown === null) {
            throw new \RuntimeException('observability-ssot-read-failed');
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_observability_span_naming_gate_parse_span_policy($markdown) as $diagnostic) {
            $violations[] = 'docs/ssot/observability.md: ' . $diagnostic;
        }

        if ($violations === [] && \is_dir($packagesRoot)) {
            foreach (coretsia_observability_span_naming_gate_find_php_sources($packagesRoot) as $absPath) {
                foreach (
                    coretsia_observability_span_naming_gate_scan_php_file(
                        $absPath,
                        $frameworkRoot,
                    ) as $violation
                ) {
                    $violations[] = $violation;
                }
            }
        }

        $violations = coretsia_observability_span_naming_gate_unique_sorted($violations);

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

function coretsia_observability_span_naming_gate_read_file(string $path): ?string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        if (!\is_file($path) || !\is_readable($path)) {
            return null;
        }

        $content = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    return \is_string($content) ? $content : null;
}

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_parse_span_policy(string $markdown): array
{
    $section = coretsia_observability_span_naming_gate_extract_markdown_section($markdown, '### Spans');
    if ($section === null) {
        return ['canonical-span-naming-policy-missing'];
    }

    $normalized = \str_replace(["\r\n", "\r"], "\n", $section);

    if (!\str_contains($normalized, '<domain>.<singular_operation>')) {
        return ['canonical-span-naming-policy-unparseable'];
    }

    if (!\preg_match('/singular/i', $normalized)) {
        return ['canonical-span-naming-policy-unparseable'];
    }

    return [];
}

function coretsia_observability_span_naming_gate_extract_markdown_section(string $markdown, string $header): ?string
{
    $normalized = \str_replace(["\r\n", "\r"], "\n", $markdown);
    $needle = $header . "\n";
    $start = \strpos($normalized, $needle);
    if ($start === false) {
        $trimmedNeedle = $header;
        $lines = \explode("\n", $normalized);
        $offset = 0;
        foreach ($lines as $line) {
            if (\trim($line) === $trimmedNeedle) {
                $start = $offset;
                break;
            }
            $offset += \strlen($line) + 1;
        }
        if ($start === false) {
            return null;
        }
    }

    $bodyStart = \strpos($normalized, "\n", $start);
    if ($bodyStart === false) {
        return '';
    }
    $bodyStart++;

    $rest = \substr($normalized, $bodyStart);
    $next = \preg_match('/^#{1,3}\s+/m', $rest, $m, PREG_OFFSET_CAPTURE);
    if ($next === 1) {
        return \substr($rest, 0, (int)$m[0][1]);
    }

    return $rest;
}

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_find_php_sources(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

    /** @var list<string> $phpFiles */
    $phpFiles = [];

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
                    \str_contains($absPath, '/docs/')
                    || \str_contains($absPath, '/tests/')
                    || \str_contains($absPath, '/tools/')
                    || \str_contains($absPath, '/var/')
                    || \str_contains($absPath, '/fixtures/')
                    || \str_contains($absPath, '/vendor/')
                ) {
                    continue;
                }

                $phpFiles[] = $absPath;
            }
        }
    }

    $phpFiles = \array_values(\array_unique($phpFiles));
    \sort($phpFiles, \SORT_STRING);

    return $phpFiles;
}

function coretsia_observability_span_naming_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
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

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan_php_file(
    string $absPath,
    string $frameworkRoot,
): array {
    $source = coretsia_observability_span_naming_gate_read_file($absPath);
    if ($source === null) {
        throw new \RuntimeException('runtime-source-read-failed');
    }

    try {
        $tokens = \token_get_all($source, \TOKEN_PARSE);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    $diagnostics = [];
    $frameworkRelPath = coretsia_observability_span_naming_gate_rel_from_framework($absPath, $frameworkRoot);
    $namespace = '';
    $uses = coretsia_observability_span_naming_gate_collect_use_aliases($tokens, $namespace);

    $count = \count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!coretsia_observability_span_naming_gate_is_runtime_type_declaration($token)) {
            continue;
        }

        if (coretsia_observability_span_naming_gate_previous_meaningful_token_is($tokens, $i, T_NEW)) {
            continue;
        }

        $open = coretsia_observability_span_naming_gate_find_next_symbol($tokens, $i, '{');
        if ($open === null) {
            continue;
        }

        $close = coretsia_observability_span_naming_gate_find_matching_symbol($tokens, $open, '{', '}');
        if ($close === null) {
            continue;
        }

        $classTokens = \array_slice($tokens, $open + 1, $close - $open - 1);
        $classInfo = coretsia_observability_span_naming_gate_analyze_class($classTokens, $uses, $namespace);

        foreach (coretsia_observability_span_naming_gate_scan_class_methods($classTokens, $classInfo) as $line) {
            $diagnostics[] = $line;
        }

        $i = $close;
    }

    $violations = [];
    foreach (coretsia_observability_span_naming_gate_unique_sorted($diagnostics) as $diagnostic) {
        $violations[] = $frameworkRelPath . ': ' . $diagnostic;
    }

    return $violations;
}

/**
 * @param array<int,mixed> $tokens
 * @return array<string,string>
 */
function coretsia_observability_span_naming_gate_collect_use_aliases(array $tokens, string &$namespace): array
{
    $uses = [];
    $depth = 0;
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;
            continue;
        }

        if ($token === '}') {
            $depth = \max(0, $depth - 1);
            continue;
        }

        if ($depth === 0 && coretsia_observability_span_naming_gate_is_token($token, T_NAMESPACE)) {
            $name = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $x = $tokens[$j];
                if ($x === ';' || $x === '{') {
                    break;
                }
                if (\is_array($x) && coretsia_observability_span_naming_gate_is_name_token_id((int)$x[0])) {
                    $name .= (string)$x[1];
                    continue;
                }
                if ($x === '\\') {
                    $name .= '\\';
                }
            }

            $namespace = \trim($name, '\\');
            continue;
        }

        if ($depth !== 0 || !coretsia_observability_span_naming_gate_is_token($token, T_USE)) {
            continue;
        }

        $statement = [];
        for ($j = $i + 1; $j < $count; $j++) {
            if ($tokens[$j] === ';') {
                break;
            }
            $statement[] = $tokens[$j];
        }

        foreach (coretsia_observability_span_naming_gate_parse_use_statement($statement) as $alias => $fqcn) {
            $uses[$alias] = $fqcn;
        }
    }

    return $uses;
}

/**
 * @param array<int,mixed> $tokens
 * @return array<string,string>
 */
function coretsia_observability_span_naming_gate_parse_use_statement(array $tokens): array
{
    $aliases = [];
    $segments = [[]];

    foreach ($tokens as $token) {
        if ($token === ',') {
            $segments[] = [];
            continue;
        }

        $segments[\count($segments) - 1][] = $token;
    }

    foreach ($segments as $segment) {
        $name = '';
        $alias = null;
        $afterAs = false;

        foreach ($segment as $token) {
            if (coretsia_observability_span_naming_gate_is_token($token, T_AS)) {
                $afterAs = true;
                continue;
            }

            if (!\is_array($token)) {
                if (!$afterAs && $token === '\\') {
                    $name .= '\\';
                }
                continue;
            }

            $text = (string)$token[1];

            if ($afterAs) {
                if ($token[0] === T_STRING) {
                    $alias = $text;
                }
                continue;
            }

            if (coretsia_observability_span_naming_gate_is_name_token_id((int)$token[0])) {
                $name .= $text;
            }
        }

        $name = \trim($name, '\\');
        if ($name === '') {
            continue;
        }

        if ($alias === null) {
            $parts = \explode('\\', $name);
            $alias = (string)\end($parts);
        }

        if ($alias !== '') {
            $aliases[$alias] = $name;
        }
    }

    return $aliases;
}

/**
 * @param array<int,mixed> $classTokens
 * @param array<string,string> $uses
 * @return array{tracerProperties:array<string,true>, privateStringConsts:array<string,string>, uses:array<string,string>, namespace:string}
 */
function coretsia_observability_span_naming_gate_analyze_class(
    array $classTokens,
    array $uses,
    string $namespace
): array {
    $tracerProperties = [];
    $privateStringConsts = [];
    $count = \count($classTokens);
    $depth = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $classTokens[$i];

        if ($token === '{' || $token === '(' || $token === '[') {
            $depth++;
            continue;
        }

        if ($token === '}' || $token === ')' || $token === ']') {
            $depth = \max(0, $depth - 1);
            continue;
        }

        if ($depth !== 0) {
            continue;
        }

        if (coretsia_observability_span_naming_gate_is_token($token, T_CONST)) {
            $segment = coretsia_observability_span_naming_gate_statement_segment($classTokens, $i);
            foreach (coretsia_observability_span_naming_gate_parse_private_string_consts($segment) as $name => $value) {
                $privateStringConsts[$name] = $value;
            }
            continue;
        }

        if (\is_array($token) && $token[0] === T_VARIABLE) {
            $segment = coretsia_observability_span_naming_gate_statement_segment($classTokens, $i);
            if (coretsia_observability_span_naming_gate_segment_contains_token($segment, T_FUNCTION)) {
                continue;
            }

            if (coretsia_observability_span_naming_gate_segment_declares_tracer_type($segment, $uses, $namespace)) {
                $tracerProperties[\ltrim((string)$token[1], '$')] = true;
            }
        }

        if (coretsia_observability_span_naming_gate_is_token($token, T_FUNCTION)) {
            $nameIndex = coretsia_observability_span_naming_gate_find_next_token_id($classTokens, $i, T_STRING);
            $methodName = $nameIndex !== null && \is_array($classTokens[$nameIndex]) ? \strtolower(
                (string)$classTokens[$nameIndex][1]
            ) : '';

            if ($methodName !== '__construct') {
                continue;
            }

            $openParen = coretsia_observability_span_naming_gate_find_next_symbol($classTokens, $i, '(');
            if ($openParen === null) {
                continue;
            }

            $closeParen = coretsia_observability_span_naming_gate_find_matching_symbol(
                $classTokens,
                $openParen,
                '(',
                ')'
            );
            if ($closeParen === null) {
                continue;
            }

            $params = \array_slice($classTokens, $openParen + 1, $closeParen - $openParen - 1);
            foreach (
                coretsia_observability_span_naming_gate_parse_params(
                    $params,
                    $uses,
                    $namespace,
                    true,
                ) as $var => $isTracer
            ) {
                if ($isTracer) {
                    $tracerProperties[$var] = true;
                }
            }
        }
    }

    return [
        'tracerProperties' => $tracerProperties,
        'privateStringConsts' => $privateStringConsts,
        'uses' => $uses,
        'namespace' => $namespace,
    ];
}

/**
 * @param array<int,mixed> $segment
 * @return array<string,string>
 */
function coretsia_observability_span_naming_gate_parse_private_string_consts(array $segment): array
{
    if (!coretsia_observability_span_naming_gate_segment_contains_token($segment, T_PRIVATE)) {
        return [];
    }

    $hasStringType = false;
    foreach ($segment as $token) {
        if (\is_array($token) && \strtolower((string)$token[1]) === 'string') {
            $hasStringType = true;
            break;
        }
    }

    if (!$hasStringType) {
        return [];
    }

    $out = [];
    $count = \count($segment);

    for ($i = 0; $i < $count; $i++) {
        $token = $segment[$i];
        if (!\is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $name = (string)$token[1];
        if (\strtolower($name) === 'string') {
            continue;
        }

        $eq = coretsia_observability_span_naming_gate_find_next_symbol($segment, $i, '=');
        if ($eq === null) {
            continue;
        }

        $valueIndex = coretsia_observability_span_naming_gate_next_meaningful_index($segment, $eq + 1);
        if ($valueIndex === null || !coretsia_observability_span_naming_gate_is_token(
            $segment[$valueIndex],
            T_CONSTANT_ENCAPSED_STRING
        )) {
            continue;
        }

        $out[$name] = coretsia_observability_span_naming_gate_decode_string_literal((string)$segment[$valueIndex][1]);
    }

    return $out;
}

/**
 * @param array<int,mixed> $classTokens
 * @param array{tracerProperties:array<string,true>, privateStringConsts:array<string,string>, uses:array<string,string>, namespace:string} $classInfo
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan_class_methods(array $classTokens, array $classInfo): array
{
    $diagnostics = [];
    $uses = $classInfo['uses'];
    $namespace = $classInfo['namespace'];
    $count = \count($classTokens);

    for ($i = 0; $i < $count; $i++) {
        if (!coretsia_observability_span_naming_gate_is_token($classTokens[$i], T_FUNCTION)) {
            continue;
        }

        $openParen = coretsia_observability_span_naming_gate_find_next_symbol($classTokens, $i, '(');
        if ($openParen === null) {
            continue;
        }

        $closeParen = coretsia_observability_span_naming_gate_find_matching_symbol($classTokens, $openParen, '(', ')');
        if ($closeParen === null) {
            continue;
        }

        $openBody = coretsia_observability_span_naming_gate_find_next_symbol($classTokens, $closeParen, '{');
        if ($openBody === null) {
            continue;
        }

        $closeBody = coretsia_observability_span_naming_gate_find_matching_symbol($classTokens, $openBody, '{', '}');
        if ($closeBody === null) {
            continue;
        }

        $params = \array_slice($classTokens, $openParen + 1, $closeParen - $openParen - 1);
        $body = \array_slice($classTokens, $openBody + 1, $closeBody - $openBody - 1);

        $methodTracerVars = [];
        foreach (
            coretsia_observability_span_naming_gate_parse_params(
                $params,
                $uses,
                $namespace,
                false,
            ) as $var => $isTracer
        ) {
            if ($isTracer) {
                $methodTracerVars[$var] = true;
            }
        }

        foreach (
            coretsia_observability_span_naming_gate_scan_method_body(
                $body,
                $classInfo,
                $methodTracerVars,
            ) as $line
        ) {
            $diagnostics[] = $line;
        }

        $i = $closeBody;
    }

    return coretsia_observability_span_naming_gate_unique_sorted($diagnostics);
}

/**
 * @param array<int,mixed> $params
 * @param array<string,string> $uses
 * @return array<string,bool>
 */
function coretsia_observability_span_naming_gate_parse_params(
    array $params,
    array $uses,
    string $namespace,
    bool $requirePromoted,
): array {
    $out = [];
    $segments = coretsia_observability_span_naming_gate_split_top_level($params, ',');

    foreach ($segments as $segment) {
        $var = null;
        foreach ($segment as $token) {
            if (\is_array($token) && $token[0] === T_VARIABLE) {
                $var = \ltrim((string)$token[1], '$');
                break;
            }
        }

        if ($var === null || $var === '') {
            continue;
        }

        if ($requirePromoted && !coretsia_observability_span_naming_gate_segment_has_visibility($segment)) {
            continue;
        }

        $out[$var] = coretsia_observability_span_naming_gate_segment_declares_tracer_type(
            $segment,
            $uses,
            $namespace,
        );
    }

    return $out;
}

/**
 * @param array<int,mixed> $body
 * @param array{tracerProperties:array<string,true>, privateStringConsts:array<string,string>, uses:array<string,string>, namespace:string} $classInfo
 * @param array<string,true> $methodTracerVars
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan_method_body(
    array $body,
    array $classInfo,
    array $methodTracerVars,
): array {
    $diagnostics = [];
    $count = \count($body);

    for ($i = 0; $i < $count; $i++) {
        $call = coretsia_observability_span_naming_gate_detect_tracer_call(
            $body,
            $i,
            $classInfo['tracerProperties'],
            $methodTracerVars,
        );

        if ($call === null) {
            continue;
        }

        $openParen = coretsia_observability_span_naming_gate_find_next_symbol($body, $call['methodIndex'], '(');
        if ($openParen === null) {
            continue;
        }

        $closeParen = coretsia_observability_span_naming_gate_find_matching_symbol($body, $openParen, '(', ')');
        if ($closeParen === null) {
            $diagnostics[] = 'span-call-arguments-unparseable';
            continue;
        }

        $args = coretsia_observability_span_naming_gate_split_top_level(
            \array_slice($body, $openParen + 1, $closeParen - $openParen - 1),
            ',',
        );

        if (coretsia_observability_span_naming_gate_contains_named_span_argument($args)) {
            $diagnostics[] = 'span-call-arguments-unparseable';
            $i = $closeParen;
            continue;
        }

        $spanName = coretsia_observability_span_naming_gate_resolve_span_name_arg(
            $args[0] ?? [],
            $classInfo['privateStringConsts'],
        );

        if ($spanName === null) {
            $diagnostics[] = 'span-name-unresolvable';
            $i = $closeParen;
            continue;
        }

        $diagnostic = coretsia_observability_span_naming_gate_validate_span_name($spanName);
        if ($diagnostic !== null) {
            $diagnostics[] = $diagnostic;
        }

        $i = $closeParen;
    }

    return coretsia_observability_span_naming_gate_unique_sorted($diagnostics);
}

/**
 * @param array<int,mixed> $tokens
 * @param array<string,true> $tracerProperties
 * @param array<string,true> $methodTracerVars
 * @return array{methodIndex:int}|null
 */
function coretsia_observability_span_naming_gate_detect_tracer_call(
    array $tokens,
    int $i,
    array $tracerProperties,
    array $methodTracerVars,
): ?array {
    $token = $tokens[$i] ?? null;
    if (!\is_array($token) || $token[0] !== T_VARIABLE) {
        return null;
    }

    $var = \ltrim((string)$token[1], '$');
    $next = coretsia_observability_span_naming_gate_next_meaningful_index($tokens, $i + 1);
    if ($next === null || !coretsia_observability_span_naming_gate_is_object_operator($tokens[$next])) {
        return null;
    }

    $nameIndex = coretsia_observability_span_naming_gate_next_meaningful_index($tokens, $next + 1);
    if ($nameIndex === null || !coretsia_observability_span_naming_gate_is_token($tokens[$nameIndex], T_STRING)) {
        return null;
    }

    if ($var === 'this') {
        $property = (string)$tokens[$nameIndex][1];
        if (!isset($tracerProperties[$property])) {
            return null;
        }

        $secondOp = coretsia_observability_span_naming_gate_next_meaningful_index($tokens, $nameIndex + 1);
        if ($secondOp === null || !coretsia_observability_span_naming_gate_is_object_operator($tokens[$secondOp])) {
            return null;
        }

        $methodIndex = coretsia_observability_span_naming_gate_next_meaningful_index($tokens, $secondOp + 1);
        if ($methodIndex === null || !coretsia_observability_span_naming_gate_is_token(
            $tokens[$methodIndex],
            T_STRING
        )) {
            return null;
        }

        if (!coretsia_observability_span_naming_gate_is_span_name_method((string)$tokens[$methodIndex][1])) {
            return null;
        }

        return ['methodIndex' => $methodIndex];
    }

    if (!isset($methodTracerVars[$var])) {
        return null;
    }

    if (!coretsia_observability_span_naming_gate_is_span_name_method((string)$tokens[$nameIndex][1])) {
        return null;
    }

    return ['methodIndex' => $nameIndex];
}

function coretsia_observability_span_naming_gate_is_span_name_method(string $method): bool
{
    return $method === 'startSpan' || $method === 'inSpan';
}

/**
 * @param array<int,mixed> $arg
 * @param array<string,string> $privateStringConsts
 */
function coretsia_observability_span_naming_gate_resolve_span_name_arg(array $arg, array $privateStringConsts): ?string
{
    $meaningful = coretsia_observability_span_naming_gate_meaningful_tokens($arg);

    if (\count($meaningful) === 1 && coretsia_observability_span_naming_gate_is_token(
        $meaningful[0],
        T_CONSTANT_ENCAPSED_STRING,
    )) {
        return coretsia_observability_span_naming_gate_decode_string_literal((string)$meaningful[0][1]);
    }

    if (\count($meaningful) === 3
        && coretsia_observability_span_naming_gate_token_text_lower($meaningful[0]) === 'self'
        && coretsia_observability_span_naming_gate_is_token($meaningful[1], T_DOUBLE_COLON)
        && coretsia_observability_span_naming_gate_is_token($meaningful[2], T_STRING)) {
        $constName = (string)$meaningful[2][1];
        return $privateStringConsts[$constName] ?? null;
    }

    return null;
}

function coretsia_observability_span_naming_gate_validate_span_name(string $spanName): ?string
{
    if (!\preg_match('/\A[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/', $spanName)) {
        return 'span-name-malformed';
    }

    $parts = \explode('.', $spanName);
    $operation = $parts[1] ?? '';

    if (coretsia_observability_span_naming_gate_operation_looks_plural($operation)) {
        return 'span-name-plural-operation';
    }

    return null;
}

function coretsia_observability_span_naming_gate_operation_looks_plural(string $operation): bool
{
    return \str_ends_with($operation, 's')
        && !\str_ends_with($operation, 'ss')
        && !\str_ends_with($operation, 'us');
}

/**
 * @param list<array<int,mixed>> $args
 */
function coretsia_observability_span_naming_gate_contains_named_span_argument(array $args): bool
{
    foreach ($args as $arg) {
        if (coretsia_observability_span_naming_gate_is_named_argument($arg)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int,mixed> $arg
 */
function coretsia_observability_span_naming_gate_is_named_argument(array $arg): bool
{
    $meaningful = coretsia_observability_span_naming_gate_meaningful_tokens($arg);

    return \count($meaningful) >= 2
        && coretsia_observability_span_naming_gate_is_token($meaningful[0], T_STRING)
        && $meaningful[1] === ':';
}

/**
 * @param array<int,mixed> $tokens
 * @return list<array<int,mixed>>
 */
function coretsia_observability_span_naming_gate_split_top_level(array $tokens, string $delimiter): array
{
    $parts = [];
    $current = [];
    $depth = 0;

    foreach ($tokens as $token) {
        if ($token === '(' || $token === '[' || $token === '{') {
            $depth++;
        } elseif ($token === ')' || $token === ']' || $token === '}') {
            $depth = \max(0, $depth - 1);
        }

        if ($depth === 0 && $token === $delimiter) {
            $parts[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    $parts[] = $current;
    return $parts;
}

/**
 * @param array<int,mixed> $segment
 * @param array<string,string> $uses
 */
function coretsia_observability_span_naming_gate_segment_declares_tracer_type(
    array $segment,
    array $uses,
    string $namespace,
): bool {
    $names = [];

    foreach ($segment as $token) {
        if (!\is_array($token)) {
            continue;
        }

        if (!coretsia_observability_span_naming_gate_is_name_token_id((int)$token[0])) {
            continue;
        }

        $names[] = (string)$token[1];
    }

    foreach ($names as $name) {
        if (coretsia_observability_span_naming_gate_resolves_to_tracer_port($name, $uses, $namespace)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string,string> $uses
 */
function coretsia_observability_span_naming_gate_resolves_to_tracer_port(
    string $name,
    array $uses,
    string $namespace,
): bool {
    $normalized = \trim($name, '\\?');
    if ($normalized === '') {
        return false;
    }

    $target = 'Coretsia\\Contracts\\Observability\\Tracing\\TracerPortInterface';

    if ($normalized === $target) {
        return true;
    }

    if ($normalized === 'TracerPortInterface') {
        return isset($uses['TracerPortInterface']) && $uses['TracerPortInterface'] === $target;
    }

    $parts = \explode('\\', $normalized);
    $head = $parts[0] ?? '';
    if ($head !== '' && isset($uses[$head])) {
        $tail = \array_slice($parts, 1);
        $resolved = $uses[$head] . ($tail === [] ? '' : '\\' . \implode('\\', $tail));
        return $resolved === $target;
    }

    if ($namespace !== '') {
        return $namespace . '\\' . $normalized === $target;
    }

    return false;
}

function coretsia_observability_span_naming_gate_decode_string_literal(string $literal): string
{
    $len = \strlen($literal);
    if ($len < 2) {
        return $literal;
    }

    $quote = $literal[0];
    if (($quote !== "'" && $quote !== '"') || $literal[$len - 1] !== $quote) {
        return $literal;
    }

    $inner = \substr($literal, 1, -1);
    if ($quote === "'") {
        return \str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
    }

    return \stripcslashes($inner);
}

/**
 * @param array<int,mixed> $tokens
 * @return array<int,mixed>
 */
function coretsia_observability_span_naming_gate_statement_segment(array $tokens, int $index): array
{
    $start = $index;
    for ($i = $index; $i >= 0; $i--) {
        if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}') {
            $start = $i + 1;
            break;
        }

        if ($i === 0) {
            $start = 0;
        }
    }

    $end = $index;
    $count = \count($tokens);
    for ($i = $index; $i < $count; $i++) {
        if ($tokens[$i] === ';') {
            $end = $i;
            break;
        }

        if ($i === $count - 1) {
            $end = $count - 1;
        }
    }

    return \array_slice($tokens, $start, $end - $start + 1);
}

/**
 * @param array<int,mixed> $tokens
 */
function coretsia_observability_span_naming_gate_find_next_symbol(array $tokens, int $start, string $symbol): ?int
{
    $count = \count($tokens);

    for ($i = $start + 1; $i < $count; $i++) {
        if ($tokens[$i] === $symbol) {
            return $i;
        }
    }

    return null;
}

/**
 * @param array<int,mixed> $tokens
 */
function coretsia_observability_span_naming_gate_find_matching_symbol(
    array $tokens,
    int $open,
    string $openSymbol,
    string $closeSymbol,
): ?int {
    $depth = 0;
    $count = \count($tokens);

    for ($i = $open; $i < $count; $i++) {
        if ($tokens[$i] === $openSymbol) {
            $depth++;
            continue;
        }

        if ($tokens[$i] === $closeSymbol) {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

/**
 * @param array<int,mixed> $tokens
 */
function coretsia_observability_span_naming_gate_find_next_token_id(array $tokens, int $start, int $id): ?int
{
    $count = \count($tokens);

    for ($i = $start + 1; $i < $count; $i++) {
        if (coretsia_observability_span_naming_gate_is_token($tokens[$i], $id)) {
            return $i;
        }

        if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '(') {
            return null;
        }
    }

    return null;
}

/**
 * @param array<int,mixed> $tokens
 */
function coretsia_observability_span_naming_gate_next_meaningful_index(array $tokens, int $start): ?int
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
 * @param array<int,mixed> $tokens
 * @return list<mixed>
 */
function coretsia_observability_span_naming_gate_meaningful_tokens(array $tokens): array
{
    $out = [];

    foreach ($tokens as $token) {
        if (\is_array($token)
            && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
            continue;
        }

        $out[] = $token;
    }

    return $out;
}

/**
 * @param array<int,mixed> $tokens
 */
function coretsia_observability_span_naming_gate_previous_meaningful_token_is(array $tokens, int $index, int $id): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (\is_array(
            $token
        ) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
            continue;
        }

        return coretsia_observability_span_naming_gate_is_token($token, $id);
    }

    return false;
}

/**
 * @param array<int,mixed> $segment
 */
function coretsia_observability_span_naming_gate_segment_contains_token(array $segment, int $id): bool
{
    foreach ($segment as $token) {
        if (coretsia_observability_span_naming_gate_is_token($token, $id)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int,mixed> $segment
 */
function coretsia_observability_span_naming_gate_segment_has_visibility(array $segment): bool
{
    return coretsia_observability_span_naming_gate_segment_contains_token($segment, T_PRIVATE)
        || coretsia_observability_span_naming_gate_segment_contains_token($segment, T_PROTECTED)
        || coretsia_observability_span_naming_gate_segment_contains_token($segment, T_PUBLIC);
}

function coretsia_observability_span_naming_gate_is_runtime_type_declaration(mixed $token): bool
{
    return coretsia_observability_span_naming_gate_is_token($token, T_CLASS)
        || coretsia_observability_span_naming_gate_is_token($token, T_TRAIT)
        || (
            \defined('T_ENUM')
            && coretsia_observability_span_naming_gate_is_token($token, (int)\constant('T_ENUM'))
        );
}

function coretsia_observability_span_naming_gate_is_object_operator(mixed $token): bool
{
    return coretsia_observability_span_naming_gate_is_token($token, T_OBJECT_OPERATOR)
        || (
            \defined('T_NULLSAFE_OBJECT_OPERATOR')
            && coretsia_observability_span_naming_gate_is_token($token, (int)\constant('T_NULLSAFE_OBJECT_OPERATOR'))
        );
}

function coretsia_observability_span_naming_gate_is_name_token_id(int $id): bool
{
    return $id === T_STRING
        || (\defined('T_NAME_QUALIFIED') && $id === (int)\constant('T_NAME_QUALIFIED'))
        || (\defined('T_NAME_FULLY_QUALIFIED') && $id === (int)\constant('T_NAME_FULLY_QUALIFIED'))
        || (\defined('T_NAME_RELATIVE') && $id === (int)\constant('T_NAME_RELATIVE'));
}

function coretsia_observability_span_naming_gate_is_token(mixed $token, int $id): bool
{
    return \is_array($token) && isset($token[0]) && $token[0] === $id;
}

function coretsia_observability_span_naming_gate_token_text_lower(mixed $token): string
{
    if (\is_array($token) && isset($token[1])) {
        return \strtolower((string)$token[1]);
    }

    return \strtolower((string)$token);
}

/**
 * @param list<string> $items
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_unique_sorted(array $items): array
{
    $items = \array_values(\array_unique($items));
    \sort($items, \SORT_STRING);

    return $items;
}
