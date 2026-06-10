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

namespace Coretsia\Kernel\Artifacts\Php;

use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Reads existing Kernel-owned PHP artifact files.
 *
 * PhpArtifactReader is intentionally narrow:
 *
 * - reads existing artifact bytes;
 * - normalizes read bytes for cache byte comparison by converting CRLF/CR to LF;
 * - parses PHP-returned artifact arrays;
 * - returns normalized bytes and parsed envelope data to CacheVerifier;
 * - converts filesystem/include/parse failures into deterministic
 *   ArtifactInvalidException reason tokens.
 *
 * This reader intentionally does not:
 *
 * - resolve artifact paths;
 * - build expected artifacts;
 * - validate envelope/header/payload schemas;
 * - calculate fingerprints;
 * - compare expected/current bytes;
 * - emit logs/spans/metrics;
 * - print output.
 *
 * Existing artifact schema validation belongs to ArtifactSchemaValidator.
 * Cache clean/dirty/invalid orchestration belongs to CacheVerifier.
 *
 * Diagnostics are intentionally safe. Exceptions produced by this reader MUST
 * NOT include absolute paths, input path strings, PHP warning text, emitted
 * artifact output, stack traces, previous throwable messages, raw artifact
 * bytes, returned PHP payloads, config values, env values, or secrets.
 *
 * @internal
 */
final readonly class PhpArtifactReader
{
    /**
     * Reads normalized artifact bytes and parses the PHP-returned artifact data.
     *
     * The returned `bytes` value is LF-normalized only:
     *
     * - `\r\n` -> `\n`
     * - `\r`   -> `\n`
     *
     * It intentionally does not trim or add final newlines. Missing or extra
     * final newlines remain real byte-level differences for CacheVerifier.
     *
     * @param non-empty-string $path Absolute or caller-root-relative filesystem
     *                               path. This value is used only for filesystem
     *                               operations and is never returned or exposed
     *                               in exceptions.
     *
     * @return array{
     *     bytes: string,
     *     envelope: array<int|string, mixed>
     * }
     *
     * @throws ArtifactInvalidException
     */
    public function read(string $path): array
    {
        return [
            'bytes' => self::normalizeBytes($this->readRawBytes($path)),
            'envelope' => $this->readReturnedArray($path),
        ];
    }

    /**
     * Reads and LF-normalizes existing artifact bytes.
     *
     * @param non-empty-string $path
     *
     * @throws ArtifactInvalidException
     */
    public function readNormalizedBytes(string $path): string
    {
        return self::normalizeBytes($this->readRawBytes($path));
    }

    /**
     * Parses an existing PHP artifact and returns the returned array.
     *
     * @param non-empty-string $path
     *
     * @return array<int|string, mixed>
     *
     * @throws ArtifactInvalidException
     */
    public function readReturnedArray(string $path): array
    {
        self::assertReadableFile($path);

        $initialOutputBufferLevel = \ob_get_level();
        $artifactEmittedOutput = false;
        $returned = null;
        $cleanupFailed = false;

        self::installSafeErrorHandler(ArtifactInvalidException::REASON_READ_FAILED);

        try {
            if (\ob_start() !== true) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            try {
                $returned = self::includeArtifact($path);
            } catch (\Throwable) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            if (\ob_get_level() <= $initialOutputBufferLevel) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            while (\ob_get_level() > $initialOutputBufferLevel + 1) {
                $nestedOutput = \ob_get_contents();

                if ($nestedOutput === false) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_READ_FAILED,
                    );
                }

                if ($nestedOutput !== '') {
                    $artifactEmittedOutput = true;
                }

                if (@\ob_end_clean() !== true) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_READ_FAILED,
                    );
                }
            }

            if (\ob_get_level() <= $initialOutputBufferLevel) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            $capturedOutput = \ob_get_contents();

            if ($capturedOutput === false) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            if ($capturedOutput !== '') {
                $artifactEmittedOutput = true;
            }
        } finally {
            while (\ob_get_level() > $initialOutputBufferLevel) {
                if (@\ob_end_clean() !== true) {
                    $cleanupFailed = true;

                    break;
                }
            }

            \restore_error_handler();
        }

        if ($cleanupFailed) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_READ_FAILED,
            );
        }

        if ($artifactEmittedOutput) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_INVALID,
            );
        }

        if (!\is_array($returned)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PHP_RETURN_TYPE_INVALID,
            );
        }

        return $returned;
    }

    /**
     * @param non-empty-string $path
     *
     * @throws ArtifactInvalidException
     */
    private function readRawBytes(string $path): string
    {
        self::assertReadableFile($path);

        self::installSafeErrorHandler(ArtifactInvalidException::REASON_READ_FAILED);

        try {
            $bytes = \file_get_contents($path);
        } catch (ArtifactInvalidException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_READ_FAILED,
            );
        } finally {
            \restore_error_handler();
        }

        if (!\is_string($bytes)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_READ_FAILED,
            );
        }

        return $bytes;
    }

    /**
     * @param non-empty-string $path
     *
     * @throws ArtifactInvalidException
     */
    private static function assertReadableFile(string $path): void
    {
        if ($path === '') {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_UNREADABLE,
            );
        }

        if (!@\is_file($path) || !@\is_readable($path)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_UNREADABLE,
            );
        }
    }

    private static function normalizeBytes(string $bytes): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * @param non-empty-string $path
     *
     * @throws ArtifactInvalidException
     */
    private static function includeArtifact(string $path): mixed
    {
        return (static function (string $__coretsiaArtifactPath): mixed {
            return include $__coretsiaArtifactPath;
        })(
            $path
        );
    }

    private static function installSafeErrorHandler(string $reason): void
    {
        \set_error_handler(
            static function () use ($reason): never {
                throw ArtifactInvalidException::withReason($reason);
            },
        );
    }
}
