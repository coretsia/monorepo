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

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class SyncPackageScaffoldCreatesMissingFilesTest extends ToolContractTestCase
{
    public function testApplyModeCreatesMissingScaffoldWithoutRewritingUserOwnedContent(): void
    {
        $scanRoot = $this->prepareTempRoot('package-scaffold-apply');

        $libraryRoot = $scanRoot . '/packages/core/sample-library';
        $runtimeRoot = $scanRoot . '/packages/platform/sample-runtime';

        $this->createLibraryPackageSkeleton($libraryRoot, 'core', 'sample-library');
        $this->createRuntimePackageSkeleton($runtimeRoot, 'platform', 'sample-runtime');

        $runtimeReadme = "# User-owned Runtime README\n\nThis content must not be rewritten.\n";
        $runtimeDefaultsConfig = $this->phpConfigFile("return [\n    'sentinel' => 'user-owned-defaults',\n];\n");
        $runtimeModuleCode = $this->phpClassFile(
            'Coretsia\\Platform\\SampleRuntime\\Module',
            'SampleRuntimeModule',
            "    public const string SENTINEL = 'user-owned-module';\n",
        );

        $this->writeBytesExact($runtimeRoot . '/README.md', $runtimeReadme);
        $this->writeBytesExact($runtimeRoot . '/config/sample-runtime.php', $runtimeDefaultsConfig);
        $this->writeBytesExact($runtimeRoot . '/src/Module/SampleRuntimeModule.php', $runtimeModuleCode);

        [$code, $output] = $this->runSyncPackageScaffold($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);

        self::assertSame($this->readBytes($this->repoRoot() . '/LICENSE'), $this->readBytes($libraryRoot . '/LICENSE'));
        self::assertSame($this->readBytes($this->repoRoot() . '/NOTICE'), $this->readBytes($libraryRoot . '/NOTICE'));
        self::assertSame($this->readBytes($this->repoRoot() . '/LICENSE'), $this->readBytes($runtimeRoot . '/LICENSE'));
        self::assertSame($this->readBytes($this->repoRoot() . '/NOTICE'), $this->readBytes($runtimeRoot . '/NOTICE'));

        self::assertFileExists($libraryRoot . '/README.md');
        self::assertSame($runtimeReadme, $this->readBytes($runtimeRoot . '/README.md'));

        self::assertFileExists($libraryRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php');
        self::assertFileExists($runtimeRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php');

        self::assertFalse(\is_dir($libraryRoot . '/config'));
        self::assertFalse(\is_dir($libraryRoot . '/src/Module'));
        self::assertFalse(\is_dir($libraryRoot . '/src/Provider'));

        self::assertFileExists($runtimeRoot . '/src/Provider/SampleRuntimeServiceProvider.php');
        self::assertFileExists($runtimeRoot . '/config/rules.php');

        self::assertSame($runtimeDefaultsConfig, $this->readBytes($runtimeRoot . '/config/sample-runtime.php'));
        self::assertSame($runtimeModuleCode, $this->readBytes($runtimeRoot . '/src/Module/SampleRuntimeModule.php'));
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runSyncPackageScaffold(string $scanRoot): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/build/sync_package_scaffold.php',
            [
                $scanRoot,
            ],
            $this->frameworkRoot(),
        );
    }

    private function prepareTempRoot(string $name): string
    {
        $root = $this->frameworkRoot() . '/var/phpunit/' . $name;

        $this->removePath($root);
        $this->ensureDir($root);

        return \rtrim(\str_replace('\\', '/', $root), '/');
    }

    private function createLibraryPackageSkeleton(string $packageRoot, string $layer, string $slug): void
    {
        $this->ensureDir($packageRoot . '/src');

        $studlySlug = $this->studly($slug);
        $namespace = $this->namespaceRoot($layer, $slug);

        $this->writeBytesExact(
            $packageRoot . '/composer.json',
            $this->composerJson($layer, $slug, 'library'),
        );

        $this->writeBytesExact(
            $packageRoot . '/src/' . $studlySlug . '.php',
            $this->phpClassFile(\rtrim($namespace, '\\'), $studlySlug),
        );
    }

    private function createRuntimePackageSkeleton(string $packageRoot, string $layer, string $slug): void
    {
        $this->ensureDir($packageRoot . '/src/Module');
        $this->ensureDir($packageRoot . '/config');

        $this->writeBytesExact(
            $packageRoot . '/composer.json',
            $this->composerJson($layer, $slug, 'runtime'),
        );
    }

    private function composerJson(string $layer, string $slug, string $kind): string
    {
        $namespaceRoot = $this->namespaceRoot($layer, $slug);
        $studlySlug = $this->studly($slug);

        $extra = [
            'kind' => $kind,
        ];

        if ($kind === 'runtime') {
            $extra['moduleId'] = $layer . '.' . $slug;
            $extra['moduleClass'] = $namespaceRoot . 'Module\\' . $studlySlug . 'Module';
            $extra['providers'] = [
                $namespaceRoot . 'Provider\\' . $studlySlug . 'ServiceProvider',
            ];
            $extra['defaultsConfigPath'] = 'config/' . $slug . '.php';
        }

        $data = [
            'name' => 'coretsia/' . $layer . '-' . $slug,
            'type' => 'library',
            'license' => 'Apache-2.0',
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
            'extra' => [
                'coretsia' => $extra,
            ],
        ];

        $json = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        self::assertIsString($json);

        return $json . "\n";
    }

    private function namespaceRoot(string $layer, string $slug): string
    {
        $studlySlug = $this->studly($slug);

        if ($layer === 'core') {
            return 'Coretsia\\' . $studlySlug . '\\';
        }

        return 'Coretsia\\' . $this->studly($layer) . '\\' . $studlySlug . '\\';
    }

    private function studly(string $value): string
    {
        $result = '';

        foreach (\explode('-', $value) as $part) {
            if ($part === '') {
                continue;
            }

            $result .= \strtoupper($part[0]) . \strtolower(\substr($part, 1));
        }

        return $result;
    }

    private function phpClassFile(string $namespace, string $className, string $body = ''): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace " . $namespace . ";\n\n"
            . "final class " . $className . "\n"
            . "{\n"
            . $body
            . "}\n";
    }

    private function phpConfigFile(string $returnStatement): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . $returnStatement;
    }

    protected function repoRoot(): string
    {
        return \rtrim(\str_replace('\\', '/', \dirname($this->frameworkRoot())), '/');
    }

    protected function ensureDir(string $dir): void
    {
        if (\is_dir($dir)) {
            return;
        }

        \mkdir($dir, 0777, true);
    }

    private function removePath(string $path): void
    {
        if (!\file_exists($path) && !\is_link($path)) {
            return;
        }

        if (\is_file($path) || \is_link($path)) {
            \unlink($path);

            return;
        }

        $entries = \scandir($path);
        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removePath($path . '/' . $entry);
        }

        \rmdir($path);
    }
}
