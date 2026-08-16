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
 * Reads existing Kernel-owned PHP-array artifact files as serialized data.
 *
 * PhpArtifactReader is intentionally narrow:
 *
 * - reads existing artifact bytes;
 * - normalizes read bytes for compatibility byte comparison when requested;
 * - decodes canonical StablePhpArrayDumper serialization without executing PHP;
 * - returns the same decoded byte snapshot and envelope data to callers;
 * - converts filesystem/serialization failures into deterministic
 *   ArtifactInvalidException reason tokens.
 *
 * This reader intentionally does not:
 *
 * - resolve artifact paths;
 * - build expected artifacts;
 * - validate envelope/header/payload schemas;
 * - calculate fingerprints;
 * - compare expected/current bytes;
 * - execute, evaluate, include, or require generated artifact source;
 * - emit logs/spans/metrics;
 * - print output.
 *
 * Existing artifact schema validation belongs to ArtifactSchemaValidator.
 * Cache clean/dirty/invalid orchestration belongs to CacheVerifier.
 *
 * Diagnostics are intentionally safe. Exceptions produced by this reader MUST
 * NOT include absolute paths, input path strings, PHP warning text, raw artifact
 * bytes, decoded payloads, source fragments, config values, env values, secrets,
 * stack traces, parser offsets, or previous throwable messages.
 *
 * @internal
 */
final readonly class PhpArtifactReader
{
    /**
     * Reads LF-normalized artifact bytes and decodes that normalized snapshot.
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
        $bytes = self::normalizeBytes(
            $this->readRawBytes($path)
        );

        return [
            'bytes' => $bytes,
            'envelope' => StablePhpArrayParser::parseStable($bytes),
        ];
    }

    /**
     * Reads exact artifact bytes and decodes the envelope from that exact byte
     * snapshot without newline normalization.
     *
     * @param non-empty-string $path
     *
     * @return array{
     *     bytes: string,
     *     envelope: array<int|string, mixed>
     * }
     *
     * @throws ArtifactInvalidException
     */
    public function readExact(
        string $path,
    ): array {
        $bytes = $this->readRawBytes($path);

        return [
            'bytes' => $bytes,
            'envelope' => StablePhpArrayParser::parseStable($bytes),
        ];
    }

    /**
     * Reads exact existing artifact bytes without newline normalization.
     *
     * @param non-empty-string $path
     *
     * @throws ArtifactInvalidException
     */
    public function readExactBytes(string $path): string
    {
        return $this->readRawBytes($path);
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

        if (
            @\is_link($path)
            || !@\is_file($path)
            || !@\is_readable($path)
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_UNREADABLE,
            );
        }
    }

    private static function normalizeBytes(string $bytes): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $bytes);
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
