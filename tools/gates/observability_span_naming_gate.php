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
use Coretsia\Tools\Support\MarkdownSectionReader;
use Coretsia\Tools\Support\PhpSourceFinder;
use Coretsia\Tools\Support\PhpTokenStream;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT',
    'CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_observability_span_naming_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $observabilityFile = $repository->resolveExistingFile('docs/ssot/observability.md');
    $markdown = DeterministicFile::readTextNormalizedEol($observabilityFile);

    /** @var list<string> $violations */
    $violations = [];

    foreach (coretsia_observability_span_naming_gate_parse_span_policy($markdown) as $diagnostic) {
        $violations[] = 'docs/ssot/observability.md: ' . $diagnostic;
    }

    if ($violations === []) {
        foreach (
            coretsia_observability_span_naming_gate_collect_php_source_files(
                $repository,
                $catalog,
                $scanRoot,
            ) as $file
        ) {
            $relativePath = $repository->relativeToRepo($file);

            foreach (
                coretsia_observability_span_naming_gate_scan_php_file(
                    $file,
                    $relativePath,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }
    }

    return coretsia_observability_span_naming_gate_unique_sorted($violations);
}

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_collect_php_source_files(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $excludedSegments = [
        'docs',
        'tests',
        'tools',
        'var',
        'fixtures',
        'vendor',
    ];
    $canonicalRoots = [];

    foreach ($catalog->all() as $product) {
        $src = $product['absolutePath'] . '/src';

        if (!is_dir($src)) {
            continue;
        }

        $canonicalRoots[] = $repository->resolveExistingDirectory($src);
    }

    $roots = GateRuntime::selectScanRoots(
        $scanRoot,
        $canonicalRoots,
        $excludedSegments,
    );

    return PhpSourceFinder::findMany($roots, $excludedSegments);
}

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_parse_span_policy(string $markdown): array
{
    $section = MarkdownSectionReader::section(
        $markdown,
        '### Spans',
    );
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

/**
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan_php_file(
    string $absPath,
    string $relativePath,
): array {
    $stream = PhpTokenStream::fromParsedFile($absPath);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($stream->classLikes() as $classInfo) {
        if ($classInfo['anonymous'] || $classInfo['kind'] === 'interface') {
            continue;
        }

        $analysis = coretsia_observability_span_naming_gate_analyze_class(
            $stream,
            $classInfo['body_start'],
            $classInfo['body_end'],
        );

        foreach (
            coretsia_observability_span_naming_gate_scan_class_methods(
                $stream,
                $classInfo['body_start'],
                $classInfo['body_end'],
                $analysis,
            ) as $line
        ) {
            $diagnostics[] = $line;
        }

        $tokens = $stream->tokens();

        foreach (
            $stream->propertyHooks(
                $classInfo['body_start'],
                $classInfo['body_end'],
            ) as $hook
        ) {
            /** @var array<string,true> $hookTracerVars */
            $hookTracerVars = [];

            if ($hook['param_start'] !== null && $hook['param_end'] !== null) {
                $params = \array_slice(
                    $tokens,
                    $hook['param_start'] + 1,
                    $hook['param_end'] - $hook['param_start'] - 1,
                );

                foreach (
                    coretsia_observability_span_naming_gate_parse_params(
                        $params,
                        $stream,
                        false,
                    ) as $var => $isTracer
                ) {
                    if ($isTracer) {
                        $hookTracerVars[$var] = true;
                    }
                }
            } elseif (
                $hook['name'] === 'set'
                && isset($analysis['tracerProperties'][$hook['property_name']])
            ) {
                $hookTracerVars['value'] = true;
            }

            $body = \array_slice(
                $tokens,
                $hook['body_start'] + 1,
                $hook['body_end'] - $hook['body_start'] - 1,
            );

            foreach (
                coretsia_observability_span_naming_gate_scan_method_body(
                    $body,
                    $analysis,
                    $hookTracerVars,
                ) as $line
            ) {
                $diagnostics[] = $line;
            }
        }
    }

    $violations = [];

    foreach (coretsia_observability_span_naming_gate_unique_sorted($diagnostics) as $diagnostic) {
        $violations[] = $relativePath . ': ' . $diagnostic;
    }

    return $violations;
}

/**
 * @return array{
 *     tracerProperties:array<string,true>,
 *     privateStringConsts:array<string,string>
 * }
 */
function coretsia_observability_span_naming_gate_analyze_class(
    PhpTokenStream $stream,
    int $bodyStart,
    int $bodyEnd,
): array {
    $tokens = $stream->tokens();

    /** @var array<string,true> $tracerProperties */
    $tracerProperties = [];

    /** @var array<string,string> $privateStringConsts */
    $privateStringConsts = [];

    $depth = 0;

    for ($i = $bodyStart + 1; $i < $bodyEnd; $i++) {
        $token = $tokens[$i];

        if ($token === '(' || $token === '[' || PhpTokenStream::isCurlyOpenToken($token)) {
            $depth++;
            continue;
        }

        if ($token === ')' || $token === ']' || $token === '}') {
            $depth = \max(0, $depth - 1);
            continue;
        }

        if ($depth !== 0) {
            continue;
        }

        if (PhpTokenStream::isToken($token, T_CONST)) {
            $segment = PhpTokenStream::statementSegment($tokens, $i);

            foreach (PhpTokenStream::parsePrivateStringConstants($segment) as $name => $value) {
                $privateStringConsts[$name] = $value;
            }

            continue;
        }

        if (!\is_array($token) || $token[0] !== T_VARIABLE) {
            continue;
        }

        $segment = PhpTokenStream::statementSegment($tokens, $i);

        if (PhpTokenStream::segmentContainsToken($segment, T_FUNCTION)) {
            continue;
        }

        if (
            coretsia_observability_span_naming_gate_segment_declares_tracer_type(
                $segment,
                $stream,
            )
        ) {
            $tracerProperties[\ltrim((string) $token[1], '$')] = true;
        }
    }

    foreach ($stream->methods($bodyStart, $bodyEnd) as $method) {
        if (
            $method['name'] !== '__construct'
            || $method['param_start'] === null
            || $method['param_end'] === null
        ) {
            continue;
        }

        $params = \array_slice(
            $tokens,
            $method['param_start'] + 1,
            $method['param_end'] - $method['param_start'] - 1,
        );

        foreach (
            coretsia_observability_span_naming_gate_parse_params(
                $params,
                $stream,
                true,
            ) as $var => $isTracer
        ) {
            if ($isTracer) {
                $tracerProperties[$var] = true;
            }
        }
    }

    return [
        'tracerProperties' => $tracerProperties,
        'privateStringConsts' => $privateStringConsts,
    ];
}

/**
 * @param array{
 *     tracerProperties:array<string,true>,
 *     privateStringConsts:array<string,string>
 * } $classInfo
 *
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_scan_class_methods(
    PhpTokenStream $stream,
    int $bodyStart,
    int $bodyEnd,
    array $classInfo,
): array {
    $tokens = $stream->tokens();

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($stream->methods($bodyStart, $bodyEnd) as $method) {
        if (
            $method['param_start'] === null
            || $method['param_end'] === null
            || $method['body_start'] === null
            || $method['body_end'] === null
        ) {
            continue;
        }

        $params = \array_slice(
            $tokens,
            $method['param_start'] + 1,
            $method['param_end'] - $method['param_start'] - 1,
        );
        $body = \array_slice(
            $tokens,
            $method['body_start'] + 1,
            $method['body_end'] - $method['body_start'] - 1,
        );

        /** @var array<string,true> $methodTracerVars */
        $methodTracerVars = [];

        foreach (
            coretsia_observability_span_naming_gate_parse_params(
                $params,
                $stream,
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
    }

    return coretsia_observability_span_naming_gate_unique_sorted($diagnostics);
}

/**
 * @param array<int,mixed> $params
 *
 * @return array<string,bool>
 */
function coretsia_observability_span_naming_gate_parse_params(
    array $params,
    PhpTokenStream $stream,
    bool $requirePromoted,
): array {
    $out = [];

    foreach (PhpTokenStream::splitTopLevel($params, ',') as $segment) {
        $var = null;

        foreach ($segment as $token) {
            if (\is_array($token) && $token[0] === T_VARIABLE) {
                $var = \ltrim((string) $token[1], '$');
                break;
            }
        }

        if ($var === null || $var === '') {
            continue;
        }

        if ($requirePromoted && !PhpTokenStream::segmentHasVisibility($segment)) {
            continue;
        }

        $out[$var] = coretsia_observability_span_naming_gate_segment_declares_tracer_type(
            $segment,
            $stream,
        );
    }

    return $out;
}

/**
 * @param array<int,mixed> $body
 * @param array{tracerProperties:array<string,true>, privateStringConsts:array<string,string>} $classInfo
 * @param array<string,true> $methodTracerVars
 *
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

        $openParen = PhpTokenStream::findNextSymbolAfter($body, $call['methodIndex'], '(');
        if ($openParen === null) {
            continue;
        }

        $closeParen = PhpTokenStream::findMatchingPairIn($body, $openParen, '(', ')');
        if ($closeParen === null) {
            $diagnostics[] = 'span-call-arguments-unparseable';
            continue;
        }

        $args = PhpTokenStream::splitTopLevel(
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
 *
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

    $var = \ltrim((string) $token[1], '$');
    $next = PhpTokenStream::nextSignificantIndex($tokens, $i + 1);
    if ($next === null || !PhpTokenStream::isObjectOperator($tokens[$next])) {
        return null;
    }

    $nameIndex = PhpTokenStream::nextSignificantIndex($tokens, $next + 1);
    if ($nameIndex === null || !PhpTokenStream::isToken($tokens[$nameIndex], T_STRING)) {
        return null;
    }

    if ($var === 'this') {
        $property = (string) $tokens[$nameIndex][1];
        if (!isset($tracerProperties[$property])) {
            return null;
        }

        $secondOp = PhpTokenStream::nextSignificantIndex($tokens, $nameIndex + 1);
        if ($secondOp === null || !PhpTokenStream::isObjectOperator($tokens[$secondOp])) {
            return null;
        }

        $methodIndex = PhpTokenStream::nextSignificantIndex($tokens, $secondOp + 1);
        if ($methodIndex === null || !PhpTokenStream::isToken(
            $tokens[$methodIndex],
            T_STRING
        )) {
            return null;
        }

        if (!coretsia_observability_span_naming_gate_is_span_name_method((string) $tokens[$methodIndex][1])) {
            return null;
        }

        return ['methodIndex' => $methodIndex];
    }

    if (!isset($methodTracerVars[$var])) {
        return null;
    }

    if (!coretsia_observability_span_naming_gate_is_span_name_method((string) $tokens[$nameIndex][1])) {
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
    $meaningful = PhpTokenStream::significantTokens($arg);

    if (\count($meaningful) === 1 && PhpTokenStream::isToken(
        $meaningful[0],
        T_CONSTANT_ENCAPSED_STRING,
    )) {
        return PhpTokenStream::decodeStringLiteral((string) $meaningful[0][1]);
    }

    if (\count($meaningful) === 3
        && PhpTokenStream::tokenTextLower($meaningful[0]) === 'self'
        && PhpTokenStream::isToken($meaningful[1], T_DOUBLE_COLON)
        && PhpTokenStream::isToken($meaningful[2], T_STRING)) {
        $constName = (string) $meaningful[2][1];
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
        if (PhpTokenStream::isNamedArgument($arg)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int,mixed> $segment
 */
function coretsia_observability_span_naming_gate_segment_declares_tracer_type(
    array $segment,
    PhpTokenStream $stream,
): bool {
    $target = 'Coretsia\\Contracts\\Observability\\Tracing\\TracerPortInterface';

    foreach ($segment as $token) {
        if (!\is_array($token) || !PhpTokenStream::isNameTokenId((int) $token[0])) {
            continue;
        }

        if (\strcasecmp($stream->resolveClassName((string) $token[1]), $target) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $items
 *
 * @return list<string>
 */
function coretsia_observability_span_naming_gate_unique_sorted(array $items): array
{
    $items = \array_values(\array_unique($items));
    \sort($items, \SORT_STRING);

    return $items;
}
