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

namespace Coretsia\Tools\Tests\Contract;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class PackageIndexGenerationContractTest extends ToolContractTestCase
{
    public function testCanonicalWorkspacePackageIndexIsGeneratedDeterministicallyByCanonicalTool(): void
    {
        $sandbox = $this->createWorkspaceSandbox('Workspace/Canonical');

        /*
         * package_index.php validates the canonical repository markers:
         * framework/, docs/, composer.json.
         *
         * Workspace fixture intentionally owns Composer data only,
         * therefore docs/ is created by this test and is not promoted
         * into the fixture.
         */
        $this->ensureDir($sandbox . '/docs');

        $script = $this->frameworkRoot() . '/tools/build/package_index.php';
        $indexPath = $sandbox . '/framework/tools/testing/package-index.php';

        [$applyCode, $applyOutput] = $this->runPhp(
            $script,
            [
                '--repo-root',
                $sandbox,
                '--apply',
            ],
            $this->frameworkRoot(),
        );

        self::assertSame(
            0,
            $applyCode,
            $applyOutput,
        );

        self::assertFileExists($indexPath);

        $firstBytes = $this->readBytes($indexPath);

        self::assertNotSame('', $firstBytes);
        self::assertFalse(\str_contains($firstBytes, "\r"));
        self::assertSame("\n", \substr($firstBytes, -1));

        $index = require $indexPath;

        self::assertIsArray($index);
        self::assertSame(
            1,
            $index['schemaVersion'] ?? null,
        );
        self::assertSame(
            'framework/tools/build/package_index.php',
            $index['generatedBy'] ?? null,
        );

        $packages = $index['packages'] ?? null;

        self::assertIsArray($packages);

        $projection = [];

        foreach ($packages as $package) {
            self::assertIsArray($package);

            $projection[] = [
                'id' => $package['id'] ?? null,
                'layer' => $package['layer'] ?? null,
                'slug' => $package['slug'] ?? null,
                'path' => $package['path'] ?? null,
                'composerName' => $package['composerName'] ?? null,
                'psr4' => $package['psr4'] ?? null,
                'kind' => $package['kind'] ?? null,
                'moduleClass' => $package['moduleClass'] ?? null,
            ];
        }

        self::assertSame(
            [
                [
                    'id' => 'core.contracts',
                    'layer' => 'core',
                    'slug' => 'contracts',
                    'path' => 'framework/packages/core/contracts',
                    'composerName' => 'coretsia/core-contracts',
                    'psr4' => 'Coretsia\\Core\\Contracts\\',
                    'kind' => 'library',
                    'moduleClass' => null,
                ],
                [
                    'id' => 'core.kernel',
                    'layer' => 'core',
                    'slug' => 'kernel',
                    'path' => 'framework/packages/core/kernel',
                    'composerName' => 'coretsia/core-kernel',
                    'psr4' => 'Coretsia\\Core\\Kernel\\',
                    'kind' => 'runtime',
                    'moduleClass' => 'Coretsia\\Core\\Kernel\\Module\\KernelModule',
                ],
                [
                    'id' => 'devtools.internal-toolkit',
                    'layer' => 'devtools',
                    'slug' => 'internal-toolkit',
                    'path' => 'framework/packages/devtools/internal-toolkit',
                    'composerName' => 'coretsia/devtools-internal-toolkit',
                    'psr4' => 'Coretsia\\Devtools\\InternalToolkit\\',
                    'kind' => 'library',
                    'moduleClass' => null,
                ],
                [
                    'id' => 'platform.cli',
                    'layer' => 'platform',
                    'slug' => 'cli',
                    'path' => 'framework/packages/platform/cli',
                    'composerName' => 'coretsia/platform-cli',
                    'psr4' => 'Coretsia\\Platform\\Cli\\',
                    'kind' => 'runtime',
                    'moduleClass' => 'Coretsia\\Platform\\Cli\\Module\\CliModule',
                ],
            ],
            $projection,
        );

        [$checkCode, $checkOutput] = $this->runPhp(
            $script,
            [
                '--repo-root',
                $sandbox,
                '--check',
            ],
            $this->frameworkRoot(),
        );

        self::assertSame(
            0,
            $checkCode,
            $checkOutput,
        );

        [$secondApplyCode, $secondApplyOutput] = $this->runPhp(
            $script,
            [
                '--repo-root',
                $sandbox,
                '--apply',
            ],
            $this->frameworkRoot(),
        );

        self::assertSame(
            0,
            $secondApplyCode,
            $secondApplyOutput,
        );

        self::assertSame(
            $firstBytes,
            $this->readBytes($indexPath),
        );
    }
}
