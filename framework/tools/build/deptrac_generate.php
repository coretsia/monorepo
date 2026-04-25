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

final class DeptracGenerateTool
{
    private const string CODE_OUT_OF_DATE = 'CORETSIA_DEPTRAC_OUT_OF_DATE';
    public const string CODE_GENERATE_FAILED = 'CORETSIA_DEPTRAC_GENERATE_FAILED';
    private const string CODE_MISSING_SSOT_RULESET = 'CORETSIA_DEPTRAC_SSOT_RULESET_MISSING';
    private const string CODE_CYCLE_DETECTED = 'CORETSIA_DEPTRAC_CYCLE_DETECTED';
    private const string CODE_ALLOWLIST_INVALID = 'CORETSIA_DEPTRAC_ALLOWLIST_INVALID';

    private const string DEPENDENCY_TABLE_PATH = 'docs/roadmap/phase0/00_2-dependency-table.md';

    /**
     * @var array<string, true>
     */
    private const array TOOLING_LAYERS = [
        'devtools' => true,
    ];

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
        $repoRoot = self::resolveRepoRoot($argv);

        $check = self::argFlag($argv, '--check');
        $apply = self::argFlag($argv, '--apply') || !$check;

        $outPath = self::argValue($argv, '--out') ?? ($repoRoot . '/framework/tools/testing/deptrac.yaml');
        $allowlistPath = self::argValue($argv, '--allowlist')
            ?? ($repoRoot . '/framework/tools/testing/deptrac.allowlist.yaml');
        $artifactsDir = self::argValue($argv, '--artifacts-dir') ?? ($repoRoot . '/framework/var/arch');

        $outPath = self::absFromRepo($repoRoot, $outPath);
        $allowlistPath = self::absFromRepo($repoRoot, $allowlistPath);
        $artifactsDir = self::absFromRepo($repoRoot, $artifactsDir);

        $allowlistMissing = !is_file($allowlistPath);
        $excludeFiles = $allowlistMissing
            ? self::DEFAULT_EXCLUDE_FILES
            : self::readAllowlistAsExcludeFiles($allowlistPath);

        self::assertAllowlistPolicy($excludeFiles);

        $ssotRuleset = self::readSsotDependencyTable($repoRoot . '/' . self::DEPENDENCY_TABLE_PATH);
        self::assertNoPackageCycles($ssotRuleset);

        $packageIndex = self::scanPackages($repoRoot);
        $model = self::buildDeptracModel($repoRoot, $outPath, $packageIndex, $ssotRuleset, $excludeFiles);

        $yaml = self::renderDeptracYaml($model);
        $changed = self::isDifferentFile($outPath, $yaml);

        if ($check) {
            if ($changed || $allowlistMissing) {
                fwrite(STDERR, self::CODE_OUT_OF_DATE . "\n");

                if ($changed) {
                    fwrite(STDERR, self::rel($repoRoot, $outPath) . "\n");
                }

                if ($allowlistMissing) {
                    fwrite(STDERR, self::rel($repoRoot, $allowlistPath) . "\n");
                }

                fwrite(STDERR, "Run: php framework/tools/build/deptrac_generate.php --apply\n");

                return 1;
            }

            fwrite(STDOUT, "OK\n");
            return 0;
        }

        if ($apply) {
            if ($allowlistMissing) {
                self::writeFile($allowlistPath, self::renderDefaultAllowlistYaml());
            }

            self::writeFile($outPath, $yaml);
            self::writeGraphArtifacts($artifactsDir, $model['nodes'], $model['edges']);
        }

        fwrite(STDOUT, "OK\n");

        if ($allowlistMissing) {
            fwrite(STDOUT, self::rel($repoRoot, $allowlistPath) . "\n");
        }

        if ($changed) {
            fwrite(STDOUT, self::rel($repoRoot, $outPath) . "\n");
        }

        fwrite(STDOUT, self::rel($repoRoot, $artifactsDir) . "\n");

        return 0;
    }

    public static function formatFailure(Throwable $e): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $e->getMessage());

        foreach ([
                     self::CODE_MISSING_SSOT_RULESET,
                     self::CODE_CYCLE_DETECTED,
                     self::CODE_ALLOWLIST_INVALID,
                 ] as $code) {
            if ($message === $code || str_starts_with($message, $code . ':')) {
                return $message;
            }
        }

        return self::CODE_GENERATE_FAILED . ': ' . $message;
    }

    /**
     * @return array<string, list<string>> package_id => package_id dependencies
     */
    private static function readSsotDependencyTable(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                self::CODE_MISSING_SSOT_RULESET . ': missing SSoT dependency table: ' . self::DEPENDENCY_TABLE_PATH
            );
        }

        $raw = self::normalizeEol((string)file_get_contents($path));

        /** @var array<string, list<string>> $ruleset */
        $ruleset = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '' || !str_starts_with($line, '|')) {
                continue;
            }

            $cells = array_map(
                static fn (string $cell): string => trim($cell),
                explode('|', trim($line, '|')),
            );

            if (count($cells) < 2) {
                continue;
            }

            if ($cells[0] === 'package_id' || str_starts_with($cells[0], '---')) {
                continue;
            }

            $packageId = self::normalizeMarkdownCell($cells[0]);
            $dependsOnCell = self::normalizeMarkdownCell($cells[1]);

            if (!self::isPackageId($packageId)) {
                continue;
            }

            $deps = [];
            if ($dependsOnCell !== '—' && $dependsOnCell !== '-') {
                foreach (explode(',', $dependsOnCell) as $dep) {
                    $dep = self::normalizeMarkdownCell($dep);
                    if ($dep === '') {
                        continue;
                    }

                    if (!self::isPackageId($dep)) {
                        throw new RuntimeException('Invalid dependency table package_id dependency: ' . $dep);
                    }

                    $deps[] = $dep;
                }
            }

            $deps = array_values(array_unique($deps));
            sort($deps, SORT_STRING);

            $ruleset[$packageId] = $deps;
        }

        ksort($ruleset, SORT_STRING);

        foreach ($ruleset as $packageId => $deps) {
            foreach ($deps as $dep) {
                if (!array_key_exists($dep, $ruleset)) {
                    throw new RuntimeException(
                        self::CODE_MISSING_SSOT_RULESET . ': dependency table references missing row ' . $dep
                        . ' from ' . $packageId,
                    );
                }
            }
        }

        return $ruleset;
    }

    private static function normalizeMarkdownCell(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "` \t\n\r\0\x0B");
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private static function isPackageId(string $value): bool
    {
        return preg_match('/\A[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*\z/u', $value) === 1;
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
    private static function scanPackages(string $repoRoot): array
    {
        $packagesRoot = $repoRoot . '/framework/packages';
        if (!is_dir($packagesRoot)) {
            throw new RuntimeException('Missing framework/packages');
        }

        $rootAbs = realpath($packagesRoot);
        if ($rootAbs === false) {
            throw new RuntimeException('Cannot resolve framework/packages');
        }

        $rootAbs = rtrim(str_replace('\\', '/', $rootAbs), '/');

        $hits = glob($rootAbs . '/*/*/composer.json', GLOB_NOSORT);
        if ($hits === false) {
            $hits = [];
        }

        $composerFiles = [];
        foreach ($hits as $hit) {
            if ($hit !== '' && is_file($hit)) {
                $composerFiles[] = str_replace('\\', '/', $hit);
            }
        }

        sort($composerFiles, SORT_STRING);

        $index = [];

        foreach ($composerFiles as $abs) {
            $rel = self::relFrom($rootAbs, $abs);
            $parts = explode('/', $rel);

            if (count($parts) !== 3) {
                continue;
            }

            [$layer, $slug, $file] = $parts;
            if ($file !== 'composer.json' || $layer === '' || $slug === '') {
                continue;
            }

            $composer = self::readJson($abs);
            $composerName = (string)($composer['name'] ?? '');

            if ($composerName === '') {
                continue;
            }

            $packageId = $layer . '/' . $slug;
            $path = 'framework/packages/' . $packageId;

            $index[] = [
                'id' => self::packageIdToLayerId($packageId),
                'packageId' => $packageId,
                'layer' => $layer,
                'slug' => $slug,
                'composerName' => $composerName,
                'path' => $path,
                'srcPath' => $path . '/src',
                'psr4' => self::extractPsr4($composer),
                'requireNames' => self::extractComposerRequireNames($composer),
            ];
        }

        usort(
            $index,
            static fn (array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']),
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
     * @param array<string, list<string>> $ssotRuleset
     * @param list<string> $excludeFiles
     * @return array{
     *     paths:list<string>,
     *     layers:list<array{name:string,pattern:string}>,
     *     ruleset:array<string, list<string>>,
     *     excludeFiles:list<string>,
     *     nodes:list<string>,
     *     edges:list<array{from:string,to:string}>
     * }
     */
    private static function buildDeptracModel(
        string $repoRoot,
        string $outPath,
        array  $packageIndex,
        array  $ssotRuleset,
        array  $excludeFiles,
    ): array {
        /** @var list<array{id:string,packageId:string,layer:string,slug:string,composerName:string,path:string,srcPath:string,psr4:string,requireNames:list<string>}> $activePackages */
        $activePackages = [];

        foreach ($packageIndex as $package) {
            if (is_dir($repoRoot . '/' . $package['srcPath'])) {
                $activePackages[] = $package;
            }
        }

        /** @var array<string, string> $composerNameToPackageId */
        $composerNameToPackageId = [];

        /** @var array<string, true> $activePackageIds */
        $activePackageIds = [];

        foreach ($activePackages as $package) {
            $composerNameToPackageId[$package['composerName']] = $package['packageId'];
            $activePackageIds[$package['packageId']] = true;
        }

        /** @var list<string> $paths */
        $paths = [];

        /** @var list<array{name:string,pattern:string}> $layers */
        $layers = [];

        foreach ($activePackages as $package) {
            $paths[] = self::pathRelativeToConfigDir($repoRoot, $outPath, $package['srcPath']);
            $layers[] = [
                'name' => $package['id'],
                'pattern' => self::classLikePatternForPsr4($package['psr4']),
            ];
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        usort(
            $layers,
            static fn (array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']),
        );

        $ruleset = self::buildRuleset(
            $activePackages,
            $activePackageIds,
            $composerNameToPackageId,
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
                $left = (string)$a['from'] . '->' . (string)$a['to'];
                $right = (string)$b['from'] . '->' . (string)$b['to'];

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
     * @param array<string, string> $composerNameToPackageId
     * @param array<string, list<string>> $ssotRuleset
     * @return array<string, list<string>>
     */
    private static function buildRuleset(
        array $activePackages,
        array $activePackageIds,
        array $composerNameToPackageId,
        array $ssotRuleset,
    ): array {
        /** @var list<string> $missing */
        $missing = [];

        /** @var array<string, list<string>> $ruleset */
        $ruleset = [];

        foreach ($activePackages as $package) {
            $packageId = $package['packageId'];
            $layerId = $package['id'];

            if (isset($ssotRuleset[$packageId])) {
                $allowedPackageIds = $ssotRuleset[$packageId];
                $allowedPackageIds = self::applyTemporalMissingDependencyCompat(
                    $package,
                    $allowedPackageIds,
                    $activePackageIds,
                    $composerNameToPackageId,
                    $ssotRuleset,
                );
            } elseif (isset(self::TOOLING_LAYERS[$package['layer']])) {
                $allowedPackageIds = self::composerRequireNamesToPackageIds(
                    $package['requireNames'],
                    $composerNameToPackageId,
                );
            } else {
                $missing[] = $packageId;
                continue;
            }

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
            $message = self::CODE_MISSING_SSOT_RULESET . ': missing dependency policy for discovered package layers';
            foreach ($missing as $packageId) {
                $message .= "\n- " . $packageId;
            }
            $message .= "\nFix: add rows to " . self::DEPENDENCY_TABLE_PATH . ' or mark the package as tooling-only.';

            throw new RuntimeException($message);
        }

        ksort($ruleset, SORT_STRING);

        return $ruleset;
    }

    /**
     * Temporal bootstrap rule:
     * when a SSoT package depends on not-yet-materialized owner packages, allow direct composer edges
     * to materialized transitive SSoT dependencies. This keeps early Phase 0 packages analyzable while
     * foundation/kernel owner packages are still absent, without opening arbitrary package edges.
     *
     * @param array{id:string,packageId:string,layer:string,slug:string,composerName:string,path:string,srcPath:string,psr4:string,requireNames:list<string>} $package
     * @param list<string> $allowedPackageIds
     * @param array<string, true> $activePackageIds
     * @param array<string, string> $composerNameToPackageId
     * @param array<string, list<string>> $ssotRuleset
     * @return list<string>
     */
    private static function applyTemporalMissingDependencyCompat(
        array $package,
        array $allowedPackageIds,
        array $activePackageIds,
        array $composerNameToPackageId,
        array $ssotRuleset,
    ): array {
        if ($allowedPackageIds === []) {
            return [];
        }

        $hasMaterializedDirectDependency = false;
        foreach ($allowedPackageIds as $allowedPackageId) {
            if (isset($activePackageIds[$allowedPackageId])) {
                $hasMaterializedDirectDependency = true;
                break;
            }
        }

        if ($hasMaterializedDirectDependency) {
            return $allowedPackageIds;
        }

        $closure = self::ssotTransitiveClosure($allowedPackageIds, $ssotRuleset);
        $composerPackageIds = self::composerRequireNamesToPackageIds($package['requireNames'], $composerNameToPackageId);

        foreach ($composerPackageIds as $composerPackageId) {
            if (isset($closure[$composerPackageId])) {
                $allowedPackageIds[] = $composerPackageId;
            }
        }

        $allowedPackageIds = array_values(array_unique($allowedPackageIds));
        sort($allowedPackageIds, SORT_STRING);

        return $allowedPackageIds;
    }

    /**
     * @param list<string> $packageIds
     * @param array<string, list<string>> $ssotRuleset
     * @return array<string, true>
     */
    private static function ssotTransitiveClosure(array $packageIds, array $ssotRuleset): array
    {
        /** @var array<string, true> $seen */
        $seen = [];

        /** @var list<string> $queue */
        $queue = $packageIds;
        sort($queue, SORT_STRING);

        while ($queue !== []) {
            $packageId = array_shift($queue);
            if (!is_string($packageId) || isset($seen[$packageId])) {
                continue;
            }

            $seen[$packageId] = true;

            foreach ($ssotRuleset[$packageId] ?? [] as $dep) {
                if (!isset($seen[$dep])) {
                    $queue[] = $dep;
                }
            }

            sort($queue, SORT_STRING);
        }

        ksort($seen, SORT_STRING);

        return $seen;
    }

    /**
     * @param list<string> $requireNames
     * @param array<string, string> $composerNameToPackageId
     * @return list<string>
     */
    private static function composerRequireNamesToPackageIds(array $requireNames, array $composerNameToPackageId): array
    {
        /** @var list<string> $packageIds */
        $packageIds = [];

        foreach ($requireNames as $requireName) {
            if (isset($composerNameToPackageId[$requireName])) {
                $packageIds[] = $composerNameToPackageId[$requireName];
            }
        }

        $packageIds = array_values(array_unique($packageIds));
        sort($packageIds, SORT_STRING);

        return $packageIds;
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
        $out .= "# Regenerate: php framework/tools/build/deptrac_generate.php --apply\n\n";
        $out .= "deptrac:\n";

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
        $out .= "# It MUST NOT exclude framework/packages/**/src/**.\n\n";
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
            return '^__CORETSIA_MISSING_PSR4__$';
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

        $raw = self::normalizeEol((string)file_get_contents($path));

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
            throw new RuntimeException(self::CODE_ALLOWLIST_INVALID . ': empty exclude_files entry');
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

            if (str_contains($normalized, 'framework/packages') && str_contains($normalized, '/src')) {
                throw new RuntimeException(
                    self::CODE_ALLOWLIST_INVALID . ': exclude_files must not cover framework/packages/**/src/**',
                );
            }

            if (str_contains($normalized, '(^|/)src') || str_contains($normalized, '/src(/|$)')) {
                throw new RuntimeException(
                    self::CODE_ALLOWLIST_INVALID . ': exclude_files must not cover src/**',
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
        array  $ruleset,
        array  &$visiting,
        array  &$visited,
        array  $stack,
        string $kind,
    ): void {
        if (isset($visited[$node])) {
            return;
        }

        if (isset($visiting[$node])) {
            $cycle = $stack;
            $cycle[] = $node;
            throw new RuntimeException(
                self::CODE_CYCLE_DETECTED . ': ' . $kind . ' dependency cycle: ' . implode(' -> ', $cycle),
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
     */
    private static function writeGraphArtifacts(string $outDirAbs, array $nodes, array $edges): void
    {
        if (!is_dir($outDirAbs) && !@mkdir($outDirAbs, 0777, true)) {
            throw new RuntimeException('Cannot create artifacts dir: ' . $outDirAbs);
        }

        $dot = self::renderDot($nodes, $edges);
        $svg = self::renderSvg($nodes, $edges);
        $html = self::renderHtml($nodes, $edges, $dot);

        self::writeFile($outDirAbs . '/deptrac_graph.dot', $dot);
        self::writeFile($outDirAbs . '/deptrac_graph.svg', $svg);
        self::writeFile($outDirAbs . '/deptrac_graph.html', $html);
    }

    /**
     * @param list<string> $nodes
     * @param list<array{from:string,to:string}> $edges
     */
    private static function renderDot(array $nodes, array $edges): string
    {
        $out = "digraph deptrac {\n";
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

        $lines = [];
        $lines[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $lines[] = '<text x="' . $headingX . '" y="' . $titleY . '">Coretsia deptrac graph (generated)</text>';
        $lines[] = '<text x="' . $headingX . '" y="' . $nodesHeadingY . '">Nodes (' . count($nodes) . ')</text>';

        $y = $nodeStartY;
        foreach ($nodes as $node) {
            $lines[] = '<text x="' . $itemX . '" y="' . $y . '">' . self::xmlEscape($node) . '</text>';
            $y += $lineHeight;
        }

        $lines[] = '<text x="' . $headingX . '" y="' . $edgesHeadingY . '">Allowed edges (' . count($edges) . ')</text>';

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
        $out = '';
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

        $old = self::normalizeEol((string)file_get_contents($path));

        return $old !== self::normalizeEol($newContent);
    }

    private static function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }

        $content = self::normalizeEol($content);
        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        file_put_contents($path, $content, LOCK_EX);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $raw = self::normalizeEol((string)file_get_contents($path));
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $composer
     */
    private static function extractPsr4(array $composer): string
    {
        $autoload = $composer['autoload'] ?? null;
        if (!is_array($autoload)) {
            return '';
        }

        $psr4 = $autoload['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            return '';
        }

        $keys = array_keys($psr4);
        usort($keys, static fn ($a, $b): int => strcmp((string)$a, (string)$b));

        return (string)$keys[0];
    }

    /**
     * @param array<string, mixed> $composer
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
            "#\n";
    }

    private static function yamlSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function packageIdToLayerId(string $packageId): string
    {
        return str_replace('/', '.', $packageId);
    }

    private static function normalizeEol(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    private static function argValue(array $argv, string $name): ?string
    {
        $count = count($argv);

        for ($i = 0; $i < $count; $i++) {
            $arg = (string)$argv[$i];

            if (str_starts_with($arg, $name . '=')) {
                $value = trim(substr($arg, strlen($name . '=')));

                return $value !== '' ? $value : null;
            }

            if ($arg === $name) {
                $next = $i + 1 < $count ? trim((string)$argv[$i + 1]) : '';

                return $next !== '' ? $next : null;
            }
        }

        return null;
    }

    private static function absFromRepo(string $repoRoot, string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $repoRoot;
        }

        if (self::isAbsolutePath($path)) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }

        return rtrim($repoRoot, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    private static function isAbsolutePath(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    private static function resolveRepoRoot(array $argv): string
    {
        $arg = self::argValue($argv, '--repo-root');

        if ($arg === null) {
            return self::repoRootUnsafe();
        }

        $candidate = str_replace('\\', '/', trim($arg));

        if (!self::isAbsolutePath($candidate)) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Cannot resolve cwd');
            }

            $candidate = rtrim(str_replace('\\', '/', $cwd), '/') . '/' . ltrim($candidate, '/');
        }

        $candidate = rtrim($candidate, '/');
        $real = realpath($candidate);

        if ($real !== false) {
            $candidate = rtrim(str_replace('\\', '/', $real), '/');
        }

        if (!is_dir($candidate . '/framework') || !is_dir($candidate . '/docs') || !is_file($candidate . '/composer.json')) {
            throw new RuntimeException('Invalid --repo-root: missing framework/docs/composer.json markers');
        }

        return $candidate;
    }

    private static function repoRootUnsafe(): string
    {
        $dir = getcwd();
        if ($dir === false) {
            throw new RuntimeException('Cannot resolve cwd');
        }

        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        for ($i = 0; $i < 30; $i++) {
            if (is_dir($dir . '/framework') && is_dir($dir . '/docs') && is_file($dir . '/composer.json')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        throw new RuntimeException('Repo root not found');
    }

    private static function pathRelativeToConfigDir(string $repoRoot, string $outPath, string $repoRelPath): string
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
        $configDir = rtrim(str_replace('\\', '/', dirname($outPath)), '/');
        $abs = $repoRoot . '/' . ltrim(str_replace('\\', '/', $repoRelPath), '/');

        return self::relPath($configDir, $abs);
    }

    private static function relPath(string $fromDirAbs, string $toAbs): string
    {
        $from = rtrim(str_replace('\\', '/', $fromDirAbs), '/');
        $to = rtrim(str_replace('\\', '/', $toAbs), '/');

        $fromParts = $from === '' ? [] : explode('/', $from);
        $toParts = $to === '' ? [] : explode('/', $to);

        $i = 0;
        $max = min(count($fromParts), count($toParts));

        while ($i < $max && $fromParts[$i] === $toParts[$i]) {
            $i++;
        }

        $up = array_fill(0, count($fromParts) - $i, '..');
        $down = array_slice($toParts, $i);
        $rel = array_merge($up, $down);

        return $rel === [] ? '.' : implode('/', $rel);
    }

    private static function rel(string $repoRoot, string $abs): string
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/') . '/';
        $abs = str_replace('\\', '/', $abs);

        return str_starts_with($abs, $repoRoot) ? substr($abs, strlen($repoRoot)) : $abs;
    }

    private static function relFrom(string $rootAbs, string $abs): string
    {
        $rootAbs = rtrim(str_replace('\\', '/', $rootAbs), '/') . '/';
        $abs = str_replace('\\', '/', $abs);

        return str_starts_with($abs, $rootAbs) ? substr($abs, strlen($rootAbs)) : $abs;
    }
}

try {
    exit(DeptracGenerateTool::main($argv));
} catch (Throwable $e) {
    fwrite(STDERR, DeptracGenerateTool::formatFailure($e) . "\n");
    exit(1);
}
