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

namespace Coretsia\Tools\Spikes\deptrac;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\_support\FixtureRoot;

/**
 * GraphArtifactBuilder (Epic 0.80.0) [SPIKE]:
 * - produce deterministic graph artifacts: dot, svg, html
 * - MUST NOT embed absolute machine paths (relative/normalized only)
 * - outputs MUST NOT embed timestamps, tool versions, or absolute machine paths
 * - MUST NOT execute external binaries (Graphviz/dot or any shell commands)
 * - svg/html MUST be pure PHP deterministic reports (NOT a layout engine)
 *
 * Stable ordering (cemented):
 * - nodes sorted by strcmp(nodeId)
 * - edges sorted by strcmp(from."->".to)
 * - adjacency lists sorted by strcmp(targetId)
 *
 * Output policy:
 * - MUST NOT emit stdout/stderr.
 * - MUST write text via DeterministicFile::writeTextLf() (LF-only + final newline).
 */
final class GraphArtifactBuilder
{
    private const string DEFAULT_FIXTURE = 'deptrac_min/package_index_ok.php';

    private function __construct()
    {
    }

    /**
     * @throws DeterministicException
     */
    public static function buildToDirFromFixture(?string $fixtureRelativePath, string $outputDir): void
    {
        $fixtureRelativePath = $fixtureRelativePath ?? self::DEFAULT_FIXTURE;

        $index = self::readFixturePackageIndex($fixtureRelativePath);

        self::buildToDirFromIndex($index, $outputDir, $fixtureRelativePath);
    }

    /**
     * @param array<string,mixed> $index
     * @throws DeterministicException
     */
    public static function buildToDirFromIndex(array $index, string $outputDir, string $fixtureRelativeHint = 'deptrac_min/package_index.php'): void
    {
        $outputDir = \rtrim(\str_replace('\\', '/', $outputDir), '/');

        if ($outputDir === '' || !\is_dir($outputDir)) {
            self::failArtifactInvalid('output-dir-invalid');
        }

        try {
            $dot = self::buildDotFromIndex($index, $fixtureRelativeHint);
            $svg = self::buildSvgFromIndex($index, $fixtureRelativeHint);
            $html = self::buildHtmlFromIndex($index, $fixtureRelativeHint);

            DeterministicFile::writeTextLf($outputDir . '/deptrac_graph.dot', $dot);
            DeterministicFile::writeTextLf($outputDir . '/deptrac_graph.svg', $svg);
            DeterministicFile::writeTextLf($outputDir . '/deptrac_graph.html', $html);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::failArtifactInvalid('graph-artifact-write-failed', $e);
        }
    }

    /**
     * @param array<string,mixed> $index
     * @throws DeterministicException
     */
    public static function buildDotFromIndex(array $index, string $fixtureRelativeHint = 'deptrac_min/package_index.php'): string
    {
        $graph = self::buildGraph($index);

        $lines = [];
        $lines[] = '// Coretsia Deptrac graph (Epic 0.80.0) - deterministic artifact.';
        $lines[] = '// fixture: ' . self::safeHeaderFixtureRef($fixtureRelativeHint);
        $lines[] = 'digraph "coretsia_deptrac" {';
        $lines[] = '  rankdir=LR;';
        $lines[] = '  node [shape=box];';

        foreach ($graph['nodes'] as $id) {
            $lines[] = '  "' . self::dotEscape($id) . '";';
        }

        foreach ($graph['edges'] as $e) {
            $lines[] = '  "' . self::dotEscape($e['from']) . '" -> "' . self::dotEscape($e['to']) . '";';
        }

        $lines[] = '}';

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string,mixed> $index
     * @throws DeterministicException
     */
    public static function buildSvgFromIndex(array $index, string $fixtureRelativeHint = 'deptrac_min/package_index.php'): string
    {
        $graph = self::buildGraph($index);

        $report = self::buildReportLines($graph, $fixtureRelativeHint);

        $lineHeight = 18;
        $paddingTop = 22;
        $width = 1200;
        $height = \max(180, $paddingTop + (\count($report) * $lineHeight) + 20);

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $lines[] = '  <rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="white"/>';
        $lines[] = '  <g font-family="monospace" font-size="14" fill="black">';

        foreach ($report as $i => $text) {
            $y = $paddingTop + ($i * $lineHeight);
            $lines[] = '    <text x="10" y="' . $y . '">' . self::xmlEscape($text) . '</text>';
        }

        $lines[] = '  </g>';
        $lines[] = '</svg>';

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string,mixed> $index
     * @throws DeterministicException
     */
    public static function buildHtmlFromIndex(array $index, string $fixtureRelativeHint = 'deptrac_min/package_index.php'): string
    {
        $graph = self::buildGraph($index);

        $fixture = self::htmlEscape(self::safeHeaderFixtureRef($fixtureRelativeHint));
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $adj = $graph['adjacency'];

        $lines = [];
        $lines[] = '<!doctype html>';
        $lines[] = '<html lang="en">';
        $lines[] = '<head>';
        $lines[] = '  <meta charset="utf-8">';
        $lines[] = '  <meta name="viewport" content="width=device-width, initial-scale=1">';
        $lines[] = '  <title>Coretsia Deptrac Graph (Epic 0.80.0)</title>';
        $lines[] = '  <style>';
        $lines[] = '    table{border-collapse:collapse;border-spacing:0;}';
        $lines[] = '    th,td{border:1px solid #000;padding:6px;vertical-align:top;}';
        $lines[] = '  </style>';
        $lines[] = '</head>';
        $lines[] = '<body>';
        $lines[] = '  <h1>Coretsia Deptrac Graph (Epic 0.80.0)</h1>';
        $lines[] = '  <p>Fixture: <code>' . $fixture . '</code></p>';

        $lines[] = '  <h2>Nodes (' . \count($nodes) . ')</h2>';
        $lines[] = '  <ul>';
        foreach ($nodes as $id) {
            $lines[] = '    <li><code>' . self::htmlEscape($id) . '</code></li>';
        }
        $lines[] = '  </ul>';

        $lines[] = '  <h2>Edges (' . \count($edges) . ')</h2>';
        $lines[] = '  <table>';
        $lines[] = '    <thead>';
        $lines[] = '      <tr><th>From</th><th>To</th></tr>';
        $lines[] = '    </thead>';
        $lines[] = '    <tbody>';
        foreach ($edges as $e) {
            $lines[] = '      <tr><td><code>' . self::htmlEscape($e['from']) . '</code></td><td><code>' . self::htmlEscape($e['to']) . '</code></td></tr>';
        }
        $lines[] = '    </tbody>';
        $lines[] = '  </table>';

        $lines[] = '  <h2>Adjacency</h2>';
        $lines[] = '  <table>';
        $lines[] = '    <thead>';
        $lines[] = '      <tr><th>Node</th><th>Targets</th></tr>';
        $lines[] = '    </thead>';
        $lines[] = '    <tbody>';
        foreach ($nodes as $id) {
            $targets = $adj[$id] ?? [];
            $targetsText = $targets === [] ? '(none)' : \implode(', ', $targets);
            $lines[] = '      <tr><td><code>' . self::htmlEscape($id) . '</code></td><td><code>' . self::htmlEscape($targetsText) . '</code></td></tr>';
        }
        $lines[] = '    </tbody>';
        $lines[] = '  </table>';

        $lines[] = '</body>';
        $lines[] = '</html>';

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    private static function buildReportLines(array $graph, string $fixtureRelativeHint): array
    {
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $adj = $graph['adjacency'];

        $lines = [];
        $lines[] = 'Coretsia Deptrac Graph (Epic 0.80.0)';
        $lines[] = 'Fixture: ' . self::safeHeaderFixtureRef($fixtureRelativeHint);
        $lines[] = '';
        $lines[] = 'Nodes (' . \count($nodes) . '):';
        foreach ($nodes as $id) {
            $lines[] = '  - ' . $id;
        }

        $lines[] = '';
        $lines[] = 'Edges (' . \count($edges) . '):';
        foreach ($edges as $e) {
            $lines[] = '  - ' . $e['from'] . ' -> ' . $e['to'];
        }

        $lines[] = '';
        $lines[] = 'Adjacency:';
        foreach ($nodes as $id) {
            $targets = $adj[$id] ?? [];
            $lines[] = '  ' . $id . ': ' . ($targets === [] ? '(none)' : \implode(', ', $targets));
        }

        return $lines;
    }

    /**
     * @param array<string,mixed> $index
     * @return array{
     *   nodes:list<string>,
     *   edges:list<array{from:string,to:string}>,
     *   adjacency:array<string,list<string>>
     * }
     *
     * @throws DeterministicException
     */
    private static function buildGraph(array $index): array
    {
        $schemaVersion = $index['schema_version'] ?? null;
        $packages = $index['packages'] ?? null;

        if (!\is_int($schemaVersion) || $schemaVersion < 1) {
            self::failArtifactInvalid('fixture-schema-version-invalid');
        }

        if (!\is_array($packages) || $packages === []) {
            self::failArtifactInvalid('fixture-packages-invalid');
        }

        /** @var array<string, list<string>> $adjacency */
        $adjacency = [];
        /** @var array<string, true> $nodeSet */
        $nodeSet = [];

        foreach ($packages as $pkg) {
            if (!\is_array($pkg)) {
                self::failArtifactInvalid('fixture-package-not-array');
            }

            $packageId = $pkg['package_id'] ?? null;
            $deps = $pkg['deps'] ?? null;

            if (!\is_string($packageId) || $packageId === '') {
                self::failArtifactInvalid('fixture-package-id-invalid');
            }

            if (!\is_array($deps) || !\array_is_list($deps)) {
                self::failArtifactInvalid('fixture-deps-invalid');
            }

            self::assertNoAbsolutePathPatterns($packageId, 'package-id');

            $nodeSet[$packageId] = true;

            $depsOut = [];
            foreach ($deps as $dep) {
                if (!\is_string($dep) || $dep === '') {
                    self::failArtifactInvalid('fixture-dep-item-invalid');
                }

                self::assertNoAbsolutePathPatterns($dep, 'dep');

                $depsOut[] = $dep;
            }

            $depsOut = \array_values(\array_unique($depsOut));
            \usort($depsOut, static fn(string $a, string $b): int => \strcmp($a, $b));

            $adjacency[$packageId] = $depsOut;
        }

        $nodes = \array_keys($nodeSet);
        \usort($nodes, static fn(string $a, string $b): int => \strcmp($a, $b));

        $nodeLookup = [];
        foreach ($nodes as $node) {
            $nodeLookup[$node] = true;
        }

        foreach ($adjacency as $from => $targets) {
            foreach ($targets as $to) {
                if (!isset($nodeLookup[$to])) {
                    self::failArtifactInvalid('fixture-unknown-dep');
                }
            }
        }

        $edgeSet = [];
        foreach ($nodes as $from) {
            $targets = $adjacency[$from] ?? [];
            foreach ($targets as $to) {
                self::assertNoAbsolutePathPatterns($from, 'edge-from');
                self::assertNoAbsolutePathPatterns($to, 'edge-to');

                $key = $from . '->' . $to;
                $edgeSet[$key] = ['from' => $from, 'to' => $to];
            }
        }

        $edgeKeys = \array_keys($edgeSet);
        \usort($edgeKeys, static fn(string $a, string $b): int => \strcmp($a, $b));

        $edges = [];
        foreach ($edgeKeys as $key) {
            /** @var array{from:string,to:string} $edge */
            $edge = $edgeSet[$key];
            $edges[] = $edge;
        }

        foreach ($nodes as $id) {
            if (!isset($adjacency[$id])) {
                $adjacency[$id] = [];
                continue;
            }

            $targets = $adjacency[$id];
            $targets = \array_values(\array_unique($targets));
            \usort($targets, static fn(string $a, string $b): int => \strcmp($a, $b));
            $adjacency[$id] = $targets;
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'adjacency' => $adjacency,
        ];
    }

    /**
     * @throws DeterministicException
     */
    private static function assertNoAbsolutePathPatterns(string $value, string $context): void
    {
        if (\str_contains($value, '\\')) {
            self::failArtifactInvalid($context . '-backslash-forbidden');
        }

        if (\str_starts_with($value, '/')) {
            self::failArtifactInvalid($context . '-abs-posix');
        }

        $lower = \strtolower($value);
        if (\str_contains($lower, '/home/') || \str_contains($lower, '/users/')) {
            self::failArtifactInvalid($context . '-abs-posix-home');
        }

        if (\preg_match('~(?i)\b[A-Z]:[\\\\/]~', $value) === 1) {
            self::failArtifactInvalid($context . '-abs-win-drive');
        }

        if (\preg_match('~\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~', $value) === 1) {
            self::failArtifactInvalid($context . '-abs-unc');
        }

        if (\preg_match('~//[^/]+/[^/]+~', $value) === 1) {
            self::failArtifactInvalid($context . '-abs-unc-slash');
        }
    }

    /**
     * @return array<string, mixed>
     * @throws DeterministicException
     */
    private static function readFixturePackageIndex(string $fixtureRelativePath): array
    {
        $fixtureRelativePath = \ltrim($fixtureRelativePath, "/\\");
        $fixtureRelativePath = \str_replace('\\', '/', $fixtureRelativePath);

        if (!\str_starts_with($fixtureRelativePath, 'deptrac_min/')) {
            self::failFixturePathInvalid('deptrac-min-only');
        }

        $path = FixtureRoot::path($fixtureRelativePath);

        if (!\is_file($path) || !\is_readable($path)) {
            self::failFixturePathInvalid('missing-fixture');
        }

        /** @var mixed $index */
        $index = require $path;

        if (!\is_array($index)) {
            self::failArtifactInvalid('fixture-return-not-array');
        }

        /** @var array<string, mixed> $index */
        return $index;
    }

    private static function safeHeaderFixtureRef(string $fixtureRelativeHint): string
    {
        $s = \str_replace('\\', '/', $fixtureRelativeHint);
        $s = \ltrim($s, "/\\");

        if (\preg_match('~(?i)\A[A-Z]:/~', $s) === 1) {
            $s = \preg_replace('~(?i)\A[A-Z]:/~', '', $s);
            if (!\is_string($s)) {
                $s = 'fixture';
            }
        }

        while (\str_starts_with($s, '//')) {
            $s = \substr($s, 2);
        }

        $parts = \array_values(\array_filter(\explode('/', $s), static fn(string $p): bool => $p !== ''));
        $n = \count($parts);

        if ($n >= 2) {
            return $parts[$n - 2] . '/' . $parts[$n - 1];
        }

        if ($n === 1) {
            return $parts[0] !== '' ? $parts[0] : 'fixture';
        }

        return 'fixture';
    }

    private static function dotEscape(string $s): string
    {
        $s = \str_replace('\\', '\\\\', $s);
        $s = \str_replace('"', '\\"', $s);

        return $s;
    }

    private static function xmlEscape(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function htmlEscape(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @throws DeterministicException
     */
    private static function failArtifactInvalid(string $message, ?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
            $message,
            $previous,
        );
    }

    /**
     * @throws DeterministicException
     */
    private static function failFixturePathInvalid(string $message, ?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID,
            $message,
            $previous,
        );
    }
}
