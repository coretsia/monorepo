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
 * DeptracGenerate (Epic 0.80.0) [SPIKE]:
 * - read package-index from fixtures under framework/tools/spikes/fixtures/deptrac_min/
 * - generate deterministic deptrac.yaml (rerun-no-diff) with stable ordering
 *
 * Output policy:
 * - MUST NOT emit stdout/stderr.
 * - MUST write text via DeterministicFile::writeTextLf() (LF-only + final newline).
 *
 * Notes:
 * - This spike produces a minimal deptrac config:
 *   - deptrac.paths:  "<repo_root>/<package.path>/src"
 *   - deptrac.layers: one per package (directory collector to src path)
 *   - deptrac.ruleset: edges from package->deps (stable sorted)
 * - It intentionally avoids any absolute paths, timestamps, or tool-version metadata.
 */
final class DeptracGenerate
{
    private const string DEFAULT_FIXTURE = 'deptrac_min/package_index_ok.php';

    private function __construct()
    {
    }

    /**
     * @throws DeterministicException
     */
    public static function generateToFile(?string $fixtureRelativePath, string $outputYamlPath): void
    {
        $fixtureRelativePath = $fixtureRelativePath ?? self::DEFAULT_FIXTURE;

        $yaml = self::generateYamlFromFixture($fixtureRelativePath);

        try {
            DeterministicFile::writeTextLf($outputYamlPath, $yaml);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                $e,
            );
        }
    }

    /**
     * @throws DeterministicException
     */
    public static function generateYamlFromFixture(?string $fixtureRelativePath = null): string
    {
        $fixtureRelativePath = $fixtureRelativePath ?? self::DEFAULT_FIXTURE;

        $index = self::readFixturePackageIndex($fixtureRelativePath);

        return self::generateYamlFromIndex($index, $fixtureRelativePath);
    }

    /**
     * @param array<string,mixed> $index
     * @throws DeterministicException
     */
    public static function generateYamlFromIndex(array $index, string $fixtureRelativeHint = 'deptrac_min/package_index.php'): string
    {
        $schemaVersion = $index['schema_version'] ?? null;
        $repoRoot = $index['repo_root'] ?? null;
        $packages = $index['packages'] ?? null;

        if (!\is_int($schemaVersion) || $schemaVersion < 1) {
            self::failArtifactInvalid('fixture-schema-version-invalid');
        }

        if (!\is_string($repoRoot) || $repoRoot === '') {
            self::failArtifactInvalid('fixture-repo-root-invalid');
        }

        if (!\is_array($packages) || $packages === []) {
            self::failArtifactInvalid('fixture-packages-invalid');
        }

        $repoRoot = self::normalizeRelativePath($repoRoot);

        /** @var array<string, array{package_id:string, composer:string, path:string, module_id:string, deps:list<string>, allowlist:list<string>}> $pkgById */
        $pkgById = [];

        foreach ($packages as $pkg) {
            if (!\is_array($pkg)) {
                self::failArtifactInvalid('fixture-package-not-array');
            }

            $packageId = $pkg['package_id'] ?? null;
            $composer = $pkg['composer'] ?? null;
            $path = $pkg['path'] ?? null;
            $moduleId = $pkg['module_id'] ?? null;
            $deps = $pkg['deps'] ?? null;
            $allowlist = $pkg['allowlist'] ?? null;

            if (!\is_string($packageId) || $packageId === '') {
                self::failArtifactInvalid('fixture-package-id-invalid');
            }
            if (!\is_string($composer) || $composer === '') {
                self::failArtifactInvalid('fixture-composer-invalid');
            }
            if (!\is_string($path) || $path === '') {
                self::failArtifactInvalid('fixture-path-invalid');
            }
            if (!\is_string($moduleId) || $moduleId === '') {
                self::failArtifactInvalid('fixture-module-id-invalid');
            }
            if (!\is_array($deps) || !\array_is_list($deps)) {
                self::failArtifactInvalid('fixture-deps-invalid');
            }
            if (!\is_array($allowlist) || !\array_is_list($allowlist)) {
                self::failArtifactInvalid('fixture-allowlist-invalid');
            }

            self::assertNoAbsolutePathPatterns($packageId, 'package-id');

            $path = self::normalizeRelativePath($path);

            $depsOut = [];
            foreach ($deps as $dep) {
                if (!\is_string($dep) || $dep === '') {
                    self::failArtifactInvalid('fixture-dep-item-invalid');
                }

                self::assertNoAbsolutePathPatterns($dep, 'dep');

                $depsOut[] = $dep;
            }

            $allowOut = [];
            foreach ($allowlist as $entry) {
                if (!\is_string($entry) || $entry === '') {
                    self::failArtifactInvalid('fixture-allowlist-item-invalid');
                }

                $allowOut[] = $entry;
            }

            self::assertAllowlistPolicy($allowOut);

            $pkgById[$packageId] = [
                'package_id' => $packageId,
                'composer' => $composer,
                'path' => $path,
                'module_id' => $moduleId,
                'deps' => $depsOut,
                'allowlist' => $allowOut,
            ];
        }

        $packageIds = \array_keys($pkgById);
        \usort($packageIds, static fn(string $a, string $b): int => \strcmp($a, $b));

        $srcPaths = [];
        foreach ($packageIds as $id) {
            $pkgPath = $pkgById[$id]['path'];
            $srcPaths[] = self::joinPath($repoRoot, $pkgPath, 'src');
        }

        /** @var array<string, list<string>> $ruleset */
        $ruleset = [];
        foreach ($packageIds as $id) {
            $depsOut = $pkgById[$id]['deps'];
            $depsOut = \array_values(\array_unique($depsOut));
            \usort($depsOut, static fn(string $a, string $b): int => \strcmp($a, $b));

            foreach ($depsOut as $to) {
                if (!isset($pkgById[$to])) {
                    self::failArtifactInvalid('fixture-unknown-dep');
                }
            }

            $ruleset[$id] = $depsOut;
        }

        self::assertNoCycles($packageIds, $ruleset);

        $lines = [];
        $lines[] = '# GENERATED by Coretsia spikes (Epic 0.80.0) - DO NOT EDIT.';
        $lines[] = '# fixture: ' . self::safeHeaderFixtureRef($fixtureRelativeHint);
        $lines[] = 'deptrac:';
        $lines[] = '  paths:';

        foreach ($srcPaths as $p) {
            $lines[] = '    - ' . self::yamlQuote($p);
        }

        $lines[] = '  layers:';
        foreach ($packageIds as $id) {
            $src = self::joinPath($repoRoot, $pkgById[$id]['path'], 'src');

            $lines[] = '    - name: ' . self::yamlQuote($id);
            $lines[] = '      collectors:';
            $lines[] = '        - type: directory';
            $lines[] = '          value: ' . self::yamlQuote($src);
        }

        $lines[] = '  ruleset:';
        foreach ($packageIds as $id) {
            $depsOut = $ruleset[$id];

            if ($depsOut === []) {
                $lines[] = '    ' . self::yamlQuote($id) . ': []';
                continue;
            }

            $lines[] = '    ' . self::yamlQuote($id) . ':';
            foreach ($depsOut as $to) {
                $lines[] = '      - ' . self::yamlQuote($to);
            }
        }

        return \implode("\n", $lines) . "\n";
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
     * @param list<string> $allowlist
     * @throws DeterministicException
     */
    private static function assertAllowlistPolicy(array $allowlist): void
    {
        foreach ($allowlist as $raw) {
            $p = \str_replace('\\', '/', \trim($raw));

            if ($p === 'src' || \str_starts_with($p, 'src/')) {
                self::failAllowlistInvalid('allowlist-forbidden-src-root');
            }

            if (!(\str_starts_with($p, 'tests/') || \str_starts_with($p, 'tools/'))) {
                self::failAllowlistInvalid('allowlist-forbidden-root');
            }
        }
    }

    /**
     * @param list<string> $packageIds
     * @param array<string, list<string>> $ruleset
     * @throws DeterministicException
     */
    private static function assertNoCycles(array $packageIds, array $ruleset): void
    {
        /** @var array<string, int> $color */
        $color = [];
        foreach ($packageIds as $id) {
            $color[$id] = 0;
        }

        $dfs = null;
        $dfs = static function (string $node) use (&$dfs, &$color, $ruleset): void {
            $color[$node] = 1;

            $targets = $ruleset[$node] ?? [];
            foreach ($targets as $to) {
                $c = $color[$to] ?? 0;

                if ($c === 1) {
                    throw new DeterministicException(
                        ErrorCodes::CORETSIA_DEPTRAC_CYCLE_DETECTED,
                        'cycle-detected',
                    );
                }

                if ($c === 0) {
                    $dfs($to);
                }
            }

            $color[$node] = 2;
        };

        foreach ($packageIds as $id) {
            if (($color[$id] ?? 0) === 0) {
                $dfs($id);
            }
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

        $index = require $path;

        if (!\is_array($index)) {
            self::failArtifactInvalid('fixture-return-not-array');
        }

        /** @var array<string, mixed> $index */
        return $index;
    }

    /**
     * @throws DeterministicException
     */
    private static function normalizeRelativePath(string $path): string
    {
        $p = \str_replace('\\', '/', \trim($path));

        if ($p === '') {
            self::failArtifactInvalid('empty-path');
        }

        if (\str_starts_with($p, '/')) {
            self::failArtifactInvalid('absolute-posix-path');
        }

        if (\preg_match('~(?i)\A[A-Z]:[\\\\/]~', $p) === 1) {
            self::failArtifactInvalid('absolute-win-drive-path');
        }

        if (\str_starts_with($p, '//') || \str_starts_with($p, '\\\\')) {
            self::failArtifactInvalid('absolute-unc-path');
        }

        while (\str_starts_with($p, './')) {
            $p = \substr($p, 2);
        }

        $p = \preg_replace('~/+~', '/', $p);
        if (!\is_string($p) || $p === '') {
            self::failArtifactInvalid('path-normalization-failed');
        }

        return $p;
    }

    private static function joinPath(string ...$parts): string
    {
        $out = [];

        foreach ($parts as $part) {
            $part = self::normalizeRelativePath($part);
            $part = \trim($part, '/');
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return \implode('/', $out);
    }

    private static function yamlQuote(string $s): string
    {
        return "'" . \str_replace("'", "''", $s) . "'";
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
    private static function failAllowlistInvalid(string $message, ?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_DEPTRAC_ALLOWLIST_INVALID,
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
