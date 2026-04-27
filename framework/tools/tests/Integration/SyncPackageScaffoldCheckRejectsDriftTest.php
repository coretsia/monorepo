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

final class SyncPackageScaffoldCheckRejectsDriftTest extends ToolContractTestCase
{
    public function testCheckModeRejectsMissingAndDriftedLegalFilesWithoutWrites(): void
    {
        $scanRoot = $this->prepareTempRoot('package-scaffold-check');

        $missingLegalRoot = $scanRoot . '/packages/core/missing-legal';
        $driftedRuntimeRoot = $scanRoot . '/packages/platform/drifted-runtime';

        $this->createCompleteLibraryPackage($missingLegalRoot, 'core', 'missing-legal', false, false);
        $this->createCompleteRuntimePackage($driftedRuntimeRoot, 'platform', 'drifted-runtime', true, true);

        $driftedLicense = "Drifted LICENSE fixture.\n";
        $driftedNotice = "Drifted NOTICE fixture.\n";

        $this->writeBytesExact($driftedRuntimeRoot . '/LICENSE', $driftedLicense);
        $this->writeBytesExact($driftedRuntimeRoot . '/NOTICE', $driftedNotice);

        [$code, $output] = $this->runSyncPackageScaffoldCheck($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC\n"
            . "packages/core/missing-legal/LICENSE: missing-canonical-legal-file\n"
            . "packages/core/missing-legal/NOTICE: missing-canonical-legal-file\n"
            . "packages/platform/drifted-runtime/LICENSE: canonical-legal-file-drift\n"
            . "packages/platform/drifted-runtime/NOTICE: canonical-legal-file-drift\n",
            $output,
        );

        self::assertFileDoesNotExist($missingLegalRoot . '/LICENSE');
        self::assertFileDoesNotExist($missingLegalRoot . '/NOTICE');
        self::assertSame($driftedLicense, $this->readBytes($driftedRuntimeRoot . '/LICENSE'));
        self::assertSame($driftedNotice, $this->readBytes($driftedRuntimeRoot . '/NOTICE'));

        $this->assertDiagnosticsAreRelativeAndSorted($output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runSyncPackageScaffoldCheck(string $scanRoot): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/build/sync_package_scaffold.php',
            [
                '--check',
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

    private function createCompleteLibraryPackage(
        string $packageRoot,
        string $layer,
        string $slug,
        bool   $withLicense,
        bool   $withNotice,
    ): void {
        $this->ensureDir($packageRoot . '/src');
        $this->ensureDir($packageRoot . '/tests/Contract');

        $studlySlug = $this->studly($slug);
        $namespace = $this->namespaceRoot($layer, $slug);

        $this->writeBytesExact($packageRoot . '/composer.json', $this->composerJson($layer, $slug, 'library'));
        $this->writeBytesExact($packageRoot . '/README.md', $this->readme($studlySlug));
        $this->writeBytesExact($packageRoot . '/src/' . $studlySlug . '.php', $this->phpClassFile(\rtrim($namespace, '\\'), $studlySlug));
        $this->writeBytesExact(
            $packageRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
            $this->noopContractTest($namespace . 'Tests\\Contract'),
        );

        if ($withLicense) {
            $this->writeBytesExact($packageRoot . '/LICENSE', $this->readBytes($this->repoRoot() . '/LICENSE'));
        }

        if ($withNotice) {
            $this->writeBytesExact($packageRoot . '/NOTICE', $this->readBytes($this->repoRoot() . '/NOTICE'));
        }
    }

    private function createCompleteRuntimePackage(
        string $packageRoot,
        string $layer,
        string $slug,
        bool   $withLicense,
        bool   $withNotice,
    ): void {
        $this->ensureDir($packageRoot . '/src/Module');
        $this->ensureDir($packageRoot . '/src/Provider');
        $this->ensureDir($packageRoot . '/config');
        $this->ensureDir($packageRoot . '/tests/Contract');

        $studlySlug = $this->studly($slug);
        $namespace = $this->namespaceRoot($layer, $slug);

        $this->writeBytesExact($packageRoot . '/composer.json', $this->composerJson($layer, $slug, 'runtime'));
        $this->writeBytesExact($packageRoot . '/README.md', $this->readme($studlySlug));
        $this->writeBytesExact(
            $packageRoot . '/src/Module/' . $studlySlug . 'Module.php',
            $this->phpClassFile($namespace . 'Module', $studlySlug . 'Module'),
        );
        $this->writeBytesExact(
            $packageRoot . '/src/Provider/' . $studlySlug . 'ServiceProvider.php',
            $this->phpClassFile($namespace . 'Provider', $studlySlug . 'ServiceProvider'),
        );
        $this->writeBytesExact($packageRoot . '/config/' . $slug . '.php', $this->phpConfigFile("return [];\n"));
        $this->writeBytesExact($packageRoot . '/config/rules.php', $this->phpConfigFile("return [];\n"));
        $this->writeBytesExact(
            $packageRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
            $this->noopContractTest($namespace . 'Tests\\Contract'),
        );

        if ($withLicense) {
            $this->writeBytesExact($packageRoot . '/LICENSE', $this->readBytes($this->repoRoot() . '/LICENSE'));
        }

        if ($withNotice) {
            $this->writeBytesExact($packageRoot . '/NOTICE', $this->readBytes($this->repoRoot() . '/NOTICE'));
        }
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

    private function readme(string $title): string
    {
        return '# ' . $title . "\n\n"
            . "Fixture package for package scaffold sync tests.\n\n"
            . "## Observability\n\n"
            . "This fixture does not emit telemetry.\n\n"
            . "## Errors\n\n"
            . "This fixture does not define runtime error codes.\n\n"
            . "## Security / Redaction\n\n"
            . "This fixture does not process sensitive runtime data.\n";
    }

    private function noopContractTest(string $namespace): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace " . $namespace . ";\n\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . "final class CrossCuttingNoopDoesNotThrowTest extends TestCase\n"
            . "{\n"
            . "    public function testNoopDoesNotThrow(): void\n"
            . "    {\n"
            . "        self::assertTrue(true);\n"
            . "    }\n"
            . "}\n";
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

    private function phpClassFile(string $namespace, string $className): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace " . $namespace . ";\n\n"
            . "final class " . $className . "\n"
            . "{\n"
            . "}\n";
    }

    private function phpConfigFile(string $returnStatement): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . $returnStatement;
    }

    private function assertDiagnosticsAreRelativeAndSorted(string $output): void
    {
        $lines = \explode("\n", \trim($output));

        self::assertNotSame([], $lines);
        self::assertSame('CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC', $lines[0]);

        $diagnostics = \array_slice($lines, 1);

        self::assertNotSame([], $diagnostics);

        foreach ($diagnostics as $diagnostic) {
            self::assertStringStartsWith('packages/', $diagnostic);
            self::assertStringNotContainsString('\\', $diagnostic);
            self::assertDoesNotMatchRegularExpression('/\A\//', $diagnostic);
            self::assertDoesNotMatchRegularExpression('/\A[A-Za-z]:[\/\\\\]/', $diagnostic);
        }

        $sorted = $diagnostics;
        \sort($sorted, \SORT_STRING);

        self::assertSame($sorted, $diagnostics);
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
