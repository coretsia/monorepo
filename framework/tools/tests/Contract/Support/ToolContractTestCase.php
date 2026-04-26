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

namespace Coretsia\Tools\Tests\Contract\Support;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

abstract class ToolContractTestCase extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tmpDirs) as $tmpDir) {
            $this->removeDir($tmpDir);
        }

        $this->tmpDirs = [];

        parent::tearDown();
    }

    protected function frameworkRoot(): string
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__, 4)), '/');
    }

    protected function repoRoot(): string
    {
        return rtrim(str_replace('\\', '/', dirname($this->frameworkRoot())), '/');
    }

    protected function spikeFixturePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return $this->frameworkRoot() . '/tools/spikes/fixtures/' . $relativePath;
    }

    /**
     * @return array<mixed>
     */
    protected function requireArrayFixture(string $relativePath): array
    {
        $path = $this->spikeFixturePath($relativePath);

        if (!is_file($path)) {
            throw new RuntimeException('Missing spike fixture: ' . $relativePath);
        }

        $value = require $path;

        if (!is_array($value)) {
            throw new RuntimeException('Spike fixture must return array: ' . $relativePath);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    protected function requireStringListFixture(string $relativePath): array
    {
        $value = $this->requireArrayFixture($relativePath);

        $out = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new RuntimeException('Spike fixture must return list<string>: ' . $relativePath);
            }

            $out[] = $item;
        }

        return $out;
    }

    protected function tempDir(string $prefix): string
    {
        $base = sys_get_temp_dir();
        if (!is_string($base) || trim($base) === '') {
            throw new RuntimeException('Cannot resolve sys_get_temp_dir().');
        }

        $base = rtrim(str_replace('\\', '/', $base), '/');
        $dir = $base . '/' . trim($prefix, '-') . '-' . bin2hex(random_bytes(8));

        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create temp dir: ' . $dir);
        }

        $this->tmpDirs[] = $dir;

        return $dir;
    }

    /**
     * @param list<string> $args
     * @return array{0:int,1:string}
     */
    protected function runPhp(string $script, array $args = [], ?string $cwd = null): array
    {
        if (!is_file($script)) {
            throw new RuntimeException('Missing PHP script: ' . $script);
        }

        $cmd = array_merge([PHP_BINARY, $script], $args);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, $cwd ?? $this->repoRoot());

        if (!is_resource($proc)) {
            throw new RuntimeException('Cannot start PHP process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($proc);

        $out = '';
        if (is_string($stdout) && $stdout !== '') {
            $out .= $stdout;
        }
        if (is_string($stderr) && $stderr !== '') {
            $out .= $stderr;
        }

        return [$code, $out];
    }

    /**
     * @param list<string> $args
     * @return array{0:int,1:string}
     */
    protected function runDeptracGenerate(string $repoRoot, array $args): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/build/deptrac_generate.php',
            array_merge(['--repo-root', $repoRoot], $args),
            $repoRoot,
        );
    }

    /**
     * @param list<string> $args
     * @return array{0:int,1:string}
     */
    protected function runWorkspaceSync(string $repoRoot, array $args): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/build/sync_composer_repositories.php',
            array_merge(['--repo-root', $repoRoot], $args),
            $repoRoot,
        );
    }

    protected function createWorkspaceSandbox(string $fixtureName): string
    {
        $fixtureRoot = $this->spikeFixturePath($fixtureName);
        if (!is_dir($fixtureRoot)) {
            throw new RuntimeException('Missing workspace fixture: ' . $fixtureName);
        }

        $sandbox = $this->tempDir('coretsia-workspace-fixture');
        $this->copyDir($fixtureRoot, $sandbox);

        self::assertFileExists($sandbox . '/composer.json');
        self::assertFileExists($sandbox . '/framework/composer.json');
        self::assertFileExists($sandbox . '/skeleton/composer.json');

        return $sandbox;
    }

    protected function createDeptracSandboxFromPackageIndexFixture(string $fixtureRelativePath): string
    {
        $fixture = $this->requireArrayFixture($fixtureRelativePath);
        $packages = $fixture['packages'] ?? null;

        if (!is_array($packages) || $packages === []) {
            throw new RuntimeException('Invalid deptrac package fixture: packages missing.');
        }

        $sandbox = $this->tempDir('coretsia-deptrac-fixture');

        $this->ensureDir($sandbox . '/docs/roadmap/phase0');
        $this->ensureDir($sandbox . '/framework/packages');
        $this->ensureDir($sandbox . '/framework/tools/testing');
        $this->ensureDir($sandbox . '/framework/var/arch');

        $this->writeJson($sandbox . '/composer.json', [
            'name' => 'coretsia/deptrac-fixture',
            'type' => 'project',
            'require' => [
                'php' => '^8.4',
            ],
        ]);

        $this->writeDependencyTable($sandbox . '/docs/roadmap/phase0/00_2-dependency-table.md', $packages);

        foreach ($packages as $packageId => $package) {
            if (!is_array($package)) {
                throw new RuntimeException('Invalid deptrac package fixture entry.');
            }

            $resolvedPackageId = $package['package_id'] ?? $packageId;
            $composerName = $package['composer'] ?? null;
            $deps = $package['deps'] ?? [];

            if (!is_string($resolvedPackageId) || !is_string($composerName) || !is_array($deps)) {
                throw new RuntimeException('Invalid deptrac package fixture schema.');
            }

            $packageRoot = $sandbox . '/framework/packages/' . $resolvedPackageId;
            $srcRoot = $packageRoot . '/src';

            $this->ensureDir($srcRoot);

            $requires = [
                'php' => '^8.4',
            ];

            foreach ($deps as $depPackageId) {
                if (!is_string($depPackageId)) {
                    throw new RuntimeException('Invalid deptrac fixture dependency.');
                }

                $depPackage = $packages[$depPackageId] ?? null;
                if (!is_array($depPackage) || !is_string($depPackage['composer'] ?? null)) {
                    throw new RuntimeException('Invalid deptrac fixture dependency target.');
                }

                $requires[$depPackage['composer']] = 'dev-main';
            }

            $this->writeJson($packageRoot . '/composer.json', [
                'name' => $composerName,
                'type' => 'library',
                'require' => $requires,
                'autoload' => [
                    'psr-4' => [
                        $this->packageNamespace($resolvedPackageId) => 'src/',
                    ],
                ],
            ]);

            $this->writePackageClass($srcRoot . '/FixtureClass.php', $this->packageNamespace($resolvedPackageId));
        }

        return $sandbox;
    }

    /**
     * @param array<mixed> $packages
     */
    private function writeDependencyTable(string $path, array $packages): void
    {
        $lines = [];
        $lines[] = '# Fixture dependency table';
        $lines[] = '';
        $lines[] = '## 4) Phase 0 baseline dependency table (MUST)';
        $lines[] = '';
        $lines[] = '| package_id | depends_on |';
        $lines[] = '|---|---|';

        $packageIds = array_keys($packages);
        usort($packageIds, static fn ($a, $b): int => strcmp((string)$a, (string)$b));

        foreach ($packageIds as $packageId) {
            $package = $packages[$packageId];

            if (!is_array($package)) {
                throw new RuntimeException('Invalid dependency table fixture package.');
            }

            $resolvedPackageId = $package['package_id'] ?? $packageId;
            $deps = $package['deps'] ?? [];

            if (!is_string($resolvedPackageId) || !is_array($deps)) {
                throw new RuntimeException('Invalid dependency table fixture schema.');
            }

            $depCells = [];
            foreach ($deps as $dep) {
                if (!is_string($dep) || $dep === '') {
                    throw new RuntimeException('Invalid dependency table fixture dependency.');
                }

                $depCells[] = '`' . $dep . '`';
            }

            $lines[] = '| `' . $resolvedPackageId . '` | ' . ($depCells === [] ? '—' : implode(', ', $depCells)) . ' |';
        }

        $this->writeBytesExact($path, implode("\n", $lines) . "\n");
    }

    protected function writeDeptracAllowlistYamlFromSpikeFixture(
        string $targetPath,
        string $fixtureRelativePath,
        bool   $normalizeSrcWildcardToRegex = false,
    ): void {
        $entries = $this->requireStringListFixture($fixtureRelativePath);

        $lines = [];
        $lines[] = 'exclude_files:';

        foreach ($entries as $entry) {
            $entry = str_replace('\\', '/', trim($entry));

            if ($normalizeSrcWildcardToRegex && ($entry === 'src' || $entry === 'src/*' || $entry === 'src/**')) {
                $entry = '#(^|/)src(/|$)#';
            }

            if ($entry === 'tests/*' || $entry === 'tests/**') {
                $entry = '#(^|/)tests(/|$)#';
            }

            $lines[] = "  - '" . str_replace("'", "''", $entry) . "'";
        }

        $this->writeBytesExact($targetPath, implode("\n", $lines) . "\n");
    }

    protected function packageNamespace(string $packageId): string
    {
        $parts = explode('/', str_replace('-', '_', $packageId));
        $namespaceParts = ['Coretsia', 'Fixture'];

        foreach ($parts as $part) {
            foreach (explode('_', $part) as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }

                $namespaceParts[] = ucfirst($piece);
            }
        }

        return implode('\\', $namespaceParts) . '\\';
    }

    private function writePackageClass(string $path, string $namespace): void
    {
        $namespace = rtrim($namespace, '\\');

        $this->writeBytesExact(
            $path,
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "final class FixtureClass\n"
            . "{\n"
            . "}\n",
        );
    }

    protected function assertWorkspaceComposerFilesMatchExpectedFixtures(string $sandbox, string $fixtureName): void
    {
        $pairs = [
            '/composer.json' => '/expected_composer_root.json',
            '/framework/composer.json' => '/expected_composer_framework.json',
            '/skeleton/composer.json' => '/expected_composer_skeleton.json',
        ];

        foreach ($pairs as $actualRel => $expectedRel) {
            self::assertSame(
                $this->normalizeEol($this->readBytes($this->spikeFixturePath($fixtureName . $expectedRel))),
                $this->normalizeEol($this->readBytes($sandbox . $actualRel)),
                'Expected workspace composer lock fixture to match: ' . $actualRel,
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function globSorted(string $pattern): array
    {
        $items = glob($pattern);
        if ($items === false) {
            $items = [];
        }

        $items = array_values(array_filter($items, static fn ($item): bool => is_string($item) && $item !== ''));
        $items = array_map(static fn (string $item): string => str_replace('\\', '/', $item), $items);

        sort($items, SORT_STRING);

        return $items;
    }

    protected function readBytes(string $path): string
    {
        $bytes = @file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Cannot read file: ' . $path);
        }

        return $bytes;
    }

    protected function writeBytesExact(string $path, string $bytes): void
    {
        $this->ensureDir(dirname($path));

        $ok = @file_put_contents($path, $bytes);
        if ($ok === false) {
            throw new RuntimeException('Cannot write file: ' . $path);
        }
    }

    /**
     * @param array<mixed> $data
     */
    protected function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->writeBytesExact($path, $json . "\n");
    }

    protected function normalizeEol(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    protected function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }
    }

    protected function copyDir(string $source, string $destination): void
    {
        $source = rtrim(str_replace('\\', '/', $source), '/');
        $destination = rtrim(str_replace('\\', '/', $destination), '/');

        if (!is_dir($source)) {
            throw new RuntimeException('Source directory does not exist: ' . $source);
        }

        $this->ensureDir($destination);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $srcPath = str_replace('\\', '/', $item->getPathname());
            $relPath = substr($srcPath, strlen($source) + 1);
            $dstPath = $destination . '/' . $relPath;

            if ($item->isDir()) {
                $this->ensureDir($dstPath);
                continue;
            }

            $this->ensureDir(dirname($dstPath));

            if (!@copy($srcPath, $dstPath)) {
                throw new RuntimeException('Cannot copy file: ' . $srcPath . ' -> ' . $dstPath);
            }
        }
    }

    protected function removeDir(string $path): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || !is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $itemPath = str_replace('\\', '/', $item->getPathname());

            if ($item->isDir()) {
                @rmdir($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
