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

final class NewPackage
{
    public static function main(array $argv): int
    {
        $opts = self::parseArgs($argv);
        $repository = self::resolveRepository($opts);
        $catalog = WorkspacePackageCatalog::discover($repository);

        $layer = self::need($opts, 'layer');
        $slug = self::need($opts, 'slug');
        $kind = self::need($opts, 'kind');

        if (!WorkspacePackageCatalog::isLayeredRoot($layer)) {
            throw new \RuntimeException('new-package-layer-invalid');
        }

        if (\preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug) !== 1) {
            throw new \RuntimeException('slug must be kebab-case (a-z0-9 and "-")');
        }

        if (!\in_array($kind, ['library', 'runtime'], true)) {
            throw new \RuntimeException('kind must be "library" or "runtime"');
        }

        if (\in_array($slug, ['app', 'modules', 'shared'], true)) {
            throw new \RuntimeException('Forbidden slug: ' . $slug);
        }

        if (
            $layer === 'core'
            && \in_array(
                $slug,
                WorkspacePackageCatalog::layeredRoots(),
                true,
            )
        ) {
            throw new \RuntimeException('Reserved core namespace collision slug: ' . $slug);
        }

        if ($slug === 'kernel' && $layer !== 'core') {
            throw new \RuntimeException('Reserved slug: ' . $slug);
        }

        if ($slug === 'observability') {
            throw new \RuntimeException('Reserved slug: ' . $slug);
        }

        $repoRoot = $repository->repoRoot();
        $packagesRoot = $repository->packagesRoot();
        $layerDir = $repository->resolve('packages/' . $layer);
        $packageDir = $repository->resolve('packages/' . $layer . '/' . $slug);

        if (!\is_dir($packagesRoot) || \is_link($packagesRoot)) {
            throw new \RuntimeException('packages-root-invalid');
        }

        if (
            (\file_exists($layerDir) || \is_link($layerDir))
            && (!\is_dir($layerDir) || \is_link($layerDir))
        ) {
            throw new \RuntimeException('package-layer-invalid');
        }

        if (\file_exists($packageDir) || \is_link($packageDir)) {
            throw new \RuntimeException('package-directory-already-exists');
        }

        $initialLayerState = self::pathState($layerDir);

        $layerAlreadyExists = $initialLayerState['directory'] && !$initialLayerState['link'];

        $varRoot = $repository->resolve('var');
        $tmpRoot = $repository->resolve('var/tmp');

        foreach ([$varRoot, $tmpRoot] as $path) {
            if (
                \is_link($path)
                || (\file_exists($path) && !\is_dir($path))
            ) {
                throw new \RuntimeException('package-staging-parent-invalid');
            }
        }

        self::mkdir($tmpRoot);
        $tmpRoot = $repository->resolveExistingDirectory('var/tmp');

        $stagingRoot = $tmpRoot . '/new-package-' . \bin2hex(\random_bytes(8));

        if (\file_exists($stagingRoot) || \is_link($stagingRoot)) {
            throw new \RuntimeException('package-staging-collision');
        }

        $stagedPackageDir = $stagingRoot . '/packages/' . $layer . '/' . $slug;
        $composerName = 'coretsia/' . $layer . '-' . $slug;

        if ($catalog->byComposerName($composerName) !== null) {
            throw new \RuntimeException('package-composer-name-already-exists');
        }

        $namespaceRoot = self::packageRootNamespace($layer, $slug);

        try {
            self::mkdir($stagingRoot);
            self::mkdir($stagedPackageDir);
            self::mkdir($stagedPackageDir . '/src');

            DeterministicFile::writeTextLf(
                $stagedPackageDir . '/composer.json',
                self::composerJson($composerName, $namespaceRoot, $layer, $slug, $kind),
            );

            self::runPackageScaffoldSync($repoRoot, $stagedPackageDir);
            self::runPackageScaffoldSync(
                $repoRoot,
                $stagedPackageDir,
                check: true,
            );

            if ($layerAlreadyExists) {
                $currentLayerState = self::pathState($layerDir);
                $currentPackageState = self::pathState($packageDir);

                if (
                    !$currentLayerState['directory']
                    || $currentLayerState['link']
                    || $currentPackageState['exists']
                    || $currentPackageState['link']
                ) {
                    throw new \RuntimeException('package-commit-target-changed');
                }

                $commitSource = $stagedPackageDir;
                $commitDestination = $packageDir;
            } else {
                $currentLayerState = self::pathState($layerDir);

                if (
                    $currentLayerState['exists']
                    || $currentLayerState['link']
                ) {
                    throw new \RuntimeException('package-commit-target-changed');
                }

                $commitSource = $stagingRoot . '/packages/' . $layer;
                $commitDestination = $layerDir;
            }

            if (!@\rename($commitSource, $commitDestination)) {
                throw new \RuntimeException('package-atomic-commit-failed');
            }

            ConsoleOutput::line('OK', false);
            ConsoleOutput::line(
                $repository->relativeToRepo($packageDir),
                false,
            );

            return 0;
        } finally {
            self::removeTree($stagingRoot);
        }
    }

    /**
     * Parse:
     * - --k=v
     * - --k v
     *
     * @return array<string,string>
     */
    private static function parseArgs(array $argv): array
    {
        /** @var array<string,true> $allowed */
        $allowed = [
            'repo-root' => true,
            'layer' => true,
            'slug' => true,
            'kind' => true,
        ];

        /** @var array<string,string> $out */
        $out = [];
        $count = \count($argv);

        for ($i = 1; $i < $count; $i++) {
            $arg = \trim((string) $argv[$i]);

            if ($arg === '' || !\str_starts_with($arg, '--')) {
                throw new \RuntimeException('new-package-argument-invalid');
            }

            $separator = \strpos($arg, '=');

            if ($separator !== false) {
                $key = \substr($arg, 2, $separator - 2);
                $value = \trim(\substr($arg, $separator + 1));
            } else {
                $key = \substr($arg, 2);
                $value = $i + 1 < $count
                    ? \trim((string) $argv[$i + 1])
                    : '';

                if (
                    $value !== ''
                    && !\str_starts_with($value, '--')
                ) {
                    $i++;
                }
            }

            if (
                !isset($allowed[$key])
                || $value === ''
                || \str_starts_with($value, '--')
                || \array_key_exists($key, $out)
            ) {
                throw new \RuntimeException('new-package-argument-invalid');
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param array<string,string> $opts
     */
    private static function need(array $opts, string $key): string
    {
        $v = $opts[$key] ?? null;

        if (!\is_string($v) || \trim($v) === '') {
            throw new \RuntimeException('Missing required arg: --' . $key);
        }

        return $v;
    }

    private static function packageRootNamespace(string $layer, string $slug): string
    {
        $studlySlug = self::studly($slug);

        if ($layer === 'core') {
            return 'Coretsia\\' . $studlySlug . '\\';
        }

        return 'Coretsia\\' . self::studly($layer) . '\\' . $studlySlug . '\\';
    }

    private static function composerJson(
        string $name,
        string $namespaceRoot,
        string $layer,
        string $slug,
        string $kind,
    ): string {
        $studlySlug = self::studly($slug);

        $coretsiaExtra = [
            'kind' => $kind,
        ];

        if ($kind === 'runtime') {
            $coretsiaExtra['moduleId'] = $layer . '.' . $slug;
            $coretsiaExtra['moduleClass'] = $namespaceRoot . 'Module\\' . $studlySlug . 'Module';
            $coretsiaExtra['providers'] = [
                $namespaceRoot . 'Provider\\' . $studlySlug . 'ServiceProvider',
            ];
            $coretsiaExtra['requires'] = [];
            $coretsiaExtra['conflicts'] = [];
            $coretsiaExtra['defaultsConfigPath'] = 'config/' . $slug . '.php';
        }

        $json = [
            'name' => $name,
            'type' => 'library',
            'description' => 'Coretsia package: ' . $name,
            'license' => 'Apache-2.0',
            'require' => [
                'php' => '^8.4',
            ],
            'autoload' => [
                'psr-4' => [
                    $namespaceRoot => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $namespaceRoot . 'Tests\\' => 'tests/',
                ],
            ],
            'config' => [
                'sort-packages' => true,
            ],
            'extra' => [
                'coretsia' => $coretsiaExtra,
            ],
        ];

        return ComposerJson::encodeCanonical($json);
    }

    /**
     * @return array{
     *     exists: bool,
     *     directory: bool,
     *     link: bool
     * }
     *
     * @phpstan-impure
     */
    private static function pathState(string $path): array
    {
        return [
            'exists' => \file_exists($path),
            'directory' => \is_dir($path),
            'link' => \is_link($path),
        ];
    }

    private static function runPackageScaffoldSync(
        string $repoRoot,
        string $packageDir,
        bool $check = false,
    ): void {
        $syncTool = $repoRoot . '/tools/build/sync_package_scaffold.php';

        if (!\is_file($syncTool) || !\is_readable($syncTool)) {
            throw new \RuntimeException('sync_package_scaffold.php missing');
        }

        $args = [
            PHP_BINARY,
            $syncTool,
            '--repo-root',
            $repoRoot,
            '--path',
            $packageDir,
        ];

        if ($check) {
            $args[] = '--check';
        }

        /** @var array<int, resource> $pipes */
        $pipes = [];

        $process = \proc_open(
            $args,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $repoRoot,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('package-scaffold-sync-start-failed');
        }

        if (isset($pipes[0]) && \is_resource($pipes[0])) {
            \fclose($pipes[0]);
        }

        $stdout = '';
        if (isset($pipes[1]) && \is_resource($pipes[1])) {
            $stdout = (string) \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
        }

        $stderr = '';
        if (isset($pipes[2]) && \is_resource($pipes[2])) {
            $stderr = (string) \stream_get_contents($pipes[2]);
            \fclose($pipes[2]);
        }

        $exitCode = \proc_close($process);

        if ($exitCode !== 0) {
            $summary = self::firstNonEmptyLine($stdout) ?? self::firstNonEmptyLine($stderr);

            if ($summary !== null) {
                throw new \RuntimeException('package-scaffold-sync-failed: ' . $summary);
            }

            throw new \RuntimeException('package-scaffold-sync-failed: exit-code=' . $exitCode);
        }
    }

    private static function removeTree(string $path): void
    {
        $path = \rtrim(\str_replace('\\', '/', $path), '/');

        if ($path === '') {
            throw new \RuntimeException('package-staging-cleanup-failed');
        }

        if (!\file_exists($path) && !\is_link($path)) {
            return;
        }

        if (\is_link($path) || \is_file($path)) {
            if (!@\unlink($path)) {
                throw new \RuntimeException('package-staging-cleanup-failed');
            }

            return;
        }

        if (!\is_dir($path)) {
            throw new \RuntimeException('package-staging-cleanup-failed');
        }

        $entries = @\scandir($path);

        if ($entries === false) {
            throw new \RuntimeException('package-staging-cleanup-failed');
        }

        $children = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $children[] = $entry;
        }

        \sort($children, \SORT_STRING);

        foreach ($children as $entry) {
            self::removeTree($path . '/' . $entry);
        }

        if (!@\rmdir($path)) {
            throw new \RuntimeException('package-staging-cleanup-failed');
        }
    }

    private static function firstNonEmptyLine(string $value): ?string
    {
        $value = self::normalizeEol($value);
        $lines = \explode("\n", $value);

        foreach ($lines as $line) {
            $line = \trim($line);

            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }

    private static function normalizeEol(string $s): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function mkdir(string $path): void
    {
        if (\is_dir($path)) {
            return;
        }

        if (!@\mkdir($path, 0777, true) && !\is_dir($path)) {
            throw new \RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private static function studly(string $kebab): string
    {
        $parts = \preg_split('/-+/', $kebab) ?: [$kebab];
        $out = '';

        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }

            $out .= \strtoupper($p[0]) . \strtolower(\substr($p, 1));
        }

        return $out;
    }

    /**
     * @param array<string,string> $opts
     */
    private static function resolveRepository(array $opts): RepositoryContext
    {
        $repoRoot = $opts['repo-root'] ?? null;

        return $repoRoot === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($repoRoot);
    }
}

try {
    exit(NewPackage::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_NEW_PACKAGE_FAILED,
    );

    exit(1);
}
