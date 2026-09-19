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
    'CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT',
    'CORETSIA_OBSERVABILITY_METRIC_CATALOG_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_observability_metric_catalog_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $packageCatalog,
    ?string $scanRoot,
): array {
    $observabilityFile = $repository->resolveExistingFile('docs/ssot/observability.md');
    $markdown = DeterministicFile::readTextNormalizedEol($observabilityFile);

    $catalogResult = coretsia_observability_metric_catalog_gate_parse_catalog($markdown);
    $allowlistResult = coretsia_observability_metric_catalog_gate_parse_global_label_allowlist($markdown);

    /** @var list<string> $violations */
    $violations = [];

    foreach ($allowlistResult['diagnostics'] as $line) {
        $violations[] = 'docs/ssot/observability.md: ' . $line;
    }

    foreach ($catalogResult['diagnostics'] as $line) {
        $violations[] = 'docs/ssot/observability.md: ' . $line;
    }

    $catalog = $catalogResult['catalog'];
    $allowlist = $allowlistResult['labels'];

    if ($catalog !== [] && $allowlist !== []) {
        foreach ($catalog as $row) {
            foreach ($row['labels'] as $label) {
                if (!isset($allowlist[$label])) {
                    $violations[] = 'docs/ssot/observability.md: catalog-label-not-allowlisted';
                }
            }
        }
    }

    if ($violations === []) {
        foreach (
            coretsia_observability_metric_catalog_gate_collect_php_source_files(
                $repository,
                $packageCatalog,
                $scanRoot,
            ) as $file
        ) {
            $relativePath = $repository->relativeToRepo($file);

            foreach (
                coretsia_observability_metric_catalog_gate_scan_php_file(
                    $file,
                    $relativePath,
                    $catalog,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }
    }

    return coretsia_observability_metric_catalog_gate_unique_sorted($violations);
}

/**
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_collect_php_source_files(
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
 * @return array{labels:array<string,true>, diagnostics:list<string>}
 */
function coretsia_observability_metric_catalog_gate_parse_global_label_allowlist(string $markdown): array
{
    $section = MarkdownSectionReader::section(
        $markdown,
        '## Label Allowlist (MUST)',
    );
    if ($section === null) {
        return ['labels' => [], 'diagnostics' => ['label-allowlist-unparseable']];
    }

    $lines = \preg_split('/\R/u', $section);
    if (!\is_array($lines)) {
        return ['labels' => [], 'diagnostics' => ['label-allowlist-unparseable']];
    }

    $labels = [];
    $collect = false;
    foreach ($lines as $line) {
        $trimmed = \trim($line);
        if (\str_starts_with($trimmed, 'The reserved baseline allowlist')) {
            $collect = true;
            continue;
        }
        if ($collect && \str_starts_with($trimmed, '### ')) {
            break;
        }
        if (!$collect) {
            continue;
        }
        if (\preg_match('/^-\s+`([^`]+)`\s*$/', $trimmed, $m) === 1) {
            $label = (string) $m[1];
            $labels[$label] = true;
        }
    }

    if ($labels === []) {
        return ['labels' => [], 'diagnostics' => ['label-allowlist-unparseable']];
    }

    return ['labels' => $labels, 'diagnostics' => []];
}

/**
 * @return array{catalog:array<string,array{owner:string,type:string,labels:list<string>}>, diagnostics:list<string>}
 */
function coretsia_observability_metric_catalog_gate_parse_catalog(string $markdown): array
{
    $section = MarkdownSectionReader::section(
        $markdown,
        '## Canonical metrics catalog',
    );
    if ($section === null) {
        return ['catalog' => [], 'diagnostics' => ['canonical-metrics-catalog-missing']];
    }

    $lines = \preg_split('/\R/u', $section);
    if (!\is_array($lines)) {
        return ['catalog' => [], 'diagnostics' => ['canonical-metrics-catalog-unparseable']];
    }

    $headerSeen = false;
    $catalog = [];
    $diagnostics = [];

    foreach ($lines as $line) {
        $trimmed = \trim($line);
        if ($trimmed === '' || !\str_starts_with($trimmed, '|')) {
            continue;
        }

        $cells = coretsia_observability_metric_catalog_gate_markdown_table_cells($trimmed);
        if (\count($cells) < 4) {
            continue;
        }

        $normalizedCells = \array_map(
            static fn (string $cell): string => \strtolower(\trim(\str_replace('`', '', $cell))),
            $cells
        );
        if (!$headerSeen) {
            if ($normalizedCells[0] === 'metric name'
                && $normalizedCells[1] === 'owner'
                && $normalizedCells[2] === 'type'
                && $normalizedCells[3] === 'labels') {
                $headerSeen = true;
            }
            continue;
        }

        if (\preg_match('/^[-: ]+$/', \str_replace('|', '', $trimmed)) === 1) {
            continue;
        }

        $metricName = coretsia_observability_metric_catalog_gate_unbacktick($cells[0]);
        $owner = coretsia_observability_metric_catalog_gate_unbacktick($cells[1]);
        $type = coretsia_observability_metric_catalog_gate_unbacktick($cells[2]);
        $labels = coretsia_observability_metric_catalog_gate_parse_catalog_labels_cell($cells[3]);

        if ($metricName === '' || $owner === '' || $type === '') {
            $diagnostics[] = 'canonical-metrics-catalog-row-unparseable';
            continue;
        }

        if (isset($catalog[$metricName])) {
            $diagnostics[] = 'canonical-metrics-catalog-duplicate-metric';
            continue;
        }

        if ($type !== 'counter' && $type !== 'observe') {
            $diagnostics[] = 'canonical-metrics-catalog-unsupported-type';
        }

        $catalog[$metricName] = [
            'owner' => $owner,
            'type' => $type,
            'labels' => $labels,
        ];
    }

    if (!$headerSeen || $catalog === []) {
        $diagnostics[] = 'canonical-metrics-catalog-unparseable';
    }

    return [
        'catalog' => $catalog,
        'diagnostics' => coretsia_observability_metric_catalog_gate_unique_sorted($diagnostics)
    ];
}

/**
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_markdown_table_cells(string $line): array
{
    $trimmed = \trim($line);
    $trimmed = \trim($trimmed, '|');
    $parts = \explode('|', $trimmed);

    $cells = [];
    foreach ($parts as $part) {
        $cells[] = \trim($part);
    }

    return $cells;
}

function coretsia_observability_metric_catalog_gate_unbacktick(string $value): string
{
    $value = \trim($value);
    if (\str_starts_with($value, '`') && \str_ends_with($value, '`') && \strlen($value) >= 2) {
        return \substr($value, 1, -1);
    }

    return \trim(\str_replace('`', '', $value));
}

/**
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_parse_catalog_labels_cell(string $cell): array
{
    $cell = \trim($cell);
    if ($cell === '' || $cell === '-' || \strtolower($cell) === 'none') {
        return [];
    }

    if (\preg_match_all('/`([^`]+)`/', $cell, $m) >= 1) {
        $labels = [];
        foreach ($m[1] as $label) {
            $label = \trim((string) $label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        \usort($labels, static fn (string $a, string $b): int => \strcmp($a, $b));
        return \array_values(\array_unique($labels));
    }

    $raw = \str_replace(',', ' ', $cell);
    $parts = \preg_split('/\s+/', $raw);
    if (!\is_array($parts)) {
        return [];
    }

    $labels = [];
    foreach ($parts as $part) {
        $label = \trim($part);
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    \usort($labels, static fn (string $a, string $b): int => \strcmp($a, $b));
    return \array_values(\array_unique($labels));
}

/**
 * @param array<string,array{owner:string,type:string,labels:list<string>}> $catalog
 *
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_scan_php_file(
    string $absPath,
    string $relativePath,
    array $catalog,
): array {
    $stream = PhpTokenStream::fromParsedFile($absPath);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($stream->classLikes() as $classInfo) {
        if ($classInfo['anonymous'] || $classInfo['kind'] === 'interface') {
            continue;
        }

        $analysis = coretsia_observability_metric_catalog_gate_analyze_class(
            $stream,
            $classInfo['body_start'],
            $classInfo['body_end'],
        );

        foreach (
            coretsia_observability_metric_catalog_gate_scan_class_methods(
                $stream,
                $classInfo['body_start'],
                $classInfo['body_end'],
                $analysis,
                $catalog,
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
            /** @var array<string,true> $hookMeterVars */
            $hookMeterVars = [];

            if ($hook['param_start'] !== null && $hook['param_end'] !== null) {
                $params = \array_slice(
                    $tokens,
                    $hook['param_start'] + 1,
                    $hook['param_end'] - $hook['param_start'] - 1,
                );

                foreach (
                    coretsia_observability_metric_catalog_gate_parse_params(
                        $params,
                        $stream,
                        false,
                    ) as $var => $isMeter
                ) {
                    if ($isMeter) {
                        $hookMeterVars[$var] = true;
                    }
                }
            } elseif (
                $hook['name'] === 'set'
                && isset($analysis['meterProperties'][$hook['property_name']])
            ) {
                $hookMeterVars['value'] = true;
            }

            $body = \array_slice(
                $tokens,
                $hook['body_start'] + 1,
                $hook['body_end'] - $hook['body_start'] - 1,
            );

            foreach (
                coretsia_observability_metric_catalog_gate_scan_method_body(
                    $body,
                    $analysis,
                    $hookMeterVars,
                    $catalog,
                ) as $line
            ) {
                $diagnostics[] = $line;
            }
        }
    }

    $violations = [];

    foreach (coretsia_observability_metric_catalog_gate_unique_sorted($diagnostics) as $diagnostic) {
        $violations[] = $relativePath . ': ' . $diagnostic;
    }

    return $violations;
}

/**
 * @return array{
 *     meterProperties:array<string,true>,
 *     privateStringConsts:array<string,string>
 * }
 */
function coretsia_observability_metric_catalog_gate_analyze_class(
    PhpTokenStream $stream,
    int $bodyStart,
    int $bodyEnd,
): array {
    $tokens = $stream->tokens();

    /** @var array<string,true> $meterProperties */
    $meterProperties = [];

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
            coretsia_observability_metric_catalog_gate_segment_declares_meter_type(
                $segment,
                $stream,
            )
        ) {
            $meterProperties[\ltrim((string) $token[1], '$')] = true;
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
            coretsia_observability_metric_catalog_gate_parse_params(
                $params,
                $stream,
                true,
            ) as $var => $isMeter
        ) {
            if ($isMeter) {
                $meterProperties[$var] = true;
            }
        }
    }

    return [
        'meterProperties' => $meterProperties,
        'privateStringConsts' => $privateStringConsts,
    ];
}

/**
 * @param array{
 *     meterProperties:array<string,true>,
 *     privateStringConsts:array<string,string>
 * } $classInfo
 * @param array<string,array{owner:string,type:string,labels:list<string>}> $catalog
 *
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_scan_class_methods(
    PhpTokenStream $stream,
    int $bodyStart,
    int $bodyEnd,
    array $classInfo,
    array $catalog,
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

        /** @var array<string,true> $methodMeterVars */
        $methodMeterVars = [];

        foreach (
            coretsia_observability_metric_catalog_gate_parse_params(
                $params,
                $stream,
                false,
            ) as $var => $isMeter
        ) {
            if ($isMeter) {
                $methodMeterVars[$var] = true;
            }
        }

        foreach (
            coretsia_observability_metric_catalog_gate_scan_method_body(
                $body,
                $classInfo,
                $methodMeterVars,
                $catalog,
            ) as $line
        ) {
            $diagnostics[] = $line;
        }
    }

    return coretsia_observability_metric_catalog_gate_unique_sorted($diagnostics);
}

/**
 * @param array<int,mixed> $params
 *
 * @return array<string,bool>
 */
function coretsia_observability_metric_catalog_gate_parse_params(
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

        $out[$var] = coretsia_observability_metric_catalog_gate_segment_declares_meter_type(
            $segment,
            $stream,
        );
    }

    return $out;
}

/**
 * @param array<int,mixed> $body
 *
 * @return array{var:string,map:array{resolvable:bool,keys:list<string>}}|null
 */
function coretsia_observability_metric_catalog_gate_capture_local_label_map_assignment(array $body, int $index): ?array
{
    $token = $body[$index] ?? null;
    if (!\is_array($token) || $token[0] !== T_VARIABLE) {
        return null;
    }

    $var = \ltrim((string) $token[1], '$');
    if ($var === '') {
        return null;
    }

    $next = PhpTokenStream::nextSignificantIndex($body, $index + 1);
    if ($next === null || $body[$next] !== '=') {
        return null;
    }

    $end = PhpTokenStream::findStatementEnd($body, $next + 1);
    if ($end === null) {
        return [
            'var' => $var,
            'map' => [
                'resolvable' => false,
                'keys' => [],
            ],
        ];
    }

    $expr = \array_slice($body, $next + 1, $end - $next - 1);
    $keys = coretsia_observability_metric_catalog_gate_resolve_array_literal_keys($expr);

    if ($keys === null) {
        return [
            'var' => $var,
            'map' => [
                'resolvable' => false,
                'keys' => [],
            ],
        ];
    }

    return [
        'var' => $var,
        'map' => [
            'resolvable' => true,
            'keys' => $keys,
        ],
    ];
}

/**
 * @param array<int,mixed> $body
 * @param array{meterProperties:array<string,true>, privateStringConsts:array<string,string>} $classInfo
 * @param array<string,true> $methodMeterVars
 * @param array<string,array{owner:string,type:string,labels:list<string>}> $catalog
 *
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_scan_method_body(
    array $body,
    array $classInfo,
    array $methodMeterVars,
    array $catalog
): array {
    $diagnostics = [];

    /** @var array<string,array{resolvable:bool,keys:list<string>}> $localLabelMaps */
    $localLabelMaps = [];

    $count = \count($body);

    for ($i = 0; $i < $count; $i++) {
        $assignment = coretsia_observability_metric_catalog_gate_capture_local_label_map_assignment($body, $i);
        if ($assignment !== null) {
            $localLabelMaps[$assignment['var']] = $assignment['map'];
        }

        $call = coretsia_observability_metric_catalog_gate_detect_meter_call(
            $body,
            $i,
            $classInfo['meterProperties'],
            $methodMeterVars
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
            $diagnostics[] = 'meter-call-arguments-unparseable';
            continue;
        }

        $args = PhpTokenStream::splitTopLevel(
            \array_slice($body, $openParen + 1, $closeParen - $openParen - 1),
            ','
        );

        if (coretsia_observability_metric_catalog_gate_contains_named_meter_argument($args)) {
            $diagnostics[] = 'meter-call-arguments-unparseable';
            $i = $closeParen;
            continue;
        }

        $metric = coretsia_observability_metric_catalog_gate_resolve_metric_name_arg(
            $args[0] ?? [],
            $classInfo['privateStringConsts']
        );

        if ($metric === null) {
            $diagnostics[] = 'metric-name-unresolvable';
            $i = $closeParen;
            continue;
        }

        if (!isset($catalog[$metric])) {
            $diagnostics[] = 'metric-name-not-in-catalog';
            $i = $closeParen;
            continue;
        }

        $expectedType = $catalog[$metric]['type'];
        if ($call['method'] === 'increment' && $expectedType !== 'counter') {
            $diagnostics[] = 'metric-method-type-mismatch';
        }

        if ($call['method'] === 'observe' && $expectedType !== 'observe') {
            $diagnostics[] = 'metric-method-type-mismatch';
        }

        $labelArg = $args[2] ?? null;
        $labelKeys = [];

        if ($labelArg !== null) {
            $labelKeys = coretsia_observability_metric_catalog_gate_resolve_label_keys_arg($labelArg, $localLabelMaps);
            if ($labelKeys === null) {
                $diagnostics[] = 'metric-label-map-unresolvable';
                $i = $closeParen;
                continue;
            }
        }

        $allowed = [];
        foreach ($catalog[$metric]['labels'] as $label) {
            $allowed[$label] = true;
        }

        foreach ($labelKeys as $key) {
            if (!isset($allowed[$key])) {
                $diagnostics[] = 'metric-label-key-not-in-catalog';
            }
        }

        $i = $closeParen;
    }

    return coretsia_observability_metric_catalog_gate_unique_sorted($diagnostics);
}

/**
 * @param array<int,mixed> $tokens
 * @param array<string,true> $meterProperties
 * @param array<string,true> $methodMeterVars
 *
 * @return array{method:string,methodIndex:int}|null
 */
function coretsia_observability_metric_catalog_gate_detect_meter_call(
    array $tokens,
    int $i,
    array $meterProperties,
    array $methodMeterVars
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
        if (!isset($meterProperties[$property])) {
            return null;
        }

        $secondOp = PhpTokenStream::nextSignificantIndex($tokens, $nameIndex + 1);
        if ($secondOp === null || !PhpTokenStream::isObjectOperator($tokens[$secondOp])) {
            return null;
        }
        $methodIndex = PhpTokenStream::nextSignificantIndex($tokens, $secondOp + 1);
        if ($methodIndex === null || !PhpTokenStream::isToken($tokens[$methodIndex], T_STRING)) {
            return null;
        }

        $method = (string) $tokens[$methodIndex][1];
        if ($method !== 'increment' && $method !== 'observe') {
            return null;
        }

        return ['method' => $method, 'methodIndex' => $methodIndex];
    }

    if (!isset($methodMeterVars[$var])) {
        return null;
    }

    $method = (string) $tokens[$nameIndex][1];
    if ($method !== 'increment' && $method !== 'observe') {
        return null;
    }

    return ['method' => $method, 'methodIndex' => $nameIndex];
}

/**
 * @param array<int,mixed> $arg
 * @param array<string,string> $privateStringConsts
 */
function coretsia_observability_metric_catalog_gate_resolve_metric_name_arg(
    array $arg,
    array $privateStringConsts
): ?string {
    $meaningful = PhpTokenStream::significantTokens($arg);
    if (\count($meaningful) === 1 && PhpTokenStream::isToken(
        $meaningful[0],
        T_CONSTANT_ENCAPSED_STRING
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

/**
 * @param array<int,mixed> $arg
 * @param array<string,array{resolvable:bool,keys:list<string>}> $localLabelMaps
 *
 * @return list<string>|null
 */
function coretsia_observability_metric_catalog_gate_resolve_label_keys_arg(array $arg, array $localLabelMaps): ?array
{
    $meaningful = PhpTokenStream::significantTokens($arg);
    if ($meaningful === []) {
        return [];
    }

    if (\count($meaningful) === 1 && PhpTokenStream::isToken($meaningful[0], T_VARIABLE)) {
        $var = \ltrim((string) $meaningful[0][1], '$');
        if (!isset($localLabelMaps[$var]) || !$localLabelMaps[$var]['resolvable']) {
            return null;
        }
        return $localLabelMaps[$var]['keys'];
    }

    return coretsia_observability_metric_catalog_gate_resolve_array_literal_keys($arg);
}

/**
 * @param array<int,mixed> $tokens
 *
 * @return list<string>|null
 */
function coretsia_observability_metric_catalog_gate_resolve_array_literal_keys(array $tokens): ?array
{
    $meaningful = PhpTokenStream::significantTokens($tokens);
    if ($meaningful === []) {
        return null;
    }

    $open = PhpTokenStream::nextSignificantIndex($meaningful, 0);
    if ($open === null || $meaningful[$open] !== '[') {
        return null;
    }

    $close = PhpTokenStream::findMatchingPairIn($meaningful, $open, '[', ']');
    if ($close === null || PhpTokenStream::nextSignificantIndex(
        $meaningful,
        $close + 1
    ) !== null) {
        return null;
    }

    $inner = \array_slice($meaningful, $open + 1, $close - $open - 1);
    if ($inner === []) {
        return [];
    }

    $entries = PhpTokenStream::splitTopLevel($inner, ',');
    $keys = [];
    foreach ($entries as $entry) {
        $entry = PhpTokenStream::significantTokens($entry);
        if ($entry === []) {
            continue;
        }
        if (PhpTokenStream::containsTopLevelSymbol($entry, '...')) {
            return null;
        }
        $arrow = PhpTokenStream::findTopLevelToken($entry, T_DOUBLE_ARROW);
        if ($arrow === null) {
            return null;
        }
        $keyTokens = PhpTokenStream::significantTokens(\array_slice($entry, 0, $arrow));
        if (\count($keyTokens) !== 1 || !PhpTokenStream::isToken(
            $keyTokens[0],
            T_CONSTANT_ENCAPSED_STRING
        )) {
            return null;
        }
        $keys[] = PhpTokenStream::decodeStringLiteral((string) $keyTokens[0][1]);
    }

    \usort($keys, static fn (string $a, string $b): int => \strcmp($a, $b));
    return \array_values(\array_unique($keys));
}

/**
 * @param list<array<int,mixed>> $args
 */
function coretsia_observability_metric_catalog_gate_contains_named_meter_argument(array $args): bool
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
function coretsia_observability_metric_catalog_gate_segment_declares_meter_type(
    array $segment,
    PhpTokenStream $stream,
): bool {
    $target = 'Coretsia\\Contracts\\Observability\\Metrics\\MeterPortInterface';

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
 * @param list<string> $values
 *
 * @return list<string>
 */
function coretsia_observability_metric_catalog_gate_unique_sorted(array $values): array
{
    $unique = [];
    foreach ($values as $value) {
        if (\is_string($value) && $value !== '') {
            $unique[$value] = true;
        }
    }

    $out = \array_keys($unique);
    \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

    return $out;
}
