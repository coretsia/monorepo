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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Exception\MaintenanceStateUnavailableException;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Tests\Support\FilesystemPermissionTestSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaintenanceStateStoreTest extends TestCase
{
    private string $root;

    /** @var array<string, true> */
    private array $directoryLinks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $base = \realpath(\sys_get_temp_dir());
        self::assertIsString($base);
        $this->root = $base . '/coretsia-http-maintenance-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $this->remove($this->root);
        }
        parent::tearDown();
    }

    public function testMissingComponentsEnableDisableAndRetainedMessageLifecycle(): void
    {
        $store = $this->store();
        $directory = $this->directory();
        $this->assertInactive($store);
        self::assertDirectoryDoesNotExist($this->root . '/var');
        self::assertTrue(\mkdir($this->root . '/var'));
        $this->assertInactive($store);
        self::assertDirectoryDoesNotExist($directory);
        self::assertTrue(\rmdir($this->root . '/var'));

        $message = new MaintenanceMessage(1, 'Scheduled / maintenance', 60);
        $bytes = StableJsonEncoder::encodeStableMap($message->toArray());
        $store->enable($message);
        self::assertSame('', \file_get_contents($directory . '/enabled'));
        self::assertSame($bytes, \file_get_contents($directory . '/message.json'));
        self::assertSame(
            "{\"message\":\"Scheduled / maintenance\",\"retryAfterSeconds\":60,\"schemaVersion\":1}\n",
            $bytes,
        );
        $this->assertActive($store, 'valid', $message);
        self::assertFileExists($directory . '/maintenance.lock');
        $this->assertEntries(['enabled', 'maintenance.lock', 'message.json']);

        $store->disable();
        $this->assertInactive($store);
        self::assertFileDoesNotExist($directory . '/enabled');
        self::assertSame($bytes, \file_get_contents($directory . '/message.json'));
        $store->disable();
        $this->assertInactive($store);
        self::assertSame($bytes, \file_get_contents($directory . '/message.json'));
        $this->assertEntries(['maintenance.lock', 'message.json']);

        $store->enable(null);
        $this->assertActive($store, 'absent', null);
        self::assertSame('', \file_get_contents($directory . '/enabled'));
        self::assertFileDoesNotExist($directory . '/message.json');
        $store->enable(null);
        $this->assertActive($store, 'absent', null);
        $this->assertEntries(['enabled', 'maintenance.lock']);
    }

    public function testSharedStoresObserveFreshStateAndIgnoreCurrentWorkingDirectory(): void
    {
        $other = $this->root . '/other';
        self::assertTrue(\mkdir($other));
        $first = $this->store();
        $writer = $this->store();
        $second = $this->store($other);
        $cwd = \getcwd();
        self::assertIsString($cwd);
        try {
            self::assertTrue(\chdir($other));
            $this->assertInactive($first);
            $this->assertInactive($second);
            $writer->enable(new MaintenanceMessage(1, 'First', 1));
            $this->assertActive($first, 'valid', new MaintenanceMessage(1, 'First', 1));
            $this->assertInactive($second);
            self::assertDirectoryDoesNotExist($other . '/var');
            $second->enable(new MaintenanceMessage(1, 'Second', 86400));
            $writer->disable();
            $this->assertInactive($first);
            $this->assertActive($second, 'valid', new MaintenanceMessage(1, 'Second', 86400));
            self::assertSame(
                'First',
                MaintenanceMessage::fromJson(
                    (string) \file_get_contents($this->directory() . '/message.json'),
                )->message(),
            );
        } finally {
            self::assertTrue(\chdir($cwd));
        }
    }

    public function testMissingAndRelativeRootsNeverCreateState(): void
    {
        $cwd = \getcwd();
        self::assertIsString($cwd);
        try {
            self::assertTrue(\chdir($this->root));
            self::assertTrue(\mkdir('relative'));
            self::assertTrue(\mkdir('relative/var/maintenance', 0700, true));
            self::assertSame(8, \file_put_contents('relative/var/maintenance/enabled', 'sentinel'));
            $roots = [$this->root . '/missing', 'relative', 'missing-relative'];
            if (\PHP_OS_FAMILY === 'Windows') {
                $absolute = \getcwd();
                self::assertIsString($absolute);
                if (\preg_match('/\A[A-Za-z]:/', $absolute) === 1) {
                    $roots[] = \substr($absolute, 0, 2) . 'relative';
                }
            } else {
                self::assertTrue(\mkdir('C:relative'));
                self::assertTrue(\mkdir('C:relative/var/maintenance', 0700, true));
                self::assertSame(8, \file_put_contents('C:relative/var/maintenance/enabled', 'sentinel'));
                $roots[] = 'C:relative';
            }
            foreach ($roots as $root) {
                $store = $this->store($root);
                self::assertUnavailable(static fn () => $store->read());
                self::assertUnavailable(static fn () => $store->enable(null));
                self::assertUnavailable(static fn () => $store->disable());
            }
            self::assertDirectoryDoesNotExist($this->root . '/missing');
            self::assertDirectoryDoesNotExist($this->root . '/missing-relative');
            self::assertDirectoryDoesNotExist($this->root . '/var');
            self::assertSame('sentinel', \file_get_contents('relative/var/maintenance/enabled'));
            self::assertSame(['.', '..', 'enabled'], \scandir('relative/var/maintenance'));
            if (\PHP_OS_FAMILY !== 'Windows') {
                self::assertSame('sentinel', \file_get_contents('C:relative/var/maintenance/enabled'));
                self::assertSame(['.', '..', 'enabled'], \scandir('C:relative/var/maintenance'));
            }
        } finally {
            self::assertTrue(\chdir($cwd));
        }
    }

    #[DataProvider('unsafeEntries')]
    public function testUnsafeRequiredEntriesFailWithoutToggling(string $entry, bool $symlink): void
    {
        $path = $this->root . '/' . $entry;
        self::assertTrue(\is_dir(\dirname($path)) || \mkdir(\dirname($path), 0700, true));
        $target = $this->root . '/sentinel';
        self::assertSame(8, \file_put_contents($target, 'sentinel'));
        if ($symlink) {
            if ($entry === 'var' || $entry === 'var/maintenance') {
                $targetDirectory = $this->root . '/target-directory';
                self::assertTrue(\mkdir($targetDirectory));
                $this->link($targetDirectory, $path);
            } else {
                $this->link($target, $path);
            }
        } elseif ($entry === 'var' || $entry === 'var/maintenance') {
            self::assertSame(8, \file_put_contents($path, 'sentinel'));
        } else {
            self::assertTrue(\mkdir($path));
        }
        if ($entry === 'var/maintenance/maintenance.lock') {
            self::assertSame(4, \file_put_contents($this->directory() . '/enabled', 'flag'));
        }

        $store = $this->store();
        self::assertUnavailable(static fn () => $store->read());
        self::assertUnavailable(static fn () => $store->enable(new MaintenanceMessage(1, 'Synthetic', null)));
        self::assertUnavailable(static fn () => $store->disable());
        self::assertSame('sentinel', \file_get_contents($target));
        if ($entry === 'var/maintenance/maintenance.lock') {
            self::assertSame('flag', \file_get_contents($this->directory() . '/enabled'));
        }
        if ($symlink && ($entry === 'var' || $entry === 'var/maintenance')) {
            self::assertSame(['.', '..'], \scandir($this->root . '/target-directory'));
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function unsafeEntries(): iterable
    {
        foreach (['var', 'var/maintenance', 'var/maintenance/enabled', 'var/maintenance/maintenance.lock'] as $entry) {
            yield $entry . '-wrong-type' => [$entry, false];
            yield $entry . '-symlink' => [$entry, true];
        }
    }

    #[DataProvider('unsafeMessageTypes')]
    public function testUnsafeOptionalMessageIsIgnoredInactiveAndInvalidActive(bool $symlink): void
    {
        $directory = $this->directory();
        self::assertTrue(\mkdir($directory, 0700, true));
        $path = $directory . '/message.json';
        $target = $this->root . '/sentinel';
        $bytes = StableJsonEncoder::encodeStableMap(new MaintenanceMessage(1, 'Synthetic', 60)->toArray());
        self::assertSame(\strlen($bytes), \file_put_contents($target, $bytes));
        if ($symlink) {
            $this->link($target, $path);
        } else {
            self::assertTrue(\mkdir($path));
        }
        $store = $this->store();
        $this->assertInactive($store);
        self::assertUnavailable(static fn () => $store->enable(null));
        self::assertUnavailable(static fn () => $store->enable(new MaintenanceMessage(1, 'Other', null)));
        self::assertFileDoesNotExist($directory . '/enabled');

        self::assertSame(4, \file_put_contents($directory . '/enabled', 'flag'));
        $this->assertActive($store, 'invalid', null);
        self::assertUnavailable(static fn () => $store->enable(null));
        self::assertUnavailable(static fn () => $store->enable(new MaintenanceMessage(1, 'Other', null)));
        self::assertSame('flag', \file_get_contents($directory . '/enabled'));
        $store->disable();
        $store->disable();
        $this->assertInactive($store);
        self::assertFileDoesNotExist($directory . '/enabled');
        self::assertSame($symlink, \is_link($path));
        if (!$symlink) {
            self::assertDirectoryExists($path);
        }
        self::assertSame($bytes, \file_get_contents($target));
    }

    /** @return iterable<string, array{bool}> */
    public static function unsafeMessageTypes(): iterable
    {
        yield 'directory' => [false];
        yield 'symlink-to-valid-json' => [true];
    }

    public function testMalformedRegularRetainedMessageCanBeReplacedOrRemoved(): void
    {
        $store = $this->store();
        $directory = $this->directory();
        self::assertTrue(\mkdir($directory, 0700, true));
        $path = $directory . '/message.json';
        self::assertSame(1, \file_put_contents($path, '{'));
        $this->assertInactive($store);
        $message = new MaintenanceMessage(1, 'Recovered', null);
        $store->enable($message);
        $this->assertActive($store, 'valid', $message);
        $store->disable();
        self::assertSame(1, \file_put_contents($path, '{'));
        $store->disable();
        self::assertSame('{', \file_get_contents($path));
        $store->enable(null);
        $this->assertActive($store, 'absent', null);
        self::assertFileDoesNotExist($path);
    }

    public function testNonEmptyFlagAndIdempotentMessageUpdatesPreserveExistingFiles(): void
    {
        $store = $this->store();
        $directory = $this->directory();
        $first = new MaintenanceMessage(1, 'First', 1);
        $store->enable($first);
        $flag = $directory . '/enabled';
        $path = $directory . '/message.json';
        self::assertSame(8, \file_put_contents($flag, 'sentinel'));
        self::assertTrue(\touch($flag, 946684800));
        self::assertTrue(\touch($path, 946684800));
        $flagStat = self::identity($flag);
        $messageStat = self::identity($path);
        $this->assertActive($store, 'valid', $first);
        $store->enable(new MaintenanceMessage(1, 'First', 1));
        self::assertSame($flagStat, self::identity($flag));
        self::assertSame($messageStat, self::identity($path));

        $store->enable(new MaintenanceMessage(1, 'Replacement', 86400));
        $this->assertActive($store, 'valid', new MaintenanceMessage(1, 'Replacement', 86400));
        self::assertSame($flagStat, self::identity($flag));
        $store->enable(null);
        $store->enable(null);
        self::assertSame('sentinel', \file_get_contents($flag));
        self::assertSame($flagStat, self::identity($flag));
        $this->assertActive($store, 'absent', null);
    }

    public function testMessageReadBoundaryPreservesActiveStateOnInvalidBytes(): void
    {
        $store = $this->store();
        $store->enable(null);
        $path = $this->directory() . '/message.json';
        $message = new MaintenanceMessage(1, 'Synthetic', null);
        $bytes = \str_pad(StableJsonEncoder::encodeStableMap($message->toArray()), 8192, ' ');
        self::assertSame(8192, \file_put_contents($path, $bytes));
        $this->assertActive($store, 'valid', $message);
        foreach ([$bytes . ' ', '{', '{"schemaVersion":2,"message":"Synthetic"}'] as $invalid) {
            self::assertSame(\strlen($invalid), \file_put_contents($path, $invalid));
            $this->assertActive($store, 'invalid', null);
        }
        // Verify that the active-state read uses bounded filesystem access.
        $source = \file_get_contents(__DIR__ . '/../../src/Maintenance/MaintenanceStateStore.php');
        self::assertIsString($source);
        self::assertStringContainsString('private const int MAX_MESSAGE_READ_BYTES = 8193;', $source);
        self::assertStringContainsString(
            'stream_get_contents($handle, self::MAX_MESSAGE_READ_BYTES)',
            $source,
        );
        self::assertStringContainsString(
            'MaintenanceMessage::fromJson(self::readBytes(self::join($directory, self::MESSAGE)))',
            $source,
        );

        // The overflow detector must never read the remainder of a larger file.
        $read = new \ReflectionMethod(MaintenanceStateStore::class, 'readBytes');
        self::assertSame(\strlen($bytes) + 8192, \file_put_contents($path, $bytes . \str_repeat(' ', 8192)));
        self::assertSame($bytes . ' ', $read->invoke(null, $path));
        $this->assertActive($store, 'invalid', null);
        $store->disable();
        $this->assertInactive($store);
    }

    public function testInaccessibleParentCannotBeConfirmedAbsentAndMutationsFailSafely(): void
    {
        $directory = $this->directory();
        self::assertTrue(\mkdir($directory, 0700, true));
        $store = $this->store();
        try {
            FilesystemPermissionTestSupport::restrict($directory, 0000, 'RD');

            self::assertFalse(
                \is_readable($directory),
                'The fixture must deny directory inspection.',
            );
            self::assertFalse(@\scandir($directory));
            self::assertUnavailable(static fn () => $store->read());
            self::assertUnavailable(static fn () => $store->enable(null));
            self::assertUnavailable(static fn () => $store->disable());
        } finally {
            FilesystemPermissionTestSupport::restore($directory, 0700);
        }
        $this->assertInactive($store);
        self::assertFileDoesNotExist($directory . '/enabled');
    }

    public function testUnreadableMessageIsInvalidOnlyWhileActive(): void
    {
        $store = $this->store();
        $store->enable(new MaintenanceMessage(1, 'Synthetic', null));
        $path = $this->directory() . '/message.json';
        try {
            FilesystemPermissionTestSupport::restrict($path, 0000, 'RD');
            FilesystemPermissionTestSupport::assertUnreadableFile($path);
            $this->assertActive($store, 'invalid', null);
            $store->disable();
            $store->disable();
            $this->assertInactive($store);
            self::assertFileExists($path);
        } finally {
            FilesystemPermissionTestSupport::restore($path, 0600);
        }
    }

    public function testRegularFlagDoesNotRequireReadableContents(): void
    {
        $store = $this->store();
        $store->enable(null);
        $flag = $this->directory() . '/enabled';
        self::assertSame(8, \file_put_contents($flag, 'sentinel'));
        try {
            FilesystemPermissionTestSupport::restrict($flag, 0000, 'RD');
            FilesystemPermissionTestSupport::assertUnreadableFile($flag);
            $this->assertActive($store, 'absent', null);
            $before = self::identity($flag);
            $store->enable(null);
            self::assertSame($before, self::identity($flag));
        } finally {
            FilesystemPermissionTestSupport::restore($flag, 0600);
        }
        self::assertSame('sentinel', \file_get_contents($flag));
    }

    public function testFailedPublicationDoesNotLeaveOperationOwnedTemporaryFiles(): void
    {
        $directory = $this->directory();
        self::assertTrue(\mkdir($directory, 0700, true));
        $destination = $directory . '/destination';
        self::assertTrue(\mkdir($destination));
        $unrelated = $directory . '/.maintenance-unrelated.tmp';
        self::assertSame(8, \file_put_contents($unrelated, 'sentinel'));

        // Inject a deterministic rename failure at the actual publication boundary.
        $publish = new \ReflectionMethod(MaintenanceStateStore::class, 'publish');
        self::assertUnavailable(static fn () => $publish->invoke(null, $directory, $destination, 'synthetic'));
        $this->assertEntries(['.maintenance-unrelated.tmp', 'destination']);
        self::assertSame('sentinel', \file_get_contents($unrelated));
        self::assertDirectoryExists($destination);
        self::assertFileDoesNotExist($directory . '/enabled');
        $source = \file_get_contents(__DIR__ . '/../../src/Maintenance/MaintenanceStateStore.php');
        self::assertIsString($source);
        self::assertStringContainsString("\$temporary = self::join(\$directory, '.maintenance-'", $source);
        self::assertStringContainsString('rename($temporary, $path)', $source);
        self::assertDoesNotMatchRegularExpression(
            '/\bunlink\s*\(\s*\$path\s*\)/',
            $source,
        );
    }

    public function testDeniedDirectoryWritesCannotReportSuccessfulToggle(): void
    {
        $store = $this->store();
        $message = new MaintenanceMessage(1, 'Original', null);
        $store->enable($message);
        $directory = $this->directory();
        $flag = $directory . '/enabled';
        $path = $directory . '/message.json';
        $original = StableJsonEncoder::encodeStableMap($message->toArray());
        try {
            FilesystemPermissionTestSupport::restrict($directory, 0500, 'WD,AD,DC');

            if (\PHP_OS_FAMILY === 'Windows') {
                FilesystemPermissionTestSupport::restrict($flag, 0600, 'DE');
            }

            self::assertFalse(
                \is_writable($directory),
                'The fixture must deny directory writes.',
            );
            self::assertTrue(\is_readable($directory));

            if (\PHP_OS_FAMILY !== 'Windows') {
                self::assertTrue(\is_executable($directory));
            }
            self::assertUnavailable(static fn () => $store->enable(new MaintenanceMessage(1, 'Replacement', null)));
            self::assertUnavailable(static fn () => $store->disable());
            self::assertSame('', \file_get_contents($flag));
            self::assertSame($original, \file_get_contents($path));
            $this->assertEntries(['enabled', 'maintenance.lock', 'message.json']);
        } finally {
            if (\PHP_OS_FAMILY === 'Windows' && \is_file($flag)) {
                FilesystemPermissionTestSupport::restore($flag, 0600);
            }

            FilesystemPermissionTestSupport::restore($directory, 0700);
        }
        $this->assertActive($store, 'valid', $message);
    }

    public function testPublicationReplacesMessageWithoutRewritingExistingInode(): void
    {
        $store = $this->store();
        $first = new MaintenanceMessage(1, 'First', null);
        $second = new MaintenanceMessage(1, 'Second', null);
        $store->enable($first);
        $directory = $this->directory();
        $path = $directory . '/message.json';
        $alias = $this->root . '/original-message';
        self::assertTrue(
            \function_exists('link') && @\link($path, $alias),
            'Creating a real filesystem hard link failed.',
        );
        self::assertFileExists($alias);
        $store->enable($second);
        self::assertSame(StableJsonEncoder::encodeStableMap($first->toArray()), \file_get_contents($alias));
        self::assertSame(StableJsonEncoder::encodeStableMap($second->toArray()), \file_get_contents($path));
        $this->assertEntries(['enabled', 'maintenance.lock', 'message.json']);
    }

    public function testReadersAndWritersUseOnePersistentFoundationLockAnchor(): void
    {
        $locks = new ScopedFileLock();
        $store = $this->store(locks: $locks);
        $store->enable(null);
        $anchor = $this->directory() . '/maintenance.lock';
        self::assertSame(8, \file_put_contents($anchor, 'sentinel'));
        $identity = self::identity($anchor);
        $this->assertActive($store, 'absent', null);
        $store->enable(new MaintenanceMessage(1, 'Synthetic', null));
        $store->disable();
        self::assertSame('sentinel', \file_get_contents($anchor));
        self::assertSame($identity, self::identity($anchor));

        // The canonical primitive is independently tested for blocking modes.
        // Verify the actual store's call sites, not a timing-dependent race.
        $source = \file_get_contents(__DIR__ . '/../../src/Maintenance/MaintenanceStateStore.php');
        self::assertIsString($source);
        self::assertStringContainsString("private const string LOCK = 'maintenance.lock';", $source);
        foreach (['read' => 'shared', 'enable' => 'exclusive', 'disable' => 'exclusive'] as $method => $mode) {
            $reflection = new \ReflectionMethod(MaintenanceStateStore::class, $method);
            $firstLine = $reflection->getStartLine();
            $lastLine = $reflection->getEndLine();
            self::assertIsInt($firstLine);
            self::assertIsInt($lastLine);
            $lines = \array_slice(\explode("\n", $source), $firstLine - 1, $lastLine - $firstLine + 1);
            self::assertMatchesRegularExpression(
                '/\$this->locks->' . $mode . '\(\s*self::join\(\$directory, self::LOCK\),/',
                \implode("\n", $lines),
            );
        }
        $locks->shared($anchor, static function () use ($locks, $anchor): void {
            try {
                $locks->exclusiveNonBlocking($anchor, static fn (): null => null);
                self::fail('maintenance-lock-conflict-not-detected');
            } catch (ScopedFileLockException $exception) {
                self::assertSame(ScopedFileLockException::REASON_BUSY, $exception->reason());
            }
        });
    }

    private function store(?string $root = null, ?ScopedFileLock $locks = null): MaintenanceStateStore
    {
        return new MaintenanceStateStore(
            new RuntimePathContext($root ?? $this->root, $this->root . '/artifacts'),
            $locks ?? new ScopedFileLock(),
        );
    }

    private function directory(): string
    {
        return $this->root . '/var/maintenance';
    }

    private function assertInactive(MaintenanceStateStore $store): void
    {
        self::assertSame(['enabled' => false, 'messageState' => 'ignored', 'message' => null], $store->read());
    }

    private function assertActive(MaintenanceStateStore $store, string $state, ?MaintenanceMessage $message): void
    {
        $snapshot = $store->read();
        self::assertSame(['enabled', 'messageState', 'message'], \array_keys($snapshot));
        self::assertTrue($snapshot['enabled']);
        self::assertSame($state, $snapshot['messageState']);
        self::assertSame($message?->toArray(), $snapshot['message']?->toArray());
    }

    /** @param list<string> $entries */
    private function assertEntries(array $entries): void
    {
        self::assertSame(['.', '..', ...$entries], \scandir($this->directory()));
    }

    /** @return array{int, int, int, int} */
    private static function identity(string $path): array
    {
        \clearstatcache(true, $path);
        $stat = \stat($path);
        self::assertIsArray($stat);
        return [$stat['dev'], $stat['ino'], $stat['mtime'], $stat['size']];
    }

    private static function assertUnavailable(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('maintenance-state-unavailable-not-reported');
        } catch (MaintenanceStateUnavailableException $exception) {
            self::assertSame('CORETSIA_MAINTENANCE_STATE_UNAVAILABLE', $exception->errorCode());
            self::assertSame('http-maintenance-state-unavailable', $exception->reason());
            self::assertSame(
                'CORETSIA_MAINTENANCE_STATE_UNAVAILABLE: http-maintenance-state-unavailable',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    private function link(string $target, string $path): void
    {
        if (\is_dir($target)) {
            $this->directoryLinks[$path] = true;
        }

        if (
            \function_exists('symlink')
            && @\symlink($target, $path)
            && \is_link($path)
        ) {
            return;
        }

        if (\PHP_OS_FAMILY === 'Windows' && \function_exists('exec')) {
            $output = [];
            $exitCode = -1;

            \exec(
                'mklink '
                . (\is_dir($target) ? '/D ' : '')
                . \escapeshellarg($path)
                . ' '
                . \escapeshellarg($target)
                . ' 2>&1',
                $output,
                $exitCode,
            );

            if ($exitCode === 0 && \is_link($path)) {
                return;
            }
        }

        self::fail(
            'Creating a real filesystem symbolic link failed. On Windows, enable Developer Mode '
            . 'or grant the test process the Create symbolic links privilege.',
        );
    }

    private function remove(string $path): void
    {
        \clearstatcache(true, $path);

        if (\is_link($path)) {
            if (\PHP_OS_FAMILY === 'Windows' && isset($this->directoryLinks[$path])) {
                \rmdir($path);
            } else {
                \unlink($path);
            }
            return;
        }
        if (!\is_dir($path)) {
            \unlink($path);
            return;
        }
        $entries = \scandir($path);
        self::assertIsArray($entries);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        \rmdir($path);
    }
}
