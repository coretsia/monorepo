#!/usr/bin/env php
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

use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';

final class DeptracGenerateTool
{
    private const string DEPENDENCY_TABLE_PATH = 'docs/architecture/DEPENDENCIES.md';

    /**
     * @var list<string>
     */
    private const array DEFAULT_EXCLUDE_FILES = [
        '#(^|/)fixtures(/|$)#',
        '#(^|/)tests(/|$)#',
        '#(^|/)vendor(/|$)#',
    ];

    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);

        $check = self::argFlag($argv, '--check');
        $apply = self::argFlag($argv, '--apply') || !$check;

        $outPath = $repository->resolve(
            self::argValue($argv, '--out') ?? 'tools/testing/deptrac.yaml',
        );
        $allowlistPath = $repository->resolve(
            self::argValue($argv, '--allowlist') ?? 'tools/testing/deptrac.allowlist.yaml',
        );
        $artifactsDir = $repository->resolve(
            self::argValue($argv, '--artifacts-dir') ?? 'var/arch',
        );

        $allowlistMissing = !is_file($allowlistPath);
        $excludeFiles = $allowlistMissing
            ? self::DEFAULT_EXCLUDE_FILES
            : self::readAllowlistAsExcludeFiles($allowlistPath);

        self::assertAllowlistPolicy($excludeFiles);

        $ssotPath = $repository->resolveExistingFile(self::DEPENDENCY_TABLE_PATH);
        $ssotRuleset = self::readSsotDependencyTable($ssotPath);
        self::assertNoPackageCycles($ssotRuleset);

        $catalog = WorkspacePackageCatalog::discover($repository);
        $packageIndex = self::scanPackages($catalog);
        $packageIndexContext = self::buildPackageIndexContext($repository, $packageIndex);

        self::assertSsotRowsMatchDiscoveredPackages(
            $packageIndexContext['packages'],
            $ssotRuleset,
        );

        self::assertComposerEdgesMatchSsot(
            $packageIndexContext['packages'],
            $ssotRuleset,
            $packageIndexContext['composerNameToPackageId'],
        );

        $model = self::buildDeptracModel(
            $repository,
            $outPath,
            $packageIndexContext,
            $ssotRuleset,
            $excludeFiles,
        );

        $yaml = self::renderDeptracYaml($model);
        $changed = self::isDifferentFile($outPath, $yaml);

        if ($check) {
            if ($changed || $allowlistMissing) {
                $diagnostics = [];

                if ($changed) {
                    $diagnostics[] = $repository->relativeToRepo($outPath);
                }

                if ($allowlistMissing) {
                    $diagnostics[] = $repository->relativeToRepo($allowlistPath);
                }

                $diagnostics[] = 'Run: php tools/build/deptrac_generate.php --apply';

                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_DEPTRAC_OUT_OF_DATE,
                    $diagnostics,
                );

                return 1;
            }

            return 0;
        }

        $graphArtifacts = self::renderGraphArtifacts(
            $artifactsDir,
            $model['nodes'],
            $model['edges'],
        );

        /** @var list<string> $changedGraphPaths */
        $changedGraphPaths = [];

        foreach ($graphArtifacts as $path => $content) {
            if (self::isDifferentFile($path, $content)) {
                $changedGraphPaths[] = $path;
            }
        }

        sort($changedGraphPaths, SORT_STRING);

        if ($apply) {
            if ($allowlistMissing) {
                DeterministicFile::writeTextLf(
                    $allowlistPath,
                    self::renderDefaultAllowlistYaml(),
                );
            }

            if ($changed) {
                DeterministicFile::writeTextLf($outPath, $yaml);
            }

            foreach ($changedGraphPaths as $path) {
                DeterministicFile::writeTextLf($path, $graphArtifacts[$path]);
            }
        }

        ConsoleOutput::line('OK', false);

        if ($allowlistMissing) {
            ConsoleOutput::line($repository->relativeToRepo($allowlistPath), false);
        }

        if ($changed) {
            ConsoleOutput::line($repository->relativeToRepo($outPath), false);
        }

        if ($changedGraphPaths !== []) {
            ConsoleOutput::line($repository->relativeToRepo($artifactsDir), false);
        }

        return 0;
    }

    public static function emitFailure(Throwable $e): void
    {
        $message = str_replace(["\r\n", "\r"], "\n", $e->getMessage());

        foreach (
            [
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING,
                ErrorCodes::CORETSIA_DEPTRAC_CYCLE_DETECTED,
                ErrorCodes::CORETSIA_DEPTRAC_ALLOWLIST_INVALID,
                ErrorCodes::CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT,
                ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED,
            ] as $code
        ) {
            if ($message === $code) {
                ConsoleOutput::codeWithDiagnostics($code);

                return;
            }

            if (!str_starts_with($message, $code . ':')) {
                continue;
            }

            $payload = trim(substr($message, strlen($code) + 1));
            $diagnostics = [];

            foreach (explode("\n", $payload) as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $diagnostics[] = $line;
                }
            }

            ConsoleOutput::codeWithDiagnostics($code, $diagnostics);

            return;
        }

        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED,
        );
    }

    /**
     * @param list<array{
     *     id:string,
     *     packageId:string,
     *     layer:string,
     *     slug:string,
     *     composerName:string,
     *     path:string,
     *     srcPath:string,
     *     psr4:string,
     *     requireNames:list<string>
     * }> $packageIndex
     * @param array<string, list<string>> $ssotRuleset
     */
    private static function assertSsotRowsMatchDiscoveredPackages(
        array $packageIndex,
        array $ssotRuleset,
    ): void {
        /** @var array<string,true> $discovered */
        $discovered = [];

        foreach ($packageIndex as $package) {
            $discovered[$package['packageId']] = true;
        }

        /** @var list<string> $missing */
        $missing = [];

        foreach (array_keys($discovered) as $packageId) {
            if (!array_key_exists($packageId, $ssotRuleset)) {
                $missing[] = $packageId;
            }
        }

        /** @var list<string> $unexpected */
        $unexpected = [];

        foreach (array_keys($ssotRuleset) as $packageId) {
            if (!isset($discovered[$packageId])) {
                $unexpected[] = $packageId;
            }
        }

        sort($missing, SORT_STRING);
        sort($unexpected, SORT_STRING);

        if ($missing === [] && $unexpected === []) {
            return;
        }

        $message = ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table package set mismatch';

        if ($missing !== []) {
            $message .= "\nMissing rows:";

            foreach ($missing as $packageId) {
                $message .= "\n- " . $packageId;
            }
        }

        if ($unexpected !== []) {
            $message .= "\nNon-materialized rows:";

            foreach ($unexpected as $packageId) {
                $message .= "\n- " . $packageId;
            }
        }

        $message .= "\nFix: make "
            . self::DEPENDENCY_TABLE_PATH
            . ' match the materialized layered package catalog.';

        throw new RuntimeException($message);
    }

    /**
     * @return array<string, list<string>> package_id => package_id dependencies
     */
    private static function readSsotDependencyTable(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                . ': missing SSoT dependency table: '
                . self::DEPENDENCY_TABLE_PATH,
            );
        }

        $lines = explode("\n", DeterministicFile::readTextNormalizedEol($path));
        $heading = '## 4) Canonical direct dependency matrix (MUST)';
        $headingIndexes = [];
        $inCodeFence = false;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            if (str_starts_with($trimmedLine, '```')) {
                $inCodeFence = !$inCodeFence;
                continue;
            }

            if (!$inCodeFence && $trimmedLine === $heading) {
                $headingIndexes[] = $index;
            }
        }

        if (count($headingIndexes) !== 1) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table heading must appear exactly once',
            );
        }

        $index = $headingIndexes[0] + 1;
        $lineCount = count($lines);

        while ($index < $lineCount && trim($lines[$index]) === '') {
            $index++;
        }

        if ($index >= $lineCount) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table header missing',
            );
        }

        $headerCells = array_map(
            static fn (string $cell): string => trim($cell),
            explode('|', trim(trim($lines[$index]), '|')),
        );

        if ($headerCells !== ['package_id', 'depends_on', 'notes']) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table header invalid',
            );
        }

        $index++;

        if ($index >= $lineCount) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table separator missing',
            );
        }

        $separatorCells = array_map(
            static fn (string $cell): string => trim($cell),
            explode('|', trim(trim($lines[$index]), '|')),
        );

        if (
            count($separatorCells) !== 3
            || preg_match('/\A-+\z/', $separatorCells[0]) !== 1
            || preg_match('/\A-+\z/', $separatorCells[1]) !== 1
            || preg_match('/\A-+\z/', $separatorCells[2]) !== 1
        ) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table separator invalid',
            );
        }

        $index++;

        /** @var array<string, list<string>> $ruleset */
        $ruleset = [];
        $previousPackageId = null;

        for (; $index < $lineCount; $index++) {
            $line = trim($lines[$index]);

            if ($line === '' || !str_starts_with($line, '|')) {
                break;
            }

            $cells = array_map(
                static fn (string $cell): string => trim($cell),
                explode('|', trim($line, '|')),
            );

            if (count($cells) !== 3) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table row must contain exactly three columns',
                );
            }

            $packageId = $cells[0];
            $dependsOnCell = $cells[1];

            if (!self::isPackageId($packageId)) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table package id invalid',
                );
            }

            if (array_key_exists($packageId, $ruleset)) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                    . ': duplicate dependency table row '
                    . $packageId,
                );
            }

            if ($previousPackageId !== null && strcmp($previousPackageId, $packageId) >= 0) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table rows must be sorted by package_id',
                );
            }

            /** @var list<string> $deps */
            $deps = [];

            if ($dependsOnCell !== '—') {
                if ($dependsOnCell === '') {
                    throw new RuntimeException(
                        ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                        . ': dependency table depends_on cell invalid '
                        . $packageId,
                    );
                }

                $rawDeps = explode(',', $dependsOnCell);
                $deps = array_map(
                    static fn (string $dep): string => trim($dep),
                    $rawDeps,
                );

                if (implode(', ', $deps) !== $dependsOnCell) {
                    throw new RuntimeException(
                        ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                        . ': dependency table depends_on separator invalid '
                        . $packageId,
                    );
                }

                foreach ($deps as $dep) {
                    if (!self::isPackageId($dep)) {
                        throw new RuntimeException(
                            ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                            . ': dependency table dependency id invalid '
                            . $packageId,
                        );
                    }

                    if ($dep === $packageId) {
                        throw new RuntimeException(
                            ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                            . ': dependency table self-dependency '
                            . $packageId,
                        );
                    }
                }

                if (count(array_unique($deps)) !== count($deps)) {
                    throw new RuntimeException(
                        ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                        . ': dependency table dependencies must be unique '
                        . $packageId,
                    );
                }

                $sortedDeps = $deps;
                sort($sortedDeps, SORT_STRING);

                if ($sortedDeps !== $deps) {
                    throw new RuntimeException(
                        ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                        . ': dependency table dependencies must be sorted '
                        . $packageId,
                    );
                }
            }

            $ruleset[$packageId] = $deps;
            $previousPackageId = $packageId;
        }

        if ($ruleset === []) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': dependency table contains no package rows',
            );
        }

        foreach ($ruleset as $packageId => $deps) {
            foreach ($deps as $dep) {
                if (!array_key_exists($dep, $ruleset)) {
                    throw new RuntimeException(
                        ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING
                        . ': dependency table references missing row '
                        . $dep
                        . ' from '
                        . $packageId,
                    );
                }
            }
        }

        return $ruleset;
    }

    private static function isPackageId(string $value): bool
    {
        return preg_match(
            '/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\/[a-z0-9]+(?:[._-][a-z0-9]+)*\z/u',
            $value,
        ) === 1;
    }

    /**
     * @return list<array{
     *     id:string,
     *     packageId:string,
     *     layer:string,
     *     slug:string,
     *     composerName:string,
     *     path:string,
     *     srcPath:string,
     *     psr4:string,
     *     requireNames:list<string>
     * }>
     */
    private static function scanPackages(WorkspacePackageCatalog $catalog): array
    {
        $index = [];

        foreach ($catalog->layeredPackages() as $product) {
            $composer = ComposerJson::readObject($product['composerJsonPath']);
            $path = $product['relativePath'];

            $index[] = [
                'id' => self::packageIdToLayerId($product['packageId']),
                'packageId' => $product['packageId'],
                'layer' => $product['layer'],
                'slug' => $product['slug'],
                'composerName' => $product['composerName'],
                'path' => $path,
                'srcPath' => $path . '/src',
                'psr4' => self::extractPsr4(
                    $composer,
                    is_dir($product['absolutePath'] . '/src'),
                    $product['packageId'],
                ),
                'requireNames' => self::extractComposerRequireNames($composer),
            ];
        }

        usort(
            $index,
            static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']),
        );

        return $index;
    }

    /**
     * @param list<array{
     *     id:string,
     *     packageId:string,
     *     layer:string,
     *     slug:string,
     *     composerName:string,
     *     path:string,
     *     srcPath:string,
     *     psr4:string,
     *     requireNames:list<string>
     * }> $packageIndex
     *
     * @return array{
     *     packages:list<array{
     *         id:string,
     *         packageId:string,
     *         layer:string,
     *         slug:string,
     *         composerName:string,
     *         path:string,
     *         srcPath:string,
     *         psr4:string,
     *         requireNames:list<string>
     *     }>,
     *     activePackages:list<array{
     *         id:string,
     *         packageId:string,
     *         layer:string,
     *         slug:string,
     *         composerName:string,
     *         path:string,
     *         srcPath:string,
     *         psr4:string,
     *         requireNames:list<string>
     *     }>,
     *     activePackageIds:array<string, true>,
     *     composerNameToPackageId:array<string, string>
     * }
     */
    private static function buildPackageIndexContext(RepositoryContext $repository, array $packageIndex): array
    {
        /** @var array<string, string> $composerNameToPackageId */
        $composerNameToPackageId = [];

        /** @var list<array{id:string,packageId:string,layer:string,slug:string,composerName:string,path:string,srcPath:string,psr4:string,requireNames:list<string>}> $activePackages */
        $activePackages = [];

        /** @var array<string, true> $activePackageIds */
        $activePackageIds = [];

        foreach ($packageIndex as $package) {
            $composerNameToPackageId[$package['composerName']] = $package['packageId'];

            if (is_dir($repository->resolve($package['srcPath']))) {
                $activePackages[] = $package;
                $activePackageIds[$package['packageId']] = true;
            }
        }

        ksort($composerNameToPackageId, SORT_STRING);
        ksort($activePackageIds, SORT_STRING);

        usort(
            $activePackages,
            static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']),
        );

        return [
            'packages' => $packageIndex,
            'activePackages' => $activePackages,
            'activePackageIds' => $activePackageIds,
            'composerNameToPackageId' => $composerNameToPackageId,
        ];
    }

    /**
     * @param list<array{
     *     id:string,
     *     packageId:string,
     *     composerName:string,
     *     requireNames:list<string>
     * }> $packageIndex
     * @param array<string, list<string>> $ssotRuleset
     * @param array<string, string> $composerNameToPackageId
     */
    private static function assertComposerEdgesMatchSsot(
        array $packageIndex,
        array $ssotRuleset,
        array $composerNameToPackageId,
    ): void {
        /** @var list<string> $diagnostics */
        $diagnostics = [];

        foreach ($packageIndex as $package) {
            $sourcePackageId = $package['packageId'];

            if (!isset($ssotRuleset[$sourcePackageId])) {
                continue; // already handled by assertSsotRowsMatchDiscoveredPackages()
            }

            $allowedSet = array_flip($ssotRuleset[$sourcePackageId]);

            foreach ($package['requireNames'] as $composerName) {
                if (!isset($composerNameToPackageId[$composerName])) {
                    $diagnostics[] = 'source:' . $sourcePackageId
                        . ' target:unknown'
                        . ' reason:composer-edge-not-in-ssot';

                    continue;
                }

                $targetPackageId = $composerNameToPackageId[$composerName];

                if (!isset($allowedSet[$targetPackageId])) {
                    $diagnostics[] = 'source:' . $sourcePackageId
                        . ' target:' . $targetPackageId
                        . ' reason:composer-edge-not-in-ssot';
                }
            }
        }

        $diagnostics = array_values(array_unique($diagnostics));
        sort($diagnostics, SORT_STRING);

        if ($diagnostics === []) {
            return;
        }

        throw new RuntimeException(
            ErrorCodes::CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT
            . ': composer-edge-not-in-ssot'
            . "\n"
            . implode("\n", $diagnostics),
        );
    }

    /**
     * @param RepositoryContext $repository
     * @param string $outPath
     * @param array $packageIndexContext
     * @param array<string, list<string>> $ssotRuleset
     * @param list<string> $excludeFiles
     *
     * @return array{
     *     paths:list<string>,
     *     layers:list<array{name:string,pattern:string}>,
     *     ruleset:array<string, list<string>>,
     *     excludeFiles:list<string>,
     *     nodes:list<string>,
     *     edges:list<array{from:string,to:string}> }
     */
    private static function buildDeptracModel(
        RepositoryContext $repository,
        string $outPath,
        array $packageIndexContext,
        array $ssotRuleset,
        array $excludeFiles,
    ): array {
        $activePackages = $packageIndexContext['activePackages'];
        $activePackageIds = $packageIndexContext['activePackageIds'];

        /** @var list<string> $paths */
        $paths = [];

        /** @var list<array{name:string,pattern:string}> $layers */
        $layers = [];

        foreach ($activePackages as $package) {
            $paths[] = self::pathRelativeToConfigDir($repository, $outPath, $package['srcPath']);
            $layers[] = [
                'name' => $package['id'],
                'pattern' => self::classLikePatternForPsr4($package['psr4']),
            ];
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        usort(
            $layers,
            static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']),
        );

        $ruleset = self::buildRuleset(
            $activePackages,
            $activePackageIds,
            $ssotRuleset,
        );

        self::assertNoLayerCycles($ruleset);

        $nodes = array_keys($ruleset);
        sort($nodes, SORT_STRING);

        /** @var list<array{from:string,to:string}> $edges */
        $edges = [];
        foreach ($ruleset as $from => $deps) {
            foreach ($deps as $to) {
                $edges[] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        usort(
            $edges,
            static function (array $a, array $b): int {
                $left = (string) $a['from'] . '->' . (string) $a['to'];
                $right = (string) $b['from'] . '->' . (string) $b['to'];

                return strcmp($left, $right);
            },
        );

        return [
            'paths' => $paths,
            'layers' => $layers,
            'ruleset' => $ruleset,
            'excludeFiles' => $excludeFiles,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param list<array{id:string,packageId:string,layer:string,slug:string,composerName:string,path:string,srcPath:string,psr4:string,requireNames:list<string>}> $activePackages
     * @param array<string, true> $activePackageIds
     * @param array<string, list<string>> $ssotRuleset
     *
     * @return array<string, list<string>>
     */
    private static function buildRuleset(
        array $activePackages,
        array $activePackageIds,
        array $ssotRuleset,
    ): array {
        /** @var list<string> $missing */
        $missing = [];

        /** @var array<string, list<string>> $ruleset */
        $ruleset = [];

        foreach ($activePackages as $package) {
            $packageId = $package['packageId'];
            $layerId = $package['id'];

            if (!isset($ssotRuleset[$packageId])) {
                $missing[] = $packageId;
                continue;
            }

            $allowedPackageIds = $ssotRuleset[$packageId];

            $allowedLayers = [];

            foreach ($allowedPackageIds as $allowedPackageId) {
                if (!isset($activePackageIds[$allowedPackageId]) || $allowedPackageId === $packageId) {
                    continue;
                }

                $allowedLayers[] = self::packageIdToLayerId($allowedPackageId);
            }

            $allowedLayers = array_values(array_unique($allowedLayers));
            sort($allowedLayers, SORT_STRING);

            $ruleset[$layerId] = $allowedLayers;
        }

        $missing = array_values(array_unique($missing));
        sort($missing, SORT_STRING);

        if ($missing !== []) {
            $message = ErrorCodes::CORETSIA_DEPTRAC_SSOT_RULESET_MISSING . ': missing dependency policy for active package layers';

            foreach ($missing as $packageId) {
                $message .= "\n- " . $packageId;
            }

            $message .= "\nFix: add rows to " . self::DEPENDENCY_TABLE_PATH . '.';

            throw new RuntimeException($message);
        }

        ksort($ruleset, SORT_STRING);

        return $ruleset;
    }

    /**
     * @param array{
     *     paths:list<string>,
     *     layers:list<array{name:string,pattern:string}>,
     *     ruleset:array<string, list<string>>,
     *     excludeFiles:list<string>,
     *     nodes:list<string>,
     *     edges:list<array{from:string,to:string}>
     * } $model
     */
    private static function renderDeptracYaml(array $model): string
    {
        $out = '';
        $out .= self::licenseHeaderYaml();
        $out .= "# GENERATED FILE (deterministic). Do not edit manually.\n";
        $out .= "# Regenerate: php tools/build/deptrac_generate.php --apply\n\n";
        $out .= "deptrac:\n";
        $out .= "  cache_file: 'var/deptrac/.deptrac.cache'\n\n";

        $out .= "  paths:\n";
        foreach ($model['paths'] as $path) {
            $out .= "    - " . self::yamlSingleQuoted($path) . "\n";
        }

        if ($model['excludeFiles'] !== []) {
            $out .= "\n  exclude_files:\n";
            foreach ($model['excludeFiles'] as $pattern) {
                $out .= "    - " . self::yamlSingleQuoted($pattern) . "\n";
            }
        }

        $out .= "\n  layers:\n";
        foreach ($model['layers'] as $layer) {
            $out .= "    - name: " . self::yamlSingleQuoted($layer['name']) . "\n";
            $out .= "      collectors:\n";
            $out .= "        - type: classLike\n";
            $out .= "          value: " . self::yamlSingleQuoted($layer['pattern']) . "\n";
        }

        $out .= "\n  ruleset:\n";
        foreach ($model['ruleset'] as $layer => $deps) {
            if ($deps === []) {
                $out .= "    " . self::yamlSingleQuoted($layer) . ": [ ]\n";
                continue;
            }

            $out .= "    " . self::yamlSingleQuoted($layer) . ":\n";
            foreach ($deps as $dep) {
                $out .= "      - " . self::yamlSingleQuoted($dep) . "\n";
            }
        }

        return $out;
    }

    private static function renderDefaultAllowlistYaml(): string
    {
        $out = '';
        $out .= self::licenseHeaderYaml();
        $out .= "# Deptrac allowlist policy.\n";
        $out .= "# This file may only exclude tests, fixtures, vendors, or tooling-only files.\n";
        $out .= "# It MUST NOT exclude packages/**/src/**.\n\n";
        $out .= "exclude_files:\n";

        foreach (self::DEFAULT_EXCLUDE_FILES as $pattern) {
            $out .= "  - " . self::yamlSingleQuoted($pattern) . "\n";
        }

        return $out;
    }

    private static function classLikePatternForPsr4(string $psr4): string
    {
        $psr4 = trim($psr4);
        if ($psr4 === '') {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED
                . ': active layered package has no PSR-4 root',
            );
        }

        $root = rtrim($psr4, '\\');
        $root = str_replace('\\', '\\\\', $root);

        return '^' . $root . '\\\\.*';
    }

    /**
     * @return list<string>
     */
    private static function readAllowlistAsExcludeFiles(string $path): array
    {
        if (!is_file($path)) {
            return self::DEFAULT_EXCLUDE_FILES;
        }

        $raw = DeterministicFile::readTextNormalizedEol($path);

        /** @var list<string> $out */
        $out = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || $line === 'exclude_files:') {
                continue;
            }

            if (!str_starts_with($line, '-')) {
                continue;
            }

            $value = trim(substr($line, 1));
            $value = trim($value, "\"' ");

            if ($value === '') {
                continue;
            }

            $out[] = self::normalizeAllowlistPattern($value);
        }

        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);

        return $out;
    }

    private static function normalizeAllowlistPattern(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));

        if ($value === '') {
            throw new RuntimeException(ErrorCodes::CORETSIA_DEPTRAC_ALLOWLIST_INVALID . ': empty exclude_files entry');
        }

        if (str_starts_with($value, '#') || str_starts_with($value, '~')) {
            return $value;
        }

        $lower = strtolower($value);

        if ($lower === 'tests/**' || $lower === 'tests/*') {
            return '#(^|/)tests(/|$)#';
        }

        if ($lower === 'fixtures/**' || $lower === 'fixtures/*') {
            return '#(^|/)fixtures(/|$)#';
        }

        if ($lower === 'vendor/**' || $lower === 'vendor/*') {
            return '#(^|/)vendor(/|$)#';
        }

        $prefix = ltrim($value, '/');

        return '#^' . preg_quote($prefix, '#') . '#';
    }

    /**
     * @param list<string> $excludeFiles
     */
    private static function assertAllowlistPolicy(array $excludeFiles): void
    {
        foreach ($excludeFiles as $pattern) {
            $normalized = strtolower(str_replace('\\', '/', $pattern));

            if (str_contains($normalized, 'packages') && str_contains($normalized, '/src')) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_ALLOWLIST_INVALID . ': exclude_files must not cover packages/**/src/**',
                );
            }

            if (str_contains($normalized, '(^|/)src') || str_contains($normalized, '/src(/|$)')) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_ALLOWLIST_INVALID . ': exclude_files must not cover src/**',
                );
            }
        }
    }

    /**
     * @param array<string, list<string>> $ruleset
     */
    private static function assertNoPackageCycles(array $ruleset): void
    {
        self::assertNoCycles($ruleset, 'package');
    }

    /**
     * @param array<string, list<string>> $ruleset
     */
    private static function assertNoLayerCycles(array $ruleset): void
    {
        self::assertNoCycles($ruleset, 'layer');
    }

    /**
     * @param array<string, list<string>> $ruleset
     */
    private static function assertNoCycles(array $ruleset, string $kind): void
    {
        /** @var array<string, true> $visiting */
        $visiting = [];

        /** @var array<string, true> $visited */
        $visited = [];

        $nodes = array_keys($ruleset);
        sort($nodes, SORT_STRING);

        foreach ($nodes as $node) {
            self::visitCycleNode($node, $ruleset, $visiting, $visited, [], $kind);
        }
    }

    /**
     * @param array<string, list<string>> $ruleset
     * @param array<string, true> $visiting
     * @param array<string, true> $visited
     * @param list<string> $stack
     */
    private static function visitCycleNode(
        string $node,
        array $ruleset,
        array &$visiting,
        array &$visited,
        array $stack,
        string $kind,
    ): void {
        if (isset($visited[$node])) {
            return;
        }

        if (isset($visiting[$node])) {
            $cycle = $stack;
            $cycle[] = $node;
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_CYCLE_DETECTED . ': ' . $kind . ' dependency cycle: ' . implode(
                    ' -> ',
                    $cycle
                ),
            );
        }

        $visiting[$node] = true;
        $stack[] = $node;

        $deps = $ruleset[$node] ?? [];
        sort($deps, SORT_STRING);

        foreach ($deps as $dep) {
            if (!array_key_exists($dep, $ruleset)) {
                continue;
            }

            self::visitCycleNode($dep, $ruleset, $visiting, $visited, $stack, $kind);
        }

        unset($visiting[$node]);
        $visited[$node] = true;
    }

    /**
     * @param list<string> $nodes
     * @param list<array{from:string,to:string}> $edges
     *
     * @return array<string,string> absolute path => rendered content
     */
    private static function renderGraphArtifacts(string $outDirAbs, array $nodes, array $edges): array
    {
        $dot = self::renderDot($nodes, $edges);

        return [
            $outDirAbs . '/deptrac_graph.dot' => $dot,
            $outDirAbs . '/deptrac_graph.html' => self::renderHtml($nodes, $edges, $dot),
            $outDirAbs . '/deptrac_graph.svg' => self::renderSvg($nodes, $edges),
        ];
    }

    /**
     * @param list<string> $nodes
     * @param list<array{from:string,to:string}> $edges
     */
    private static function renderDot(array $nodes, array $edges): string
    {
        $out = implode("\n", self::licenseHeaderSlashLines()) . "\n";
        $out .= "digraph deptrac {\n";
        $out .= "  graph [rankdir=\"LR\"];\n";

        foreach ($nodes as $node) {
            $out .= '  "' . self::dotEscape($node) . "\";\n";
        }

        foreach ($edges as $edge) {
            $out .= '  "' . self::dotEscape($edge['from']) . '" -> "' . self::dotEscape($edge['to']) . "\";\n";
        }

        $out .= "}\n";

        return $out;
    }

    /**
     * @param list<string> $nodes
     * @param list<array{from:string,to:string}> $edges
     */
    private static function renderSvg(array $nodes, array $edges): string
    {
        $lineHeight = 18;
        $titleY = 24;
        $nodesHeadingY = 50;
        $itemX = 24;
        $headingX = 10;
        $nodeStartY = 72;
        $afterNodesGap = 12;
        $afterEdgesHeadingGap = 22;
        $bottomPadding = 24;

        /** @var list<string> $textLines */
        $textLines = [
            'Coretsia deptrac graph (generated)',
            'Nodes (' . count($nodes) . ')',
            'Allowed edges (' . count($edges) . ')',
        ];

        foreach ($nodes as $node) {
            $textLines[] = $node;
        }

        foreach ($edges as $edge) {
            $textLines[] = $edge['from'] . ' -> ' . $edge['to'];
        }

        $maxTextLength = 0;
        foreach ($textLines as $textLine) {
            $maxTextLength = max($maxTextLength, strlen($textLine));
        }

        $width = max(1000, 48 + ($maxTextLength * 8));

        $y = $nodeStartY;
        foreach ($nodes as $_node) {
            $y += $lineHeight;
        }

        $y += $afterNodesGap;
        $edgesHeadingY = $y;
        $y += $afterEdgesHeadingGap;

        foreach ($edges as $_edge) {
            $y += $lineHeight;
        }

        $height = max(180, $y + $bottomPadding);

        $lines = self::licenseHeaderHtmlLines();
        $lines[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $lines[] = '<text x="' . $headingX . '" y="' . $titleY . '">Coretsia deptrac graph (generated)</text>';
        $lines[] = '<text x="' . $headingX . '" y="' . $nodesHeadingY . '">Nodes (' . count($nodes) . ')</text>';

        $y = $nodeStartY;
        foreach ($nodes as $node) {
            $lines[] = '<text x="' . $itemX . '" y="' . $y . '">' . self::xmlEscape($node) . '</text>';
            $y += $lineHeight;
        }

        $lines[] = '<text x="' . $headingX . '" y="' . $edgesHeadingY . '">Allowed edges (' . count(
            $edges
        ) . ')</text>';

        $y = $edgesHeadingY + $afterEdgesHeadingGap;
        foreach ($edges as $edge) {
            $lines[] = '<text x="' . $itemX . '" y="' . $y . '">'
                . self::xmlEscape($edge['from'] . ' -> ' . $edge['to'])
                . '</text>';
            $y += $lineHeight;
        }

        $lines[] = '</svg>';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $nodes
     * @param list<array{from:string,to:string}> $edges
     */
    private static function renderHtml(array $nodes, array $edges, string $dot): string
    {
        $out = implode("\n", self::licenseHeaderHtmlLines()) . "\n";
        $out .= "<!doctype html>\n";
        $out .= "<html lang=\"en\">\n";
        $out .= "<head>\n";
        $out .= "<meta charset=\"utf-8\">\n";
        $out .= "<title>Coretsia deptrac graph</title>\n";
        $out .= "</head>\n";
        $out .= "<body>\n";
        $out .= "<h1>Coretsia deptrac graph</h1>\n";
        $out .= "<h2>Nodes (" . count($nodes) . ")</h2>\n";
        $out .= "<ul>\n";

        foreach ($nodes as $node) {
            $out .= "<li>" . self::xmlEscape($node) . "</li>\n";
        }

        $out .= "</ul>\n";
        $out .= "<h2>Allowed edges (" . count($edges) . ")</h2>\n";
        $out .= "<ul>\n";

        foreach ($edges as $edge) {
            $out .= "<li>" . self::xmlEscape($edge['from'] . ' -> ' . $edge['to']) . "</li>\n";
        }

        $out .= "</ul>\n";
        $out .= "<h2>DOT</h2>\n";
        $out .= "<pre>" . self::xmlEscape($dot) . "</pre>\n";
        $out .= "</body>\n";
        $out .= "</html>\n";

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function licenseHeaderSlashLines(): array
    {
        return [
            '// Coretsia Framework (Monorepo)',
            '//',
            '// Project: Coretsia Framework (Monorepo)',
            '// Authors: Vladyslav Mudrichenko and contributors',
            '// Copyright (c) 2026 Vladyslav Mudrichenko',
            '//',
            '// SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko',
            '// SPDX-License-Identifier: Apache-2.0',
            '//',
            '// For contributors list, see git history.',
            '// See LICENSE and NOTICE in the project root for full license information.',
            '',
        ];
    }

    /**
     * @return list<string>
     */
    private static function licenseHeaderHtmlLines(): array
    {
        return [
            '<!--',
            '  Coretsia Framework (Monorepo)',
            '',
            '  Project: Coretsia Framework (Monorepo)',
            '  Authors: Vladyslav Mudrichenko and contributors',
            '  Copyright (c) 2026 Vladyslav Mudrichenko',
            '',
            '  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko',
            '  SPDX-License-Identifier: Apache-2.0',
            '',
            '  For contributors list, see git history.',
            '  See LICENSE and NOTICE in the project root for full license information.',
            '-->',
            '',
        ];
    }

    private static function dotEscape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private static function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function isDifferentFile(string $path, string $newContent): bool
    {
        if (!is_file($path)) {
            return true;
        }

        return DeterministicFile::readBytesExact($path) !== $newContent;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private static function extractPsr4(
        array $composer,
        bool $required,
        string $packageId,
    ): string {
        $autoload = $composer['autoload'] ?? null;

        if ($autoload instanceof \stdClass) {
            $autoload = get_object_vars($autoload);
        }

        $psr4 = is_array($autoload) && !array_is_list($autoload)
            ? ($autoload['psr-4'] ?? null)
            : null;

        if ($psr4 instanceof \stdClass) {
            $psr4 = get_object_vars($psr4);
        }

        if (!is_array($psr4) || array_is_list($psr4)) {
            if ($required) {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED
                    . ': source-owning layered package must declare exactly one PSR-4 root '
                    . $packageId,
                );
            }

            return '';
        }

        $keys = array_keys($psr4);

        foreach ($keys as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException(
                    ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED
                    . ': layered package declares invalid PSR-4 root '
                    . $packageId,
                );
            }
        }

        sort($keys, SORT_STRING);

        if ($required && count($keys) !== 1) {
            throw new RuntimeException(
                ErrorCodes::CORETSIA_DEPTRAC_GENERATE_FAILED
                . ': source-owning layered package must declare exactly one PSR-4 root '
                . $packageId,
            );
        }

        return $keys[0] ?? '';
    }

    /**
     * @param array<string, mixed> $composer
     *
     * @return list<string>
     */
    private static function extractComposerRequireNames(array $composer): array
    {
        $require = $composer['require'] ?? [];
        if (!is_array($require)) {
            return [];
        }

        /** @var list<string> $names */
        $names = [];

        foreach (array_keys($require) as $name) {
            if (!is_string($name) || !str_starts_with($name, 'coretsia/')) {
                continue;
            }

            $names[] = $name;
        }

        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    private static function licenseHeaderYaml(): string
    {
        return
            "# Coretsia Framework (Monorepo)\n" .
            "#\n" .
            "# Project: Coretsia Framework (Monorepo)\n" .
            "# Authors: Vladyslav Mudrichenko and contributors\n" .
            "# Copyright (c) 2026 Vladyslav Mudrichenko\n" .
            "#\n" .
            "# SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko\n" .
            "# SPDX-License-Identifier: Apache-2.0\n" .
            "#\n" .
            "# For contributors list, see git history.\n" .
            "# See LICENSE and NOTICE in the project root for full license information.\n" .
            "\n";
    }

    private static function yamlSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function packageIdToLayerId(string $packageId): string
    {
        return str_replace('/', '.', $packageId);
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    private static function argValue(array $argv, string $name): ?string
    {
        $count = count($argv);

        for ($i = 0; $i < $count; $i++) {
            $arg = (string) $argv[$i];

            if (str_starts_with($arg, $name . '=')) {
                $value = trim(substr($arg, strlen($name . '=')));

                if ($value === '') {
                    throw new RuntimeException('argument-value-invalid');
                }

                return $value;
            }

            if ($arg === $name) {
                $next = $i + 1 < $count ? trim((string) $argv[$i + 1]) : '';

                if ($next === '' || str_starts_with($next, '--')) {
                    throw new RuntimeException('argument-value-invalid');
                }

                return $next;
            }
        }

        return null;
    }

    private static function pathRelativeToConfigDir(
        RepositoryContext $repository,
        string $outPath,
        string $repoRelPath,
    ): string {
        $configDir = self::canonicalExistingPath(\dirname($outPath));
        $abs = self::canonicalExistingPath($repository->resolve($repoRelPath));

        return self::relPath($configDir, $abs);
    }

    private static function canonicalExistingPath(string $path): string
    {
        $normalized = \rtrim(\str_replace('\\', '/', $path), '/');

        if ($normalized === '') {
            return '';
        }

        $real = \realpath($normalized);

        if (\is_string($real)) {
            return \rtrim(\str_replace('\\', '/', $real), '/');
        }

        return $normalized;
    }

    private static function relPath(string $fromDirAbs, string $toAbs): string
    {
        $from = rtrim(str_replace('\\', '/', $fromDirAbs), '/');
        $to = rtrim(str_replace('\\', '/', $toAbs), '/');

        $fromParts = $from === '' ? [] : explode('/', $from);
        $toParts = $to === '' ? [] : explode('/', $to);

        $i = 0;
        $max = min(count($fromParts), count($toParts));

        while ($i < $max && self::samePathSegment($fromParts[$i], $toParts[$i])) {
            $i++;
        }

        $up = array_fill(0, count($fromParts) - $i, '..');
        $down = array_slice($toParts, $i);
        $rel = array_merge($up, $down);

        return $rel === [] ? '.' : implode('/', $rel);
    }

    private static function samePathSegment(string $left, string $right): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return strcasecmp($left, $right) === 0;
        }

        return strcmp($left, $right) === 0;
    }

    private static function resolveRepository(array $argv): RepositoryContext
    {
        $repoRoot = self::argValue($argv, '--repo-root');

        return $repoRoot === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($repoRoot);
    }
}

try {
    exit(DeptracGenerateTool::main($argv));
} catch (Throwable $e) {
    DeptracGenerateTool::emitFailure($e);
    exit(1);
}
