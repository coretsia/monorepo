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

final class NewPackageAtomicCreationTest extends ToolContractTestCase
{
    public function testExistingLayerCreationCommitsFullyPreparedPackageAtomically(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $this->ensureDir($sandbox . '/framework/packages/core');

        $packageDir = $sandbox . '/framework/packages/core/atomic-library';

        self::assertDirectoryDoesNotExist($packageDir);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-library',
                '--kind=library',
            ],
        );

        self::assertSame(0, $code, $output);

        self::assertDirectoryExists($packageDir);
        self::assertDirectoryExists($packageDir . '/src');
        self::assertFileExists($packageDir . '/composer.json');

        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $composerBytes = $this->readBytes($packageDir . '/composer.json');
        $composer = \json_decode(
            $composerBytes,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame(
            'coretsia/core-atomic-library',
            $composer['name'] ?? null,
        );
        self::assertSame(
            'library',
            $composer['extra']['coretsia']['kind'] ?? null,
        );
        self::assertFalse(\str_contains($composerBytes, "\r"));
        self::assertSame("\n", \substr($composerBytes, -1));

        self::assertSame(
            [],
            \glob($sandbox . '/framework/var/tmp/new-package-*') ?: [],
        );
    }

    public function testAbsentLayerCreationCommitsPreparedLayerAtomically(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $layerDir = $sandbox . '/framework/packages/integrations';
        $packageDir = $layerDir . '/atomic-runtime';

        self::assertDirectoryDoesNotExist($layerDir);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=integrations',
                '--slug=atomic-runtime',
                '--kind=runtime',
            ],
        );

        self::assertSame(0, $code, $output);

        self::assertDirectoryExists($packageDir);
        self::assertDirectoryExists($packageDir . '/src');
        self::assertFileExists($packageDir . '/composer.json');

        $this->assertPackageScaffoldIsCanonical(
            $sandbox,
            $packageDir,
        );

        $composerBytes = $this->readBytes($packageDir . '/composer.json');
        $composer = \json_decode(
            $composerBytes,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame(
            'coretsia/integrations-atomic-runtime',
            $composer['name'] ?? null,
        );
        self::assertSame(
            'runtime',
            $composer['extra']['coretsia']['kind'] ?? null,
        );
        self::assertFalse(\str_contains($composerBytes, "\r"));
        self::assertSame("\n", \substr($composerBytes, -1));

        self::assertSame(
            [],
            \glob($sandbox . '/framework/var/tmp/new-package-*') ?: [],
        );
    }

    public function testFailureBeforeCommitLeavesNoPartialPackageOrStagingTree(): void
    {
        $sandbox = $this->createNewPackageSandbox(
            includeScaffoldTool: false,
        );

        $this->ensureDir($sandbox . '/framework/packages/core');

        $packageDir = $sandbox . '/framework/packages/core/atomic-failure';

        self::assertDirectoryDoesNotExist($packageDir);
        self::assertFileDoesNotExist($sandbox . '/framework/tools/build/sync_package_scaffold.php');

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-failure',
                '--kind=library',
            ],
        );

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString(
            'CORETSIA_NEW_PACKAGE_FAILED',
            $output,
        );
        self::assertDirectoryDoesNotExist($packageDir);

        self::assertSame(
            [],
            \glob($sandbox . '/framework/var/tmp/new-package-*') ?: [],
        );
    }

    public function testExistingTargetFailsWithoutMutationOrStagingArtifacts(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $existingPackage = $sandbox . '/framework/packages/core/atomic-existing';

        $this->ensureDir($existingPackage);

        $this->writeBytesExact(
            $existingPackage . '/keep.txt',
            "keep-existing-package\n",
        );

        $before = $this->readBytes($existingPackage . '/keep.txt');

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-existing',
                '--kind=library',
            ],
        );

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString(
            'CORETSIA_NEW_PACKAGE_FAILED',
            $output,
        );
        self::assertDirectoryExists($existingPackage);
        self::assertSame(
            $before,
            $this->readBytes($existingPackage . '/keep.txt'),
        );
        self::assertFileDoesNotExist($existingPackage . '/composer.json');

        self::assertSame(
            [],
            \glob($sandbox . '/framework/var/tmp/new-package-*') ?: [],
        );
    }

    private function createNewPackageSandbox(
        bool $includeScaffoldTool = true,
    ): string {
        $sandbox = $this->tempDir('coretsia-new-package');

        $this->ensureDir($sandbox . '/framework/packages');
        $this->ensureDir($sandbox . '/framework/tools/build');
        $this->ensureDir($sandbox . '/framework/vendor');
        $this->ensureDir($sandbox . '/skeleton');

        $this->writeJson(
            $sandbox . '/composer.json',
            [
                'name' => 'coretsia/new-package-test',
                'type' => 'project',
            ],
        );

        $this->writeBytesExact(
            $sandbox . '/LICENSE',
            "test-license\n",
        );

        $this->writeBytesExact(
            $sandbox . '/NOTICE',
            "test-notice\n",
        );

        $this->writeBytesExact(
            $sandbox . '/SECURITY.md',
            "test-security\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/vendor/autoload.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn true;\n",
        );

        $this->copyDir(
            $this->frameworkRoot() . '/tools/support',
            $sandbox . '/framework/tools/support',
        );

        if ($includeScaffoldTool) {
            $this->writeBytesExact(
                $sandbox . '/framework/tools/build/sync_package_scaffold.php',
                $this->readBytes(
                    $this->frameworkRoot() . '/tools/build/sync_package_scaffold.php',
                ),
            );
        }

        return $sandbox;
    }

    private function assertPackageScaffoldIsCanonical(
        string $sandbox,
        string $packageDir,
    ): void {
        [$code, $output] = $this->runPhp(
            $sandbox . '/framework/tools/build/sync_package_scaffold.php',
            [
                '--check',
                $packageDir,
            ],
            $sandbox . '/framework',
        );

        self::assertSame(
            0,
            $code,
            "Expected final package scaffold --check to pass.\nOutput:\n" . $output,
        );

        self::assertSame(
            '',
            $this->normalizeEol($output),
            'Successful final package scaffold --check must be silent.',
        );
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: int, 1: string}
     */
    private function runNewPackage(string $sandbox, array $args): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/build/new-package.php',
            \array_merge(
                ['--repo-root=' . $sandbox],
                $args,
            ),
            $this->frameworkRoot(),
        );
    }
}
