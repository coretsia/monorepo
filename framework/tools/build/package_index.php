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

final class PackageIndexTool
{
    public static function main(array $argv): int
    {
        $repoRoot = self::resolveRepoRoot($argv);

        $check = self::argFlag($argv, '--check');
        $apply = self::argFlag($argv, '--apply') || !$check; // default apply unless --check

        $outPath = self::argValue($argv, '--out') ?? ($repoRoot . '/framework/var/package-index.php');
        $outPath = self::absFromRepo($repoRoot, $outPath);

        $packages = self::buildIndex($repoRoot);

        $payload = [
            'schemaVersion' => 1,
            'generatedBy' => 'framework/tools/build/package_index.php',
            'packages' => $packages,
        ];

        $php = self::renderPhpReturnFile($payload);

        $changed = self::isDifferentFile($outPath, $php);

        if ($check) {
            if ($changed) {
                fwrite(STDERR, "CORETSIA_PACKAGE_INDEX_OUT_OF_DATE\n");
                fwrite(STDERR, "framework/var/package-index.php\n");
                return 1;
            }

            fwrite(STDOUT, "OK\n");
            return 0;
        }

        if ($apply) {
            self::writeFile($outPath, $php);
        }

        fwrite(STDOUT, "OK\n");
        if ($changed) {
            fwrite(STDOUT, self::rel($repoRoot, $outPath) . "\n");
        }

        return 0;
    }

    /**
     * Scan pattern (single-choice): framework/packages/*\/*\/composer.json
     * Output order (single-choice): sort by package "path" using strcmp (locale-independent).
     *
     * @return list<array<string,mixed>>
     */
    private static function buildIndex(string $repoRoot): array
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

        $pattern = $rootAbs . '/*/*/composer.json';
        $hits = glob($pattern, GLOB_NOSORT);
        if ($hits === false) {
            $hits = [];
        }

        $items = [];

        foreach ($hits as $abs) {
            if (!is_string($abs) || $abs === '' || !is_file($abs)) {
                continue;
            }

            $abs = str_replace('\\', '/', $abs);
            $rel = self::relFrom($rootAbs, $abs);

            // Expected: <layer>/<slug>/composer.json
            $parts = explode('/', $rel);
            if (count($parts) !== 3) {
                continue;
            }

            [$layer, $slug, $file] = $parts;
            if ($file !== 'composer.json' || $layer === '' || $slug === '') {
                continue;
            }

            $data = self::readJson($abs);

            $name = (string)($data['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $psr4 = self::extractPsr4($data);
            $type = (string)($data['type'] ?? 'library');

            $extra = $data['extra'] ?? [];
            $coretsia = (is_array($extra) && isset($extra['coretsia']) && is_array($extra['coretsia']))
                ? $extra['coretsia']
                : [];

            $kind = (string)($coretsia['kind'] ?? $type);
            $moduleId = $coretsia['moduleId'] ?? null;
            $moduleClass = $coretsia['moduleClass'] ?? null;
            $providers = $coretsia['providers'] ?? [];
            $defaultsConfigPath = $coretsia['defaultsConfigPath'] ?? null;

            if (!is_string($moduleId) || trim($moduleId) === '') {
                $moduleId = null;
            }
            if (!is_string($moduleClass) || trim($moduleClass) === '') {
                $moduleClass = null;
            }
            if (!is_string($defaultsConfigPath) || trim($defaultsConfigPath) === '') {
                $defaultsConfigPath = null;
            }
            if (!is_array($providers)) {
                $providers = [];
            }

            $providers = array_values(array_map('strval', $providers));
            sort($providers, SORT_STRING);

            $path = 'framework/packages/' . $layer . '/' . $slug;

            $items[] = [
                'id' => $layer . '.' . $slug,
                'layer' => $layer,
                'slug' => $slug,
                'path' => $path,
                'composerName' => $name,
                'psr4' => $psr4,
                'kind' => $kind,
                'moduleId' => $moduleId,
                'moduleClass' => $moduleClass,
                'providers' => $providers,
                'defaultsConfigPath' => $defaultsConfigPath,
            ];
        }

        usort(
            $items,
            static fn(array $a, array $b): int => strcmp((string)$a['path'], (string)$b['path'])
        );

        return $items;
    }

    private static function renderPhpReturnFile(array $payload): string
    {
        $payload = self::normalizePayload($payload);

        $export = var_export($payload, true);
        $export = self::normalizeEol($export);

        $out = "<?php\n\n";
        $out .= "declare(strict_types=1);\n\n";
        $out .= "/*\n";
        $out .= " * GENERATED FILE (tooling-only).\n";
        $out .= " * MUST NOT be used by runtime.\n";
        $out .= " * Regenerate: composer arch:package-index:generate\n";
        $out .= " */\n\n";
        $out .= "return " . $export . ";\n";

        return $out;
    }

    private static function normalizePayload(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $out = [];
            foreach ($value as $v) {
                $out[] = self::normalizePayload($v);
            }
            return $out;
        }

        $keys = array_keys($value);
        usort($keys, static fn($a, $b) => strcmp((string)$a, (string)$b));

        $out = [];
        foreach ($keys as $k) {
            $out[(string)$k] = self::normalizePayload($value[$k]);
        }
        return $out;
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
     * @return array<string,mixed>
     */
    private static function readJson(string $path): array
    {
        $raw = self::normalizeEol((string)file_get_contents($path));
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

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
        usort($keys, static fn($a, $b) => strcmp((string)$a, (string)$b));
        return (string)$keys[0];
    }

    private static function normalizeEol(string $s): string
    {
        return str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    private static function argValue(array $argv, string $name): ?string
    {
        $n = count($argv);
        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];

            if (str_starts_with($a, $name . '=')) {
                $v = trim(substr($a, strlen($name . '=')));
                return $v !== '' ? $v : null;
            }

            if ($a === $name) {
                $next = ($i + 1 < $n) ? trim((string)$argv[$i + 1]) : '';
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

        $p = rtrim($repoRoot, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
        return rtrim($p, '/');
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

    private static function resolveRepoRoot(array $argv): string
    {
        $arg = self::argValue($argv, '--repo-root');

        if ($arg === null) {
            return self::repoRootUnsafe();
        }

        $candidate = $arg;

        if (!self::isAbsolutePath($candidate)) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Cannot resolve cwd');
            }
            $candidate = rtrim(str_replace('\\', '/', $cwd), '/') . '/' . ltrim(
                    str_replace('\\', '/', $candidate),
                    '/'
                );
        }

        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');

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
}

try {
    exit(PackageIndexTool::main($argv));
} catch (Throwable $e) {
    $msg = str_replace(["\r\n", "\r"], "\n", $e->getMessage());
    fwrite(STDERR, "CORETSIA_PACKAGE_INDEX_FAILED: $msg\n");
    exit(1);
}
