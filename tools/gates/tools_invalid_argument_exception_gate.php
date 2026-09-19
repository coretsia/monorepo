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

exit(GateRuntime::execute(
    'CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_FORBIDDEN',
    'CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository): array {
        return coretsia_tools_invalid_argument_exception_gate_scan(
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
 * @return list<string>
 */
function coretsia_tools_invalid_argument_exception_gate_scan(
    RepositoryContext $repository,
    array $argv,
): array {
    $scanRoot = GateRuntime::resolveToolsScanRoot($repository, $argv);

    $allowlisted = [
        'build/sync_composer_repositories.php' => true,
        'support/DeterministicException.php' => true,
    ];

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (PhpSourceFinder::find($scanRoot, ['tests', 'fixtures']) as $absFile) {
        $relPath = GateRuntime::relativeToTools($repository, $absFile);

        if (isset($allowlisted[$relPath])) {
            continue;
        }

        $code = DeterministicFile::readTextNormalizedEol($absFile);

        $tokens = GateRuntime::withSuppressedErrors(
            static fn (): array => token_get_all($code, TOKEN_PARSE),
        );

        if (!coretsia_tools_iae_detect_throw_new_invalid_argument_exception($tokens)) {
            continue;
        }

        $diagnostics[] = $relPath . ': throw-new-InvalidArgumentException';
    }

    return $diagnostics;
}

function coretsia_tools_iae_is_ignorable_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    return $token[0] === \T_WHITESPACE
        || $token[0] === \T_COMMENT
        || $token[0] === \T_DOC_COMMENT;
}

/**
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_tools_iae_next_non_ignorable_index(array $tokens, int $from): ?int
{
    $n = \count($tokens);
    for ($i = $from; $i < $n; $i++) {
        if (coretsia_tools_iae_is_ignorable_token($tokens[$i])) {
            continue;
        }
        return $i;
    }
    return null;
}

function coretsia_tools_iae_is_name_token(int $id): bool
{
    return $id === \T_STRING
        || $id === (\defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1)
        || $id === (\defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1)
        || $id === (\defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1);
}

function coretsia_tools_iae_last_name_segment(string $name): string
{
    $name = \ltrim($name, '\\');
    $parts = \explode('\\', $name);
    $last = $parts[\count($parts) - 1] ?? $name;
    return (string) $last;
}

/**
 * Detect pattern: `throw new \InvalidArgumentException(...)` (and unqualified variants).
 *
 * @param list<array{0:int,1:string,2?:int}|string> $tokens
 */
function coretsia_tools_iae_detect_throw_new_invalid_argument_exception(array $tokens): bool
{
    $n = \count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (!\is_array($t) || $t[0] !== \T_THROW) {
            continue;
        }

        $j = coretsia_tools_iae_next_non_ignorable_index($tokens, $i + 1);
        if ($j === null) {
            continue;
        }

        // Allow optional "(" right after throw: `throw (new ...)`
        if ($tokens[$j] === '(') {
            $j = coretsia_tools_iae_next_non_ignorable_index($tokens, $j + 1);
            if ($j === null) {
                continue;
            }
        }

        $tok = $tokens[$j];
        if (!\is_array($tok) || $tok[0] !== \T_NEW) {
            continue;
        }

        $k = coretsia_tools_iae_next_non_ignorable_index($tokens, $j + 1);
        if ($k === null) {
            continue;
        }

        $nameTok = $tokens[$k];
        if (!\is_array($nameTok) || !coretsia_tools_iae_is_name_token((int) $nameTok[0])) {
            continue;
        }

        $raw = (string) ($nameTok[1] ?? '');
        if ($raw === '') {
            continue;
        }

        $last = coretsia_tools_iae_last_name_segment($raw);

        if (\strcasecmp($last, 'InvalidArgumentException') === 0) {
            return true;
        }
    }

    return false;
}
