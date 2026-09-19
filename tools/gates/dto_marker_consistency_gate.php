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
    'CORETSIA_DTO_MARKER_VIOLATION',
    'CORETSIA_DTO_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        return coretsia_dto_marker_consistency_gate_scan(
            $repository,
            $catalog,
            $scanRoot,
        );
    },
));

/**
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_scan(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
    ?string $scanRoot,
): array {
    $files = coretsia_dto_marker_consistency_gate_collect_php_source_files(
        $repository,
        $catalog,
        $scanRoot,
    );

    $markerPackage = $catalog->byComposerName('coretsia/core-dto-attribute');

    if ($markerPackage === null) {
        throw new \RuntimeException('canonical-dto-marker-package-missing');
    }

    $canonicalMarkerFile = $repository->resolveExistingFile(
        $markerPackage['absolutePath'] . '/src/Attribute/Dto.php',
    );

    /** @var list<string> $diagnostics */
    $diagnostics = [];
    $hasCanonicalMarkerStrategy = false;

    /** @var list<string> $alternativeStrategyPaths */
    $alternativeStrategyPaths = [];

    foreach ($files as $file) {
        $relativePath = $repository->relativeToRepo($file);

        foreach (
            coretsia_dto_marker_consistency_gate_analyze_php_file(
                $file,
                $relativePath,
                $canonicalMarkerFile,
                $hasCanonicalMarkerStrategy,
                $alternativeStrategyPaths,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    if ($hasCanonicalMarkerStrategy && $alternativeStrategyPaths !== []) {
        $uniqueAlternativePaths = \array_values(\array_unique($alternativeStrategyPaths));
        \sort($uniqueAlternativePaths, \SORT_STRING);

        foreach ($uniqueAlternativePaths as $path) {
            $diagnostics[] = $path . ': multiple-dto-marker-strategies';
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_collect_php_source_files(
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
 * @param list<string> $alternativeStrategyPaths
 *
 * @return list<string>
 */
function coretsia_dto_marker_consistency_gate_analyze_php_file(
    string $path,
    string $relativePath,
    string $canonicalMarkerFile,
    bool &$hasCanonicalMarkerStrategy,
    array &$alternativeStrategyPaths,
): array {
    $stream = PhpTokenStream::fromFile($path);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($stream->attributeNames() as $rawAttributeName) {
        $resolvedAttributeName = $stream->resolveClassName($rawAttributeName);

        if (\strcasecmp($resolvedAttributeName, 'Coretsia\\Dto\\Attribute\\Dto') === 0) {
            $hasCanonicalMarkerStrategy = true;
            continue;
        }

        if (coretsia_dto_marker_consistency_gate_is_dto_marker_like_name($resolvedAttributeName)) {
            $diagnostics[] = $relativePath . ': non-canonical-dto-marker';
            $alternativeStrategyPaths[] = $relativePath;
        }
    }

    foreach (coretsia_dto_marker_consistency_gate_extract_class_likes($stream) as $classLike) {
        $shortName = $classLike['name'];
        $fqn = $stream->namespace() === ''
            ? $shortName
            : $stream->namespace() . '\\' . $shortName;

        if (
            $classLike['kind'] === 'class'
            && \strcasecmp($fqn, 'Coretsia\\Dto\\Attribute\\Dto') === 0
            && $path === $canonicalMarkerFile
        ) {
            $hasCanonicalMarkerStrategy = true;
            continue;
        }

        if (
            $classLike['kind'] === 'class'
            && coretsia_dto_marker_consistency_gate_class_like_has_native_attribute(
                $classLike['attributes'],
                $stream,
            )
            && coretsia_dto_marker_consistency_gate_is_dto_marker_class_name($shortName)
        ) {
            $diagnostics[] = $relativePath . ': custom-dto-marker-class';
            $alternativeStrategyPaths[] = $relativePath;
            continue;
        }

        if (
            $classLike['kind'] === 'interface'
            && coretsia_dto_marker_consistency_gate_is_legacy_dto_interface_name($shortName)
        ) {
            $diagnostics[] = $relativePath . ': legacy-dto-interface-marker';
            $alternativeStrategyPaths[] = $relativePath;
        }
    }

    return $diagnostics;
}

/**
 * @return list<array{kind:string,name:string,attributes:list<string>}>
 */
function coretsia_dto_marker_consistency_gate_extract_class_likes(PhpTokenStream $stream): array
{
    $tokens = $stream->tokens();
    /** @var list<array{kind:string,name:string,attributes:list<string>}> $classLikes */
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

        if (\is_array($token) && ($token[0] === T_CLASS || $token[0] === T_INTERFACE)) {
            if (
                $token[0] === T_CLASS
                && $stream->previousSignificantTokenIsNew($i)
            ) {
                $pendingAttributes = [];
                continue;
            }

            $name = $stream->nextClassLikeName($i);

            if ($name !== null) {
                $classLikes[] = [
                    'kind' => $token[0] === T_CLASS ? 'class' : 'interface',
                    'name' => $name,
                    'attributes' => $pendingAttributes,
                ];
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
function coretsia_dto_marker_consistency_gate_class_like_has_native_attribute(
    array $attributeNames,
    PhpTokenStream $stream,
): bool {
    foreach ($attributeNames as $attributeName) {
        if (\strcasecmp($stream->resolveClassName($attributeName), 'Attribute') === 0) {
            return true;
        }
    }

    return false;
}

function coretsia_dto_marker_consistency_gate_is_dto_marker_like_name(string $fqn): bool
{
    if (\strcasecmp($fqn, 'Coretsia\\Dto\\Attribute\\Dto') === 0) {
        return false;
    }

    $shortName = \strtolower(
        coretsia_dto_marker_consistency_gate_short_class_name($fqn),
    );

    return $shortName === 'dto'
        || \str_ends_with($shortName, 'dto')
        || \str_ends_with($shortName, 'dtoattribute')
        || \str_ends_with($shortName, 'dtomarker')
        || \str_contains($shortName, 'dtomarker')
        || \str_contains($shortName, 'dtoattribute');
}

function coretsia_dto_marker_consistency_gate_is_dto_marker_class_name(string $shortName): bool
{
    $shortName = \strtolower($shortName);

    return $shortName === 'dto'
        || \str_ends_with($shortName, 'dtoattribute')
        || \str_ends_with($shortName, 'dtomarker')
        || \str_contains($shortName, 'dtomarker')
        || \str_contains($shortName, 'dtoattribute');
}

function coretsia_dto_marker_consistency_gate_is_legacy_dto_interface_name(string $shortName): bool
{
    $shortName = \strtolower($shortName);

    return $shortName === 'dtointerface'
        || \str_ends_with($shortName, 'dtointerface');
}

function coretsia_dto_marker_consistency_gate_short_class_name(string $fqn): string
{
    $segments = \explode('\\', \trim($fqn, '\\'));

    return (string) \end($segments);
}
