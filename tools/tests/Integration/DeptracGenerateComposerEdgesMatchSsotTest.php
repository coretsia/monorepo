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

use Coretsia\Tools\Support\RepositoryContext;
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
            $repoRoot . '/docs/architecture/DEPENDENCIES.md',
            <<<'MD'
# Synthetic dependency table

## 4) Canonical direct dependency matrix (MUST)

| package_id  | depends_on | notes |
|-------------|------------|-------|
| core/source | —          |       |
| core/target | —          |       |
MD,
        );

        $this->makeDir($repoRoot . '/tools/testing');
        $this->makeDir($repoRoot . '/packages/core/source/src');
        $this->makeDir($repoRoot . '/packages/core/target/src');

        $this->writeComposerJson(
            $repoRoot . '/packages/core/source/composer.json',
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
            $repoRoot . '/packages/core/target/composer.json',
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
                'CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT',
                'composer-edge-not-in-ssot',
                'source:core/source target:core/target reason:composer-edge-not-in-ssot',
            ],
            $lines,
        );

        self::assertStringNotContainsString('CORETSIA_DEPTRAC_GENERATE_FAILED', $stderr);
        self::assertStringNotContainsString('composer.json', $stderr);
        self::assertStringNotContainsString('"require"', $stderr);
        self::assertStringNotContainsString('coretsia/core-target', $stderr);
        self::assertStringNotContainsString('packages', $stderr);
        self::assertStringNotContainsString('RuntimeException', $stderr);
        self::assertStringNotContainsString('Stack trace', $stderr);

        self::assertStringNotContainsString(
            str_replace('\\', '/', $repoRoot),
            str_replace('\\', '/', $stderr),
        );
    }

    public function testRuntimeOnlyComposerEdgeDoesNotWidenSourceRuleset(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();
        $result = $this->runDeptracGenerateCheck($repoRoot, true);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('', $result['stderr']);

        $config = file_get_contents($repoRoot . '/tools/testing/deptrac.yaml');
        self::assertIsString($config);
        self::assertStringContainsString("'platform.source': [ ]", $config);
        self::assertStringNotContainsString("      - 'platform.target'", $config);
    }

    public function testRuntimeModuleRequirementWithoutComposerDependencyFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();

        $this->writeRuntimePackage(
            $repoRoot,
            'platform',
            'source',
            [],
            ['platform.target'],
        );

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT',
            $result['stderr'],
        );
        self::assertStringContainsString(
            'source:platform/source target:platform/target reason:runtime-module-composer-require-missing',
            $result['stderr'],
        );
    }

    public function testRuntimeOnlyComposerEdgeWithoutModuleRequirementFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();
        $this->writeRuntimePackage($repoRoot, 'platform', 'source', ['coretsia/platform-target' => '^0.8.0'], []);

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT', $result['stderr']);
        self::assertStringContainsString('source:platform/source target:platform/target', $result['stderr']);
    }

    public function testRuntimeOnlyComposerEdgeWithWrongModuleRequirementFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();
        $this->writeRuntimePackage(
            $repoRoot,
            'platform',
            'source',
            ['coretsia/platform-target' => '^0.8.0'],
            ['platform.other'],
        );

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT', $result['stderr']);
    }

    public function testRuntimeOnlyComposerEdgeToNonRuntimePackageFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();
        $this->writeRuntimePackage($repoRoot, 'platform', 'target', [], [], false);

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT', $result['stderr']);
    }

    public function testRuntimeOnlyComposerEdgeInForbiddenLayerDirectionFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot('core', 'platform');

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT', $result['stderr']);
        self::assertStringContainsString('source:core/source target:platform/target', $result['stderr']);
    }

    public function testRuntimeOnlyComposerEdgeToDevtoolsFails(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot('enterprise', 'devtools');
        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'source:enterprise/source target:devtools/target reason:devtools-composer-dependency-forbidden',
            $result['stderr'],
        );
    }

    public function testNonDevtoolsSourcePermissionIsForbidden(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot('enterprise', 'devtools');
        $policyPath = $repoRoot . '/docs/architecture/DEPENDENCIES.md';
        $policy = file_get_contents($policyPath);
        self::assertIsString($policy);

        $this->writeFile(
            $policyPath,
            str_replace('| enterprise/source | — | |', '| enterprise/source | devtools/target | |', $policy),
        );
        $this->writeRuntimePackage($repoRoot, 'enterprise', 'source', [], []);

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'source:enterprise/source target:devtools/target reason:devtools-source-dependency-forbidden',
            $result['stderr'],
        );
    }

    public function testNonDevtoolsComposerDependencyIsForbiddenEvenWhenSourceMatrixAllowsIt(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot('enterprise', 'devtools');
        $policyPath = $repoRoot . '/docs/architecture/DEPENDENCIES.md';
        $policy = file_get_contents($policyPath);
        self::assertIsString($policy);

        $this->writeFile(
            $policyPath,
            str_replace('| enterprise/source | — | |', '| enterprise/source | devtools/target | |', $policy),
        );

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'source:enterprise/source target:devtools/target reason:devtools-composer-dependency-forbidden',
            $result['stderr'],
        );
    }

    public function testSourceMatrixCannotOverrideLayerDirection(): void
    {
        foreach (
            [
                ['core', 'platform'],
                ['platform', 'integrations'],
                ['integrations', 'enterprise'],
                ['devtools', 'enterprise'],
            ] as [$sourceLayer, $targetLayer]
        ) {
            $repoRoot = $this->createRuntimeOnlyRepoRoot($sourceLayer, $targetLayer);
            $policyPath = $repoRoot . '/docs/architecture/DEPENDENCIES.md';
            $policy = file_get_contents($policyPath);
            self::assertIsString($policy);

            $originalRow = '| ' . $sourceLayer . '/source | — | |';
            $updatedRow = '| ' . $sourceLayer . '/source | ' . $targetLayer . '/target | |';
            self::assertStringContainsString($originalRow, $policy);

            $this->writeFile($policyPath, str_replace($originalRow, $updatedRow, $policy));
            $this->writeRuntimePackage(
                $repoRoot,
                $sourceLayer,
                'source',
                ['coretsia/' . $targetLayer . '-target' => '^0.8.0'],
                [],
            );

            $result = $this->runDeptracGenerateCheck($repoRoot);

            self::assertSame(1, $result['exitCode']);
            self::assertStringContainsString(
                'source:' . $sourceLayer . '/source target:' . $targetLayer . '/target reason:source-layer-dependency-forbidden',
                $result['stderr'],
            );
        }
    }

    public function testDevtoolsPackageCannotContributeRuntimeModule(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot('devtools', 'platform');
        $this->writeRuntimePackage($repoRoot, 'devtools', 'source', [], []);

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'source:devtools/source reason:devtools-runtime-module-forbidden',
            $result['stderr'],
        );
    }

    public function testRuntimeOnlyComposerEdgesCannotFormComposerCycle(): void
    {
        $repoRoot = $this->createRuntimeOnlyRepoRoot();
        $this->writeRuntimePackage(
            $repoRoot,
            'platform',
            'target',
            ['coretsia/platform-source' => '^0.8.0'],
            ['platform.source'],
        );

        $result = $this->runDeptracGenerateCheck($repoRoot);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('CORETSIA_DEPTRAC_CYCLE_DETECTED', $result['stderr']);
        self::assertStringContainsString('package dependency cycle', $result['stderr']);
    }

    private function createRuntimeOnlyRepoRoot(
        string $sourceLayer = 'platform',
        string $targetLayer = 'platform',
    ): string {
        $repoRoot = $this->createSyntheticRepoRoot();
        $this->writeComposerJson(
            $repoRoot . '/composer.json',
            [
                'name' => 'coretsia/synthetic-root',
                'type' => 'project',
                'require' => ['php' => '^8.4'],
            ],
        );

        $packageIds = [$sourceLayer . '/source', $targetLayer . '/target'];
        sort($packageIds, SORT_STRING);

        $table = "# Synthetic dependency table\n\n"
            . "## 4) Canonical direct dependency matrix (MUST)\n\n"
            . "| package_id | depends_on | notes |\n"
            . "|------------|------------|-------|\n";

        foreach ($packageIds as $packageId) {
            $table .= '| ' . $packageId . " | — | |\n";
        }

        $this->writeFile($repoRoot . '/docs/architecture/DEPENDENCIES.md', $table);
        $this->makeDir($repoRoot . '/tools/testing');
        $this->writeRuntimePackage(
            $repoRoot,
            $sourceLayer,
            'source',
            ['coretsia/' . $targetLayer . '-target' => '^0.8.0'],
            [$targetLayer . '.target'],
        );
        $this->writeRuntimePackage($repoRoot, $targetLayer, 'target', [], []);

        return $repoRoot;
    }

    /**
     * @param array<string, string> $composerRequires
     * @param list<string> $moduleRequires
     */
    private function writeRuntimePackage(
        string $repoRoot,
        string $layer,
        string $slug,
        array $composerRequires,
        array $moduleRequires,
        bool $runtime = true,
    ): void {
        $packagePath = $repoRoot . '/packages/' . $layer . '/' . $slug;
        $this->makeDir($packagePath . '/src');

        $composer = [
            'name' => 'coretsia/' . $layer . '-' . $slug,
            'type' => 'library',
            'license' => 'Apache-2.0',
            'require' => ['php' => '^8.4', ...$composerRequires],
            'autoload' => [
                'psr-4' => [
                    'Coretsia\\Synthetic\\' . ucfirst($slug) . '\\' => 'src/',
                ],
            ],
        ];

        if ($runtime) {
            $composer['extra'] = [
                'coretsia' => [
                    'kind' => 'runtime',
                    'moduleId' => $layer . '.' . $slug,
                    'requires' => $moduleRequires,
                ],
            ];
        }

        $this->writeComposerJson($packagePath . '/composer.json', $composer);
    }

    /**
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runDeptracGenerateCheck(string $repoRoot, bool $apply = false): array
    {
        $script = RepositoryContext::discoverFrom(__DIR__)
            ->resolveExistingFile('tools/build/deptrac_generate.php');

        self::assertFileExists($script);

        $process = proc_open(
            [
                PHP_BINARY,
                $script,
                $apply ? '--apply' : '--check',
                '--repo-root',
                $repoRoot,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $repoRoot,
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
