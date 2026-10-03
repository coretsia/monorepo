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

namespace Coretsia\Tools\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallationCatalogGenerationContractTest extends TestCase
{
    public function testCommittedCatalogPassesAuthoringCheckAndGeneratorUsesDeterministicRenderer(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $pipes = [];
        $process = \proc_open(
            [
                \PHP_BINARY,
                $repoRoot . '/tools/build/installation_catalog.php',
                '--check',
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $repoRoot,
            null,
            ['bypass_shell' => true],
        );

        self::assertIsResource($process);

        \fclose($pipes[0]);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        self::assertSame(
            0,
            \proc_close($process),
            $stdout . $stderr,
        );

        $source = \file_get_contents($repoRoot . '/tools/build/installation_catalog.php');

        self::assertIsString($source);
        self::assertStringContainsString(
            'DeterministicPhpReturnFile::render(',
            $source,
        );
        self::assertStringContainsString("'schemaVersion' => 1", $source);
        self::assertStringContainsString('ReleaseLine::load(', $source);
    }

    public function testCheckRejectsGeneratedCatalogDrift(): void
    {
        $root = self::fixtureRoot(
            self::packageManifest([
                'kind' => 'runtime',
                'moduleId' => 'core.kernel',
                'requires' => [],
                'conflicts' => [],
            ]),
        );

        try {
            $apply = self::runTool($root, '--apply');

            self::assertSame(
                0,
                $apply['exitCode'],
                $apply['stdout'] . $apply['stderr'],
            );

            $catalogPath = $root
                . '/packages/core/kernel/resources/packaging/'
                . 'installation-catalog.php';

            self::assertFileExists($catalogPath);
            \file_put_contents(
                $catalogPath,
                " \n",
                \FILE_APPEND,
            );

            $check = self::runTool($root, '--check');

            self::assertSame(1, $check['exitCode']);
            self::assertStringContainsString(
                'installation-catalog-out-of-date',
                $check['stdout'] . $check['stderr'],
            );
        } finally {
            self::remove($root);
        }
    }

    public function testGeneratorRejectsMalformedModuleMetadataAndExcludesNonRuntimeLibrary(): void
    {
        $root = self::fixtureRoot(
            self::packageManifest([
                'kind' => 'library',
            ]),
        );

        try {
            $apply = self::runTool($root, '--apply');

            self::assertSame(
                0,
                $apply['exitCode'],
                $apply['stdout'] . $apply['stderr'],
            );

            $catalog = require $root
                . '/packages/core/kernel/resources/packaging/'
                . 'installation-catalog.php';

            self::assertIsArray($catalog);
            self::assertSame([], $catalog['modules'] ?? null);
        } finally {
            self::remove($root);
        }

        foreach (
            [
                [
                    'kind' => 'runtime',
                ],
                [
                    'kind' => 'runtime',
                    'moduleId' => 'Core.Kernel',
                ],
                [
                    'kind' => 'library',
                    'moduleId' => 'core.kernel',
                ],
            ] as $coretsia
        ) {
            $root = self::fixtureRoot(
                self::packageManifest([
                    'kind' => 'runtime',
                    'moduleId' => 'core.kernel',
                    'requires' => [],
                    'conflicts' => [],
                ]),
            );

            try {
                $apply = self::runTool($root, '--apply');

                self::assertSame(
                    0,
                    $apply['exitCode'],
                    $apply['stdout'] . $apply['stderr'],
                );

                self::writePackageManifest(
                    $root,
                    self::packageManifest($coretsia),
                );

                $check = self::runTool($root, '--check');

                self::assertSame(1, $check['exitCode']);
                self::assertStringContainsString(
                    'installation-catalog-invalid',
                    $check['stdout'] . $check['stderr'],
                );
            } finally {
                self::remove($root);
            }
        }
    }

    /** @param array<string, mixed> $coretsia */
    private static function packageManifest(
        array $coretsia,
    ): array {
        return [
            'name' => 'coretsia/core-kernel',
            'type' => 'library',
            'extra' => [
                'coretsia' => $coretsia,
            ],
        ];
    }

    /** @param array<string, mixed> $manifest */
    private static function fixtureRoot(
        array $manifest,
    ): string {
        $repoRoot = \dirname(__DIR__, 3);
        $autoload = $repoRoot . '/vendor/autoload.php';

        self::assertFileExists($autoload);

        $root = \sys_get_temp_dir()
            . '/coretsia-installation-catalog-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root . '/tools/build', 0777, true);
        \mkdir($root . '/tools/support', 0777, true);
        \mkdir($root . '/tools/release', 0777, true);
        \mkdir(
            $root . '/packages/core/kernel/resources/packaging',
            0777,
            true,
        );
        \mkdir($root . '/vendor', 0777, true);

        self::assertTrue(
            \copy(
                $repoRoot . '/tools/build/installation_catalog.php',
                $root . '/tools/build/installation_catalog.php',
            ),
        );
        self::assertTrue(
            \copy(
                $repoRoot . '/tools/support/bootstrap.php',
                $root . '/tools/support/bootstrap.php',
            ),
        );

        \file_put_contents($root . '/composer.json', "{}\n");
        \file_put_contents(
            $root . '/vendor/autoload.php',
            "<?php\n\nrequire "
            . \var_export($autoload, true)
            . ";\n",
        );
        \file_put_contents(
            $root . '/tools/release/release-line.json',
            \json_encode(
                [
                    'schemaVersion' =>
                        'coretsia.releaseLine.v1',
                    'currentMinor' => '0.7',
                    'devVersion' => '0.7.x-dev',
                    'publicConstraint' => '^0.7.0',
                ],
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        self::writePackageManifest($root, $manifest);

        return $root;
    }

    /** @param array<string, mixed> $manifest */
    private static function writePackageManifest(
        string $root,
        array $manifest,
    ): void {
        \file_put_contents(
            $root . '/packages/core/kernel/composer.json',
            \json_encode(
                $manifest,
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
    }

    /**
     * @return array{
     *     exitCode: int,
     *     stdout: string,
     *     stderr: string
     * }
     */
    private static function runTool(
        string $root,
        string $operation,
    ): array {
        $pipes = [];
        $process = \proc_open(
            [
                \PHP_BINARY,
                $root . '/tools/build/installation_catalog.php',
                $operation,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
            null,
            ['bypass_shell' => true],
        );

        self::assertIsResource($process);

        \fclose($pipes[0]);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        return [
            'exitCode' => \proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);

            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        @\rmdir($path);
    }
}
