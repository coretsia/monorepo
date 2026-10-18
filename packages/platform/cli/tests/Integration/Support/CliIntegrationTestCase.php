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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use PHPUnit\Framework\TestCase;

/**
 * Isolated real-process fixtures for packaged CLI and Composer-proxy integration.
 * The proxy fixture models Composer's documented proxy variables; production
 * bootstrap and command execution always use the actual installed classes.
 */
abstract class CliIntegrationTestCase extends TestCase
{
    protected static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 6);
    }

    protected static function packageRoot(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected static function temporaryRoot(string $name): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-cli-' . $name . '-' . \bin2hex(\random_bytes(8));
        if (!\mkdir($root, 0700, true) && !\is_dir($root)) {
            throw new \RuntimeException('cli-test-root-create-failed');
        }

        return $root;
    }

    protected static function write(string $path, string $source): void
    {
        $directory = \dirname($path);
        if (!\is_dir($directory) && !\mkdir($directory, 0700, true)) {
            throw new \RuntimeException('cli-test-directory-create-failed');
        }
        if (\file_put_contents($path, $source) !== \strlen($source)) {
            throw new \RuntimeException('cli-test-file-write-failed');
        }
    }

    protected static function removeTree(string $root): void
    {
        if (!\file_exists($root) && !\is_link($root)) {
            return;
        }
        if (!\is_dir($root) || \is_link($root)) {
            @\unlink($root);
            return;
        }
        foreach (new \FilesystemIterator($root) as $item) {
            self::removeTree($item->getPathname());
        }
        @\rmdir($root);
    }

    /**
     * @param list<string> $arguments
     * @param array<string,string> $environment
     * @return array{exit:int,stdout:string,stderr:string}
     */
    protected static function execute(
        string $script,
        array $arguments = [],
        ?string $cwd = null,
        array $environment = [],
    ): array {
        $env = \getenv();
        $env = \is_array($env) ? $env : [];
        foreach ($environment as $key => $value) {
            $env[$key] = $value;
        }
        $process = \proc_open(
            [\PHP_BINARY, '-d', 'display_errors=1', $script, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd ?? \dirname($script),
            $env,
        );
        if (!\is_resource($process)) {
            throw new \RuntimeException('cli-test-process-start-failed');
        }
        \fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exit = \proc_close($process);
        if (!\is_string($stdout) || !\is_string($stderr)) {
            throw new \RuntimeException('cli-test-process-capture-failed');
        }

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    protected static function assertSafeFailure(array $result, string $diagnostic): void
    {
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame($diagnostic . "\n", $result['stderr']);
        self::assertStringNotContainsString('Warning', $result['stderr']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
        self::assertStringNotContainsString('Stack trace', $result['stderr']);
    }

    /** Make a real temporary installed-layout consumer, without invoking Composer or the network. */
    protected static function consumer(): string
    {
        $autoload = self::repositoryRoot() . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        self::assertTrue(\is_readable($autoload), 'Composer autoload must be readable.');
        $root = self::temporaryRoot('consumer');
        self::write(
            $root . '/coretsia',
            (string) \file_get_contents(self::repositoryRoot() . '/packages/applications/skeleton/coretsia'),
        );
        self::write(
            $root . '/composer.json',
            (string) \file_get_contents(self::repositoryRoot() . '/packages/applications/skeleton/composer.json'),
        );
        self::write(
            $root . '/vendor/autoload.php',
            '<?php return require ' . \var_export($autoload, true) . ';' . "\n",
        );

        $package = self::packageRoot();
        $installed = $root . '/vendor/coretsia/platform-cli';
        self::copyTree($package . '/src', $installed . '/src');
        self::copyTree($package . '/bin', $installed . '/bin');
        self::copyTree($package . '/config', $installed . '/config');
        self::write($installed . '/composer.json', (string) \file_get_contents($package . '/composer.json'));
        self::write(
            $root . '/vendor/bin/coretsia',
            <<<'PHP'
#!/usr/bin/env php
<?php
$_composer_autoload_path = __DIR__ . '/../autoload.php';
$_composer_bin_dir = __DIR__;
return include __DIR__ . '/../coretsia/platform-cli/bin/coretsia';
PHP,
        );

        return $root;
    }

    private static function copyTree(string $from, string $to): void
    {
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            ) as $entry
        ) {
            $relative = \substr($entry->getPathname(), \strlen($from) + 1);
            if ($entry->isDir()) {
                if (!\is_dir($to . '/' . $relative)) {
                    \mkdir($to . '/' . $relative, 0700, true);
                }
            } else {
                self::write($to . '/' . $relative, (string) \file_get_contents($entry->getPathname()));
            }
        }
    }

    /** @return array<string,mixed> */
    protected static function jsonRecord(string $stdout): array
    {
        $decoded = \json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('coretsia.cli-output@1', $decoded['schema']);
        return $decoded;
    }

    protected static function excludeCli(string $root): void
    {
        self::write(
            $root . '/config/app.php',
            <<<'PHP'
<?php
return ['moduleOverrides' => ['console' => ['include' => [], 'exclude' => ['platform.cli']]]];
PHP,
        );
    }

    protected static function assertNoArtifacts(string $root): void
    {
        self::assertFalse(
            \file_exists($root . '/var/cache/console')
            || \is_link($root . '/var/cache/console'),
        );
    }
}
