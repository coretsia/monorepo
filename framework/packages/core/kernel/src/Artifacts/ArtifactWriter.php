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

namespace Coretsia\Kernel\Artifacts;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Psr\Log\LoggerInterface;

/**
 * Atomically writes Kernel-owned text artifacts.
 *
 * ArtifactWriter owns the artifact write operation boundary:
 *
 * - LF-only byte normalization;
 * - exactly one final newline;
 * - temporary file creation in the same target directory;
 * - full temporary file write before rename;
 * - atomic rename where supported by the filesystem/PHP runtime;
 * - best-effort temporary file cleanup on failure;
 * - best-effort write-time permissions.
 *
 * This writer intentionally does not:
 *
 * - resolve artifact paths;
 * - build artifact envelopes;
 * - dump PHP arrays except through StablePhpArrayDumper;
 * - calculate fingerprints;
 * - validate artifact schemas;
 * - compare cache clean/dirty state.
 *
 * Artifact content is never augmented with timestamps, tool versions, absolute
 * paths, hostnames, mtimes, permissions, filesystem owners, user names, process
 * ids, or other process-specific bytes.
 *
 * Observability is best-effort. Logger/meter/tracer/stopwatch failures are
 * swallowed and MUST NOT change artifact write behavior.
 *
 * @internal
 */
final readonly class ArtifactWriter
{
    private const string SPAN_ARTIFACTS_WRITE = 'kernel.artifacts_write';

    private const string METRIC_ARTIFACTS_WRITE_TOTAL = 'kernel.artifacts_write_total';
    private const string METRIC_ARTIFACTS_WRITE_DURATION_MS = 'kernel.artifacts_write_duration_ms';

    private const string LOG_EVENT_ARTIFACTS_WRITE = 'kernel.artifacts.write';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    private const int FILE_PERMISSIONS = 0644;
    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_SAFE_PATH_BYTES = 512;

    private const string TEMP_PREFIX = '.coretsia-artifact-';

    public function __construct(
        private StablePhpArrayDumper $phpArrayDumper,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
    }

    /**
     * Dumps and writes a PHP artifact envelope.
     *
     * @param non-empty-string $targetPath Absolute or caller-root-relative
     *                                     filesystem target path.
     * @param non-empty-string $relativePath Normalized skeleton-root-relative
     *                                       artifact path for safe diagnostics.
     * @param array<int|string, mixed> $envelope Canonical artifact envelope.
     *
     * @return array{basename: non-empty-string, bytes: int, path: non-empty-string}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactWriteFailedException
     */
    public function writePhpEnvelope(
        string $targetPath,
        string $relativePath,
        array $envelope,
    ): array {
        return $this->writeTextArtifact(
            targetPath: $targetPath,
            relativePath: $relativePath,
            bytes: $this->phpArrayDumper->dumpEnvelope($envelope),
        );
    }

    /**
     * Writes a text artifact using LF-only stable bytes.
     *
     * `targetPath` is used only for filesystem operations and MUST NOT be
     * exported to logs, metrics, traces, exception messages, or returned data.
     *
     * `relativePath` is the already-normalized safe path used for diagnostics
     * and deterministic compiler/cache result data.
     *
     * @param non-empty-string $targetPath
     * @param non-empty-string $relativePath
     *
     * @return array{basename: non-empty-string, bytes: int, path: non-empty-string}
     *
     * @throws ArtifactWriteFailedException
     */
    public function writeTextArtifact(
        string $targetPath,
        string $relativePath,
        string $bytes,
    ): array {
        $relativePath = self::safeRelativePath($relativePath);
        $basename = self::basename($relativePath);
        $bytes = self::normalizeTextBytes($bytes);
        $byteCount = self::safeCount(\strlen($bytes));

        $startedAt = $this->safeStartTimer();
        $span = $this->safeStartSpan($byteCount);

        $outcome = self::OUTCOME_FAILURE;

        try {
            $this->writeAtomically($targetPath, $bytes);
            $outcome = self::OUTCOME_SUCCESS;

            return [
                'basename' => $basename,
                'bytes' => $byteCount,
                'path' => $relativePath,
            ];
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->safeEmitObservability(
                span: $span,
                relativePath: $relativePath,
                basename: $basename,
                byteCount: $byteCount,
                outcome: $outcome,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * @throws ArtifactWriteFailedException
     */
    private function writeAtomically(string $targetPath, string $bytes): void
    {
        if ($targetPath === '') {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        $targetDirectory = self::targetDirectory($targetPath);

        $this->ensureTargetDirectory($targetDirectory);

        $temporaryPath = $this->createTemporaryFile($targetDirectory);

        try {
            $this->writeFullTemporaryFile($temporaryPath, $bytes);
            $this->applyBestEffortPermissions($temporaryPath);
            $this->renameTemporaryFile($temporaryPath, $targetPath);

            $temporaryPath = null;
        } finally {
            if ($temporaryPath !== null) {
                self::cleanupTemporaryFile($temporaryPath);
            }
        }
    }

    /**
     * @throws ArtifactWriteFailedException
     */
    private function ensureTargetDirectory(string $targetDirectory): void
    {
        if ($targetDirectory === '') {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        if (@\is_dir($targetDirectory)) {
            return;
        }

        if (@\file_exists($targetDirectory)) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        if (!@\mkdir($targetDirectory, 0775, true) && !@\is_dir($targetDirectory)) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_CREATE_FAILED,
            );
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws ArtifactWriteFailedException
     */
    private function createTemporaryFile(string $targetDirectory): string
    {
        $temporaryPath = @\tempnam($targetDirectory, self::TEMP_PREFIX);

        if ($temporaryPath === false) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TEMP_FILE_CREATE_FAILED,
            );
        }

        if (!self::sameDirectory(\dirname($temporaryPath), $targetDirectory)) {
            self::cleanupTemporaryFile($temporaryPath);

            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TEMP_FILE_CREATE_FAILED,
            );
        }

        return $temporaryPath;
    }

    /**
     * @throws ArtifactWriteFailedException
     */
    private function writeFullTemporaryFile(string $temporaryPath, string $bytes): void
    {
        $written = @\file_put_contents($temporaryPath, $bytes, \LOCK_EX);

        if (!\is_int($written) || $written !== \strlen($bytes)) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TEMP_FILE_WRITE_FAILED,
            );
        }
    }

    private function applyBestEffortPermissions(string $temporaryPath): void
    {
        @\chmod($temporaryPath, self::FILE_PERMISSIONS);
    }

    /**
     * @throws ArtifactWriteFailedException
     */
    private function renameTemporaryFile(string $temporaryPath, string $targetPath): void
    {
        if (!@\rename($temporaryPath, $targetPath)) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TEMP_FILE_RENAME_FAILED,
            );
        }
    }

    private function safeStartTimer(): mixed
    {
        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeStopTimer(mixed $startedAt): int
    {
        if (!\is_int($startedAt)) {
            return 0;
        }

        try {
            return self::safeCount($this->stopwatch->stop($startedAt));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeStartSpan(int $byteCount): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(
                self::SPAN_ARTIFACTS_WRITE,
                self::spanAttributes($byteCount),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeEmitObservability(
        ?SpanInterface $span,
        string $relativePath,
        string $basename,
        int $byteCount,
        string $outcome,
        int $durationMs,
    ): void {
        $this->safeFinishSpan($span, $byteCount);
        $this->safeEmitMetrics($outcome, $durationMs);
        $this->safeLogSummary(
            relativePath: $relativePath,
            basename: $basename,
            byteCount: $byteCount,
            outcome: $outcome,
            durationMs: $durationMs,
        );
    }

    private function safeFinishSpan(?SpanInterface $span, int $byteCount): void
    {
        if ($span === null) {
            return;
        }

        try {
            $span->setAttributes(self::spanAttributes($byteCount));
        } catch (\Throwable) {
            // Observability is best-effort and must not alter artifact writes.
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // Observability is best-effort and must not alter artifact writes.
        }
    }

    private function safeEmitMetrics(string $outcome, int $durationMs): void
    {
        try {
            $labels = [
                'outcome' => self::safeOutcome($outcome),
            ];

            $this->meter->increment(self::METRIC_ARTIFACTS_WRITE_TOTAL, 1, $labels);
            $this->meter->observe(self::METRIC_ARTIFACTS_WRITE_DURATION_MS, $durationMs, $labels);
        } catch (\Throwable) {
            // Observability is best-effort and must not alter artifact writes.
        }
    }

    private function safeLogSummary(
        string $relativePath,
        string $basename,
        int $byteCount,
        string $outcome,
        int $durationMs,
    ): void {
        try {
            $this->logger->info(
                self::LOG_EVENT_ARTIFACTS_WRITE,
                [
                    'artifact_basename' => $basename,
                    'artifact_path' => $relativePath,
                    'byte_count' => $byteCount,
                    'duration_ms' => $durationMs,
                    'outcome' => self::safeOutcome($outcome),
                ],
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter artifact writes.
        }
    }

    /**
     * @return array<string, int>
     */
    private static function spanAttributes(int $byteCount): array
    {
        return [
            'byte_count' => $byteCount,
        ];
    }

    private static function normalizeTextBytes(string $bytes): string
    {
        $normalized = \str_replace(["\r\n", "\r"], "\n", $bytes);
        $normalized = \rtrim($normalized, "\n") . "\n";

        if (\str_contains($normalized, "\r")) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_FINAL_BYTES_INVALID,
            );
        }

        return $normalized;
    }

    /**
     * @return non-empty-string
     *
     * @throws ArtifactWriteFailedException
     */
    private static function safeRelativePath(string $path): string
    {
        $normalized = \str_replace('\\', '/', $path);

        if (
            $normalized === ''
            || \strlen($normalized) > self::MAX_SAFE_PATH_BYTES
            || self::containsUnsafeBytes($normalized)
            || self::looksLikeAbsolutePath($normalized)
            || \str_contains($normalized, '://')
            || \str_contains($normalized, ':')
            || \str_contains($normalized, '//')
            || $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        ) {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        return $normalized;
    }

    /**
     * @return non-empty-string
     */
    private static function basename(string $relativePath): string
    {
        $position = \strrpos($relativePath, '/');

        if ($position === false) {
            return $relativePath;
        }

        $basename = \substr($relativePath, $position + 1);

        if ($basename === '') {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        return $basename;
    }

    /**
     * @return non-empty-string
     *
     * @throws ArtifactWriteFailedException
     */
    private static function targetDirectory(string $targetPath): string
    {
        $directory = \dirname($targetPath);

        if ($directory === '' || $directory === '.') {
            throw ArtifactWriteFailedException::withReason(
                ArtifactWriteFailedException::REASON_TARGET_DIRECTORY_INVALID,
            );
        }

        return $directory;
    }

    private static function normalizeDirectory(string $directory): string
    {
        return \rtrim(\str_replace('\\', '/', $directory), '/');
    }

    private static function sameDirectory(string $left, string $right): bool
    {
        $leftReal = @\realpath($left);
        $rightReal = @\realpath($right);

        $leftNormalized = self::normalizeDirectory(
            \is_string($leftReal) ? $leftReal : $left,
        );
        $rightNormalized = self::normalizeDirectory(
            \is_string($rightReal) ? $rightReal : $right,
        );

        if (\PHP_OS_FAMILY === 'Windows') {
            $leftNormalized = \strtolower($leftNormalized);
            $rightNormalized = \strtolower($rightNormalized);
        }

        return $leftNormalized === $rightNormalized;
    }

    private static function cleanupTemporaryFile(string $temporaryPath): void
    {
        if (@\file_exists($temporaryPath)) {
            @\unlink($temporaryPath);
        }
    }

    private static function safeOutcome(string $outcome): string
    {
        return $outcome === self::OUTCOME_SUCCESS
            ? self::OUTCOME_SUCCESS
            : self::OUTCOME_FAILURE;
    }

    private static function safeCount(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return \min($value, self::MAX_SAFE_COUNT);
    }

    private static function containsUnsafeBytes(string $value): bool
    {
        return \preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }
}
