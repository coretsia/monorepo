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
    'CORETSIA_OBSERVABILITY_NAMING_DRIFT',
    'CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_observability_naming_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_observability_naming_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $observabilityFile = $repository->resolveExistingFile('docs/ssot/observability.md');
    $markdown = DeterministicFile::readTextNormalizedEol($observabilityFile);
    $policy = coretsia_observability_naming_gate_parse_observability_policy($markdown);

    if ($policy['allowed'] === [] || $policy['forbidden'] === []) {
        throw new \RuntimeException('observability-policy-empty');
    }

    $files = coretsia_observability_naming_gate_collect_php_source_files(
        $repository,
        $catalog,
        $scanRoot,
    );

    /** @var list<string> $violations */
    $violations = [];

    foreach ($files as $file) {
        $relativePath = $repository->relativeToRepo($file);

        foreach (
            coretsia_observability_naming_gate_scan_php_file(
                $file,
                $relativePath,
                $policy['allowed'],
                $policy['forbidden'],
            ) as $violation
        ) {
            $violations[] = $violation;
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @return list<string>
 */
function coretsia_observability_naming_gate_collect_php_source_files(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $excludedSegments = ['tests', 'fixtures', 'vendor'];
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
 * @return array{allowed:array<string, true>, forbidden:array<string, true>}
 */
function coretsia_observability_naming_gate_parse_observability_policy(string $markdown): array
{
    $allowedSection = MarkdownSectionReader::section(
        $markdown,
        '## Label Allowlist (MUST)',
    );
    $forbiddenSection = MarkdownSectionReader::section(
        $markdown,
        '## Forbidden Label Keys (MUST)',
    );

    return [
        'allowed' => coretsia_observability_naming_gate_parse_label_section($allowedSection),
        'forbidden' => coretsia_observability_naming_gate_parse_label_section($forbiddenSection),
    ];
}

function coretsia_observability_naming_gate_parse_label_section(?string $section): array
{
    if ($section === null) {
        return [];
    }

    $lines = \preg_split('/\R/u', $section);

    if (!\is_array($lines)) {
        throw new \RuntimeException('observability-lines-invalid');
    }

    /** @var array<string, true> $labels */
    $labels = [];
    $collecting = false;

    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if (\preg_match('/^-\s+`([^`]+)`$/u', $trimmed, $m) === 1) {
            $collecting = true;
            $label = $m[1];

            if (!\preg_match('/^[a-z][a-z0-9_]*$/', $label)) {
                throw new \RuntimeException('observability-label-invalid');
            }

            $labels[$label] = true;
            continue;
        }

        if ($collecting && $trimmed !== '') {
            break;
        }
    }

    \ksort($labels, \SORT_STRING);

    return $labels;
}

/**
 * @param array<string, true> $allowedLabels
 * @param array<string, true> $forbiddenLabels
 *
 * @return list<string>
 */
function coretsia_observability_naming_gate_scan_php_file(
    string $absPath,
    string $relativePath,
    array $allowedLabels,
    array $forbiddenLabels,
): array {
    $stream = PhpTokenStream::fromFile($absPath);
    $tokens = $stream->tokens();

    /** @var list<string> $violations */
    $violations = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $value = PhpTokenStream::decodeStringLiteral($token[1]);

        if ($value === '') {
            continue;
        }

        $isMetricNameSlot = coretsia_observability_naming_gate_is_metric_name_slot($tokens, $i);

        if ($isMetricNameSlot) {
            if (!coretsia_observability_naming_gate_is_valid_metric_name($value)) {
                $violations[] = $relativePath . ': metric-name-invalid';
            }

            continue;
        }

        $isSpanNameSlot = coretsia_observability_naming_gate_is_span_name_slot($tokens, $i);

        if ($isSpanNameSlot) {
            if (!coretsia_observability_naming_gate_is_valid_span_name($value)) {
                $violations[] = $relativePath . ': span-name-invalid';
            }

            continue;
        }

        $isArrayKey = coretsia_observability_naming_gate_is_array_key_token($tokens, $i);

        if (!$isArrayKey) {
            if (
                isset($forbiddenLabels[$value])
                && coretsia_observability_naming_gate_is_observability_scalar_label_key_context(
                    $tokens,
                    $i,
                    $relativePath,
                )
            ) {
                $violations[] = $relativePath . ': forbidden-label-key';
            }

            if (
                !isset($forbiddenLabels[$value])
                && !isset($allowedLabels[$value])
                && coretsia_observability_naming_gate_is_observability_scalar_label_key_context(
                    $tokens,
                    $i,
                    $relativePath,
                )
            ) {
                $violations[] = $relativePath . ': label-key-not-allowlisted';
            }

            continue;
        }

        if (coretsia_observability_naming_gate_is_observability_structural_key($value)) {
            continue;
        }

        if (!coretsia_observability_naming_gate_is_explicit_observability_label_array_key(
            $tokens,
            $i,
            $relativePath,
        )) {
            continue;
        }

        if (isset($forbiddenLabels[$value])) {
            $violations[] = $relativePath . ': forbidden-label-key';
            continue;
        }

        if (!isset($allowedLabels[$value])) {
            $violations[] = $relativePath . ': label-key-not-allowlisted';
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_array_key_token(array $tokens, int $index): bool
{
    $nextIndex = PhpTokenStream::nextSignificantIndex($tokens, $index + 1);

    if ($nextIndex === null) {
        return false;
    }

    $next = $tokens[$nextIndex];

    return \is_array($next) && $next[0] === T_DOUBLE_ARROW;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_metric_name_slot(array $tokens, int $index): bool
{
    $constantName = coretsia_observability_naming_gate_constant_name_for_assigned_literal($tokens, $index);
    if ($constantName !== null) {
        return coretsia_observability_naming_gate_is_metric_constant_name($constantName);
    }

    return coretsia_observability_naming_gate_is_direct_metric_name_call_argument($tokens, $index);
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_span_name_slot(array $tokens, int $index): bool
{
    $constantName = coretsia_observability_naming_gate_constant_name_for_assigned_literal($tokens, $index);
    if ($constantName !== null) {
        return coretsia_observability_naming_gate_is_span_constant_name($constantName);
    }

    return coretsia_observability_naming_gate_is_direct_span_name_call_argument($tokens, $index);
}

function coretsia_observability_naming_gate_is_span_constant_name(string $name): bool
{
    $name = coretsia_observability_naming_gate_normalize_identifier($name);

    return \str_starts_with($name, 'span_')
        || \str_ends_with($name, '_span')
        || \str_contains($name, 'span_name');
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_direct_span_name_call_argument(array $tokens, int $index): bool
{
    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $index);
    if ($parenIndex === null) {
        return false;
    }

    $firstArgumentIndex = PhpTokenStream::nextSignificantIndex($tokens, $parenIndex + 1);
    if ($firstArgumentIndex !== $index) {
        return false;
    }

    $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return false;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return false;
    }

    return coretsia_observability_naming_gate_is_span_name_callable_name(
        coretsia_observability_naming_gate_normalize_identifier($name[1]),
    );
}

function coretsia_observability_naming_gate_is_span_name_callable_name(string $name): bool
{
    return isset(
        [
            'startspan' => true,
            'inspan' => true,
            'span' => true,
        ][$name],
    );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_constant_name_for_assigned_literal(array $tokens, int $index): ?string
{
    $equalsIndex = PhpTokenStream::previousSignificantIndex($tokens, $index - 1);
    if ($equalsIndex === null || ($tokens[$equalsIndex] ?? null) !== '=') {
        return null;
    }

    $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $equalsIndex - 1);
    if ($nameIndex === null) {
        return null;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return null;
    }

    if (!coretsia_observability_naming_gate_is_const_assignment($tokens, $nameIndex)) {
        return null;
    }

    return coretsia_observability_naming_gate_normalize_identifier($name[1]);
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_const_assignment(array $tokens, int $nameIndex): bool
{
    for ($i = $nameIndex - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($token === ';' || $token === '{' || $token === '}') {
            return false;
        }

        if (\is_array($token) && $token[0] === T_CONST) {
            return true;
        }
    }

    return false;
}

function coretsia_observability_naming_gate_is_metric_constant_name(string $name): bool
{
    $name = coretsia_observability_naming_gate_normalize_identifier($name);

    if (\str_starts_with($name, 'metric_') || \str_ends_with($name, '_metric')) {
        return true;
    }

    return (bool) \preg_match(
        '/_(total|duration_ms|duration_seconds|count|counter|gauge|histogram|timer|seconds|milliseconds|bytes)\z/',
        $name,
    );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_direct_metric_name_call_argument(array $tokens, int $index): bool
{
    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $index);
    if ($parenIndex === null) {
        return false;
    }

    $firstArgumentIndex = PhpTokenStream::nextSignificantIndex($tokens, $parenIndex + 1);
    if ($firstArgumentIndex !== $index) {
        return false;
    }

    $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return false;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return false;
    }

    return coretsia_observability_naming_gate_is_metric_name_callable_name(
        coretsia_observability_naming_gate_normalize_identifier($name[1]),
    );
}

function coretsia_observability_naming_gate_is_metric_name_callable_name(string $name): bool
{
    return isset(
        [
            'increment' => true,
            'decrement' => true,
            'observe' => true,
            'counter' => true,
            'histogram' => true,
            'gauge' => true,
            'timer' => true,
            'measure' => true,
            'measurement' => true,
            'metric' => true,
            'recordmetric' => true,
            'recordmeasurement' => true,
        ][$name],
    )
        || \str_contains($name, 'metric');
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_explicit_observability_label_array_key(
    array $tokens,
    int $index,
    string $relativePath,
): bool {
    $containerKey = coretsia_observability_naming_gate_observability_container_key_for_array_key($tokens, $index);
    if ($containerKey !== null) {
        return true;
    }

    $callName = coretsia_observability_naming_gate_call_name_for_array_argument($tokens, $index);
    if ($callName === null) {
        return false;
    }

    return coretsia_observability_naming_gate_is_labelish_callable_name($callName)
        || (
            coretsia_observability_naming_gate_is_metric_context($tokens, $index, $relativePath)
            && coretsia_observability_naming_gate_is_observability_callable_name($callName)
        );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_observability_container_key_for_array_key(
    array $tokens,
    int $index,
): ?string {
    $openIndex = coretsia_observability_naming_gate_current_short_array_open_index($tokens, $index);
    if ($openIndex === null) {
        return null;
    }

    $containerKey = coretsia_observability_naming_gate_array_value_key_before_open($tokens, $openIndex);
    if ($containerKey === null) {
        return null;
    }

    if (isset(
        [
            'label' => true,
            'labels' => true,
            'dimension' => true,
            'dimensions' => true,
            'tag' => true,
            'tags' => true,
            'metric_label' => true,
            'metric_labels' => true,
        ][$containerKey],
    )) {
        return $containerKey;
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_current_short_array_open_index(array $tokens, int $index): ?int
{
    $depth = 0;

    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($token === ']') {
            $depth++;
            continue;
        }

        if ($token === '[') {
            if ($depth === 0) {
                return $i;
            }

            $depth--;
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_array_value_key_before_open(array $tokens, int $openIndex): ?string
{
    $arrowIndex = PhpTokenStream::previousSignificantIndex($tokens, $openIndex - 1);
    if ($arrowIndex === null) {
        return null;
    }

    $arrow = $tokens[$arrowIndex];
    if (!\is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW) {
        return null;
    }

    $keyIndex = PhpTokenStream::previousSignificantIndex($tokens, $arrowIndex - 1);
    if ($keyIndex === null) {
        return null;
    }

    $key = $tokens[$keyIndex];

    if (\is_array($key) && $key[0] === T_CONSTANT_ENCAPSED_STRING) {
        return coretsia_observability_naming_gate_normalize_identifier(
            PhpTokenStream::decodeStringLiteral($key[1]),
        );
    }

    if (\is_array($key) && $key[0] === T_STRING) {
        return coretsia_observability_naming_gate_normalize_identifier($key[1]);
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_call_name_for_array_argument(array $tokens, int $index): ?string
{
    $openIndex = coretsia_observability_naming_gate_current_short_array_open_index($tokens, $index);
    if ($openIndex === null) {
        return null;
    }

    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $openIndex);
    if ($parenIndex === null) {
        return null;
    }

    $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return null;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return null;
    }

    return coretsia_observability_naming_gate_normalize_identifier($name[1]);
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_find_enclosing_call_open_paren_index(array $tokens, int $index): ?int
{
    $depth = 0;

    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($token === ')') {
            $depth++;
            continue;
        }

        if ($token === '(') {
            if ($depth === 0) {
                $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $i - 1);
                if ($nameIndex === null) {
                    return null;
                }

                $name = $tokens[$nameIndex];

                return \is_array($name) && $name[0] === T_STRING ? $i : null;
            }

            $depth--;
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_observability_scalar_label_key_context(
    array $tokens,
    int $index,
    string $relativePath,
): bool {
    $callName = coretsia_observability_naming_gate_call_name_for_scalar_argument($tokens, $index);
    if ($callName === null) {
        return false;
    }

    return coretsia_observability_naming_gate_is_labelish_callable_name($callName)
        || (
            coretsia_observability_naming_gate_is_metric_context($tokens, $index, $relativePath)
            && coretsia_observability_naming_gate_is_observability_callable_name($callName)
        );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_call_name_for_scalar_argument(array $tokens, int $index): ?string
{
    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $index);
    if ($parenIndex === null) {
        return null;
    }

    $nameIndex = PhpTokenStream::previousSignificantIndex($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return null;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return null;
    }

    return coretsia_observability_naming_gate_normalize_identifier($name[1]);
}

function coretsia_observability_naming_gate_is_labelish_callable_name(string $name): bool
{
    return \str_contains($name, 'label')
        || \str_contains($name, 'dimension')
        || \str_contains($name, 'tag');
}

function coretsia_observability_naming_gate_is_observability_callable_name(string $name): bool
{
    return coretsia_observability_naming_gate_is_labelish_callable_name($name)
        || \str_contains($name, 'metric')
        || \str_contains($name, 'counter')
        || \str_contains($name, 'histogram')
        || \str_contains($name, 'gauge')
        || \str_contains($name, 'timer')
        || \str_contains($name, 'observe')
        || \str_contains($name, 'span')
        || \str_contains($name, 'trace');
}

function coretsia_observability_naming_gate_normalize_identifier(string $value): string
{
    return \strtolower(\str_replace('-', '_', $value));
}

function coretsia_observability_naming_gate_is_valid_metric_name(string $value): bool
{
    return (bool) \preg_match(
        '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+_[a-z][a-z0-9]*(?:_[a-z][a-z0-9]*)*$/',
        $value,
    );
}

function coretsia_observability_naming_gate_is_valid_span_name(string $value): bool
{
    return (bool) \preg_match(
        '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/',
        $value,
    );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_metric_context(array $tokens, int $index, string $relativePath): bool
{
    $path = \strtolower(\str_replace('\\', '/', $relativePath));
    if (
        \str_contains($path, '/metric/')
        || \str_contains($path, '/metrics/')
        || \str_contains($path, '/observability/')
    ) {
        return true;
    }

    $context = coretsia_observability_naming_gate_context_words($tokens, $index, 56);

    foreach (
        [
            'metric',
            'metrics',
            'counter',
            'histogram',
            'gauge',
            'timer',
            'observe',
            'measurement',
            'measure',
            'increment',
            'decrement',
        ] as $needle
    ) {
        if (\str_contains($context, $needle)) {
            return true;
        }
    }

    return false;
}

function coretsia_observability_naming_gate_is_observability_structural_key(string $value): bool
{
    return isset(
        [
            'attribute' => true,
            'attributes' => true,
            'bucket' => true,
            'buckets' => true,
            'description' => true,
            'dimension' => true,
            'dimensions' => true,
            'label' => true,
            'labels' => true,
            'metric' => true,
            'metrics' => true,
            'name' => true,
            'span' => true,
            'spans' => true,
            'tag' => true,
            'tags' => true,
            'type' => true,
            'unit' => true,
            'value' => true,
        ][$value],
    );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_context_words(array $tokens, int $index, int $radius): string
{
    /** @var list<string> $words */
    $words = [];

    $start = \max(0, $index - $radius);
    $end = \min(\count($tokens) - 1, $index + $radius);

    for ($i = $start; $i <= $end; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            continue;
        }

        if (
            $token[0] !== T_STRING
            && $token[0] !== T_VARIABLE
            && $token[0] !== T_NAME_FULLY_QUALIFIED
            && $token[0] !== T_NAME_QUALIFIED
            && $token[0] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            continue;
        }

        $text = $token[1];

        if ($token[0] === T_VARIABLE) {
            $text = \ltrim($text, '$');
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $text = PhpTokenStream::decodeStringLiteral($text);
        }

        $text = \strtolower($text);
        $text = \preg_replace('/[^a-z0-9_]+/', ' ', $text);
        if (!\is_string($text) || $text === '') {
            continue;
        }

        $words[] = $text;
    }

    return ' ' . \implode(' ', $words) . ' ';
}
