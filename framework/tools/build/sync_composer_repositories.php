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

final class SyncComposerRepositories
{
    public const string CODE_FAILED = 'CORETSIA_WORKSPACE_SYNC_FAILED';
    private const string CODE_MANAGED_BLOCK_INVALID = 'CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID';
    private const string CODE_MANAGED_REPOS_OUT_OF_SYNC = 'CORETSIA_WORKSPACE_MANAGED_REPOS_OUT_OF_SYNC';
    private const string MANAGED_FLAG = 'coretsia_managed';
    private const string RELEASE_LINE_PATH = 'framework/tools/release/release-line.json';
    private const string RELEASE_LINE_SCHEMA_VERSION = 'coretsia.releaseLine.v1';

    public static function main(array $argv): int
    {
        $repoRoot = self::resolveRepoRoot($argv);
        $check = self::argFlag($argv, '--check');

        $releaseLine = self::loadReleaseLine($repoRoot);
        $workspacePackageVersions = self::discoverWorkspacePackageVersions(
            $repoRoot,
            $releaseLine['devVersion'],
        );

        $targets = [
            [
                'path' => $repoRoot . '/composer.json',
                'desired' => self::desiredManagedReposForRoot($workspacePackageVersions),
            ],
            [
                'path' => $repoRoot . '/framework/composer.json',
                'desired' => self::desiredManagedReposForFramework($workspacePackageVersions),
            ],
            [
                'path' => $repoRoot . '/skeleton/composer.json',
                'desired' => self::desiredManagedReposForSkeleton($workspacePackageVersions),
            ],
        ];

        $changedFiles = [];
        $invalidBlockFiles = [];

        foreach ($targets as $t) {
            $path = (string)$t['path'];
            /** @var list<array<string,mixed>> $desired */
            $desired = $t['desired'];

            $result = self::syncOne($path, $desired, !$check, $repoRoot);

            if ($result['invalidManagedBlock']) {
                $invalidBlockFiles[] = self::rel($repoRoot, $path);
            }
            if ($result['changed']) {
                $changedFiles[] = self::rel($repoRoot, $path);
            }
        }

        sort($invalidBlockFiles, SORT_STRING);
        sort($changedFiles, SORT_STRING);

        if ($check) {
            if ($invalidBlockFiles !== []) {
                fwrite(STDERR, self::CODE_MANAGED_BLOCK_INVALID . "\n");
                foreach ($invalidBlockFiles as $p) {
                    fwrite(STDERR, $p . "\n");
                }
                return 1;
            }

            if ($changedFiles !== []) {
                fwrite(STDERR, self::CODE_MANAGED_REPOS_OUT_OF_SYNC . "\n");
                foreach ($changedFiles as $p) {
                    fwrite(STDERR, $p . "\n");
                }
                return 1;
            }

            fwrite(STDOUT, "OK\n");
            return 0;
        }

        fwrite(STDOUT, "OK\n");
        foreach ($changedFiles as $p) {
            fwrite(STDOUT, $p . "\n");
        }
        return 0;
    }

    /**
     * @param list<array<string,mixed>> $desiredManaged
     * @return array{changed:bool,invalidManagedBlock:bool}
     */
    private static function syncOne(
        string $composerJsonPath,
        array $desiredManaged,
        bool $apply,
        string $repoRoot
    ): array {
        if (!is_file($composerJsonPath)) {
            throw new RuntimeException('composer.json missing: ' . self::rel($repoRoot, $composerJsonPath));
        }

        $originalBytes = (string)file_get_contents($composerJsonPath);
        $bytesForJsonDecode = $originalBytes;

        // UTF-8 BOM: ignore for decode, but keep for backup bytes.
        if (str_starts_with($bytesForJsonDecode, "\xEF\xBB\xBF")) {
            $bytesForJsonDecode = substr($bytesForJsonDecode, 3);
        }

        $originalTextNormalized = self::normalizeEol($bytesForJsonDecode);
        $data = json_decode($originalTextNormalized, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON: ' . self::rel($repoRoot, $composerJsonPath));
        }

        $repos = $data['repositories'] ?? [];
        if (!is_array($repos)) {
            throw new RuntimeException('repositories must be array: ' . self::rel($repoRoot, $composerJsonPath));
        }
        $repos = array_values($repos);

        // Detect non-contiguous managed block (if managed entries exist).
        $managedIdx = [];
        foreach ($repos as $i => $r) {
            if (!is_array($r)) {
                throw new RuntimeException(
                    'repository entry must be object: ' . self::rel($repoRoot, $composerJsonPath)
                );
            }
            if (self::isManaged($r)) {
                $managedIdx[] = (int)$i;
            }
        }

        $invalidManagedBlock = false;
        if ($managedIdx !== []) {
            $min = min($managedIdx);
            $max = max($managedIdx);
            // contiguous means: count == (max-min+1)
            if (count($managedIdx) !== (($max - $min) + 1)) {
                $invalidManagedBlock = true;
            }
        }

        // Keep user repos in original order, drop all managed (regardless of block validity).
        $userRepos = [];
        foreach ($repos as $r) {
            /** @var array<string,mixed> $r */
            if (!self::isManaged($r)) {
                $userRepos[] = $r; // keep object key insertion order as-is
            }
        }

        // Canonical managed entries (stable key order inside each entry, desired order preserved).
        $managed = self::canonicalizeManaged($desiredManaged);

        // Managed block MUST be contiguous; canonical placement is a single block at the end.
        $expectedRepos = array_values(array_merge($userRepos, $managed));

        $data['repositories'] = $expectedRepos;

        // Canonical JSON bytes:
        // - json-like pretty format (4 spaces) then rewritten to 2 spaces indentation
        // - LF-only + final newline
        // - global key order preserved from decoded JSON (except repositories replaced)
        $newJson = self::encodeComposerJsonCanonical($data);

        // Compare bytes (single-choice): drift is bytes drift (CRLF/BOM differences count as drift).
        $changed = ($newJson !== self::normalizeToLfFinalNewline($bytesForJsonDecode));

        if (!$changed) {
            // Still treat invalidManagedBlock as an error condition in --check mode even if content is already canonical.
            return ['changed' => false, 'invalidManagedBlock' => $invalidManagedBlock];
        }

        if ($apply) {
            self::writeBackupIfNeeded($composerJsonPath, $originalBytes, $repoRoot);
            file_put_contents($composerJsonPath, $newJson, LOCK_EX);
        }

        return ['changed' => true, 'invalidManagedBlock' => $invalidManagedBlock];
    }

    /**
     * Root composer.json managed repositories.
     *
     * @param array<string,string> $workspacePackageVersions
     * @return list<array<string,mixed>>
     */
    private static function desiredManagedReposForRoot(array $workspacePackageVersions): array
    {
        return [
            self::managedPathRepo('framework', true),
            self::managedPathRepo('framework/packages/*/*', true, $workspacePackageVersions),
            self::managedPathRepo('skeleton', true),
        ];
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
     * @return array<string,string>
     */
    private static function discoverWorkspacePackageVersions(string $repoRoot, string $devVersion): array
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

        /** @var array<string,string> $versions */
        $versions = [];

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

            $versions[$expectedName] = $devVersion;
        }

        ksort($versions, SORT_STRING);

        if ($versions === []) {
            throw new RuntimeException('No workspace packages discovered under framework/packages');
        }

        return $versions;
    }

    /**
     * @return array<string,mixed>
     */
    private static function readJsonObject(string $path, string $repoRoot): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('JSON file missing: ' . self::rel($repoRoot, $path));
        }

        $raw = (string)file_get_contents($path);

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $raw = self::normalizeEol($raw);
        $data = json_decode($raw, true);

        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Invalid JSON object: ' . self::rel($repoRoot, $path));
        }

        return $data;
    }

    /**
     * @param array<string,string> $workspacePackageVersions
     * @return list<array<string,mixed>>
     */
    private static function desiredManagedReposForFramework(array $workspacePackageVersions): array
    {
        return [
            self::managedPathRepo('packages/*/*', true, $workspacePackageVersions),
        ];
    }

    /**
     * @param array<string,string> $workspacePackageVersions
     * @return list<array<string,mixed>>
     */
    private static function desiredManagedReposForSkeleton(array $workspacePackageVersions): array
    {
        return [
            self::managedPathRepo('../framework', true),
            self::managedPathRepo('../framework/packages/*/*', true, $workspacePackageVersions),
        ];
    }

    /**
     * @param array<string,string>|null $workspacePackageVersions
     * @return array<string,mixed>
     */
    private static function managedPathRepo(string $url, bool $symlink, ?array $workspacePackageVersions = null): array
    {
        /** @var array<string,mixed> $options */
        $options = [
            'symlink' => $symlink,
        ];

        if ($workspacePackageVersions !== null) {
            $options['reference'] = 'config';
            $options['versions'] = $workspacePackageVersions;
        }

        return [
            'type' => 'path',
            'url' => $url,
            'options' => $options,
            self::MANAGED_FLAG => true,
        ];
    }

    /**
     * @param array<string,mixed> $repo
     */
    private static function isManaged(array $repo): bool
    {
        return ($repo[self::MANAGED_FLAG] ?? false) === true;
    }

    /**
     * @param list<array<string,mixed>> $desired
     * @return list<array<string,mixed>>
     */
    private static function canonicalizeManaged(array $desired): array
    {
        $out = [];

        foreach ($desired as $r) {
            if (!is_array($r)) {
                throw new RuntimeException('desiredManaged must be list<object>');
            }

            $type = $r['type'] ?? null;
            $url = $r['url'] ?? null;

            if (!is_string($type) || $type === '' || !is_string($url) || $url === '') {
                throw new RuntimeException('managed repo must contain type+url');
            }

            $options = $r['options'] ?? [];
            if (!is_array($options) || array_is_list($options)) {
                throw new RuntimeException('options must be object');
            }

            /** @var array<string,mixed> $canonicalOptions */
            $canonicalOptions = [
                'symlink' => (bool)($options['symlink'] ?? true),
            ];

            if (array_key_exists('reference', $options)) {
                $reference = $options['reference'];

                if (!is_string($reference) || $reference === '') {
                    throw new RuntimeException('options.reference must be non-empty string');
                }

                $canonicalOptions['reference'] = $reference;
            }

            if (array_key_exists('versions', $options)) {
                $versions = $options['versions'];

                if (!is_array($versions)) {
                    throw new RuntimeException('options.versions must be non-empty object');
                }

                if ($versions === [] || array_is_list($versions)) {
                    throw new RuntimeException('options.versions must be non-empty object');
                }

                /** @var array<string,string> $canonicalVersions */
                $canonicalVersions = [];

                foreach ($versions as $name => $version) {
                    if (!is_string($name) || $name === '') {
                        throw new RuntimeException('options.versions package name must be non-empty string');
                    }

                    if (!is_string($version) || $version === '') {
                        throw new RuntimeException('options.versions package version must be non-empty string');
                    }

                    $canonicalVersions[$name] = $version;
                }

                ksort($canonicalVersions, SORT_STRING);

                $canonicalOptions['versions'] = $canonicalVersions;
            }

            $out[] = [
                'type' => $type,
                'url' => $url,
                'options' => $canonicalOptions,
                self::MANAGED_FLAG => true,
            ];
        }

        return $out;
    }

    /**
     * Canonical composer.json encoder (single-choice):
     * - UTF-8 text
     * - LF-only + final "\n"
     * - pretty format: 4 spaces indentation, then rewrite indentation to 2 spaces on line prefix
     * - NO global key sorting/reordering (preserve insertion order in arrays)
     */
    private static function encodeComposerJsonCanonical(array $data): string
    {
        $pretty4 = self::encodeJsonPretty($data, 4);
        $pretty4 = self::normalizeEol($pretty4);

        $two = self::reindentLeadingSpaces($pretty4, 4, 2);
        $two = self::normalizeToLfFinalNewline($two);

        return $two;
    }

    /**
     * Pretty-print JSON with a given indent size.
     * NOTE: json_encode() is forbidden in framework/tools/\**
     * this is a minimal deterministic encoder for composer.json.
     */
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

            // Control chars -> \u00XX
            if ($o < 0x20) {
                $out .= sprintf('\\u%04x', $o);
                continue;
            }

            // Keep UTF-8 bytes as-is (composer.json is UTF-8).
            $out .= $c;
        }

        // Extra safety for JS-unsafe separators (optional but deterministic):
        $out = str_replace("\u{2028}", '\\u2028', $out);
        $out = str_replace("\u{2029}", '\\u2029', $out);

        return $out;
    }

    /**
     * Rewrite only leading indentation on each line:
     * from N spaces per level to M spaces per level.
     */
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

    private static function writeBackupIfNeeded(string $composerJsonPath, string $originalBytes, string $repoRoot): void
    {
        // Only if bytes differ: caller guarantees it is changing the file.
        $backupDir = $repoRoot . '/framework/var/backups/workspace';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0777, true)) {
            throw new RuntimeException('Cannot create backup dir: framework/var/backups/workspace');
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

        // writeBytesExact: do not normalize EOL/BOM/etc.
        file_put_contents($dst, $originalBytes, LOCK_EX);
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
    exit(SyncComposerRepositories::main($argv));
} catch (Throwable $e) {
    $msg = str_replace(["\r\n", "\r"], "\n", $e->getMessage());
    fwrite(STDERR, SyncComposerRepositories::CODE_FAILED . ": {$msg}\n");
    exit(1);
}
