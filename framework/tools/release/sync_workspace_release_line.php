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

require_once __DIR__ . '/../spikes/_support/ConsoleOutput.php';
require_once __DIR__ . '/../spikes/_support/ErrorCodes.php';
require_once __DIR__ . '/../spikes/_support/DeterministicException.php';
require_once __DIR__ . '/../spikes/_support/DeterministicFile.php';

final class SyncWorkspaceReleaseLine
{
    public const string CODE_FAILED = 'CORETSIA_RELEASE_LINE_WORKSPACE_SYNC_FAILED';
    private const string CODE_OUT_OF_SYNC = 'CORETSIA_RELEASE_LINE_WORKSPACE_OUT_OF_SYNC';
    private const string RELEASE_LINE_PATH = 'framework/tools/release/release-line.json';
    private const string RELEASE_LINE_SCHEMA_VERSION = 'coretsia.releaseLine.v1';

    public static function main(array $argv): int
    {
        $repoRoot = self::resolveRepoRoot($argv);
        $check = self::argFlag($argv, '--check');

        $releaseLine = self::loadReleaseLine($repoRoot);
        $packageNames = self::discoverWorkspacePackageNames($repoRoot);

        $frameworkComposerPath = $repoRoot . '/framework/composer.json';

        $changed = self::syncFrameworkComposerRequireDev(
            $frameworkComposerPath,
            $packageNames,
            $releaseLine['devVersion'],
            !$check,
            $repoRoot,
        );

        if ($check) {
            if ($changed) {
                \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                    self::CODE_OUT_OF_SYNC,
                    [self::rel($repoRoot, $frameworkComposerPath)],
                );
                return 1;
            }

            return 0;
        }

        \Coretsia\Tools\Spikes\_support\ConsoleOutput::line('OK', false);

        if ($changed) {
            \Coretsia\Tools\Spikes\_support\ConsoleOutput::line(self::rel($repoRoot, $frameworkComposerPath), false);
        }

        return 0;
    }

    /**
     * @return array{currentMinor:string,devVersion:string,publicConstraint:string}
     */
    private static function loadReleaseLine(string $repoRoot): array
    {
        $path = $repoRoot . '/' . self::RELEASE_LINE_PATH;
        $data = self::readJsonObject($path, $repoRoot);

        $schemaVersion = $data['schemaVersion'] ?? null;
        $currentMinor = $data['currentMinor'] ?? null;
        $devVersion = $data['devVersion'] ?? null;
        $publicConstraint = $data['publicConstraint'] ?? null;

        if ($schemaVersion !== self::RELEASE_LINE_SCHEMA_VERSION) {
            throw new RuntimeException('release-line schemaVersion invalid: ' . self::rel($repoRoot, $path));
        }

        if (!is_string($currentMinor) || preg_match('~\A[0-9]+\.[0-9]+\z~', $currentMinor) !== 1) {
            throw new RuntimeException('release-line currentMinor invalid: ' . self::rel($repoRoot, $path));
        }

        if (!is_string($devVersion) || $devVersion !== $currentMinor . '.x-dev') {
            throw new RuntimeException('release-line devVersion invalid: ' . self::rel($repoRoot, $path));
        }

        if (!is_string($publicConstraint) || $publicConstraint !== '^' . $currentMinor . '.0') {
            throw new RuntimeException('release-line publicConstraint invalid: ' . self::rel($repoRoot, $path));
        }

        return [
            'currentMinor' => $currentMinor,
            'devVersion' => $devVersion,
            'publicConstraint' => $publicConstraint,
        ];
    }

    /**
     * @return list<string>
     */
    private static function discoverWorkspacePackageNames(string $repoRoot): array
    {
        $packagesRoot = $repoRoot . '/framework/packages';

        if (!is_dir($packagesRoot)) {
            throw new RuntimeException('Missing framework packages root: framework/packages');
        }

        $packagesRootReal = realpath($packagesRoot);
        if ($packagesRootReal === false) {
            throw new RuntimeException('Cannot resolve framework packages root: framework/packages');
        }

        $packagesRootReal = rtrim(str_replace('\\', '/', $packagesRootReal), '/');

        $hits = glob($packagesRootReal . '/*/*/composer.json', GLOB_NOSORT);
        if ($hits === false) {
            $hits = [];
        }

        $hits = array_values(
            array_map(
                static fn (string $path): string => str_replace('\\', '/', $path),
                $hits,
            )
        );
        sort($hits, SORT_STRING);

        $names = [];

        foreach ($hits as $composerJsonPath) {
            if (!is_file($composerJsonPath)) {
                continue;
            }

            $relative = self::relFrom($packagesRootReal, $composerJsonPath);
            $parts = explode('/', $relative);

            if (count($parts) !== 3 || $parts[2] !== 'composer.json') {
                throw new RuntimeException('Invalid package composer path: ' . self::rel($repoRoot, $composerJsonPath));
            }

            [$layer, $slug] = [$parts[0], $parts[1]];

            if (preg_match('~\A[a-z][a-z0-9-]*\z~', $layer) !== 1) {
                throw new RuntimeException('Invalid package layer: ' . self::rel($repoRoot, $composerJsonPath));
            }

            if (preg_match('~\A[a-z0-9][a-z0-9-]*\z~', $slug) !== 1) {
                throw new RuntimeException('Invalid package slug: ' . self::rel($repoRoot, $composerJsonPath));
            }

            $composer = self::readJsonObject($composerJsonPath, $repoRoot);

            $name = $composer['name'] ?? null;
            $expectedName = 'coretsia/' . $layer . '-' . $slug;

            if ($name !== $expectedName) {
                throw new RuntimeException(
                    'Package composer name mismatch: '
                    . self::rel($repoRoot, $composerJsonPath)
                    . ' expected '
                    . $expectedName
                );
            }

            $names[] = $expectedName;
        }

        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        if ($names === []) {
            throw new RuntimeException('No workspace packages discovered under framework/packages');
        }

        return $names;
    }

    /**
     * @param list<string> $packageNames
     */
    private static function syncFrameworkComposerRequireDev(
        string $frameworkComposerPath,
        array $packageNames,
        string $devVersion,
        bool $apply,
        string $repoRoot,
    ): bool {
        $originalBytes = self::readFile($frameworkComposerPath, $repoRoot);
        $bytesForJsonDecode = $originalBytes;

        if (str_starts_with($bytesForJsonDecode, "\xEF\xBB\xBF")) {
            $bytesForJsonDecode = substr($bytesForJsonDecode, 3);
        }

        $data = json_decode(self::normalizeEol($bytesForJsonDecode), true);

        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Invalid JSON object: ' . self::rel($repoRoot, $frameworkComposerPath));
        }

        $requireDev = $data['require-dev'] ?? [];

        if (!is_array($requireDev) || array_is_list($requireDev)) {
            throw new RuntimeException('framework composer require-dev must be object');
        }

        /** @var array<string,string> $extRequirements */
        $extRequirements = [];

        /** @var array<string,string> $externalRequirements */
        $externalRequirements = [];

        /** @var array<string,true> $discovered */
        $discovered = [];

        foreach ($packageNames as $packageName) {
            $discovered[$packageName] = true;
        }

        foreach ($requireDev as $name => $constraint) {
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('framework composer require-dev package name invalid');
            }

            if (!is_string($constraint) || $constraint === '') {
                throw new RuntimeException('framework composer require-dev constraint invalid for ' . $name);
            }

            if (str_starts_with($name, 'ext-')) {
                $extRequirements[$name] = $constraint;
                continue;
            }

            if (str_starts_with($name, 'coretsia/')) {
                if (!isset($discovered[$name])) {
                    throw new RuntimeException('Unknown internal workspace package in require-dev: ' . $name);
                }

                // Managed below from discovered workspace packages.
                continue;
            }

            $externalRequirements[$name] = $constraint;
        }

        ksort($extRequirements, SORT_STRING);
        ksort($externalRequirements, SORT_STRING);

        /** @var array<string,string> $internalRequirements */
        $internalRequirements = [];

        foreach ($packageNames as $packageName) {
            $internalRequirements[$packageName] = $devVersion;
        }

        ksort($internalRequirements, SORT_STRING);

        $data['require-dev'] = array_merge(
            $extRequirements,
            $internalRequirements,
            $externalRequirements,
        );

        $newJson = self::encodeComposerJsonCanonical($data);
        $changed = ($newJson !== self::normalizeToLfFinalNewline($bytesForJsonDecode));

        if ($changed && $apply) {
            self::writeBackupIfNeeded($frameworkComposerPath, $originalBytes, $repoRoot);
            \Coretsia\Tools\Spikes\_support\DeterministicFile::writeTextLf($frameworkComposerPath, $newJson);
        }

        return $changed;
    }

    /**
     * @return array<string,mixed>
     */
    private static function readJsonObject(string $path, string $repoRoot): array
    {
        $raw = self::readFile($path, $repoRoot);

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $data = json_decode(self::normalizeEol($raw), true);

        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Invalid JSON object: ' . self::rel($repoRoot, $path));
        }

        return $data;
    }

    private static function readFile(string $path, string $repoRoot): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('File missing: ' . self::rel($repoRoot, $path));
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException('File read failed: ' . self::rel($repoRoot, $path));
        }

        return $contents;
    }

    private static function writeBackupIfNeeded(string $composerJsonPath, string $originalBytes, string $repoRoot): void
    {
        $backupDir = $repoRoot . '/framework/var/backups/release-line';

        if (!is_dir($backupDir) && !@mkdir($backupDir, 0777, true)) {
            throw new RuntimeException('Cannot create backup dir: framework/var/backups/release-line');
        }

        $base = basename(dirname($composerJsonPath)) . '__composer.json.bak';
        $dst = $backupDir . '/' . $base;

        if (is_file($dst)) {
            $i = 1;

            while (true) {
                $candidate = $backupDir . '/' . $base . '.' . $i;

                if (!is_file($candidate)) {
                    $dst = $candidate;
                    break;
                }

                $i++;

                if ($i > 999) {
                    throw new RuntimeException('Too many backups for: ' . $base);
                }
            }
        }

        \Coretsia\Tools\Spikes\_support\DeterministicFile::writeBytesExact($dst, $originalBytes);
    }

    /**
     * Canonical composer.json encoder:
     * - UTF-8 text
     * - LF-only + final "\n"
     * - pretty format: 4 spaces indentation, then rewrite indentation to 2 spaces on line prefix
     * - NO global key sorting/reordering except require-dev rebuilt intentionally
     */
    private static function encodeComposerJsonCanonical(array $data): string
    {
        $pretty4 = self::encodeJsonPretty($data, 4);
        $pretty4 = self::normalizeEol($pretty4);

        $two = self::reindentLeadingSpaces($pretty4, 4, 2);
        $two = self::normalizeToLfFinalNewline($two);

        return $two;
    }

    private static function encodeJsonPretty(mixed $value, int $indentSize): string
    {
        return self::encodeJsonValue($value, 0, $indentSize);
    }

    private static function encodeJsonValue(mixed $value, int $level, int $indentSize): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value)) {
            throw new RuntimeException('JSON float forbidden in tooling encoder');
        }

        if (is_string($value)) {
            return '"' . self::escapeJsonString($value) . '"';
        }

        if (!is_array($value)) {
            throw new RuntimeException('Unsupported JSON type: ' . gettype($value));
        }

        $indent = str_repeat(' ', $level * $indentSize);
        $childIndent = str_repeat(' ', ($level + 1) * $indentSize);

        if (array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];

            foreach ($value as $v) {
                $parts[] = $childIndent . self::encodeJsonValue($v, $level + 1, $indentSize);
            }

            return "[\n" . implode(",\n", $parts) . "\n" . $indent . "]";
        }

        $parts = [];

        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                throw new RuntimeException('JSON object keys must be strings');
            }

            $parts[] = $childIndent
                . '"'
                . self::escapeJsonString($k)
                . '": '
                . self::encodeJsonValue($v, $level + 1, $indentSize);
        }

        return "{\n" . implode(",\n", $parts) . "\n" . $indent . "}";
    }

    private static function escapeJsonString(string $s): string
    {
        $out = '';
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            $o = ord($c);

            if ($c === '"') {
                $out .= '\\"';
                continue;
            }

            if ($c === '\\') {
                $out .= '\\\\';
                continue;
            }

            if ($o === 8) {
                $out .= '\\b';
                continue;
            }

            if ($o === 12) {
                $out .= '\\f';
                continue;
            }

            if ($o === 10) {
                $out .= '\\n';
                continue;
            }

            if ($o === 13) {
                $out .= '\\r';
                continue;
            }

            if ($o === 9) {
                $out .= '\\t';
                continue;
            }

            if ($o < 0x20) {
                $out .= sprintf('\\u%04x', $o);
                continue;
            }

            $out .= $c;
        }

        $out = str_replace("\u{2028}", '\\u2028', $out);
        $out = str_replace("\u{2029}", '\\u2029', $out);

        return $out;
    }

    private static function reindentLeadingSpaces(string $json, int $from, int $to): string
    {
        $out = preg_replace_callback('~^( +)~m', static function (array $m) use ($from, $to): string {
            $n = strlen($m[1]);

            if ($n % $from !== 0) {
                return $m[1];
            }

            $levels = intdiv($n, $from);

            return str_repeat(' ', $levels * $to);
        }, $json);

        if (!is_string($out)) {
            throw new RuntimeException('indent rewrite failed');
        }

        return $out;
    }

    private static function normalizeEol(string $s): string
    {
        return str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function normalizeToLfFinalNewline(string $s): string
    {
        $s = self::normalizeEol($s);

        if (!str_ends_with($s, "\n")) {
            $s .= "\n";
        }

        return $s;
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    /**
     * Read `--repo-root` argument:
     * - `--repo-root=/path`
     * - `--repo-root /path`
     */
    private static function argRepoRoot(array $argv): ?string
    {
        $n = count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];

            if (str_starts_with($a, '--repo-root=')) {
                $v = trim(substr($a, strlen('--repo-root=')));
                return $v !== '' ? $v : null;
            }

            if ($a === '--repo-root') {
                $next = ($i + 1 < $n) ? trim((string)$argv[$i + 1]) : '';
                return $next !== '' ? $next : null;
            }
        }

        return null;
    }

    private static function resolveRepoRoot(array $argv): string
    {
        $arg = self::argRepoRoot($argv);

        if ($arg === null) {
            return self::repoRootUnsafe();
        }

        $candidate = str_replace('\\', '/', trim($arg));

        if (!self::isAbsolutePath($candidate)) {
            $cwd = getcwd();

            if ($cwd === false) {
                throw new RuntimeException('Cannot resolve cwd');
            }

            $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
            $candidate = $cwd . '/' . ltrim($candidate, '/');
        }

        $candidate = rtrim($candidate, '/');

        $real = realpath($candidate);

        if ($real !== false) {
            $candidate = rtrim(str_replace('\\', '/', $real), '/');
        }

        $hasMonorepoMarkers =
            is_dir($candidate . '/framework')
            && is_dir($candidate . '/skeleton')
            && is_file($candidate . '/composer.json');

        $hasFixtureMarkers =
            is_file($candidate . '/framework/composer.json')
            && is_file($candidate . '/skeleton/composer.json');

        if (!$hasMonorepoMarkers && !$hasFixtureMarkers) {
            throw new RuntimeException('Invalid --repo-root: missing framework/skeleton/composer.json markers');
        }

        return $candidate;
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

    private static function repoRootUnsafe(): string
    {
        $dir = getcwd();

        if ($dir === false) {
            throw new RuntimeException('Cannot resolve cwd');
        }

        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        for ($i = 0; $i < 30; $i++) {
            if (is_dir($dir . '/framework') && is_dir($dir . '/skeleton') && is_file($dir . '/composer.json')) {
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
}

try {
    exit(SyncWorkspaceReleaseLine::main($argv));
} catch (Throwable $e) {
    $msg = str_replace(["\r\n", "\r"], "\n", $e->getMessage());
    \Coretsia\Tools\Spikes\_support\ConsoleOutput::line(SyncWorkspaceReleaseLine::CODE_FAILED . ": {$msg}");
    exit(1);
}
