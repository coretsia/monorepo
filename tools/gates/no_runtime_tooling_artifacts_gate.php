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
    'CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION',
    'CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $catalog = WorkspacePackageCatalog::discover($repository);

        $scanRoots = coretsia_no_runtime_tooling_artifacts_gate_collect_scan_roots(
            $repository,
            $catalog,
        );

        if ($scanRoots === []) {
            return [];
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (
            PhpSourceFinder::findMany(
                $scanRoots,
                ['tests', 'Tests', 'fixtures', 'Fixtures', 'vendor'],
            ) as $absPath
        ) {
            $source = DeterministicFile::readTextNormalizedEol($absPath);
            $repoRelativePath = $repository->relativeToRepo($absPath);

            foreach (coretsia_no_runtime_tooling_artifacts_gate_detect_reasons($source) as $reason) {
                $violations[] = $repoRelativePath . ': ' . $reason;
            }
        }

        return $violations;
    },
));

/**
 * @return list<string>
 */
function coretsia_no_runtime_tooling_artifacts_gate_collect_scan_roots(
    RepositoryContext $repository,
    WorkspacePackageCatalog $catalog,
): array {
    /** @var list<string> $roots */
    $roots = [];

    foreach ($catalog->layeredPackages() as $product) {
        if ($product['layer'] === 'devtools') {
            continue;
        }

        foreach (['src', 'config'] as $relativeRoot) {
            $root = $product['absolutePath'] . '/' . $relativeRoot;

            if (!is_dir($root)) {
                continue;
            }

            $roots[] = $repository->resolveExistingDirectory($root);
        }
    }

    return $roots;
}

/**
 * @return list<string>
 */
function coretsia_no_runtime_tooling_artifacts_gate_detect_reasons(string $source): array
{
    $source = coretsia_no_runtime_tooling_artifacts_gate_without_comments($source);

    /** @var array<string, true> $reasons */
    $reasons = [];

    if (
        coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
            $source,
            'Coretsia\\Tools\\',
        )
    ) {
        $reasons['runtime-imports-tools'] = true;
    }

    if (
        coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
            $source,
            'Coretsia\\Devtools\\',
        )
    ) {
        $reasons['runtime-imports-devtools'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_devtools_package_reference($source)) {
        $reasons['runtime-references-devtools-package'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_architecture_artifact_path($source)) {
        $reasons['runtime-reads-architecture-artifact'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($source)) {
        $reasons['runtime-reads-repository-tools'] = true;
    }

    if (coretsia_no_runtime_tooling_artifacts_gate_contains_executed_tooling_path($source)) {
        $reasons['runtime-executes-tooling-path'] = true;
    }

    $out = \array_keys($reasons);
    \sort($out, \SORT_STRING);

    return $out;
}

function coretsia_no_runtime_tooling_artifacts_gate_without_comments(string $source): string
{
    $out = '';

    foreach (\token_get_all($source) as $token) {
        if (!\is_array($token)) {
            $out .= $token;
            continue;
        }

        if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
            $lineBreaks = \substr_count($token[1], "\n");

            $out .= $lineBreaks === 0
                ? ' '
                : \str_repeat("\n", $lineBreaks);

            continue;
        }

        $out .= $token[1];
    }

    return $out;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_namespace_prefix(
    string $source,
    string $namespacePrefix,
): bool {
    $escapedPrefix = \str_replace('\\', '\\\\', $namespacePrefix);

    return \str_contains($source, $namespacePrefix)
        || \str_contains($source, $escapedPrefix);
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_devtools_package_reference(string $source): bool
{
    foreach (
        [
            'devtools/internal-toolkit',
            'coretsia/devtools-internal-toolkit',
        ] as $needle
    ) {
        if (\str_contains($source, $needle)) {
            return true;
        }
    }

    return false;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_architecture_artifact_path(string $source): bool
{
    return \preg_match(
        '~(?<![A-Za-z0-9_.-])var[\\\\/]+arch(?:[\\\\/]+|\\b)~iu',
        $source,
    ) === 1;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path(string $source): bool
{
    foreach (
        [
            '~(?<![A-Za-z0-9_.-])tools[\\\\/]+~iu',
        ] as $pattern
    ) {
        if (\preg_match($pattern, $source) === 1) {
            return true;
        }
    }

    return false;
}

function coretsia_no_runtime_tooling_artifacts_gate_contains_executed_tooling_path(string $source): bool
{
    if (!coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($source)) {
        return false;
    }

    $source = \str_replace(["\r\n", "\r"], "\n", $source);

    foreach (\explode("\n", $source) as $line) {
        if (!coretsia_no_runtime_tooling_artifacts_gate_contains_tooling_path($line)) {
            continue;
        }

        if (
            \preg_match(
                '~\\b(?:exec|shell_exec|system|passthru|proc_open|popen)\\s*\\(~iu',
                $line,
            ) === 1
        ) {
            return true;
        }

        if (\str_contains($line, '`')) {
            return true;
        }

        if (
            \preg_match(
                '~(?:^|[^A-Za-z0-9_-])(?:php|composer|sh|bash)\\s+[^\\n]*(?<![A-Za-z0-9_.-])tools[\\\\/]+~iu',
                $line,
            ) === 1
        ) {
            return true;
        }
    }

    return false;
}
