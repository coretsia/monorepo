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
 *
 * Deterministic split plan generator (CI evidence artifact; MUST NOT be committed).
 *
 * Usage:
 *   php .github/scripts/split-plan.php --out=ci/split-plan.json --tag=v1.2.3
 *   php .github/scripts/split-plan.php --out=ci/split-plan.json
 */

final class SplitPlan
{
    private const string SCHEMA_VERSION = 'coretsia.splitPlan.v1';
    private const string VENDOR = 'coretsia';

    /** @var list<string> */
    private const array ALLOWED_LAYERS = [
        'core',
        'platform',
        'integrations',
        'enterprise',
        'devtools',
        'presets',
    ];

    public static function main(array $argv): int
    {
        try {
            $args = self::parseArgs($argv);

            $out = $args['out'] ?? null;
            $tag = $args['tag'] ?? null;

            if (!is_string($out) || $out === '') {
                throw new RuntimeException('Missing required argument: --out=PATH');
            }

            $tagOut = null;
            if (is_string($tag) && $tag !== '') {
                if (!preg_match('/\Av[0-9]+\.[0-9]+\.[0-9]+\z/', $tag)) {
                    throw new RuntimeException('Invalid --tag format. Expected: vMAJOR.MINOR.PATCH (example: v1.2.3)');
                }
                $tagOut = $tag;
            }

            $repoRoot = dirname(__DIR__, 2); // .github/scripts -> repo root
            $packagesRoot = $repoRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'packages';

            if (!is_dir($packagesRoot)) {
                throw new RuntimeException('Missing directory: framework/packages/');
            }

            $sourceCommit = self::gitHead($repoRoot);

            $layers = self::listDirs($packagesRoot);

            foreach ($layers as $layer) {
                if (!in_array($layer, self::ALLOWED_LAYERS, true)) {
                    throw new RuntimeException(sprintf(
                        'Unknown layer directory under framework/packages/: "%s" (allowed: %s)',
                        $layer,
                        implode(', ', self::ALLOWED_LAYERS)
                    ));
                }
            }

            /** @var list<array{package_id:string,pathPrefix:string,splitRepo:string,composerName:string}> $packages */
            $packages = [];

            foreach ($layers as $layer) {
                $layerPath = $packagesRoot . DIRECTORY_SEPARATOR . $layer;

                foreach (self::listDirs($layerPath) as $slug) {
                    self::assertValidSlug($slug);

                    $pkgPathRel = 'framework/packages/' . $layer . '/' . $slug;
                    $pkgPathAbs = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pkgPathRel);

                    $composerJsonAbs = $pkgPathAbs . DIRECTORY_SEPARATOR . 'composer.json';
                    if (!is_file($composerJsonAbs)) {
                        throw new RuntimeException(sprintf('Missing composer.json: %s', $composerJsonAbs));
                    }

                    $expectedComposerName = self::VENDOR . '/' . $layer . '-' . $slug;
                    self::assertValidComposerName($expectedComposerName);

                    $composer = self::readJsonFile($composerJsonAbs);
                    $actualName = $composer['name'] ?? null;

                    if (!is_string($actualName) || $actualName === '') {
                        throw new RuntimeException(sprintf('composer.json missing "name": %s', $composerJsonAbs));
                    }
                    self::assertValidComposerName($actualName);

                    if ($actualName !== $expectedComposerName) {
                        throw new RuntimeException(sprintf(
                            'composer name mismatch for %s: expected "%s", got "%s"',
                            $pkgPathRel,
                            $expectedComposerName,
                            $actualName
                        ));
                    }

                    $packageId = $layer . '/' . $slug;
                    $pathPrefix = $pkgPathRel . '/';
                    $splitRepo = self::VENDOR . '/' . $layer . '-' . $slug;

                    // Keys MUST be in exact order (schema):
                    $packages[] = [
                        'package_id' => $packageId,
                        'pathPrefix' => $pathPrefix,
                        'splitRepo' => $splitRepo,
                        'composerName' => $actualName,
                    ];
                }
            }

            usort($packages, static fn(array $a, array $b): int => strcmp($a['package_id'], $b['package_id']));

            // Keys MUST be in exact order (schema):
            $plan = [
                'schemaVersion' => self::SCHEMA_VERSION,
                'sourceCommit' => $sourceCommit,
                'tag' => $tagOut,
                'packages' => $packages,
            ];

            $jsonBytes = self::jsonBytes($plan) . "\n";
            self::writeFileAtomic($repoRoot, $out, $jsonBytes);

            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * @return array<string, string|bool>
     */
    private static function parseArgs(array $argv): array
    {
        $out = [];
        foreach ($argv as $i => $arg) {
            if ($i === 0) {
                continue;
            }
            if (!is_string($arg) || $arg === '') {
                continue;
            }
            if ($arg === '--help' || $arg === '-h') {
                throw new RuntimeException(
                    "Usage: php .github/scripts/split-plan.php --out=PATH [--tag=vMAJOR.MINOR.PATCH]\n"
                );
            }
            if (!str_starts_with($arg, '--')) {
                throw new RuntimeException(sprintf('Unknown argument: %s', $arg));
            }
            $eq = strpos($arg, '=');
            if ($eq === false) {
                $key = substr($arg, 2);
                $out[$key] = true;
                continue;
            }
            $key = substr($arg, 2, $eq - 2);
            $val = substr($arg, $eq + 1);
            $out[$key] = $val;
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private static function listDirs(string $path): array
    {
        $items = @scandir($path);
        if ($items === false) {
            throw new RuntimeException(sprintf('Cannot read directory: %s', $path));
        }

        $dirs = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($full)) {
                continue;
            }
            if (is_link($full)) {
                throw new RuntimeException(sprintf('Symlink directories are forbidden: %s', $full));
            }
            $dirs[] = $name;
        }

        sort($dirs, SORT_STRING);
        return $dirs;
    }

    private static function assertValidSlug(string $slug): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug)) {
            throw new RuntimeException(sprintf('Invalid slug: "%s" (expected kebab-case)', $slug));
        }
    }

    private static function assertValidComposerName(string $name): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*\z/', $name)) {
            throw new RuntimeException(sprintf('Invalid composer package name: "%s"', $name));
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function readJsonFile(string $file): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Cannot read file: %s', $file));
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Invalid JSON object in: %s', $file));
        }

        /** @var array<string,mixed> $data */
        return $data;
    }

    private static function jsonBytes(mixed $value): string
    {
        self::assertJsonEncodable($value);

        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
        );

        if (!is_string($json)) {
            throw new RuntimeException('json_encode failed unexpectedly');
        }

        return $json;
    }

    private static function assertJsonEncodable(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (!is_int($k) && !is_string($k)) {
                    throw new RuntimeException('Invalid array key type (must be int|string)');
                }
                self::assertJsonEncodable($v);
            }
            return;
        }

        if (is_object($value)) {
            throw new RuntimeException('Objects are forbidden in JSON input (must be arrays/scalars)');
        }
    }

    private static function gitHead(string $repoRoot): string
    {
        $cmd = ['git', '-C', $repoRoot, 'rev-parse', 'HEAD'];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to execute git to resolve sourceCommit');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($proc);

        if ($code !== 0) {
            throw new RuntimeException('git rev-parse HEAD failed: ' . trim((string)$stderr));
        }

        $sha = trim((string)$stdout);
        if ($sha === '' || !preg_match('/\A[0-9a-f]{40}\z/i', $sha)) {
            throw new RuntimeException('Invalid git HEAD hash: ' . $sha);
        }

        return strtolower($sha);
    }

    private static function writeFileAtomic(string $repoRoot, string $out, string $bytes): void
    {
        $outAbs = $out;
        if (!str_starts_with($outAbs, DIRECTORY_SEPARATOR) && !preg_match('/\A[A-Za-z]:[\\\\\\/]/', $outAbs)) {
            $outAbs = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outAbs);
        }

        $dir = dirname($outAbs);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }

        $tmp = $outAbs . '.tmp';
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new RuntimeException(sprintf('Cannot open for write: %s', $tmp));
        }

        try {
            $len = strlen($bytes);
            $w = fwrite($fh, $bytes);
            if ($w === false || $w !== $len) {
                throw new RuntimeException(sprintf('Short write: %s', $tmp));
            }
        } finally {
            fclose($fh);
        }

        if (is_file($outAbs) && !@unlink($outAbs)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Cannot remove existing file: %s', $outAbs));
        }

        if (!@rename($tmp, $outAbs)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Cannot move into place: %s', $outAbs));
        }
    }
}

exit(SplitPlan::main($argv));
