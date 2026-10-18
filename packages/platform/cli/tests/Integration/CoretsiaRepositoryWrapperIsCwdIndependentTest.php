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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Platform\Cli\Bootstrap\CliEntrypointPathsResolver;
use Coretsia\Platform\Cli\Exception\CliBootstrapException;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CoretsiaRepositoryWrapperIsCwdIndependentTest extends CliIntegrationTestCase
{
    public function testRepositoryWrapperProducesIdenticalResultsOutsideRepositoryCwd(): void
    {
        $repositoryRoot = self::canonicalRepositoryRoot();
        $wrapper = $repositoryRoot . '/coretsia';
        $skeletonSource = $repositoryRoot . '/packages/applications/skeleton';
        self::assertFileExists($wrapper);
        self::assertDirectoryExists($skeletonSource);
        self::assertFileExists($repositoryRoot . '/vendor/autoload.php');

        $outside = self::temporaryRoot('wrapper-outside-cwd');
        try {
            self::write(
                $outside . '/vendor/autoload.php',
                '<?php throw new \\RuntimeException("WRONG_CWD_AUTOLOAD");' . "\n",
            );
            self::write(
                $outside . '/config/app.php',
                '<?php throw new \\RuntimeException("WRONG_CWD_CONFIG");' . "\n",
            );

            foreach (
                [
                    ['list', '--format=json', '--color=never'],
                    ['help', 'config:compile', '--format=json', '--color=never'],
                ] as $arguments
            ) {
                $expected = self::execute($wrapper, $arguments, $repositoryRoot);
                self::assertSame(0, $expected['exit'], $expected['stderr']);
                self::assertSame('', $expected['stderr']);
                self::assertSame($arguments[0], self::jsonRecord($expected['stdout'])['meta']['command']);

                foreach ([$skeletonSource, $outside] as $cwd) {
                    $actual = self::execute($wrapper, $arguments, $cwd);
                    self::assertSame($expected['exit'], $actual['exit'], $cwd . ': ' . $actual['stderr']);
                    self::assertSame(
                        self::normalizeOutput($expected['stdout']),
                        self::normalizeOutput($actual['stdout']),
                        $cwd,
                    );
                    self::assertSame(
                        self::normalizeOutput($expected['stderr']),
                        self::normalizeOutput($actual['stderr']),
                        $cwd,
                    );
                    self::assertSame(
                        self::jsonRecord($expected['stdout']),
                        self::jsonRecord($actual['stdout']),
                    );
                }
            }
        } finally {
            self::removeTree($outside);
        }
    }

    public function testWrapperAndPackagedEntrypointUseOnlyRepositoryRelativeRootAndAutoload(): void
    {
        $repositoryRoot = self::canonicalRepositoryRoot();
        $wrapper = $repositoryRoot . '/coretsia';
        $binary = $repositoryRoot . '/packages/platform/cli/bin/coretsia';
        $autoload = $repositoryRoot . '/vendor/autoload.php';

        $wrapperSource = (string) \file_get_contents($wrapper);
        self::assertStringContainsString('$repositoryRoot = __DIR__;', $wrapperSource);
        self::assertStringContainsString(
            '$GLOBALS[\'coretsia_application_root\'] = $repositoryRoot;',
            $wrapperSource,
        );
        self::assertStringContainsString(
            "require \$repositoryRoot . '/packages/platform/cli/bin/coretsia'",
            $wrapperSource,
        );

        $binarySource = (string) \file_get_contents($binary);
        self::assertStringContainsString(
            "\$autoload = \$wrapperRoot . '/vendor/autoload.php';",
            $binarySource,
        );
        self::assertStringContainsString('require_once $autoload;', $binarySource);

        $paths = new CliEntrypointPathsResolver()->resolve(
            $binary,
            $autoload,
            null,
            $repositoryRoot,
        );
        self::assertSame($repositoryRoot, $paths->applicationRoot());
        self::assertSame(self::canonicalPath($autoload), $paths->autoloadPath());

        try {
            new CliEntrypointPathsResolver()->resolve(
                $binary,
                $autoload,
                null,
                $repositoryRoot . '/packages/applications/skeleton',
            );
            self::fail('The Skeleton source directory must not replace the monorepo application root.');
        } catch (CliBootstrapException $exception) {
            self::assertSame(CliBootstrapException::REASON_LAUNCHER_INVALID, $exception->reason());
        }

        foreach (
            [
                $wrapperSource,
                $binarySource,
                (string) \file_get_contents(self::packageRoot() . '/src/Bootstrap/CliEntrypointPathsResolver.php'),
            ] as $source
        ) {
            foreach (
                [
                    'getcwd(',
                    'scandir(',
                    'glob(',
                    'RecursiveDirectoryIterator',
                    'packages/applications/skeleton/coretsia',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    private static function canonicalRepositoryRoot(): string
    {
        return self::canonicalPath(self::repositoryRoot());
    }

    private static function canonicalPath(string $path): string
    {
        $resolved = \realpath($path);
        self::assertIsString($resolved);

        return \str_replace('\\', '/', $resolved);
    }

    private static function normalizeOutput(string $output): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $output);
    }
}
