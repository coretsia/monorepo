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

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::executeSelectedViolation(
    'CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository): array {
        return coretsia_internal_toolkit_no_dup_gate_scan(
            $repository,
            isset($_SERVER['argv']) && is_array($_SERVER['argv'])
                ? $_SERVER['argv']
                : [],
        );
    },
));

/**
 * @param list<mixed> $argv
 *
 * @return array{
 *     violationCode:non-empty-string,
 *     diagnostics:list<string>
 * }
 */
function coretsia_internal_toolkit_no_dup_gate_scan(
    RepositoryContext $repository,
    array $argv,
): array {
    $scanRoot = GateRuntime::resolveToolsScanRoot($repository, $argv);

    $forbiddenNames = [
        'toStudly',
        'toSnake',
        'normalizeRelative',
        'encodeStable',
    ];

    /** @var list<string> $diagnostics */
    $diagnostics = [];
    $hasJsonEncode = false;

    foreach (PhpSourceFinder::find($scanRoot, ['tests', 'fixtures']) as $absFile) {
        $relPath = GateRuntime::relativeToTools($repository, $absFile);

        $code = DeterministicFile::readTextNormalizedEol($absFile);

        $tokens = GateRuntime::withSuppressedErrors(
            static fn (): array => token_get_all($code),
        );

        if (coretsia_tools_detect_json_encode_call($tokens)) {
            $hasJsonEncode = true;
            $diagnostics[] = $relPath . ': json_encode';
        }

        foreach (coretsia_tools_find_declared_function_like_names($tokens) as $name) {
            if (!in_array($name, $forbiddenNames, true)) {
                continue;
            }

            $diagnostics[] = $relPath . ': ' . $name;
        }
    }

    return [
        'violationCode' => $hasJsonEncode
            ? 'CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN'
            : 'CORETSIA_TOOLKIT_DUPLICATION_DETECTED',
        'diagnostics' => $diagnostics,
    ];
}

/**
 * @return list<string> declared function-like names from `function <name>(...)` (covers functions and methods).
 */
function coretsia_tools_find_declared_function_like_names(array $tokens): array
{
    $names = [];
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t) || $t[0] !== \T_FUNCTION) {
            continue;
        }

        $j = $i + 1;
        $j = coretsia_tools_skip_trivia($tokens, $j);

        if ($j < $n && $tokens[$j] === '&') {
            $j++;
            $j = coretsia_tools_skip_trivia($tokens, $j);
        }

        if ($j < $n && \is_array($tokens[$j]) && $tokens[$j][0] === \T_STRING) {
            $names[] = (string) $tokens[$j][1];
        }
    }

    return $names;
}

function coretsia_tools_skip_trivia(array $tokens, int $i): int
{
    $n = \count($tokens);
    while ($i < $n) {
        $t = $tokens[$i];
        if (\is_array($t) && ($t[0] === \T_WHITESPACE || $t[0] === \T_COMMENT || $t[0] === \T_DOC_COMMENT)) {
            $i++;
            continue;
        }
        break;
    }
    return $i;
}

/**
 * Token-based detection of forbidden json_encode(...) calls:
 * - token type: T_STRING | T_NAME_QUALIFIED | T_NAME_FULLY_QUALIFIED
 * - last segment equals "json_encode" (case-insensitive)
 * - next non-whitespace token is "("
 * - ignore if immediately preceded (ignoring whitespace) by T_OBJECT_OPERATOR or T_DOUBLE_COLON
 */
function coretsia_tools_detect_json_encode_call(array $tokens): bool
{
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t)) {
            continue;
        }

        $type = $t[0];
        if ($type !== \T_STRING && $type !== \T_NAME_QUALIFIED && $type !== \T_NAME_FULLY_QUALIFIED) {
            continue;
        }

        $raw = \ltrim((string) $t[1], '\\');
        $parts = \explode('\\', $raw);
        $last = $parts[\count($parts) - 1];

        if (\strtolower((string) $last) !== 'json_encode') {
            continue;
        }

        $prev = coretsia_tools_prev_non_ws_token($tokens, $i);
        if ($prev !== null && \is_array($prev) && ($prev[0] === \T_OBJECT_OPERATOR || $prev[0] === \T_DOUBLE_COLON)) {
            continue;
        }

        $next = coretsia_tools_next_non_ws_token($tokens, $i);
        if ($next === '(') {
            return true;
        }
    }

    return false;
}

function coretsia_tools_prev_non_ws_token(array $tokens, int $i): array|string|null
{
    for ($j = $i - 1; $j >= 0; $j--) {
        $t = $tokens[$j];
        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }
        return $t;
    }
    return null;
}

function coretsia_tools_next_non_ws_token(array $tokens, int $i): array|string|null
{
    $n = \count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }
        return $t;
    }
    return null;
}
