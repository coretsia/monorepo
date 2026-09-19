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

/*
 * Deterministic split plan generator (CI evidence artifact; MUST NOT be committed).
 *
 * Usage:
 *   php .github/scripts/split-plan.php --out=ci/split-plan.json --tag=v1.2.3
 *   php .github/scripts/split-plan.php --out=ci/split-plan.json
 */

use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../../tools/support/ErrorCodes.php';
require_once __DIR__ . '/../../tools/support/DeterministicException.php';
require_once __DIR__ . '/../../tools/support/DeterministicFile.php';
require_once __DIR__ . '/../../tools/support/RepositoryContext.php';
require_once __DIR__ . '/../../tools/support/ComposerJson.php';
require_once __DIR__ . '/../../tools/support/WorkspacePackageCatalog.php';

final class SplitPlan
{
    private const string SCHEMA_VERSION = 'coretsia.splitPlan.v1';

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

            $repository = RepositoryContext::discoverFrom(__DIR__);
            $repoRoot = $repository->repoRoot();
            $sourceCommit = self::gitHead($repoRoot);

            self::assertNoSymlinkDirectories(
                $repository->packagesRoot(),
            );

            $catalog = WorkspacePackageCatalog::discover($repository);

            /** @var list<array{package_id:string,pathPrefix:string,splitRepo:string,composerName:string}> $packages */
            $packages = self::buildPackages($catalog);

            usort(
                $packages,
                static fn (array $a, array $b): int => strcmp(
                    $a['package_id'],
                    $b['package_id'],
                ),
            );

            // Keys MUST be in exact order (schema):
            $plan = [
                'schemaVersion' => self::SCHEMA_VERSION,
                'sourceCommit' => $sourceCommit,
                'tag' => $tagOut,
                'packages' => $packages,
            ];

            $jsonBytes = self::jsonBytes($plan);

            DeterministicFile::writeTextLf(
                self::resolveOutputPath($repository, $out),
                $jsonBytes,
            );

            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
    }

    private static function resolveOutputPath(
        RepositoryContext $repository,
        string $out,
    ): string {
        $outputPath = $repository->resolve($out);
        $relativePath = $repository->relativeToRepo($outputPath);

        if ($relativePath === '.') {
            throw new RuntimeException('split-plan-output-path-invalid');
        }

        $current = $repository->repoRoot();

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('split-plan-output-path-invalid');
            }

            $current .= '/' . $segment;

            if (is_link($current)) {
                throw new RuntimeException('split-plan-output-symlink-not-allowed');
            }

            if (!file_exists($current)) {
                break;
            }
        }

        return $outputPath;
    }

    private static function assertNoSymlinkDirectories(
        string $packagesRoot,
    ): void {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $packagesRoot,
                    FilesystemIterator::SKIP_DOTS,
                ),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo) {
                    continue;
                }

                if ($item->isLink() && $item->isDir()) {
                    throw new RuntimeException('split-plan-package-symlink-directory-forbidden');
                }
            }
        } catch (UnexpectedValueException) {
            throw new RuntimeException('split-plan-package-tree-unreadable');
        }
    }

    /**
     * @return list<array{
     *     package_id:string,
     *     pathPrefix:string,
     *     splitRepo:string,
     *     composerName:string
     * }>
     */
    private static function buildPackages(
        WorkspacePackageCatalog $catalog,
    ): array {
        $packages = [];
        $seenPackageIds = [];

        foreach ($catalog->all() as $product) {
            $packageId = self::splitPackageId($product);

            if (isset($seenPackageIds[$packageId])) {
                throw new RuntimeException('split-plan-package-id-collision');
            }

            $seenPackageIds[$packageId] = true;

            // Keys MUST be in exact order (schema):
            $packages[] = [
                'package_id' => $packageId,
                'pathPrefix' => $product['relativePath'] . '/',
                'splitRepo' => $product['composerName'],
                'composerName' => $product['composerName'],
            ];
        }

        return $packages;
    }

    /**
     * @param array{
     *     kind:string,
     *     relativePath:string,
     *     absolutePath:string,
     *     composerJsonPath:string,
     *     composerName:string,
     *     layer:string|null,
     *     slug:string|null,
     *     packageId:string|null
     * } $product
     */
    private static function splitPackageId(array $product): string
    {
        if ($product['packageId'] !== null) {
            return $product['packageId'];
        }

        if (
            $product['kind']
            !== WorkspacePackageCatalog::KIND_SPECIAL_DISTRIBUTION
        ) {
            throw new RuntimeException('split-plan-product-kind-invalid');
        }

        return $product['composerName'];
    }

    /**
     * @return array{out?:string,tag?:string}
     */
    private static function parseArgs(array $argv): array
    {
        /** @var array{out?:string,tag?:string} $out */
        $out = [];

        foreach ($argv as $i => $arg) {
            if ($i === 0) {
                continue;
            }

            if (!is_string($arg) || $arg === '') {
                throw new RuntimeException('split-plan-argument-invalid');
            }

            if ($arg === '--help' || $arg === '-h') {
                throw new RuntimeException(
                    "Usage: php .github/scripts/split-plan.php --out=PATH [--tag=vMAJOR.MINOR.PATCH]\n",
                );
            }

            if (!str_starts_with($arg, '--')) {
                throw new RuntimeException('split-plan-argument-invalid');
            }

            $eq = strpos($arg, '=');

            if ($eq === false) {
                throw new RuntimeException('split-plan-argument-invalid');
            }

            $key = substr($arg, 2, $eq - 2);
            $value = substr($arg, $eq + 1);

            if (
                !in_array($key, ['out', 'tag'], true)
                || $value === ''
                || array_key_exists($key, $out)
            ) {
                throw new RuntimeException('split-plan-argument-invalid');
            }

            $out[$key] = $value;
        }

        return $out;
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
            throw new RuntimeException('git rev-parse HEAD failed: ' . trim((string) $stderr));
        }

        $sha = trim((string) $stdout);
        if ($sha === '' || !preg_match('/\A[0-9a-f]{40}\z/i', $sha)) {
            throw new RuntimeException('Invalid git HEAD hash: ' . $sha);
        }

        return strtolower($sha);
    }
}

exit(SplitPlan::main($argv));
