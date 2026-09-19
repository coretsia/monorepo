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

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_ATOMIC_WRITE_VIOLATION',
    'CORETSIA_ATOMIC_WRITE_GATE_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        [$scanRoot, $allowlistPath] = coretsia_atomic_write_gate_resolve_inputs(
            $repository,
            $argv,
        );
        $allowlist = coretsia_atomic_write_gate_load_allowlist($allowlistPath);
        $diagnostics = [];

        foreach (PhpSourceFinder::find($scanRoot) as $file) {
            $repoRelative = $repository->relativeToRepo($file);

            if (coretsia_atomic_write_gate_should_skip_file($repoRelative, $allowlist)) {
                continue;
            }

            $tokens = PhpTokenStream::fromParsedFile($file)->tokens();

            foreach (coretsia_atomic_write_gate_scan_tokens($tokens) as $line) {
                $diagnostics[] = $repoRelative . ':' . (string) $line;
            }
        }

        return $diagnostics;
    },
));

/**
 * @param list<mixed> $argv
 * @return array{0:string,1:string}
 */
function coretsia_atomic_write_gate_resolve_inputs(
    RepositoryContext $repository,
    array $argv,
): array {
    $path = null;
    $allowlist = null;

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if (!\is_string($arg)) {
            throw new \RuntimeException('atomic-write-argument-invalid');
        }

        if (\str_starts_with($arg, '--path=')) {
            if ($path !== null) {
                throw new \RuntimeException('atomic-write-path-argument-duplicate');
            }

            $path = \substr($arg, \strlen('--path='));

            if ($path === '') {
                throw new \RuntimeException('atomic-write-path-argument-invalid');
            }

            continue;
        }

        if (\str_starts_with($arg, '--allowlist=')) {
            if ($allowlist !== null) {
                throw new \RuntimeException('atomic-write-allowlist-argument-duplicate');
            }

            $allowlist = \substr($arg, \strlen('--allowlist='));

            if ($allowlist === '') {
                throw new \RuntimeException('atomic-write-allowlist-argument-invalid');
            }

            continue;
        }

        throw new \RuntimeException('atomic-write-argument-invalid');
    }

    $scanRoot = $path === null
        ? $repository->toolsRoot()
        : $repository->resolveExistingDirectory($path);

    if (!RepositoryContext::containsPath($repository->toolsRoot(), $scanRoot)) {
        throw new \RuntimeException('atomic-write-scan-root-outside-tools');
    }

    $allowlistPath = $repository->resolveExistingFile(
        $allowlist ?? 'tools/config/atomic_write_allowlist.php',
    );

    return [$scanRoot, $allowlistPath];
}

/**
 * @return array<string, true>
 */
function coretsia_atomic_write_gate_load_allowlist(string $allowlistPath): array
{
    $value = require $allowlistPath;

    if (!\is_array($value) || !\array_is_list($value)) {
        throw new \RuntimeException('atomic-write-allowlist-invalid');
    }

    /** @var list<string> $paths */
    $paths = [];

    foreach ($value as $entry) {
        if (!\is_array($entry) || \array_is_list($entry)) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        $keys = \array_keys($entry);
        \sort($keys, \SORT_STRING);

        if ($keys !== ['path', 'reason']) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        $path = $entry['path'] ?? null;
        $reason = $entry['reason'] ?? null;

        if (!\is_string($path) || !\is_string($reason)) {
            throw new \RuntimeException('atomic-write-allowlist-entry-invalid');
        }

        if (!coretsia_atomic_write_gate_is_valid_repo_relative_path($path)) {
            throw new \RuntimeException('atomic-write-allowlist-path-invalid');
        }

        if (\preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/', $reason) !== 1) {
            throw new \RuntimeException('atomic-write-allowlist-reason-invalid');
        }

        $paths[] = $path;
    }

    $sorted = $paths;
    \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));

    if ($paths !== $sorted) {
        throw new \RuntimeException('atomic-write-allowlist-not-sorted');
    }

    if (\count(\array_unique($paths)) !== \count($paths)) {
        throw new \RuntimeException('atomic-write-allowlist-duplicate');
    }

    /** @var array<string, true> $lookup */
    $lookup = [];
    foreach ($paths as $path) {
        $lookup[$path] = true;
    }

    \ksort($lookup, \SORT_STRING);

    return $lookup;
}

/**
 * @param array<string, true> $allowlist
 */
function coretsia_atomic_write_gate_should_skip_file(
    string $repoRelative,
    array $allowlist,
): bool {
    if (isset($allowlist[$repoRelative])) {
        return true;
    }

    if ($repoRelative === 'tools/support/DeterministicFile.php') {
        return true;
    }

    if (\str_starts_with($repoRelative, 'tools/tests/') || \str_contains($repoRelative, '/tests/')) {
        return true;
    }

    return \str_contains($repoRelative, '/fixtures/');
}

/**
 * @param list<mixed> $tokens
 *
 * @return list<int>
 */
function coretsia_atomic_write_gate_scan_tokens(array $tokens): array
{
    /** @var list<int> $lines */
    $lines = [];

    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            continue;
        }

        $id = $token[0];
        $text = (string) $token[1];
        $line = (int) ($token[2] ?? 0);

        if ($id === \T_COMMENT || $id === \T_DOC_COMMENT) {
            continue;
        }

        $call = coretsia_atomic_write_gate_function_call_name($tokens, $i);
        if ($call !== null) {
            if (\in_array($call, ['file_put_contents', 'fwrite', 'rename', 'copy'], true)) {
                $lines[] = $line;
                continue;
            }

            if ($call === 'fopen' && coretsia_atomic_write_gate_fopen_mode_is_write_or_unknown($tokens, $i)) {
                $lines[] = $line;
                continue;
            }
        }

        if ($id === \T_NEW && coretsia_atomic_write_gate_new_splfileobject_mode_is_write_or_unknown($tokens, $i)) {
            $lines[] = $line;
            continue;
        }
    }

    $lines = \array_values(\array_unique($lines));
    \sort($lines, \SORT_NUMERIC);

    return $lines;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_function_call_name(array $tokens, int $i): ?string
{
    $token = $tokens[$i];

    if (!\is_array($token)) {
        return null;
    }

    $id = $token[0];
    $text = (string) $token[1];

    $allowedNameTokens = [\T_STRING];

    if (\defined('T_NAME_FULLY_QUALIFIED')) {
        $allowedNameTokens[] = \T_NAME_FULLY_QUALIFIED;
    }
    if (\defined('T_NAME_QUALIFIED')) {
        $allowedNameTokens[] = \T_NAME_QUALIFIED;
    }

    if (!\in_array($id, $allowedNameTokens, true)) {
        return null;
    }

    $name = \strtolower(\ltrim($text, '\\'));

    if (\str_contains($name, '\\')) {
        return null;
    }

    $next = PhpTokenStream::nextSignificantIndex($tokens, $i + 1);

    if ($next === null || coretsia_atomic_write_gate_token_text($tokens[$next]) !== '(') {
        return null;
    }

    $prev = PhpTokenStream::previousSignificantIndex($tokens, $i - 1);

    if ($prev !== null) {
        $prevToken = $tokens[$prev];

        if (\is_array($prevToken)) {
            if (\in_array($prevToken[0], [\T_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION, \T_NEW], true)) {
                return null;
            }
        } elseif ($prevToken === '\\') {
            // \fopen(...): allowed global function call.
        }
    }

    return $name;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_fopen_mode_is_write_or_unknown(array $tokens, int $callIndex): bool
{
    $open = PhpTokenStream::nextSignificantIndex($tokens, $callIndex + 1);
    if ($open === null || coretsia_atomic_write_gate_token_text($tokens[$open]) !== '(') {
        return true;
    }

    $comma = coretsia_atomic_write_gate_find_next_top_level_comma($tokens, $open);
    if ($comma === null) {
        return true;
    }

    $modeIndex = PhpTokenStream::nextSignificantIndex($tokens, $comma + 1);
    if ($modeIndex === null) {
        return true;
    }

    $mode = coretsia_atomic_write_gate_string_literal_value($tokens[$modeIndex] ?? null);

    if ($mode === null) {
        return true;
    }

    return !coretsia_atomic_write_gate_is_read_only_fopen_mode($mode);
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_new_splfileobject_mode_is_write_or_unknown(array $tokens, int $newIndex): bool
{
    $classIndex = PhpTokenStream::nextSignificantIndex($tokens, $newIndex + 1);
    if ($classIndex === null) {
        return false;
    }

    $className = coretsia_atomic_write_gate_read_class_name($tokens, $classIndex);
    if ($className === null || \strtolower(\ltrim($className, '\\')) !== 'splfileobject') {
        return false;
    }

    $open = PhpTokenStream::nextSignificantIndex($tokens, $classIndex + 1);
    if ($open === null || coretsia_atomic_write_gate_token_text($tokens[$open]) !== '(') {
        return true;
    }

    $comma = coretsia_atomic_write_gate_find_next_top_level_comma($tokens, $open);
    if ($comma === null) {
        // Default SplFileObject mode is read-only.
        return false;
    }

    $modeIndex = PhpTokenStream::nextSignificantIndex($tokens, $comma + 1);
    if ($modeIndex === null) {
        return true;
    }

    $mode = coretsia_atomic_write_gate_string_literal_value($tokens[$modeIndex] ?? null);

    if ($mode === null) {
        return true;
    }

    return !coretsia_atomic_write_gate_is_read_only_fopen_mode($mode);
}

function coretsia_atomic_write_gate_is_read_only_fopen_mode(string $mode): bool
{
    $mode = \strtolower(\trim($mode));

    return \in_array($mode, ['r', 'rb', 'rt'], true);
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_read_class_name(array $tokens, int $start): ?string
{
    $out = '';
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        $t = $tokens[$i];

        if (\is_array($t) && $t[0] === \T_WHITESPACE) {
            continue;
        }

        if (\is_array($t)) {
            $id = $t[0];
            $text = (string) $t[1];

            if ($id === \T_STRING) {
                $out .= $text;
                continue;
            }

            if (\defined('T_NAME_FULLY_QUALIFIED') && $id === \T_NAME_FULLY_QUALIFIED) {
                return $text;
            }

            if (\defined('T_NAME_QUALIFIED') && $id === \T_NAME_QUALIFIED) {
                return $text;
            }
        }

        if ((string) $t === '\\') {
            $out .= '\\';
            continue;
        }

        break;
    }

    return $out !== '' ? $out : null;
}

/**
 * @param list<mixed> $tokens
 */
function coretsia_atomic_write_gate_find_next_top_level_comma(array $tokens, int $openIndex): ?int
{
    $depth = 0;
    $count = \count($tokens);

    for ($i = $openIndex; $i < $count; $i++) {
        $text = coretsia_atomic_write_gate_token_text($tokens[$i]);

        if ($text === '(' || $text === '[' || PhpTokenStream::isCurlyOpenToken($tokens[$i])) {
            $depth++;
            continue;
        }

        if ($text === ')' || $text === ']' || $text === '}') {
            $depth--;

            if ($depth <= 0) {
                return null;
            }

            continue;
        }

        if ($text === ',' && $depth === 1) {
            return $i;
        }
    }

    return null;
}

function coretsia_atomic_write_gate_token_text(mixed $token): string
{
    if (\is_array($token)) {
        return (string) $token[1];
    }

    return (string) $token;
}

function coretsia_atomic_write_gate_string_literal_value(mixed $token): ?string
{
    if (!\is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }

    return PhpTokenStream::decodeStringLiteral((string) $token[1]);
}

function coretsia_atomic_write_gate_is_valid_repo_relative_path(string $path): bool
{
    if ($path === '' || RepositoryContext::isAbsolutePath($path)) {
        return false;
    }

    if (\str_contains($path, '\\') || \str_contains($path, "\0")) {
        return false;
    }

    if (\str_contains($path, '*') || \str_contains($path, '?') || \str_contains($path, '[') || \str_contains(
        $path,
        ']',
    )) {
        return false;
    }

    if (
        $path === '.'
        || $path === '..'
        || \str_starts_with($path, './')
        || \str_starts_with($path, '../')
        || \str_contains($path, '/./')
        || \str_contains($path, '/../')
        || \str_ends_with($path, '/.')
        || \str_ends_with($path, '/..')
        || \str_contains($path, '//')
    ) {
        return false;
    }

    return \preg_match('/\A[A-Za-z0-9._\/-]+\z/', $path) === 1;
}
