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

namespace Coretsia\Platform\Http\Maintenance;

use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Exception\MaintenanceStateUnavailableException;

/**
 * Application-root-local maintenance state access for HTTP and CLI.
 *
 * Each operation observes fresh filesystem state. Cooperating operations use
 * one persistent Foundation lock anchor in an operator-controlled directory.
 * No request state, paths derived from the current working directory, or
 * generated artifacts participate in this boundary.
 *
 * Publication is atomic per file, not a crash-transactional multi-file update.
 * A failed operation may leave already completed filesystem changes in place.
 *
 * @internal
 * @phpstan-type MaintenanceSnapshot array{enabled: bool, messageState: 'absent'|'valid'|'invalid'|'ignored', message: MaintenanceMessage|null}
 */
final readonly class MaintenanceStateStore
{
    private const string FLAG = 'enabled';

    private const string MESSAGE = 'message.json';

    private const string LOCK = 'maintenance.lock';

    private const int MAX_MESSAGE_READ_BYTES = 8193;

    private const int MODE_TYPE_MASK = 0170000;

    private const int MODE_DIRECTORY = 0040000;

    private const int MODE_REGULAR = 0100000;

    public function __construct(
        private RuntimePathContext $paths,
        private ScopedFileLock $locks,
    ) {
    }

    /** @return MaintenanceSnapshot */
    public function read(): array
    {
        try {
            $directory = $this->stateDirectory();
            if (!$this->directoryExists($directory) || !self::regularEntryExists($directory, self::FLAG)) {
                return self::inactive();
            }

            self::regularEntryExists($directory, self::LOCK);

            /** @var MaintenanceSnapshot $snapshot */
            $snapshot = $this->locks->shared(
                self::join($directory, self::LOCK),
                function () use ($directory): array {
                    $this->assertLockedDirectory($directory);
                    if (!self::regularEntryExists($directory, self::FLAG)) {
                        return self::inactive();
                    }

                    return self::readActiveMessage($directory);
                },
            );

            return $snapshot;
        } catch (\Throwable) {
            throw MaintenanceStateUnavailableException::unavailable();
        }
    }

    public function enable(?MaintenanceMessage $message): void
    {
        try {
            $bytes = $message === null ? null : StableJsonEncoder::encodeStableMap($message->toArray());
            $directory = $this->stateDirectory();
            $this->prepareLock($directory);

            $this->locks->exclusive(
                self::join($directory, self::LOCK),
                function () use ($directory, $bytes): void {
                    $this->assertLockedDirectory($directory);
                    $enabled = self::regularEntryExists($directory, self::FLAG);
                    $hasMessage = self::regularEntryExists($directory, self::MESSAGE);
                    $messagePath = self::join($directory, self::MESSAGE);

                    // Prepare the public message before publishing a new flag.
                    // Existing regular messages need not contain valid JSON.
                    if ($bytes === null) {
                        if ($hasMessage && !@\unlink($messagePath)) {
                            throw MaintenanceStateUnavailableException::unavailable();
                        }
                    } elseif (!$hasMessage || self::readBytes($messagePath) !== $bytes) {
                        self::publish($directory, $messagePath, $bytes);
                    }

                    if (!$enabled) {
                        self::publish($directory, self::join($directory, self::FLAG), '');
                    }
                },
            );
        } catch (\Throwable) {
            throw MaintenanceStateUnavailableException::unavailable();
        }
    }

    public function disable(): void
    {
        try {
            $directory = $this->stateDirectory();
            $this->prepareLock($directory);

            $this->locks->exclusive(
                self::join($directory, self::LOCK),
                function () use ($directory): void {
                    $this->assertLockedDirectory($directory);
                    if (self::regularEntryExists($directory, self::FLAG)
                        && !@\unlink(self::join($directory, self::FLAG))) {
                        throw MaintenanceStateUnavailableException::unavailable();
                    }
                },
            );
        } catch (\Throwable) {
            throw MaintenanceStateUnavailableException::unavailable();
        }
    }

    private function stateDirectory(): string
    {
        $root = $this->paths->applicationRoot();
        if (!self::isAbsoluteRoot($root)) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        self::assertAccessibleDirectory($root);

        return self::join($root, 'var/maintenance');
    }

    private static function isAbsoluteRoot(string $root): bool
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            // A drive-relative path or a path relative to the current drive
            // is not a fully qualified application root.
            return \preg_match('~\A[A-Za-z]:/~', $root) === 1
                || (\preg_match('~\A//[^/]+/[^/]+(?:/|\z)~', $root) === 1
                    && !\str_starts_with($root, '//?/')
                    && !\str_starts_with($root, '//./'));
        }

        return \str_starts_with($root, '/');
    }

    private static function join(string $directory, string $name): string
    {
        return \rtrim($directory, '/') . '/' . $name;
    }

    /**
     * Missing runtime directory components are allowed, but only after their
     * absence has been confirmed in an accessible parent. Never follow a
     * symlink in either of the two runtime directory components.
     */
    private function directoryExists(string $directory): bool
    {
        $root = $this->paths->applicationRoot();
        self::assertAccessibleDirectory($root);

        $var = self::join($root, 'var');
        if (!self::directoryEntryExists($root, 'var')) {
            return false;
        }

        self::assertAccessibleDirectory($var);
        if (!self::directoryEntryExists($var, 'maintenance')) {
            return false;
        }

        self::assertAccessibleDirectory($directory);

        return true;
    }

    private function prepareLock(string $directory): void
    {
        if ($this->directoryExists($directory)) {
            self::regularEntryExists($directory, self::LOCK);
        }
    }

    private function assertLockedDirectory(string $directory): void
    {
        if (!$this->directoryExists($directory) || !self::regularEntryExists($directory, self::LOCK)) {
            throw MaintenanceStateUnavailableException::unavailable();
        }
    }

    private static function assertAccessibleDirectory(string $directory): void
    {
        \clearstatcache(true);

        if (!@\is_dir($directory) || !@\is_readable($directory)
            || (\PHP_OS_FAMILY !== 'Windows' && !@\is_executable($directory))) {
            throw MaintenanceStateUnavailableException::unavailable();
        }
    }

    private static function directoryEntryExists(string $directory, string $name): bool
    {
        $mode = self::entryType($directory, $name);
        if ($mode === null) {
            return false;
        }

        if ($mode !== self::MODE_DIRECTORY) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        return true;
    }

    private static function regularEntryExists(string $directory, string $name): bool
    {
        $mode = self::entryType($directory, $name);
        if ($mode === null) {
            return false;
        }

        if ($mode !== self::MODE_REGULAR) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        return true;
    }

    /**
     * A failed lstat alone does not establish absence. Inspect the accessible
     * parent and retry a listed entry before declaring the state unavailable.
     * lstat inspects the entry itself rather than following a symbolic link.
     */
    private static function entryType(string $directory, string $name): ?int
    {
        $path = self::join($directory, $name);
        \clearstatcache(true);
        $stat = @\lstat($path);
        if ($stat !== false) {
            return $stat['mode'] & self::MODE_TYPE_MASK;
        }

        self::assertAccessibleDirectory($directory);
        $entries = @\scandir($directory, \SCANDIR_SORT_NONE);
        if ($entries === false) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        foreach ($entries as $entry) {
            if ($entry !== $name && (\PHP_OS_FAMILY !== 'Windows' || \strcasecmp($entry, $name) !== 0)) {
                continue;
            }

            \clearstatcache(true);
            $stat = @\lstat($path);
            if ($stat === false) {
                throw MaintenanceStateUnavailableException::unavailable();
            }

            return $stat['mode'] & self::MODE_TYPE_MASK;
        }

        return null;
    }

    /** @return MaintenanceSnapshot */
    private static function inactive(): array
    {
        return ['enabled' => false, 'messageState' => 'ignored', 'message' => null];
    }

    /** @return MaintenanceSnapshot */
    private static function readActiveMessage(string $directory): array
    {
        try {
            if (!self::regularEntryExists($directory, self::MESSAGE)) {
                return ['enabled' => true, 'messageState' => 'absent', 'message' => null];
            }

            $message = MaintenanceMessage::fromJson(self::readBytes(self::join($directory, self::MESSAGE)));

            return ['enabled' => true, 'messageState' => 'valid', 'message' => $message];
        } catch (\Throwable) {
            return ['enabled' => true, 'messageState' => 'invalid', 'message' => null];
        }
    }

    /** Reads only the bounded message prefix, including one overflow byte. */
    private static function readBytes(string $path): string
    {
        $handle = @\fopen($path, 'rb');
        if (!\is_resource($handle)) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        try {
            $stat = @\fstat($handle);
            if ($stat === false || ($stat['mode'] & self::MODE_TYPE_MASK) !== self::MODE_REGULAR || $stat['size'] < 0) {
                throw MaintenanceStateUnavailableException::unavailable();
            }

            $bytes = @\stream_get_contents($handle, self::MAX_MESSAGE_READ_BYTES);
            if ($bytes === false || \strlen($bytes) !== \min($stat['size'], self::MAX_MESSAGE_READ_BYTES)) {
                throw MaintenanceStateUnavailableException::unavailable();
            }
        } finally {
            if (!@\fclose($handle)) {
                throw MaintenanceStateUnavailableException::unavailable();
            }
        }

        return $bytes;
    }

    /** Publishes exact bytes through an operation-owned same-directory file. */
    private static function publish(string $directory, string $path, string $bytes): void
    {
        $temporary = self::join($directory, '.maintenance-' . \bin2hex(\random_bytes(16)) . '.tmp');
        $handle = @\fopen($temporary, \PHP_OS_FAMILY === 'Windows' ? 'xb' : 'xbe');
        if (!\is_resource($handle)) {
            throw MaintenanceStateUnavailableException::unavailable();
        }

        $published = false;

        try {
            try {
                $length = \strlen($bytes);
                $offset = 0;

                while ($offset < $length) {
                    $written = @\fwrite($handle, \substr($bytes, $offset));
                    if ($written === false || $written === 0) {
                        throw MaintenanceStateUnavailableException::unavailable();
                    }

                    $offset += $written;
                }

                if (!@\fflush($handle)) {
                    throw MaintenanceStateUnavailableException::unavailable();
                }
            } finally {
                if (!@\fclose($handle)) {
                    throw MaintenanceStateUnavailableException::unavailable();
                }
            }

            if (!@\rename($temporary, $path)) {
                throw MaintenanceStateUnavailableException::unavailable();
            }

            $published = true;
        } finally {
            if (!$published && !@\unlink($temporary)) {
                throw MaintenanceStateUnavailableException::unavailable();
            }
        }
    }
}
