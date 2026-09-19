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

use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\PhpSourceFinder;
use Coretsia\Tools\Support\PhpTokenStream;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_DTO_NO_LOGIC_VIOLATION',
    'CORETSIA_DTO_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_dto_no_logic_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $files = coretsia_dto_no_logic_gate_collect_php_source_files(
        $repository,
        $catalog,
        $scanRoot,
    );

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($files as $file) {
        $relativePath = $repository->relativeToRepo($file);

        foreach (coretsia_dto_no_logic_gate_analyze_php_file($file, $relativePath) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_collect_php_source_files(
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
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_analyze_php_file(string $path, string $relativePath): array
{
    $stream = PhpTokenStream::fromFile($path);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (coretsia_dto_no_logic_gate_extract_dto_classes($stream) as $classInfo) {
        foreach (coretsia_dto_no_logic_gate_analyze_dto_class($stream, $classInfo) as $reason) {
            $diagnostics[] = $relativePath . ': ' . $reason;
        }
    }

    return $diagnostics;
}

/**
 * @return list<array{name:string,body_start:int,body_end:int}>
 */
function coretsia_dto_no_logic_gate_extract_dto_classes(PhpTokenStream $stream): array
{
    $tokens = $stream->tokens();
    /** @var list<array{name:string,body_start:int,body_end:int}> $classes */
    $classes = [];

    /** @var list<string> $pendingAttributes */
    $pendingAttributes = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (\is_array($token) && $token[0] === T_ATTRIBUTE) {
            [$attributeNames, $endIndex] = $stream->parseAttributeGroup($i);
            $pendingAttributes = \array_merge($pendingAttributes, $attributeNames);
            $i = $endIndex;
            continue;
        }

        if (PhpTokenStream::isIgnorableBetweenAttributeAndClassLike($token)) {
            continue;
        }

        if (\is_array($token) && $token[0] === T_CLASS) {
            if ($stream->previousSignificantTokenIsNew($i)) {
                $pendingAttributes = [];
                continue;
            }

            $name = $stream->nextClassLikeName($i);
            $bodyStart = $stream->findNextSymbolIndex($i, '{');

            if ($name !== null && $bodyStart !== null) {
                $bodyEnd = $stream->findMatchingPair($bodyStart, '{', '}');

                if (
                    $bodyEnd !== null
                    && coretsia_dto_no_logic_gate_attributes_contain_canonical_dto_marker(
                        $pendingAttributes,
                        $stream,
                    )
                ) {
                    $classes[] = [
                        'name' => $name,
                        'body_start' => $bodyStart,
                        'body_end' => $bodyEnd,
                    ];
                }

                if ($bodyEnd !== null) {
                    $i = $bodyEnd;
                }
            }

            $pendingAttributes = [];
            continue;
        }

        if (PhpTokenStream::isSignificantToken($token)) {
            $pendingAttributes = [];
        }
    }

    return $classes;
}

/**
 * @param list<string> $attributeNames
 */
function coretsia_dto_no_logic_gate_attributes_contain_canonical_dto_marker(
    array $attributeNames,
    PhpTokenStream $stream,
): bool {
    foreach ($attributeNames as $attributeName) {
        if (
            \strcasecmp(
                $stream->resolveClassName($attributeName),
                'Coretsia\\Dto\\Attribute\\Dto',
            ) === 0
        ) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{name:string,body_start:int,body_end:int} $classInfo
 *
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_analyze_dto_class(PhpTokenStream $stream, array $classInfo): array
{
    $tokens = $stream->tokens();

    /** @var list<string> $violations */
    $violations = [];

    $constructor = null;

    if (
        coretsia_dto_no_logic_gate_class_body_uses_property_hooks(
            $tokens,
            $classInfo['body_start'],
            $classInfo['body_end'],
        )
    ) {
        $violations[] = 'disallowed-property-hook';
    }

    foreach (
        $stream->methods(
            $classInfo['body_start'],
            $classInfo['body_end'],
        ) as $method
    ) {
        $methodName = $method['name'];

        if ($methodName !== '__construct') {
            $violations[] = 'disallowed-method';
            continue;
        }

        $constructor = $method;
    }

    if ($constructor !== null) {
        foreach (coretsia_dto_no_logic_gate_analyze_constructor($tokens, $constructor) as $reason) {
            $violations[] = $reason;
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_no_logic_gate_class_body_uses_property_hooks(
    array $tokens,
    int $bodyStart,
    int $bodyEnd,
): bool {
    $depth = 0;
    $segmentStart = $bodyStart + 1;

    for ($i = $bodyStart + 1; $i < $bodyEnd; $i++) {
        $token = $tokens[$i];

        if ($depth === 0 && $token === '{') {
            $segment = \array_slice($tokens, $segmentStart, $i - $segmentStart);
            $hasVariable = false;
            $hasFunction = false;

            foreach ($segment as $segmentToken) {
                if (!\is_array($segmentToken)) {
                    continue;
                }

                if ($segmentToken[0] === T_VARIABLE) {
                    $hasVariable = true;
                } elseif ($segmentToken[0] === T_FUNCTION) {
                    $hasFunction = true;
                }
            }

            if ($hasVariable && !$hasFunction) {
                return true;
            }
        }

        if (PhpTokenStream::isCurlyOpenToken($token)) {
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

        if ($depth === 0 && $token === ';') {
            $segmentStart = $i + 1;
        }
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 * @param array{name:string|null,param_start:int|null,param_end:int|null,body_start:int|null,body_end:int|null} $constructor
 *
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_analyze_constructor(array $tokens, array $constructor): array
{
    $paramStart = $constructor['param_start'];
    $paramEnd = $constructor['param_end'];
    $bodyStart = $constructor['body_start'];
    $bodyEnd = $constructor['body_end'];

    if ($paramStart === null || $paramEnd === null || $bodyStart === null || $bodyEnd === null) {
        return ['constructor-nontrivial-body'];
    }

    $parameterNames = coretsia_dto_no_logic_gate_extract_parameter_names($tokens, $paramStart, $paramEnd);

    $violations = coretsia_dto_no_logic_gate_detect_specific_constructor_violations(
        $tokens,
        $bodyStart,
        $bodyEnd,
    );

    if ($violations !== []) {
        $violations = \array_values(\array_unique($violations));
        \sort($violations, \SORT_STRING);

        return $violations;
    }

    if (
        !coretsia_dto_no_logic_gate_constructor_body_is_empty_or_trivial_assignments(
            $tokens,
            $bodyStart,
            $bodyEnd,
            $parameterNames,
        )
    ) {
        return ['constructor-nontrivial-body'];
    }

    return [];
}

/**
 * @param list<array|string> $tokens
 *
 * @return array<string,true>
 */
function coretsia_dto_no_logic_gate_extract_parameter_names(array $tokens, int $paramStart, int $paramEnd): array
{
    /** @var array<string,true> $names */
    $names = [];

    for ($i = $paramStart + 1; $i < $paramEnd; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_VARIABLE) {
            continue;
        }

        $name = \ltrim($token[1], '$');

        if ($name !== '') {
            $names[$name] = true;
        }
    }

    \ksort($names, \SORT_STRING);

    return $names;
}

/**
 * @param list<array|string> $tokens
 *
 * @return list<string>
 */
function coretsia_dto_no_logic_gate_detect_specific_constructor_violations(
    array $tokens,
    int $bodyStart,
    int $bodyEnd,
): array {
    /** @var list<string> $violations */
    $violations = [];

    for ($i = $bodyStart + 1; $i < $bodyEnd; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            continue;
        }

        if (coretsia_dto_no_logic_gate_is_control_flow_token($token)) {
            $violations[] = 'constructor-control-flow';
            continue;
        }

        if (coretsia_dto_no_logic_gate_is_loop_token($token)) {
            $violations[] = 'constructor-loop';
            continue;
        }

        if (
            $token[0] === T_TRY
            || $token[0] === T_CATCH
            || $token[0] === T_FINALLY
        ) {
            $violations[] = 'constructor-try-catch';
            continue;
        }

        if ($token[0] === T_THROW) {
            $violations[] = 'constructor-throw';
            continue;
        }

        if ($token[0] === T_NEW) {
            $violations[] = 'constructor-new-object';
            continue;
        }

        if ($token[0] === T_DOUBLE_COLON) {
            $violations[] = 'constructor-static-call';
            continue;
        }

        if (
            coretsia_dto_no_logic_gate_is_object_operator_token($token)
            && coretsia_dto_no_logic_gate_object_operator_starts_method_call($tokens, $i)
        ) {
            $violations[] = 'constructor-calls-method';
            continue;
        }

        if (coretsia_dto_no_logic_gate_name_token_starts_function_call($tokens, $i)) {
            $violations[] = 'constructor-calls-function';
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_no_logic_gate_is_control_flow_token(array $token): bool
{
    return $token[0] === T_IF
        || $token[0] === T_ELSEIF
        || $token[0] === T_ELSE
        || $token[0] === T_SWITCH
        || $token[0] === T_MATCH;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_no_logic_gate_is_loop_token(array $token): bool
{
    return $token[0] === T_FOR
        || $token[0] === T_FOREACH
        || $token[0] === T_WHILE
        || $token[0] === T_DO;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_no_logic_gate_is_object_operator_token(array $token): bool
{
    return $token[0] === T_OBJECT_OPERATOR
        || $token[0] === T_NULLSAFE_OBJECT_OPERATOR;
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_no_logic_gate_object_operator_starts_method_call(array $tokens, int $operatorIndex): bool
{
    $nameIndex = coretsia_dto_no_logic_gate_next_meaningful_token_index($tokens, $operatorIndex + 1);

    if ($nameIndex === null) {
        return false;
    }

    $name = $tokens[$nameIndex];

    if (!\is_array($name) || $name[0] !== T_STRING) {
        return false;
    }

    $nextIndex = coretsia_dto_no_logic_gate_next_meaningful_token_index($tokens, $nameIndex + 1);

    return $nextIndex !== null && $tokens[$nextIndex] === '(';
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_no_logic_gate_name_token_starts_function_call(array $tokens, int $index): bool
{
    $token = $tokens[$index];

    if (!\is_array($token) || !coretsia_dto_no_logic_gate_is_name_token($token)) {
        return false;
    }

    $nextIndex = coretsia_dto_no_logic_gate_next_meaningful_token_index($tokens, $index + 1);

    if ($nextIndex === null || $tokens[$nextIndex] !== '(') {
        return false;
    }

    $previous = coretsia_dto_no_logic_gate_previous_meaningful_token($tokens, $index - 1);

    if ($previous === null) {
        return true;
    }

    if (\is_array($previous)) {
        if (
            $previous[0] === T_OBJECT_OPERATOR
            || $previous[0] === T_NULLSAFE_OBJECT_OPERATOR
            || $previous[0] === T_DOUBLE_COLON
            || $previous[0] === T_NEW
            || $previous[0] === T_FUNCTION
        ) {
            return false;
        }
    }

    return true;
}

/**
 * @param array{0:int,1:string,2:int} $token
 */
function coretsia_dto_no_logic_gate_is_name_token(array $token): bool
{
    return $token[0] === T_STRING
        || $token[0] === T_NAME_QUALIFIED
        || $token[0] === T_NAME_FULLY_QUALIFIED
        || $token[0] === T_NAME_RELATIVE;
}

/**
 * @param list<array|string> $tokens
 * @param array<string,true> $parameterNames
 */
function coretsia_dto_no_logic_gate_constructor_body_is_empty_or_trivial_assignments(
    array $tokens,
    int $bodyStart,
    int $bodyEnd,
    array $parameterNames,
): bool {
    $meaningfulTokens = coretsia_dto_no_logic_gate_meaningful_tokens_between($tokens, $bodyStart + 1, $bodyEnd - 1);

    if ($meaningfulTokens === []) {
        return true;
    }

    $count = \count($meaningfulTokens);
    $i = 0;

    while ($i < $count) {
        if ($i + 5 >= $count) {
            return false;
        }

        if (!coretsia_dto_no_logic_gate_is_this_variable_token($meaningfulTokens[$i])) {
            return false;
        }

        if (!coretsia_dto_no_logic_gate_is_object_operator_array_token($meaningfulTokens[$i + 1])) {
            return false;
        }

        if (!coretsia_dto_no_logic_gate_is_string_token($meaningfulTokens[$i + 2])) {
            return false;
        }

        if ($meaningfulTokens[$i + 3] !== '=') {
            return false;
        }

        if (!coretsia_dto_no_logic_gate_is_parameter_variable_token($meaningfulTokens[$i + 4], $parameterNames)) {
            return false;
        }

        if ($meaningfulTokens[$i + 5] !== ';') {
            return false;
        }

        $i += 6;
    }

    return true;
}

/**
 * @param list<array|string> $tokens
 *
 * @return list<array|string>
 */
function coretsia_dto_no_logic_gate_meaningful_tokens_between(array $tokens, int $start, int $end): array
{
    /** @var list<array|string> $meaningful */
    $meaningful = [];

    for ($i = $start; $i <= $end; $i++) {
        $token = $tokens[$i] ?? null;

        if ($token === null || PhpTokenStream::isTriviaToken($token)) {
            continue;
        }

        $meaningful[] = $token;
    }

    return $meaningful;
}

/**
 * @param array|string $token
 */
function coretsia_dto_no_logic_gate_is_this_variable_token($token): bool
{
    return \is_array($token)
        && $token[0] === T_VARIABLE
        && $token[1] === '$this';
}

/**
 * @param array|string $token
 */
function coretsia_dto_no_logic_gate_is_object_operator_array_token($token): bool
{
    return \is_array($token) && $token[0] === T_OBJECT_OPERATOR;
}

/**
 * @param array|string $token
 */
function coretsia_dto_no_logic_gate_is_string_token($token): bool
{
    return \is_array($token) && $token[0] === T_STRING;
}

/**
 * @param array|string $token
 * @param array<string,true> $parameterNames
 */
function coretsia_dto_no_logic_gate_is_parameter_variable_token($token, array $parameterNames): bool
{
    if (!\is_array($token) || $token[0] !== T_VARIABLE) {
        return false;
    }

    $name = \ltrim($token[1], '$');

    return isset($parameterNames[$name]);
}

/**
 * @param list<array|string> $tokens
 */
function coretsia_dto_no_logic_gate_next_meaningful_token_index(array $tokens, int $start): ?int
{
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        if (!PhpTokenStream::isTriviaToken($tokens[$i])) {
            return $i;
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens
 *
 * @return array|string|null
 */
function coretsia_dto_no_logic_gate_previous_meaningful_token(array $tokens, int $start)
{
    for ($i = $start; $i >= 0; $i--) {
        if (!PhpTokenStream::isTriviaToken($tokens[$i])) {
            return $tokens[$i];
        }
    }

    return null;
}
