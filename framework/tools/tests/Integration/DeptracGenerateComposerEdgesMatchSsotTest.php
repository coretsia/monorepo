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

namespace Coretsia\Tools\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DeptracGenerateComposerEdgesMatchSsotTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tmpRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpRoots as $tmpRoot) {
            $this->removeTree($tmpRoot);
        }

        $this->tmpRoots = [];

        parent::tearDown();
    }

    public function testSyntheticComposerEdgeMissingFromSsotFailsDeterministically(): void
    {
        $repoRoot = $this->createSyntheticRepoRoot();

        $this->writeComposerJson(
            $repoRoot . '/composer.json',
            [
                'name' => 'coretsia/synthetic-root',
                'type' => 'project',
                'require' => [
                    'php' => '^8.4',
                ],
            ],
        );

        $this->writeFile(
            $repoRoot . '/docs/roadmap/phase0/00_2-dependency-table.md',
            <<<'MD'
# Synthetic dependency table

| package_id  | depends_on | notes |
|-------------|------------|-------|
| core/source | —          |       |
| core/target | —          |       |
MD,
        );

        $this->makeDir($repoRoot . '/framework/tools/testing');
        $this->makeDir($repoRoot . '/framework/packages/core/source/src');
        $this->makeDir($repoRoot . '/framework/packages/core/target/src');

        $this->writeComposerJson(
            $repoRoot . '/framework/packages/core/source/composer.json',
            [
                'name' => 'coretsia/core-source',
                'type' => 'library',
                'license' => 'Apache-2.0',
                'require' => [
                    'php' => '^8.4',
                    'coretsia/core-target' => '0.5.x-dev',
                ],
                'autoload' => [
                    'psr-4' => [
                        'Coretsia\\Source\\' => 'src/',
                    ],
                ],
            ],
        );

        $this->writeComposerJson(
            $repoRoot . '/framework/packages/core/target/composer.json',
            [
                'name' => 'coretsia/core-target',
                'type' => 'library',
                'license' => 'Apache-2.0',
                'require' => [
                    'php' => '^8.4',
                ],
                'autoload' => [
                    'psr-4' => [
                        'Coretsia\\Target\\' => 'src/',
                    ],
                ],
            ],
        );

        $result = $this->runDeptracGenerateCheck($repoRoot);

        $stdout = self::normalizeEol($result['stdout']);
        $stderr = self::normalizeEol($result['stderr']);

        self::assertSame(1, $result['exitCode']);
        self::assertSame('', $stdout);

        $lines = explode("\n", trim($stderr));

        self::assertSame(
            [
                'CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT: composer-edge-not-in-ssot',
                'source:core/source target:core/target reason:composer-edge-not-in-ssot',
            ],
            $lines,
        );

        self::assertStringNotContainsString('CORETSIA_DEPTRAC_GENERATE_FAILED', $stderr);
        self::assertStringNotContainsString('composer.json', $stderr);
        self::assertStringNotContainsString('"require"', $stderr);
        self::assertStringNotContainsString('coretsia/core-target', $stderr);
        self::assertStringNotContainsString('framework/packages', $stderr);
        self::assertStringNotContainsString('RuntimeException', $stderr);
        self::assertStringNotContainsString('Stack trace', $stderr);

        self::assertStringNotContainsString(
            str_replace('\\', '/', $repoRoot),
            str_replace('\\', '/', $stderr),
        );
    }

    /**
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runDeptracGenerateCheck(string $repoRoot): array
    {
        $frameworkRoot = dirname(__DIR__, 3);
        $script = $frameworkRoot . '/tools/build/deptrac_generate.php';

        self::assertFileExists($script);

        $process = proc_open(
            [
                PHP_BINARY,
                $script,
                '--check',
                '--repo-root',
                $repoRoot,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $frameworkRoot,
        );

        if (!is_resource($process)) {
            self::fail('Failed to start deptrac_generate.php process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function createSyntheticRepoRoot(): string
    {
        $root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/')
            . '/coretsia-deptrac-edge-'
            . bin2hex(random_bytes(8));

        $this->makeDir($root);
        $this->tmpRoots[] = $root;

        return $root;
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        $this->makeDir($dir);

        $content = self::normalizeEol($content);
        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $written = file_put_contents($path, $content);
        if (!is_int($written) || $written <= 0) {
            self::fail('Failed to write synthetic file.');
        }
    }

    private function makeDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            self::fail('Failed to create synthetic directory.');
        }
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $node) {
            $nodePath = $node->getPathname();

            if ($node->isDir() && !$node->isLink()) {
                @rmdir($nodePath);
                continue;
            }

            @unlink($nodePath);
        }

        @rmdir($path);
    }

    private static function normalizeEol(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function writeComposerJson(string $path, array $composer): void
    {
        $json = json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->writeFile($path, $json);
    }
}
