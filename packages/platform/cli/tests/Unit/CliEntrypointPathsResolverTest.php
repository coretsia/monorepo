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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Platform\Cli\Bootstrap\CliEntrypointPathsResolver;
use Coretsia\Platform\Cli\Exception\CliBootstrapException;
use PHPUnit\Framework\TestCase;

final class CliEntrypointPathsResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \sys_get_temp_dir() . '/coretsia-cli-paths-' . \bin2hex(\random_bytes(8));
        foreach (['vendor/bin', 'vendor/coretsia/platform-cli/bin', 'packages/platform/cli/bin'] as $directory) {
            self::assertTrue(\mkdir($this->root . '/' . $directory, 0700, true));
        }
        foreach (
            [
                'vendor/autoload.php',
                'vendor/bin/coretsia',
                'vendor/coretsia/platform-cli/bin/coretsia',
                'packages/platform/cli/bin/coretsia',
                'coretsia',
            ] as $file
        ) {
            self::assertNotFalse(\file_put_contents($this->root . '/' . $file, '<?php return true;'));
        }
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                \rmdir($entry->getPathname());
            } else {
                \unlink($entry->getPathname());
            }
        }
        \rmdir($this->root);
    }

    public function testComposerProxyAndInstalledBinaryResolveConsumerApplicationRoot(): void
    {
        foreach (['vendor/bin/coretsia', 'vendor/coretsia/platform-cli/bin/coretsia'] as $launcher) {
            $paths = new CliEntrypointPathsResolver()->resolve(
                $this->root . '/' . $launcher,
                $this->root . '/vendor/./autoload.php',
                $this->root . '/vendor/bin',
                null,
            );
            self::assertSame(self::normalizedRealpath($this->root), $paths->applicationRoot());
            self::assertSame(self::normalizedRealpath($this->root . '/vendor/autoload.php'), $paths->autoloadPath());
        }
    }

    public function testCanonicalExplicitRootWorksForMonorepoWrapperAndPackagedBinary(): void
    {
        foreach (['coretsia', 'packages/platform/cli/bin/coretsia'] as $launcher) {
            $paths = new CliEntrypointPathsResolver()->resolve(
                $this->root . '/' . $launcher,
                $this->root . '/vendor/autoload.php',
                null,
                $this->root,
            );
            self::assertSame(self::normalizedRealpath($this->root), $paths->applicationRoot());
        }
    }

    public function testCurrentWorkingDirectoryCannotChangeRootSelection(): void
    {
        $before = \getcwd();
        self::assertIsString($before);
        try {
            self::assertTrue(\chdir(\sys_get_temp_dir()));
            $paths = new CliEntrypointPathsResolver()->resolve(
                $this->root . '/vendor/bin/coretsia',
                $this->root . '/vendor/autoload.php',
                $this->root . '/vendor/bin',
                null,
            );
            self::assertSame(self::normalizedRealpath($this->root), $paths->applicationRoot());
        } finally {
            \chdir($before);
        }
    }

    public function testConflictingRootSourcesFailWithSafeDiagnostics(): void
    {
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/vendor/autoload.php',
            $this->root . '/vendor/bin',
            $this->root,
            CliBootstrapException::REASON_APPLICATION_ROOT_CONFLICT,
        );
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/vendor/bin/coretsia',
            null,
            $this->root,
            CliBootstrapException::REASON_APPLICATION_ROOT_CONFLICT,
        );
    }

    public function testUnsupportedAndUnresolvedLayoutsFailWithoutAncestorDiscovery(): void
    {
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/vendor/autoload.php',
            null,
            null,
            CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED,
        );
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/vendor/autoload.php',
            $this->root . '/vendor/bin',
            null,
            CliBootstrapException::REASON_LAUNCHER_INVALID,
        );
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/vendor/autoload.php',
            null,
            $this->root . '/vendor',
            CliBootstrapException::REASON_LAUNCHER_INVALID,
        );
        $this->assertInvalid(
            $this->root . '/missing/coretsia',
            $this->root . '/vendor/autoload.php',
            null,
            $this->root,
            CliBootstrapException::REASON_LAUNCHER_INVALID,
        );
        $this->assertInvalid(
            $this->root . '/coretsia',
            $this->root . '/missing/autoload.php',
            null,
            $this->root,
            CliBootstrapException::REASON_AUTOLOAD_INVALID,
        );
        $this->assertInvalid(
            'relative/coretsia',
            $this->root . '/vendor/autoload.php',
            null,
            $this->root,
            CliBootstrapException::REASON_LAUNCHER_INVALID,
        );
    }

    public function testResolverContainsNoDirectoryCrawlerOrApplicationConfigLoader(): void
    {
        $method = new \ReflectionMethod(CliEntrypointPathsResolver::class, 'resolve');
        $path = $method->getFileName();
        self::assertIsString($path);
        $source = \file_get_contents($path);
        self::assertIsString($source);
        foreach (
            [
                'getcwd(',
                'scandir(',
                'glob(',
                'RecursiveDirectoryIterator',
                'ConfigRepositoryInterface',
                'config/cli.php',
                'config/rules.php',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private static function normalizedRealpath(string $path): string
    {
        $realpath = \realpath($path);
        self::assertIsString($realpath);

        return \str_replace('\\', '/', $realpath);
    }

    private function assertInvalid(
        string $launcher,
        string $autoload,
        ?string $bin,
        ?string $explicit,
        string $reason,
    ): void {
        try {
            new CliEntrypointPathsResolver()->resolve($launcher, $autoload, $bin, $explicit);
        } catch (CliBootstrapException $exception) {
            self::assertSame(CliBootstrapException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertSame(CliBootstrapException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            foreach ([$this->root, $launcher, $autoload, 'vendor/bin', 'autoload.php'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $exception->getMessage());
            }
            self::assertNull($exception->getPrevious());
            return;
        }
        self::fail('Expected a canonical layout failure.');
    }
}
