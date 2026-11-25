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

use Coretsia\Foundation\Filesystem\AtomicFileWriter;
use Coretsia\Foundation\Filesystem\Exception\AtomicFileWriteException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AtomicFileWriterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = \sys_get_temp_dir() . '/coretsia-atomic-writer-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        parent::tearDown();
    }

    public function testCreatesAndReplacesCompleteExactBytesWithoutTemporaryFiles(): void
    {
        $path = $this->root . '/nested/cache/artifact.php';
        $writer = new AtomicFileWriter();
        $original = \str_repeat("old\r\n\0", 4096);
        $writer->write($path, $original);
        self::assertSame($original, \file_get_contents($path));
        self::assertSame(['artifact.php'], self::entries(\dirname($path)));

        if (\PHP_OS_FAMILY !== 'Windows') {
            \clearstatcache(true, $path);
            self::assertSame(0644, \fileperms($path) & 0777);
        }

        // A hard link to the old inode distinguishes replacement from in-place truncation.
        $oldLink = $this->root . '/previous';
        $linked = \PHP_OS_FAMILY !== 'Windows' && @\link($path, $oldLink);
        $replacement = "new\r\n\0";
        $writer->write($path, $replacement);
        self::assertSame($replacement, \file_get_contents($path));
        if ($linked) {
            self::assertSame($original, \file_get_contents($oldLink));
        }
        self::assertSame(['artifact.php'], self::entries(\dirname($path)));
        $writer->write($path, '');
        self::assertSame('', \file_get_contents($path));
        self::assertSame(['artifact.php'], self::entries(\dirname($path)));
    }

    public function testFailedRenamePreservesExistingTargetAndCleansTemporaryFiles(): void
    {
        $target = $this->root . '/artifact.php';
        self::assertTrue(\mkdir($target));
        self::assertSame(8, \file_put_contents($target . '/marker', 'original'));

        self::assertWriteFails($target, AtomicFileWriteException::REASON_TEMP_FILE_RENAME_FAILED);
        self::assertDirectoryExists($target);
        self::assertSame('original', \file_get_contents($target . '/marker'));
        self::assertSame(['artifact.php'], self::entries($this->root));
        self::assertSame(['marker'], self::entries($target));
    }

    public function testFailedPublicationPreservesExistingRegularFile(): void
    {
        $directory = $this->root . '/locked';
        self::assertTrue(\mkdir($directory));
        $target = $directory . '/artifact.php';
        self::assertSame(8, \file_put_contents($target, 'original'));
        $windows = \PHP_OS_FAMILY === 'Windows';

        if ($windows) {
            self::setWindowsDirectoryWriteDenied($directory, true);
        } else {
            self::assertTrue(\chmod($directory, 0500));
        }

        try {
            \clearstatcache(true, $directory);
            $probe = @\fopen($directory . '/write-probe', 'xb');
            if (\is_resource($probe)) {
                \fclose($probe);
                \unlink($directory . '/write-probe');
                self::fail('The fixture must deny file creation.');
            }

            self::assertWriteFails($target, AtomicFileWriteException::REASON_TEMP_FILE_CREATE_FAILED);
            self::assertSame('original', \file_get_contents($target));
            self::assertSame(['artifact.php'], self::entries($directory));
        } finally {
            if ($windows) {
                self::setWindowsDirectoryWriteDenied($directory, false);
            } else {
                self::assertTrue(\chmod($directory, 0700));
            }
        }
    }

    #[RunInSeparateProcess]
    public function testFailedTemporaryWritePreservesExistingRegularFileAndCleansTemporaryFiles(): void
    {
        $target = $this->root . '/artifact.php';
        $writer = new AtomicFileWriter();
        $writer->write($target, 'original');

        // Load the exception before replacing the native file stream wrapper.
        self::assertTrue(\class_exists(AtomicFileWriteException::class));

        AtomicFileWriterPartialWriteStream::$written = 0;
        $failure = null;

        $unregistered = \stream_wrapper_unregister('file');
        $registered = $unregistered && \stream_wrapper_register('file', AtomicFileWriterPartialWriteStream::class);

        if (!$registered) {
            if ($unregistered) {
                \stream_wrapper_restore('file');
            }
            self::fail('Could not install the partial-write fixture.');
        }

        try {
            $writer->write($target, \str_repeat('a', 4096));
        } catch (AtomicFileWriteException $exception) {
            $failure = $exception;
        } finally {
            \stream_wrapper_restore('file');
            \clearstatcache();
        }

        self::assertSame(8, AtomicFileWriterPartialWriteStream::$written);
        self::assertInstanceOf(AtomicFileWriteException::class, $failure);
        self::assertSame(AtomicFileWriteException::ERROR_CODE, $failure->errorCode());
        self::assertSame(AtomicFileWriteException::REASON_TEMP_FILE_WRITE_FAILED, $failure->reason());
        self::assertSame(
            AtomicFileWriteException::ERROR_CODE . ': ' . AtomicFileWriteException::REASON_TEMP_FILE_WRITE_FAILED,
            $failure->getMessage(),
        );
        self::assertNull($failure->getPrevious());
        self::assertSame('original', \file_get_contents($target));
        self::assertSame(['artifact.php'], self::entries($this->root));
    }

    public function testInvalidTargetAndBlockedDirectoryExposeOnlySafeReasons(): void
    {
        $blocked = $this->root . '/blocked';
        self::assertSame(8, \file_put_contents($blocked, 'original'));
        self::assertWriteFails('', AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID);
        self::assertWriteFails(
            $this->root . "/invalid\0name",
            AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID,
        );
        self::assertWriteFails($blocked . '/artifact.php', AtomicFileWriteException::REASON_TARGET_DIRECTORY_INVALID);
        self::assertWriteFails(
            $blocked . '/nested/artifact.php',
            AtomicFileWriteException::REASON_TARGET_DIRECTORY_CREATE_FAILED,
        );
        self::assertSame('original', \file_get_contents($blocked));
        self::assertSame(['blocked'], self::entries($this->root));
    }

    private static function setWindowsDirectoryWriteDenied(string $directory, bool $denied): void
    {
        $arguments = $denied
            ? ['/deny', '*S-1-1-0:(WD)']
            : ['/remove:d', '*S-1-1-0'];

        $process = \proc_open(
            ['icacls.exe', \str_replace('/', '\\', $directory), ...$arguments],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        self::assertIsResource($process);
        \fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        self::assertSame(0, \proc_close($process), (string) $stdout . (string) $stderr);
    }

    private static function assertWriteFails(string $target, string $reason): void
    {
        try {
            new AtomicFileWriter()->write($target, 'replacement');
            self::fail('Expected atomic publication failure.');
        } catch (AtomicFileWriteException $exception) {
            self::assertSame(AtomicFileWriteException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertSame(AtomicFileWriteException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /** @return list<string> */
    private static function entries(string $directory): array
    {
        $entries = \scandir($directory);
        self::assertIsArray($entries);

        return \array_values(\array_diff($entries, ['.', '..']));
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path) || \is_link($path)) {
            @\unlink($path);

            return;
        }
        foreach (self::entries($path) as $entry) {
            self::removeTree($path . '/' . $entry);
        }
        \rmdir($path);
    }
}

/** Test-only native file delegation with a deterministic short write. */
final class AtomicFileWriterPartialWriteStream
{
    /** @var resource|null */
    public $context;

    public static int $written = 0;

    /** @var resource|null */
    private $handle = null;

    private static function native(\Closure $operation): mixed
    {
        \stream_wrapper_restore('file');
        try {
            return $operation();
        } finally {
            \stream_wrapper_unregister('file');
            \stream_wrapper_register('file', self::class);
        }
    }

    /** @return array<mixed>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        return self::native(static fn () => ($flags & \STREAM_URL_STAT_LINK) !== 0 ? @\lstat($path) : @\stat($path));
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->handle = self::native(static fn () => @\fopen($path, $mode));
        if (!\is_resource($this->handle)) {
            return false;
        }

        $openedPath = $path;
        return true;
    }

    public function stream_write(string $bytes): int
    {
        if (self::$written !== 0) {
            return 0;
        }

        self::$written = (int) \fwrite($this->handle, \substr($bytes, 0, 8));
        return self::$written;
    }

    public function stream_lock(int $operation): bool
    {
        return $operation === 0 || \flock($this->handle, $operation);
    }

    public function stream_flush(): bool
    {
        return \fflush($this->handle);
    }

    public function stream_close(): void
    {
        \fclose($this->handle);
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return $option === \STREAM_META_ACCESS && self::native(static fn () => @\chmod($path, $value));
    }

    public function rename(string $from, string $to): bool
    {
        return self::native(static fn () => @\rename($from, $to));
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn () => @\unlink($path));
    }
}
