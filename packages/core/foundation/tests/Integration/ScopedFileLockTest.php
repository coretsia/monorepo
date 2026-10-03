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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use PHPUnit\Framework\TestCase;

final class ScopedFileLockTest extends TestCase
{
    public function testSharedAndExclusiveOperationsReturnValuesAndKeepPersistentLockFile(): void
    {
        $root = self::tempRoot();

        try {
            $path = $root . '/locks/project.lock';
            $lock = new ScopedFileLock();

            self::assertSame(
                'shared',
                $lock->shared(
                    $path,
                    static fn (): string => 'shared',
                ),
            );
            self::assertSame(
                'exclusive',
                $lock->exclusive(
                    $path,
                    static fn (): string => 'exclusive',
                ),
            );
            self::assertFileExists($path);
            self::assertDirectoryExists(\dirname($path));
        } finally {
            self::remove($root);
        }
    }

    public function testSharedAndExclusiveOperationsBlockUntilConflictingLockIsReleased(): void
    {
        if (!\function_exists('proc_open')) {
            self::assertBlockingLockModeContract();

            return;
        }

        $root = self::tempRoot();

        try {
            $script = $root . '/holder.php';

            \file_put_contents(
                $script,
                '<?php $h=fopen($argv[1],"c+b"); flock($h,(int)$argv[2]); '
                . 'fwrite(STDOUT,"READY\\n"); fflush(STDOUT); usleep(500000); '
                . 'file_put_contents($argv[3],"released"); flock($h,LOCK_UN);',
            );

            foreach (
                [
                    ['shared', \LOCK_EX],
                    ['exclusive', \LOCK_SH],
                ] as [$method, $holderMode]
            ) {
                $path = $root . '/' . $method . '.lock';
                $released = $root . '/' . $method . '.released';
                $pipes = [];
                $process = \proc_open(
                    [
                        \PHP_BINARY,
                        $script,
                        $path,
                        (string) $holderMode,
                        $released,
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
                self::assertSame("READY\n", \fgets($pipes[1]));

                try {
                    self::assertSame(
                        'acquired',
                        new ScopedFileLock()->{$method}(
                            $path,
                            static fn (): string => 'acquired',
                        ),
                    );
                    self::assertFileExists($released);
                } finally {
                    \fclose($pipes[1]);
                    \fclose($pipes[2]);
                    \proc_close($process);
                }
            }
        } finally {
            self::remove($root);
        }
    }

    public function testExclusiveNonBlockingReportsBusyWhileAnotherProcessHoldsLock(): void
    {
        if (!\function_exists('proc_open')) {
            self::assertNonBlockingBusyContract();

            return;
        }

        $root = self::tempRoot();

        try {
            $path = $root . '/project.lock';
            $script = $root . '/holder.php';

            \file_put_contents(
                $script,
                '<?php $h=fopen($argv[1],"c+b"); flock($h,LOCK_SH); '
                . 'fwrite(STDOUT,"READY\\n"); fflush(STDOUT); usleep(800000);',
            );

            $pipes = [];
            $process = \proc_open(
                [\PHP_BINARY, $script, $path],
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
            self::assertSame("READY\n", \fgets($pipes[1]));

            try {
                new ScopedFileLock()->exclusiveNonBlocking(
                    $path,
                    static fn (): null => null,
                );
                self::fail('Expected lock contention.');
            } catch (ScopedFileLockException $exception) {
                self::assertSame(
                    ScopedFileLockException::REASON_BUSY,
                    $exception->reason(),
                );
                self::assertSame(
                    ScopedFileLockException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertStringNotContainsString(
                    $path,
                    $exception->getMessage(),
                );
            }

            \fclose($pipes[1]);
            \fclose($pipes[2]);
            \proc_close($process);
        } finally {
            self::remove($root);
        }
    }

    public function testExclusiveNonBlockingFlockFailureWithoutWouldBlockReportsFailed(): void
    {
        self::withFailureStream(
            static function (): void {
                ScopedFileLockFailureStreamWrapper::$failAcquire = true;

                try {
                    new ScopedFileLock()->exclusiveNonBlocking(
                        'coretsia-lock-test://root/x.lock',
                        static fn (): null => null,
                    );
                    self::fail('Expected non-blocking lock acquisition failure.');
                } catch (ScopedFileLockException $exception) {
                    self::assertSame(
                        ScopedFileLockException::REASON_FAILED,
                        $exception->reason(),
                    );
                }
            },
        );
    }

    public function testChmodFailureRemainsBestEffortAndOpenFailureHasSafeDiagnostics(): void
    {
        self::withFailureStream(
            static function (): void {
                ScopedFileLockFailureStreamWrapper::$failMetadata = true;

                self::assertSame(
                    'ok',
                    new ScopedFileLock()->exclusive(
                        'coretsia-lock-test://root/x.lock',
                        static fn (): string => 'ok',
                    ),
                );

                ScopedFileLockFailureStreamWrapper::$failMetadata = false;
                ScopedFileLockFailureStreamWrapper::$failOpen = true;
                $path = 'coretsia-lock-test://root/sensitive-diagnostic.lock';

                try {
                    new ScopedFileLock()->exclusive(
                        $path,
                        static fn (): null => null,
                    );
                    self::fail('Expected lock open failure.');
                } catch (ScopedFileLockException $exception) {
                    self::assertSame(
                        ScopedFileLockException::REASON_FAILED,
                        $exception->reason(),
                    );
                    self::assertSame(
                        'scoped-file-lock-failed',
                        $exception->getMessage(),
                    );
                    self::assertStringNotContainsString(
                        $path,
                        $exception->getMessage(),
                    );
                }
            },
        );
    }

    public function testCleanupFailureIsReportedUnlessOperationAlreadyFailed(): void
    {
        self::withFailureStream(
            static function (): void {
                ScopedFileLockFailureStreamWrapper::$failUnlock = true;

                try {
                    new ScopedFileLock()->exclusive(
                        'coretsia-lock-test://root/x.lock',
                        static fn (): string => 'ok',
                    );
                    self::fail('Expected cleanup failure.');
                } catch (ScopedFileLockException $exception) {
                    self::assertSame(
                        ScopedFileLockException::REASON_FAILED,
                        $exception->reason(),
                    );
                }

                $expected = new \RuntimeException('operation-failed');

                try {
                    new ScopedFileLock()->exclusive(
                        'coretsia-lock-test://root/x.lock',
                        static function () use ($expected): never {
                            throw $expected;
                        },
                    );
                    self::fail('Expected operation failure.');
                } catch (\RuntimeException $actual) {
                    self::assertSame($expected, $actual);
                }
            },
        );
    }

    public function testRejectsUnsafeLockDirectorySymlink(): void
    {
        $root = self::tempRoot();

        try {
            $target = $root . '/target';
            $linkDirectory = $root . '/link';

            \mkdir($target);

            if (
                !\function_exists('symlink')
                || !@\symlink($target, $linkDirectory)
            ) {
                $source = self::methodSource(ScopedFileLock::class, 'ensureDirectory');

                self::assertStringContainsString('@\is_link($directory)', $source);

                return;
            }

            $this->expectException(ScopedFileLockException::class);

            new ScopedFileLock()->exclusive(
                $linkDirectory . '/x.lock',
                static fn (): null => null,
            );
        } finally {
            self::remove($root);
        }
    }

    public function testRejectsUnsafeLockFileSymlink(): void
    {
        $root = self::tempRoot();

        try {
            $target = $root . '/target.lock';
            $link = $root . '/link.lock';

            \file_put_contents($target, '');

            if (
                !\function_exists('symlink')
                || !@\symlink($target, $link)
            ) {
                $source = self::methodSource(ScopedFileLock::class, 'withLock');

                self::assertStringContainsString('@\is_link($lockPath)', $source);

                return;
            }

            $this->expectException(ScopedFileLockException::class);

            new ScopedFileLock()->exclusive(
                $link,
                static fn (): null => null,
            );
        } finally {
            self::remove($root);
        }
    }

    public function testOperationExceptionRemainsPrimary(): void
    {
        $root = self::tempRoot();

        try {
            $expected = new \RuntimeException('operation-failed');

            try {
                new ScopedFileLock()->exclusive(
                    $root . '/x.lock',
                    static function () use ($expected): never {
                        throw $expected;
                    },
                );
                self::fail('Expected operation exception.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }
        } finally {
            self::remove($root);
        }
    }

    public function testExceptionRejectsUnknownReasonAndExposesStableSafeContract(): void
    {
        $exception = new ScopedFileLockException();

        self::assertSame(
            'CORETSIA_SCOPED_FILE_LOCK_FAILED',
            $exception->errorCode(),
        );
        self::assertSame(
            'scoped-file-lock-failed',
            $exception->reason(),
        );
        self::assertSame(
            'scoped-file-lock-failed',
            $exception->getMessage(),
        );

        foreach (['', 'unknown'] as $reason) {
            try {
                new ScopedFileLockException($reason);
                self::fail('Expected invalid lock reason.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private static function assertBlockingLockModeContract(): void
    {
        $shared = self::methodSource(
            ScopedFileLock::class,
            'shared',
        );
        $exclusive = self::methodSource(
            ScopedFileLock::class,
            'exclusive',
        );
        $withLock = self::methodSource(
            ScopedFileLock::class,
            'withLock',
        );

        self::assertStringContainsString('mode: \LOCK_SH', $shared);
        self::assertStringContainsString(
            'nonBlocking: false',
            $shared,
        );
        self::assertStringContainsString(
            'mode: \LOCK_EX',
            $exclusive,
        );
        self::assertStringContainsString(
            'nonBlocking: false',
            $exclusive,
        );
        self::assertStringContainsString(
            ': @\flock($handle, $mode);',
            $withLock,
        );
    }

    private static function assertNonBlockingBusyContract(): void
    {
        $exclusive = self::methodSource(
            ScopedFileLock::class,
            'exclusiveNonBlocking',
        );
        $withLock = self::methodSource(
            ScopedFileLock::class,
            'withLock',
        );

        self::assertStringContainsString(
            'mode: \LOCK_EX',
            $exclusive,
        );
        self::assertStringContainsString(
            'nonBlocking: true',
            $exclusive,
        );
        self::assertStringContainsString(
            '$mode | \LOCK_NB',
            $withLock,
        );
        self::assertStringContainsString(
            '$wouldBlock === 1',
            $withLock,
        );
        self::assertStringContainsString(
            'ScopedFileLockException::REASON_BUSY',
            $withLock,
        );
    }

    private static function methodSource(
        string $class,
        string $method,
    ): string {
        $reflection = new \ReflectionMethod($class, $method);
        $path = $reflection->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        return \implode(
            '',
            \array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ),
        );
    }

    private static function tempRoot(): string
    {
        $path = \sys_get_temp_dir()
            . '/coretsia-lock-test-'
            . \bin2hex(\random_bytes(8));

        \mkdir($path, 0777, true);

        return $path;
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

    private static function withFailureStream(\Closure $operation): void
    {
        self::assertTrue(
            \stream_wrapper_register(
                'coretsia-lock-test',
                ScopedFileLockFailureStreamWrapper::class,
            ),
        );

        try {
            ScopedFileLockFailureStreamWrapper::reset();
            $operation();
        } finally {
            ScopedFileLockFailureStreamWrapper::reset();
            self::assertTrue(
                \stream_wrapper_unregister('coretsia-lock-test'),
            );
        }
    }
}

final class ScopedFileLockFailureStreamWrapper
{
    public mixed $context = null;

    public static bool $failAcquire = false;
    public static bool $failMetadata = false;
    public static bool $failOpen = false;
    public static bool $failUnlock = false;

    public static function reset(): void
    {
        self::$failAcquire = false;
        self::$failMetadata = false;
        self::$failOpen = false;
        self::$failUnlock = false;
    }

    /** @return array{mode: int} */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'mode' => \str_ends_with($path, '.lock')
                ? 0100644
                : 0040775,
        ];
    }

    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath,
    ): bool {
        return !self::$failOpen;
    }

    public function stream_lock(int $operation): bool
    {
        if ($operation === \LOCK_UN) {
            return !self::$failUnlock;
        }

        return !self::$failAcquire;
    }

    public function stream_close(): void
    {
    }

    public function stream_metadata(
        string $path,
        int $option,
        mixed $value,
    ): bool {
        return !self::$failMetadata;
    }
}
