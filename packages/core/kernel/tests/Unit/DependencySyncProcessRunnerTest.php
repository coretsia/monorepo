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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use PHPUnit\Framework\TestCase;

final class DependencySyncProcessRunnerTest extends TestCase
{
    public function testPhpScriptUsesExactPhpBinaryAndBoundedStdinWithoutShell(): void
    {
        $root = self::root();

        try {
            $script = $root . '/echo.php';
            \file_put_contents(
                $script,
                '<?php echo json_encode(['
                . 'PHP_BINARY,'
                . 'array_slice($argv,1),'
                . 'substr(stream_get_contents(STDIN),0,32)'
                . ']);',
            );

            $result = new DependencySyncProcessRunner('composer')
                ->runPhpScript(
                    $root,
                    $script,
                    ['--flag=value'],
                    5,
                    'payload',
                );

            self::assertSame(0, $result['exitCode']);

            $decoded = \json_decode(
                $result['stdout'],
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertSame(\PHP_BINARY, $decoded[0]);
            self::assertSame(['--flag=value'], $decoded[1]);
            self::assertSame('payload', $decoded[2]);
        } finally {
            self::remove($root);
        }
    }

    public function testRejectsShellExecutableTokens(): void
    {
        foreach (
            [
                'composer --version',
                'composer;touch',
                'https://example.test/composer.phar',
            ] as $invalid
        ) {
            try {
                new DependencySyncProcessRunner($invalid);
                self::fail('Expected executable token rejection.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                    $exception->errorCode(),
                );
            }
        }
    }

    public function testUnavailableExplicitComposerIsAcceptedByConstructionButFailsAtRun(): void
    {
        $root = self::root();

        try {
            $runner = new DependencySyncProcessRunner('/definitely/missing/composer');

            try {
                $runner->runComposer(
                    $root,
                    ['--version'],
                    1,
                );
                self::fail('Expected unavailable Composer failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                    $exception->errorCode(),
                );
            }

            $script = $root . '/still-usable.php';

            \file_put_contents($script, '<?php echo stream_get_contents(STDIN);');

            $result = $runner->runPhpScript(
                $root,
                $script,
                [],
                5,
                'verification-input',
            );

            self::assertSame(0, $result['exitCode']);
            self::assertSame('verification-input', $result['stdout']);
        } finally {
            self::remove($root);
        }
    }

    public function testTimeoutAndCapturedOutputAreBounded(): void
    {
        $root = self::root();

        try {
            $script = $root . '/slow.php';
            \file_put_contents(
                $script,
                '<?php '
                . 'fwrite(STDOUT,str_repeat("x",100000)); '
                . 'fwrite(STDERR,str_repeat("y",100000)); '
                . 'usleep(1500000);',
            );

            $result = new DependencySyncProcessRunner()->runPhpScript(
                $root,
                $script,
                [],
                1,
            );

            self::assertTrue($result['timedOut']);
            self::assertLessThanOrEqual(
                65_536,
                \strlen($result['stdout']),
            );
            self::assertLessThanOrEqual(
                65_536,
                \strlen($result['stderr']),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testComposerArgumentsAndCwdInjectionStringsRemainLiteralArgv(): void
    {
        $root = self::root();
        $cwd = $root . '/cwd;literal';
        $sentinel = $root . '/injected';
        $script = $root . '/composer.php';

        \mkdir($cwd, 0777, true);
        \file_put_contents(
            $script,
            '<?php echo json_encode(['
            . 'getcwd(),'
            . 'array_slice($argv,1)'
            . ']);',
        );

        try {
            $argument = ';touch ' . $sentinel;

            $result = new DependencySyncProcessRunner($script)->runComposer(
                $cwd,
                [$argument],
                5,
            );

            self::assertSame(0, $result['exitCode']);

            $decoded = \json_decode(
                $result['stdout'],
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
            $resolvedCwd = \realpath($cwd);

            self::assertIsString($resolvedCwd);
            self::assertSame($resolvedCwd, $decoded[0]);
            self::assertSame([$argument], $decoded[1]);
            self::assertFileDoesNotExist($sentinel);
        } finally {
            self::remove($root);
        }
    }

    public function testBothExecutionModesUseArrayArgvAndBypassShell(): void
    {
        $path = new \ReflectionClass(DependencySyncProcessRunner::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('@\proc_open(', $source);
        self::assertStringContainsString("'bypass_shell' => true", $source);
        self::assertStringContainsString('\substr($stdin, $stdinOffset, 8192)', $source);
        self::assertStringNotContainsString('shell_exec(', $source);
        self::assertStringNotContainsString('system(', $source);
        self::assertStringNotContainsString('passthru(', $source);
    }

    public function testDirectComposerTokenAndExplicitPhpOrPharExecutablesAreAccepted(): void
    {
        new DependencySyncProcessRunner('composer');
        self::addToAssertionCount(1);

        $root = self::root();

        try {
            foreach (['php', 'phar'] as $extension) {
                $script = $root . '/composer.' . $extension;

                \file_put_contents(
                    $script,
                    '<?php echo json_encode(['
                    . 'PHP_BINARY,'
                    . 'array_slice($argv,1),'
                    . 'stream_get_contents(STDIN)'
                    . ']);',
                );

                $result = new DependencySyncProcessRunner($script)
                    ->runComposer(
                        $root,
                        ['--flag=value'],
                        5,
                        'payload',
                    );

                self::assertSame(0, $result['exitCode']);

                $decoded = \json_decode(
                    $result['stdout'],
                    true,
                    512,
                    \JSON_THROW_ON_ERROR,
                );

                self::assertSame(\PHP_BINARY, $decoded[0]);
                self::assertSame(['--flag=value'], $decoded[1]);
                self::assertSame('payload', $decoded[2]);
            }
        } finally {
            self::remove($root);
        }
    }

    public function testComposerTimeoutAndCapturedOutputAreBounded(): void
    {
        $root = self::root();

        try {
            $script = $root . '/composer.php';

            \file_put_contents(
                $script,
                '<?php '
                . 'fwrite(STDOUT,str_repeat("x",100000)); '
                . 'fwrite(STDERR,str_repeat("y",100000)); '
                . 'usleep(1500000);',
            );

            $result = new DependencySyncProcessRunner($script)
                ->runComposer(
                    $root,
                    ['--version'],
                    1,
                );

            self::assertTrue($result['timedOut']);
            self::assertLessThanOrEqual(
                65_536,
                \strlen($result['stdout']),
            );
            self::assertLessThanOrEqual(
                65_536,
                \strlen($result['stderr']),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testExplicitAbsoluteExecutableExecutesDirectly(): void
    {
        $root = self::root();

        try {
            $runner = new DependencySyncProcessRunner(\PHP_BINARY);
            $result = $runner->runComposer(
                $root,
                [
                    '-r',
                    'echo json_encode(['
                    . 'PHP_BINARY,'
                    . 'stream_get_contents(STDIN)'
                    . ']);',
                ],
                5,
                'payload',
            );

            self::assertSame(0, $result['exitCode']);

            $decoded = \json_decode(
                $result['stdout'],
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertSame(\PHP_BINARY, $decoded[0]);
            self::assertSame('payload', $decoded[1]);
        } finally {
            self::remove($root);
        }
    }

    public function testWindowsComposerResolutionSelectsOnlySupportedLaunchableCandidates(): void
    {
        $root = self::root();
        $first = $root . '/first launcher';
        $second = $root . '/composer tools';
        $phpLauncher = $root . '/php launcher';
        $standalone = $root . '/standalone composer';
        $override = $root . '/explicit composer';

        $previousEnvironment = [
            'PATH' => \getenv('PATH'),
            'PATHEXT' => \getenv('PATHEXT'),
            'COMPOSER_BINARY' => \getenv('COMPOSER_BINARY'),
        ];

        try {
            foreach ([$first, $second, $phpLauncher, $standalone, $override] as $directory) {
                \mkdir($directory, 0777, true);
            }

            $script = '<?php echo json_encode(['
                . 'PHP_BINARY,'
                . 'array_slice($argv,1),'
                . 'stream_get_contents(STDIN)'
                . ']);';

            \file_put_contents($first . '/composer.bat', '@exit /b 99');
            \file_put_contents($first . '/composer.js', 'unsupported');
            \file_put_contents($second . '/composer.bat', '@exit /b 99');
            \file_put_contents($second . '/composer.cmd', '@exit /b 99');
            \file_put_contents($second . '/composer.phar', $script);
            \file_put_contents($second . '/composer.php', $script);
            \file_put_contents($phpLauncher . '/composer.cmd', '@exit /b 99');
            \file_put_contents($phpLauncher . '/composer.php', $script);
            \file_put_contents($standalone . '/composer.phar', $script);
            \file_put_contents($override . '/composer.php', $script);

            $path = '"' . $first . '"' . \PATH_SEPARATOR . '"' . $second . '"';

            $resolver = new \ReflectionMethod(
                DependencySyncProcessRunner::class,
                'resolveWindowsExecutableToken',
            );

            $cases = [
                [
                    false,
                    $path,
                    '.JS;.BAT;.CMD',
                    $second . '/composer.phar',
                ],
                [
                    $first . '/composer.bat',
                    $path,
                    '.BAT',
                    $second . '/composer.phar',
                ],
                [
                    false,
                    $standalone,
                    '.EXE',
                    $standalone . '/composer.phar',
                ],
                [
                    false,
                    $standalone,
                    false,
                    $standalone . '/composer.phar',
                ],
                [
                    $override . '/composer.php',
                    $path,
                    '.BAT',
                    $override . '/composer.php',
                ],
                [
                    false,
                    $path,
                    '.PHP;.BAT',
                    $second . '/composer.php',
                ],
                [
                    false,
                    $path,
                    '.BAT;.PHP',
                    $second . '/composer.phar',
                ],
                [
                    $second . '/composer.bat',
                    $standalone,
                    '.PHP',
                    $second . '/composer.phar',
                ],
                [
                    $phpLauncher . '/composer.cmd',
                    $standalone,
                    '.BAT',
                    $phpLauncher . '/composer.php',
                ],
                [
                    $first . '/composer.js',
                    $path,
                    '.JS;.BAT',
                    $second . '/composer.phar',
                ],
                [
                    false,
                    $standalone . \PATH_SEPARATOR . $second,
                    '.BAT',
                    $standalone . '/composer.phar',
                ],
                [
                    false,
                    $override,
                    '.EXE',
                    $override . '/composer.php',
                ],
            ];

            foreach ($cases as [$composerBinary, $searchPath, $pathExtensions, $expected]) {
                foreach (
                    [
                        'COMPOSER_BINARY' => $composerBinary,
                        'PATH' => $searchPath,
                        'PATHEXT' => $pathExtensions,
                    ] as $name => $value
                ) {
                    \putenv(
                        $value === false
                            ? $name
                            : $name . '=' . $value,
                    );
                }

                $resolved = $resolver->invoke(null, 'composer');

                self::assertSame(\realpath($expected), $resolved);
                self::assertIsString($resolved);

                $executables = [$resolved];

                if (\PHP_OS_FAMILY === 'Windows') {
                    $executables[] = 'composer';
                }

                foreach ($executables as $executable) {
                    $result = new DependencySyncProcessRunner($executable)->runComposer(
                        $root,
                        ['--flag=value'],
                        5,
                        'payload',
                    );

                    self::assertSame(0, $result['exitCode']);
                    self::assertFalse($result['timedOut']);

                    $decoded = \json_decode(
                        $result['stdout'],
                        true,
                        512,
                        \JSON_THROW_ON_ERROR,
                    );

                    self::assertSame(\PHP_BINARY, $decoded[0]);
                    self::assertSame(['--flag=value'], $decoded[1]);
                    self::assertSame('payload', $decoded[2]);
                }
            }

            if (\PHP_OS_FAMILY === 'Windows') {
                foreach (
                    [
                        $second . '/composer.bat',
                        $second . '/composer.cmd',
                        $phpLauncher . '/composer.cmd',
                    ] as $launcher
                ) {
                    $result = new DependencySyncProcessRunner($launcher)
                        ->runComposer(
                            $root,
                            ['--version'],
                            5,
                            'payload',
                        );

                    self::assertSame(0, $result['exitCode']);
                    self::assertFalse($result['timedOut']);

                    $decoded = \json_decode(
                        $result['stdout'],
                        true,
                        512,
                        \JSON_THROW_ON_ERROR,
                    );

                    self::assertSame(\PHP_BINARY, $decoded[0]);
                    self::assertSame(['--version'], $decoded[1]);
                    self::assertSame('payload', $decoded[2]);
                }
            }

            \putenv('COMPOSER_BINARY');
            \putenv('PATH=' . $first);
            \putenv('PATHEXT=.JS;.BAT');

            self::assertNull($resolver->invoke(null, 'composer'));

            $runner = new DependencySyncProcessRunner();

            $argvResolver = new \ReflectionMethod(
                DependencySyncProcessRunner::class,
                'composerArgv',
            );

            if (\PHP_OS_FAMILY === 'Windows') {
                try {
                    $argvResolver->invoke($runner);

                    self::fail('Expected unavailable Composer resolution failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                        $exception->errorCode(),
                    );
                }

                try {
                    $runner->runComposer($root, ['--version'], 5);

                    self::fail('Expected unavailable Composer failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                        $exception->errorCode(),
                    );
                }
            } else {
                self::assertSame(
                    ['composer'],
                    $argvResolver->invoke($runner),
                );
            }
        } finally {
            foreach ($previousEnvironment as $name => $value) {
                \putenv($value === false ? $name : $name . '=' . $value);
            }

            self::remove($root);
        }
    }

    private static function root(): string
    {
        $path = \sys_get_temp_dir() . '/coretsia-process-runner-' . \bin2hex(\random_bytes(8));

        \mkdir($path, 0777, true);

        return $path;
    }

    private static function remove(string $path): void
    {
        if (\is_file($path) || \is_link($path)) {
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
