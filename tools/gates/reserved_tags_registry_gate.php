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
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
    'CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $repository = coretsia_reserved_tags_registry_gate_resolve_repository(
            $repository,
            isset($_SERVER['argv']) && is_array($_SERVER['argv'])
                ? $_SERVER['argv']
                : [],
        );

        $catalog = WorkspacePackageCatalog::discover($repository);
        $tagsFile = $repository->resolveExistingFile('docs/ssot/tags.md');

        $expectedConstants = coretsia_reserved_tags_registry_gate_parse_tag_registry($tagsFile);

        if ($expectedConstants === []) {
            throw new RuntimeException('tag-registry-empty');
        }

        /** @var array<string,true> $knownReservedTags */
        $knownReservedTags = [];

        foreach (array_keys($expectedConstants) as $tag) {
            $knownReservedTags[$tag] = true;
        }

        /** @var list<string> $violations */
        $violations = [];

        $reservedTagsRelPath = 'packages/core/foundation/src/Tag/ReservedTags.php';

        $reservedTagsFile = null;
        $foundation = $catalog->byPackageId('core/foundation');

        if ($foundation === null) {
            $violations[] = $reservedTagsRelPath . ': reserved-tags-registry-missing';
        } else {
            $candidate = $foundation['absolutePath'] . '/src/Tag/ReservedTags.php';

            if (!is_file($candidate)) {
                $violations[] = $reservedTagsRelPath . ': reserved-tags-registry-missing';
            } else {
                $reservedTagsFile = $repository->resolveExistingFile($candidate);
                $reservedTagsRelPath = $repository->relativeToRepo($reservedTagsFile);

                $reservedConstants = coretsia_reserved_tags_registry_gate_extract_constant_declarations(
                    $reservedTagsFile,
                );

                $reservedConstantsByName = [];

                foreach ($reservedConstants as $constant) {
                    $reservedConstantsByName[$constant['name']][] = $constant;
                }

                foreach ($expectedConstants as $tag => $constantName) {
                    $matches = $reservedConstantsByName[$constantName] ?? [];

                    if ($matches === []) {
                        $violations[] = $reservedTagsRelPath
                            . ': reserved-tag-constant-missing:'
                            . $constantName;

                        continue;
                    }

                    foreach ($matches as $constant) {
                        if ($constant['visibility'] !== 'public') {
                            $violations[] = $reservedTagsRelPath
                                . ': reserved-tag-constant-not-public:'
                                . $constantName;

                            continue;
                        }

                        if ($constant['value'] !== $tag) {
                            $violations[] = $reservedTagsRelPath
                                . ': reserved-tag-constant-value-mismatch:'
                                . $constantName;
                        }
                    }
                }

                foreach ($reservedConstants as $constant) {
                    if ($constant['visibility'] !== 'public') {
                        continue;
                    }

                    $value = $constant['value'];

                    if (
                        $value === null
                        || !coretsia_reserved_tags_registry_gate_is_tag_like($value)
                    ) {
                        continue;
                    }

                    if (!isset($expectedConstants[$value])) {
                        $violations[] = $reservedTagsRelPath
                            . ': reserved-tag-extra-public-constant:'
                            . $constant['name'];

                        continue;
                    }

                    if (
                        $constant['name']
                        !== $expectedConstants[$value]
                    ) {
                        $violations[] = $reservedTagsRelPath
                            . ': reserved-tag-extra-public-constant:'
                            . $constant['name'];
                    }
                }
            }
        }

        $packageSrcFiles = coretsia_reserved_tags_registry_gate_package_src_php_files(
            $repository,
            $catalog,
        );

        foreach ($packageSrcFiles as $absPath) {
            $relPath = $repository->relativeToRepo($absPath);

            if (str_ends_with($relPath, '/src/Provider/Tags.php')) {
                $violations[] = $relPath . ': provider-tags-file-forbidden';
            }

            if (
                $reservedTagsFile !== null
                && $absPath === $reservedTagsFile
            ) {
                continue;
            }

            foreach (
                coretsia_reserved_tags_registry_gate_extract_constant_declarations(
                    $absPath,
                ) as $constant
            ) {
                $value = $constant['value'];

                if (
                    $value !== null
                    && isset($knownReservedTags[$value])
                ) {
                    $violations[] = $relPath
                        . ': reserved-tag-local-constant-forbidden:'
                        . $constant['name'];

                    continue;
                }

                if ($constant['references_reserved_tags']) {
                    $violations[] = $relPath
                        . ': reserved-tag-local-constant-forbidden:'
                        . $constant['name'];
                }
            }
        }

        return $violations;
    },
));

/**
 * @return array<string, string> tag => expected ReservedTags constant name
 */
function coretsia_reserved_tags_registry_gate_parse_tag_registry(string $tagsFile): array
{
    $content = DeterministicFile::readTextNormalizedEol($tagsFile);
    $lines = \preg_split('/\R/u', $content);

    if (!\is_array($lines)) {
        throw new \RuntimeException('tag-registry-lines-invalid');
    }

    /** @var array<string, string> $tags */
    $tags = [];

    $inRegistry = false;

    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if ($trimmed === '## Reserved Tag Registry (MUST)') {
            $inRegistry = true;
            continue;
        }

        if ($inRegistry && \str_starts_with($trimmed, '## ')) {
            break;
        }

        if (!$inRegistry) {
            continue;
        }

        if (!\str_starts_with($trimmed, '|')) {
            continue;
        }

        if (\str_contains($trimmed, '| tag ') || \str_contains($trimmed, '|---')) {
            continue;
        }

        if (!\preg_match('/^\|\s*`([^`]+)`\s*\|\s*`([^`]+)`\s*\|/u', $trimmed, $m)) {
            continue;
        }

        $tag = $m[1];
        $ownerPackageId = $m[2];

        if (!coretsia_reserved_tags_registry_gate_is_tag_like($tag)) {
            throw new \RuntimeException('tag-registry-tag-invalid');
        }

        if (
            \preg_match(
                '/\A([a-z0-9]+(?:[._-][a-z0-9]+)*)\/([a-z0-9]+(?:[._-][a-z0-9]+)*)\z/',
                $ownerPackageId,
                $ownerMatches,
            ) !== 1
            || !WorkspacePackageCatalog::isLayeredRoot($ownerMatches[1])
        ) {
            throw new \RuntimeException('tag-registry-owner-invalid');
        }

        if (isset($tags[$tag])) {
            throw new \RuntimeException('tag-registry-duplicate-tag');
        }

        $tags[$tag] = coretsia_reserved_tags_registry_gate_constant_name_for_tag($tag);
    }

    return $tags;
}

function coretsia_reserved_tags_registry_gate_constant_name_for_tag(string $tag): string
{
    return \strtoupper(\str_replace('.', '_', $tag));
}

function coretsia_reserved_tags_registry_gate_is_tag_like(string $value): bool
{
    return \preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/', $value) === 1;
}

/**
 * @param list<mixed> $argv
 */
function coretsia_reserved_tags_registry_gate_resolve_repository(
    RepositoryContext $repository,
    array $argv,
): RepositoryContext {
    $root = null;

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if (!is_string($arg) || !str_starts_with($arg, '--root=')) {
            throw new RuntimeException('reserved-tags-argument-invalid');
        }

        if ($root !== null) {
            throw new RuntimeException('reserved-tags-root-duplicate');
        }

        $root = substr($arg, strlen('--root='));

        if ($root === '') {
            throw new RuntimeException('reserved-tags-root-empty');
        }
    }

    return $root === null
        ? $repository
        : RepositoryContext::fromRepoRoot($root);
}

/**
 * @return list<string>
 */
function coretsia_reserved_tags_registry_gate_package_src_php_files(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
): array {
    $roots = [];

    foreach ($catalog->all() as $product) {
        $src = $product['absolutePath'] . '/src';

        if (!is_dir($src)) {
            continue;
        }

        $roots[] = $repository->resolveExistingDirectory($src);
    }

    return PhpSourceFinder::findMany($roots);
}

/**
 * @return list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}>
 */
function coretsia_reserved_tags_registry_gate_extract_constant_declarations(string $phpFile): array
{
    $source = DeterministicFile::readTextNormalizedEol($phpFile);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    /** @var list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}> $constants */
    $constants = [];

    $pendingVisibility = 'public';
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            if ($token === ';' || $token === '{' || $token === '}') {
                $pendingVisibility = 'public';
            }

            continue;
        }

        if ($token[0] === \T_PUBLIC) {
            $pendingVisibility = 'public';
            continue;
        }

        if ($token[0] === \T_PROTECTED) {
            $pendingVisibility = 'protected';
            continue;
        }

        if ($token[0] === \T_PRIVATE) {
            $pendingVisibility = 'private';
            continue;
        }

        if ($token[0] !== \T_CONST) {
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

        foreach (
            coretsia_reserved_tags_registry_gate_extract_constants_from_declaration(
                $declarationTokens,
                $pendingVisibility,
            ) as $constant
        ) {
            $constants[] = $constant;
        }

        $pendingVisibility = 'public';
        $i = $j;
    }

    return $constants;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $declarationTokens
 *
 * @return list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}>
 */
function coretsia_reserved_tags_registry_gate_extract_constants_from_declaration(
    array $declarationTokens,
    string $visibility,
): array {
    /** @var list<list<array{0:int, 1:string, 2:int}|string>> $segments */
    $segments = [];
    $current = [];
    $squareDepth = 0;
    $parenDepth = 0;
    $braceDepth = 0;

    foreach ($declarationTokens as $token) {
        if ($token === '[') {
            $squareDepth++;
        } elseif ($token === ']') {
            $squareDepth = \max(0, $squareDepth - 1);
        } elseif ($token === '(') {
            $parenDepth++;
        } elseif ($token === ')') {
            $parenDepth = \max(0, $parenDepth - 1);
        } elseif ($token === '{') {
            $braceDepth++;
        } elseif ($token === '}') {
            $braceDepth = \max(0, $braceDepth - 1);
        }

        if ($token === ',' && $squareDepth === 0 && $parenDepth === 0 && $braceDepth === 0) {
            $segments[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    if ($current !== []) {
        $segments[] = $current;
    }

    /** @var list<array{name:string, value:string|null, visibility:string, references_reserved_tags:bool}> $constants */
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
        $nameIndex = null;

        for ($i = $equalsIndex - 1; $i >= 0; $i--) {
            $token = $segment[$i];

            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === \T_STRING) {
                $name = $token[1];
                $nameIndex = $i;
                break;
            }
        }

        if ($name === null || $name === '' || $nameIndex === null) {
            continue;
        }

        $declaredType = coretsia_reserved_tags_registry_gate_declared_const_type($segment, $nameIndex);
        $isStringLikeConstant = $declaredType === null || $declaredType === 'string';

        $value = null;
        $referencesReservedTags = false;
        $segmentCount = \count($segment);

        for ($i = $equalsIndex + 1; $i < $segmentCount; $i++) {
            $token = $segment[$i];

            if (\is_array($token)) {
                if (
                    $isStringLikeConstant
                    && $value === null
                    && $token[0] === \T_CONSTANT_ENCAPSED_STRING
                ) {
                    $value = coretsia_reserved_tags_registry_gate_decode_php_string_literal($token[1]);
                    continue;
                }

                if (
                    coretsia_reserved_tags_registry_gate_is_name_token($token[0])
                    && coretsia_reserved_tags_registry_gate_last_name_segment($token[1]) === 'ReservedTags'
                ) {
                    $referencesReservedTags = true;
                }
            }
        }

        $constants[] = [
            'name' => $name,
            'value' => $isStringLikeConstant ? $value : null,
            'visibility' => $visibility,
            'references_reserved_tags' => $referencesReservedTags,
        ];
    }

    return $constants;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $segment
 */
function coretsia_reserved_tags_registry_gate_declared_const_type(array $segment, int $nameIndex): ?string
{
    for ($i = $nameIndex - 1; $i >= 0; $i--) {
        $token = $segment[$i];

        if (coretsia_reserved_tags_registry_gate_is_ignorable_token($token)) {
            continue;
        }

        if (!\is_array($token)) {
            return null;
        }

        if ($token[0] !== \T_STRING) {
            return null;
        }

        $candidate = \strtolower($token[1]);

        return \in_array(
            $candidate,
            ['array', 'bool', 'false', 'float', 'int', 'mixed', 'null', 'string', 'true'],
            true,
        ) ? $candidate : null;
    }

    return null;
}

/**
 * @param int $id
 */
function coretsia_reserved_tags_registry_gate_is_name_token(int $id): bool
{
    return $id === \T_STRING
        || $id === (\defined('T_NAME_QUALIFIED') ? \T_NAME_QUALIFIED : -1)
        || $id === (\defined('T_NAME_FULLY_QUALIFIED') ? \T_NAME_FULLY_QUALIFIED : -1)
        || $id === (\defined('T_NAME_RELATIVE') ? \T_NAME_RELATIVE : -1);
}

function coretsia_reserved_tags_registry_gate_last_name_segment(string $name): string
{
    $name = \ltrim($name, '\\');
    $parts = \explode('\\', $name);

    return (string) ($parts[\count($parts) - 1] ?? $name);
}

/**
 * @param array{0:int,1:string,2?:int}|string $token
 */
function coretsia_reserved_tags_registry_gate_is_ignorable_token(array|string $token): bool
{
    if (!\is_array($token)) {
        return false;
    }

    return $token[0] === \T_WHITESPACE
        || $token[0] === \T_COMMENT
        || $token[0] === \T_DOC_COMMENT;
}

function coretsia_reserved_tags_registry_gate_decode_php_string_literal(string $literal): string
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
