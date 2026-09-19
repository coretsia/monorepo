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
    'CORETSIA_DTO_SHAPE_VIOLATION',
    'CORETSIA_DTO_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_dto_shape_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_dto_shape_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $files = coretsia_dto_shape_gate_collect_php_source_files(
        $repository,
        $catalog,
        $scanRoot,
    );

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($files as $file) {
        $relativePath = $repository->relativeToRepo($file);

        foreach (coretsia_dto_shape_gate_analyze_php_file($file, $relativePath) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_dto_shape_gate_collect_php_source_files(
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
function coretsia_dto_shape_gate_analyze_php_file(string $path, string $relativePath): array
{
    $stream = PhpTokenStream::fromFile($path);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (coretsia_dto_shape_gate_extract_marked_class_likes($stream) as $classInfo) {
        foreach (coretsia_dto_shape_gate_analyze_marked_class_like($stream, $classInfo) as $reason) {
            $diagnostics[] = $relativePath . ': ' . $reason;
        }
    }

    return $diagnostics;
}

/**
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
function coretsia_dto_shape_gate_extract_marked_class_likes(PhpTokenStream $stream): array
{
    $tokens = $stream->tokens();
    /** @var list<array{kind:string,name:string,token_index:int,body_start:int,body_end:int,is_final:bool,is_abstract:bool,has_extends:bool,has_implements:bool}> $classLikes */
    $classLikes = [];

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

        if (\is_array($token) && coretsia_dto_shape_gate_is_class_like_token($token)) {
            if (
                $token[0] === T_CLASS
                && $stream->previousSignificantTokenIsNew($i)
            ) {
                $pendingAttributes = [];
                continue;
            }

            $name = $stream->nextClassLikeName($i);
            $bodyStart = $stream->findNextSymbolIndex($i, '{');

            if ($name !== null && $bodyStart !== null) {
                $bodyEnd = $stream->findMatchingPair($bodyStart, '{', '}');

                if (
                    $bodyEnd !== null
                    && coretsia_dto_shape_gate_attributes_contain_canonical_dto_marker(
                        $pendingAttributes,
                        $stream,
                    )
                ) {
                    $classLikes[] = [
                        'kind' => coretsia_dto_shape_gate_class_like_kind($token),
                        'name' => $name,
                        'token_index' => $i,
                        'body_start' => $bodyStart,
                        'body_end' => $bodyEnd,
                        'is_final' => coretsia_dto_shape_gate_class_declaration_has_modifier($tokens, $i, T_FINAL),
                        'is_abstract' => coretsia_dto_shape_gate_class_declaration_has_modifier(
                            $tokens,
                            $i,
                            T_ABSTRACT
                        ),
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

        if (PhpTokenStream::isSignificantToken($token)) {
            $pendingAttributes = [];
        }
    }

    return $classLikes;
}

/**
 * @param list<string> $attributeNames
 */
function coretsia_dto_shape_gate_attributes_contain_canonical_dto_marker(
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
function coretsia_dto_shape_gate_class_declaration_has_modifier(array $tokens, int $classIndex, int $modifier): bool
{
    for ($i = $classIndex - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!PhpTokenStream::isSignificantToken($token)) {
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
 *
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_marked_class_like(PhpTokenStream $stream, array $classInfo): array
{
    $tokens = $stream->tokens();

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
            $stream,
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

        if ($depth === 0 && !PhpTokenStream::isTriviaToken($token)) {
            $statement[] = $token;
        }

        if (PhpTokenStream::isCurlyOpenToken($token)) {
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
        if (PhpTokenStream::isTriviaToken($token)) {
            continue;
        }

        return \is_array($token) && $token[0] === T_USE;
    }

    return false;
}

/**
 * @param list<array|string> $tokens
 *
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

        if (PhpTokenStream::isTriviaToken($token)) {
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
 * @return list<string>
 */
function coretsia_dto_shape_gate_analyze_promoted_properties(
    PhpTokenStream $stream,
    int $bodyStart,
    int $bodyEnd,
): array {
    $tokens = $stream->tokens();

    /** @var list<string> $violations */
    $violations = [];

    foreach ($stream->methods($bodyStart, $bodyEnd) as $method) {
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
 *
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

        if (PhpTokenStream::isTriviaToken($token)) {
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
