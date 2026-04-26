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

namespace Coretsia\Tools\Spikes\_support;

final class DeterministicFile
{
    private const string MSG_READ_FAILED = 'spikes-io-read-failed';
    private const string MSG_WRITE_FAILED = 'spikes-io-write-failed';

    private function __construct()
    {
    }

    /**
     * Read raw bytes exactly as stored.
     *
     * MUST:
     *  - no path leaks
     *  - deterministic error codes/messages
     */
    public static function readBytesExact(string $path): string
    {
        try {
            return self::readBytes($path);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable) {
            // MUST NOT leak $path or OS-specific errors.
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
                self::MSG_READ_FAILED,
            );
        }
    }

    public static function readTextNormalizedEol(string $path): string
    {
        try {
            $bytes = self::readBytes($path);

            return self::normalizeEolToLf($bytes);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable) {
            // MUST NOT leak $path or OS-specific errors.
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
                self::MSG_READ_FAILED,
            );
        }
    }

    public static function hashSha256NormalizedEol(string $path): string
    {
        try {
            $normalized = self::readTextNormalizedEol($path);

            $hash = hash('sha256', $normalized);
            if ($hash === false || $hash === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
                    self::MSG_READ_FAILED,
                );
            }

            /** @var non-empty-string $hash */
            return $hash;
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable) {
            // MUST NOT leak $path or OS-specific errors.
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
                self::MSG_READ_FAILED,
            );
        }
    }

    public static function writeTextLf(string $path, string $content): void
    {
        try {
            $normalized = self::normalizeEolToLf($content);
            if ($normalized === '' || !str_ends_with($normalized, "\n")) {
                $normalized .= "\n";
            }

            self::ensureParentDirectory($path);
            self::writeBytesInternal($path, $normalized);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable) {
            // MUST NOT leak $path or OS-specific errors.
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                self::MSG_WRITE_FAILED,
            );
        }
    }

    public static function writeBytesExact(string $path, string $bytes): void
    {
        try {
            self::ensureParentDirectory($path);
            self::writeBytesInternal($path, $bytes);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable) {
            // MUST NOT leak $path or OS-specific errors.
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                self::MSG_WRITE_FAILED,
            );
        }
    }

    private static function normalizeEolToLf(string $content): string
    {
        // Normalize CRLF first, then CR => LF
        $content = str_replace("\r\n", "\n", $content);

        return str_replace("\r", "\n", $content);
    }

    private static function readBytes(string $path): string
    {
        $result = self::guardRead(
            static fn (): string|false => file_get_contents($path),
        );

        if ($result === false) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
                self::MSG_READ_FAILED,
            );
        }

        return $result;
    }

    private static function ensureParentDirectory(string $path): void
    {
        $dir = dirname($path);

        // dirname('file') === '.'; dirname('.') === '.'
        if ($dir === '' || $dir === '.' || $dir === DIRECTORY_SEPARATOR) {
            return;
        }

        $exists = self::guardWrite(
            static fn (): bool => is_dir($dir),
        );

        if ($exists) {
            return;
        }

        $ok = self::guardWrite(
            static fn (): bool => mkdir($dir, 0777, true),
        );

        if ($ok === false) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                self::MSG_WRITE_FAILED,
            );
        }

        $existsAfter = self::guardWrite(
            static fn (): bool => is_dir($dir),
        );

        if ($existsAfter !== true) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                self::MSG_WRITE_FAILED,
            );
        }
    }

    private static function writeBytesInternal(string $path, string $bytes): void
    {
        $fp = self::guardWrite(
            static fn () => fopen($path, 'wb'),
        );

        if ($fp === false) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                self::MSG_WRITE_FAILED,
            );
        }

        try {
            $len = strlen($bytes);
            $written = 0;

            while ($written < $len) {
                $chunk = substr($bytes, $written);

                $n = self::guardWrite(
                    static fn () => fwrite($fp, $chunk),
                );

                if ($n === false || $n === 0) {
                    throw new DeterministicException(
                        ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                        self::MSG_WRITE_FAILED,
                    );
                }

                $written += $n;
            }
        } finally {
            // Must not emit warnings/notices; must not leak OS messages.
            $closed = self::guardWrite(
                static fn (): bool => fclose($fp),
            );

            if ($closed === false) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                    self::MSG_WRITE_FAILED,
                );
            }
        }
    }

    /**
     * Wrap an operation in a temporary error handler; convert any warning/notice and any Throwable
     * into a deterministic exception (no paths, no OS messages, no previous).
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private static function guardRead(callable $operation): mixed
    {
        return self::guard(
            ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED,
            self::MSG_READ_FAILED,
            $operation,
        );
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private static function guardWrite(callable $operation): mixed
    {
        return self::guard(
            ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
            self::MSG_WRITE_FAILED,
            $operation,
        );
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private static function guard(string $code, string $message, callable $operation): mixed
    {
        $hadPhpError = false;

        set_error_handler(
            static function (int $severity, string $phpMessage) use (&$hadPhpError): bool {
                $hadPhpError = true;

                // Swallow everything deterministically: no stderr noise, no OS messages.
                return true;
            },
        );

        try {
            $result = $operation();
        } catch (\Throwable) {
            // MUST NOT propagate original Throwable message (may contain paths/OS text).
            throw new DeterministicException($code, $message);
        } finally {
            restore_error_handler();
        }

        if ($hadPhpError) {
            throw new DeterministicException($code, $message);
        }

        /** @var T $result */
        return $result;
    }
}
