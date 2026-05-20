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

namespace Coretsia\Tools\Spikes\workspace\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\workspace\ComposerJsonCanonicalizer;
use Coretsia\Tools\Spikes\workspace\NewPackageWorkflow;
use PHPUnit\Framework\TestCase;

final class NewPackageAtomicWorkflowPrototypeTest extends TestCase
{
    public function testDryRunDoesNotTouchWorkspaceButComputesDeterministicOutputs(): void
    {
        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            $frameworkPath = self::joinPath($tmp, 'framework/composer.json');
            $skeletonPath = self::joinPath($tmp, 'skeleton/composer.json');

            $frameworkBefore = DeterministicFile::readBytesExact($frameworkPath);
            $skeletonBefore = DeterministicFile::readBytesExact($skeletonPath);

            $result = NewPackageWorkflow::run(
                $tmp,
                'core',
                'foo',
                'coretsia/core-foo',
                ['Coretsia\\Core\\Foo\\' => 'src/'],
                'library',
                null,
                false
            );

            self::assertArrayHasKey('packageIndex', $result);
            self::assertArrayHasKey('changedPaths', $result);

            /** @var list<string> $changedPaths */
            $changedPaths = $result['changedPaths'];

            // Sorted unique list is produced by workflow.
            $sorted = $changedPaths;
            \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));
            self::assertSame($sorted, $changedPaths);
            self::assertSame(\array_values(\array_unique($changedPaths)), $changedPaths);

            // Must include new package dir and release-line managed repository version updates.
            self::assertContains('framework/packages/core/foo/', $changedPaths);
            self::assertContains('framework/composer.json', $changedPaths);
            self::assertContains('skeleton/composer.json', $changedPaths);

            // Workspace MUST remain unchanged on disk for dry-run.
            self::assertSame($frameworkBefore, DeterministicFile::readBytesExact($frameworkPath));
            self::assertSame($skeletonBefore, DeterministicFile::readBytesExact($skeletonPath));

            self::assertDirectoryDoesNotExist(self::joinPath($tmp, 'framework/packages/core/foo'));
        } finally {
            self::removeTreeBestEffort($tmp);
        }
    }

    public function testApplyCreatesNewPackageAndUpdatesComposerFilesWithoutLeavingUowArtifacts(): void
    {
        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            $result = NewPackageWorkflow::run(
                $tmp,
                'core',
                'foo',
                'coretsia/core-foo',
                ['Coretsia\\Core\\Foo\\' => 'src/'],
                'library',
                null,
                true
            );

            // New package dir created.
            $pkgDir = self::joinPath($tmp, 'framework/packages/core/foo');
            self::assertDirectoryExists($pkgDir);
            self::assertDirectoryExists(self::joinPath($pkgDir, 'src'));

            $pkgComposerPath = self::joinPath($pkgDir, 'composer.json');
            self::assertFileExists($pkgComposerPath);

            // composer.json content matches the canonical bytes for the workflow's minimal shape.
            $expectedComposer = self::expectedMinimalPackageComposerJsonBytes(
                'coretsia/core-foo',
                ['Coretsia\\Core\\Foo\\' => 'src/'],
                'library',
                null
            );

            $actualComposer = DeterministicFile::readBytesExact($pkgComposerPath);
            self::assertSame($expectedComposer, $actualComposer);

            // Updated root composer.json files match golden fixtures.
            $frameworkComposer = self::decodeJsonMap(
                DeterministicFile::readBytesExact(self::joinPath($tmp, 'framework/composer.json')),
            );
            $skeletonComposer = self::decodeJsonMap(
                DeterministicFile::readBytesExact(self::joinPath($tmp, 'skeleton/composer.json')),
            );

            $frameworkVersions = self::packageWildcardVersions($frameworkComposer, 'packages/*/*');
            $skeletonVersions = self::packageWildcardVersions($skeletonComposer, '../framework/packages/*/*');

            $releaseLine = json_decode(
                DeterministicFile::readBytesExact(self::joinPath($tmp, 'framework/tools/release/release-line.json')),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            self::assertIsArray($releaseLine);
            self::assertArrayHasKey('devVersion', $releaseLine);
            self::assertIsString($releaseLine['devVersion']);

            self::assertSame($releaseLine['devVersion'], $frameworkVersions['coretsia/core-foo'] ?? null);
            self::assertSame($releaseLine['devVersion'], $skeletonVersions['coretsia/core-foo'] ?? null);

            $frameworkSorted = $frameworkVersions;
            \ksort($frameworkSorted, \SORT_STRING);
            self::assertSame($frameworkSorted, $frameworkVersions);

            $skeletonSorted = $skeletonVersions;
            \ksort($skeletonSorted, \SORT_STRING);
            self::assertSame($skeletonSorted, $skeletonVersions);

            // No backups created in the workspace by NewPackageWorkflow (sync backups live only in staging temp).
            self::assertFileDoesNotExist(self::joinPath($tmp, 'framework/composer.json.bak'));
            self::assertFileDoesNotExist(self::joinPath($tmp, 'skeleton/composer.json.bak'));

            // No UoW backup artifacts left in the workspace.
            self::assertFileDoesNotExist(self::joinPath($tmp, 'framework/composer.json.uow.old'));
            self::assertFileDoesNotExist(self::joinPath($tmp, 'skeleton/composer.json.uow.old'));

            // packageIndex contains the new entry and remains deterministic (sorted by path).
            self::assertArrayHasKey('packageIndex', $result);
            self::assertIsArray($result['packageIndex']);

            /** @var list<array<string,mixed>> $index */
            $index = $result['packageIndex'];

            // Must contain the new package.
            $found = false;
            foreach ($index as $entry) {
                if (($entry['path'] ?? null) === 'packages/core/foo') {
                    $found = true;
                    self::assertSame('foo', $entry['slug']);
                    self::assertSame('core', $entry['layer']);
                    self::assertSame('coretsia/core-foo', $entry['composerName']);
                    self::assertSame(['Coretsia\\Core\\Foo\\' => 'src/'], $entry['psr4']);
                    self::assertSame('library', $entry['kind']);
                    self::assertArrayNotHasKey('moduleClass', $entry);
                }
            }
            self::assertTrue($found);

            $paths = [];
            foreach ($index as $entry) {
                $paths[] = (string)$entry['path'];
            }
            $sorted = $paths;
            \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));
            self::assertSame($sorted, $paths);
        } finally {
            self::removeTreeBestEffort($tmp);
        }
    }

    public function testApplyRollsBackWhenTargetPackageAlreadyExists(): void
    {
        $tmp = self::makeTempDir();
        try {
            self::copyTreeDeterministic(self::fixtureRoot(), $tmp);

            // Create a conflicting target dir so apply fails after it already attempted file swaps.
            $conflict = self::joinPath($tmp, 'framework/packages/core/foo');
            if (!@\mkdir($conflict, 0777, true) && !\is_dir($conflict)) {
                self::fail('failed to create conflict dir');
            }

            $frameworkPath = self::joinPath($tmp, 'framework/composer.json');
            $skeletonPath = self::joinPath($tmp, 'skeleton/composer.json');

            $frameworkBefore = DeterministicFile::readBytesExact($frameworkPath);
            $skeletonBefore = DeterministicFile::readBytesExact($skeletonPath);

            try {
                NewPackageWorkflow::run(
                    $tmp,
                    'core',
                    'foo',
                    'coretsia/core-foo',
                    ['Coretsia\\Core\\Foo\\' => 'src/'],
                    'library',
                    null,
                    true
                );

                self::fail('expected exception');
            } catch (\RuntimeException $e) {
                self::assertSame(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID, $e->getMessage());
            }

            // Rollback guarantee: workspace bytes unchanged after failure.
            self::assertSame($frameworkBefore, DeterministicFile::readBytesExact($frameworkPath));
            self::assertSame($skeletonBefore, DeterministicFile::readBytesExact($skeletonPath));

            // No new artifacts (beyond the pre-created conflict).
            self::assertFileDoesNotExist(self::joinPath($tmp, 'framework/composer.json.uow.old'));
            self::assertFileDoesNotExist(self::joinPath($tmp, 'skeleton/composer.json.uow.old'));
        } finally {
            self::removeTreeBestEffort($tmp);
        }
    }

    private static function expectedMinimalPackageComposerJsonBytes(
        string $composerName,
        array $psr4,
        string $kind,
        ?string $moduleClass
    ): string {
        // Mirrors NewPackageWorkflow::buildMinimalPackageComposerJsonBytes().
        $composer = [];

        $composer['name'] = $composerName;
        $composer['type'] = 'library';

        $composer['require'] = [
            'php' => '^8.4',
        ];

        $composer['autoload'] = [
            'psr-4' => $psr4,
        ];

        $coretsia = [
            'kind' => $kind,
        ];
        if ($moduleClass !== null) {
            $coretsia['moduleClass'] = $moduleClass;
        }

        $composer['extra'] = [
            'coretsia' => $coretsia,
        ];

        return ComposerJsonCanonicalizer::encodeCanonical($composer);
    }

    private static function fixtureRoot(): string
    {
        return self::normalizePath(__DIR__ . '/../../fixtures/workspace_min');
    }

    private static function fixturePath(string $rel): string
    {
        return self::joinPath(self::fixtureRoot(), $rel);
    }

    private static function makeTempDir(): string
    {
        $base = \rtrim(self::normalizePath((string)\sys_get_temp_dir()), '/');
        $dir = $base . '/coretsia-spikes-workspace-' . \bin2hex(\random_bytes(8));

        if (!@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('failed to create temp dir');
        }

        return $dir;
    }

    private static function copyTreeDeterministic(string $srcDir, string $dstDir): void
    {
        if (!\is_dir($srcDir)) {
            self::fail('fixture root missing: ' . $srcDir);
        }

        if (!\is_dir($dstDir) && !@\mkdir($dstDir, 0777, true) && !\is_dir($dstDir)) {
            self::fail('failed to create dst dir: ' . $dstDir);
        }

        $entries = self::listDirectoryEntriesSorted($srcDir);
        foreach ($entries as $name) {
            $src = self::joinPath($srcDir, $name);
            $dst = self::joinPath($dstDir, $name);

            if (\is_dir($src)) {
                self::copyTreeDeterministic($src, $dst);
                continue;
            }

            if (\is_file($src)) {
                $bytes = DeterministicFile::readBytesExact($src);
                DeterministicFile::writeBytesExact($dst, $bytes);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function listDirectoryEntriesSorted(string $dir): array
    {
        $out = [];

        foreach (new \DirectoryIterator($dir) as $fi) {
            if ($fi->isDot()) {
                continue;
            }

            $name = $fi->getFilename();
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $out[] = $name;
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values($out);
    }

    private static function removeTreeBestEffort(string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === '' || $path === '.' || $path === '/' || $path === \DIRECTORY_SEPARATOR) {
            return;
        }

        if (\is_file($path)) {
            @\unlink($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        $entries = self::listDirectoryEntriesSorted($path);
        foreach ($entries as $name) {
            self::removeTreeBestEffort(self::joinPath($path, $name));
        }

        @\rmdir($path);
    }

    private static function joinPath(string $base, string $rel): string
    {
        $b = \rtrim(self::normalizePath($base), '/');
        $r = \ltrim(self::normalizePath($rel), '/');

        return $r === '' ? $b : ($b . '/' . $r);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    /**
     * @return array<string,mixed>
     * @throws \JsonException
     */
    private static function decodeJsonMap(string $bytes): array
    {
        $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertFalse(\array_is_list($decoded));

        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string,string>
     */
    private static function packageWildcardVersions(array $composer, string $url): array
    {
        $repositories = $composer['repositories'] ?? null;
        self::assertIsArray($repositories);

        foreach ($repositories as $repo) {
            self::assertIsArray($repo);

            if (($repo['url'] ?? null) !== $url) {
                continue;
            }

            $options = $repo['options'] ?? null;
            self::assertIsArray($options);

            $versions = $options['versions'] ?? null;
            self::assertIsArray($versions);

            /** @var array<string,string> $versions */
            return $versions;
        }

        self::fail('Package wildcard path repository not found: ' . $url);
    }
}
