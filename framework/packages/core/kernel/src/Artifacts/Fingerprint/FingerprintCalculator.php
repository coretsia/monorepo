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

namespace Coretsia\Kernel\Artifacts\Fingerprint;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Psr\Log\LoggerInterface;

/**
 * Calculates deterministic Kernel artifact fingerprints.
 *
 * This service is intentionally narrow:
 *
 * - it consumes the normalized fingerprint input produced by
 *   ConfigFingerprintInputBuilder;
 * - it normalizes the input again at the operation boundary to fail safely on
 *   accidental non-json-like values;
 * - it serializes through Foundation StableJsonEncoder;
 * - it returns lowercase hex sha256 over the stable JSON bytes.
 *
 * This class MUST NOT:
 *
 * - build fingerprint input;
 * - re-run config discovery;
 * - re-run module discovery;
 * - re-run env loading;
 * - re-run config merging;
 * - use PHP native serialization;
 * - include raw config values directly;
 * - include raw env values directly;
 * - include secrets, absolute paths, timestamps, mtimes, permissions,
 *   filesystem owners, hostnames, process ids, random bytes, or
 *   locale-dependent bytes.
 *
 * Observability is owned at this operation boundary. It must stay safe and
 * best-effort: logger/meter/tracer failures are swallowed and never change
 * fingerprint calculation behavior.
 *
 * @internal
 */
final readonly class FingerprintCalculator
{
    private const string SPAN_FINGERPRINT_CALCULATE = 'kernel.fingerprint_calculate';

    private const string METRIC_FINGERPRINT_CALCULATE_TOTAL = 'kernel.fingerprint_calculate_total';
    private const string METRIC_FINGERPRINT_CALCULATE_DURATION_MS = 'kernel.fingerprint_calculate_duration_ms';

    private const string LOG_EVENT_FINGERPRINT_CALCULATE = 'kernel.fingerprint.calculate';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_BUCKET_NAMES = 32;
    private const int MAX_BUCKET_NAME_BYTES = 64;

    private const string SAFE_BUCKET_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,63}\z/';
    private const string DIGEST_PATTERN = '/\A[a-f0-9]{64}\z/';

    public function __construct(
        private PayloadNormalizer $payloadNormalizer,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
    }

    /**
     * Calculates a stable lowercase hex sha256 digest for deterministic
     * fingerprint input.
     *
     * @param array<string, mixed> $fingerprintInput Normalized structure
     *                                               produced by
     *                                               ConfigFingerprintInputBuilder.
     *
     * @return non-empty-string Lowercase 64-character hex sha256 digest.
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws \InvalidArgumentException when canonical stable JSON encoding
     *                                   rejects the input shape.
     */
    public function calculate(array $fingerprintInput): string
    {
        $startedAt = $this->safeStartTimer();
        $summary = self::observabilitySummary($fingerprintInput);
        $span = $this->safeStartSpan($summary);

        $outcome = self::OUTCOME_FAILURE;

        try {
            $normalizedInput = $this->payloadNormalizer->normalizeMap(
                $fingerprintInput,
                'fingerprintInput',
            );

            $json = StableJsonEncoder::encodeStable($normalizedInput);
            $fingerprint = \hash('sha256', $json);

            if (\preg_match(self::DIGEST_PATTERN, $fingerprint) !== 1) {
                throw new \RuntimeException('fingerprint-digest-invalid');
            }

            $outcome = self::OUTCOME_SUCCESS;

            return $fingerprint;
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->safeEmitObservability(
                span: $span,
                summary: $summary,
                outcome: $outcome,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * @param array<string, mixed> $fingerprintInput
     *
     * @return array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * }
     */
    private static function observabilitySummary(array $fingerprintInput): array
    {
        $observabilityMetadata = $fingerprintInput['observabilityMetadata'] ?? null;

        if (!\is_array($observabilityMetadata)) {
            return self::derivedObservabilitySummary($fingerprintInput);
        }

        $bucketNames = self::bucketNames($observabilityMetadata['bucketNames'] ?? null);
        $sourceCandidateCount = self::sumIntegerMap($observabilityMetadata['sourceCandidateCounts'] ?? null);
        $missingCandidateCount = self::sumIntegerMap($observabilityMetadata['missingCandidateCounts'] ?? null);

        $validationSubjectCounts = $observabilityMetadata['validationSubjectCounts'] ?? [];
        $validatedCount = self::safeIntFromMap($validationSubjectCounts, 'validated');
        $unvalidatedCount = self::safeIntFromMap($validationSubjectCounts, 'unvalidated');

        return [
            'bucket_count' => self::safeCountValue(
                $bucketNames !== [] ? \count($bucketNames) : \count($fingerprintInput)
            ),
            'bucket_names' => $bucketNames,
            'source_candidate_count' => $sourceCandidateCount,
            'missing_source_candidate_count' => $missingCandidateCount,
            'env_overlay_metadata_count' => self::envOverlayMetadataCount($fingerprintInput['envOverlay'] ?? null),
            'validation_subject_validated_count' => $validatedCount,
            'validation_subject_unvalidated_count' => $unvalidatedCount,
        ];
    }

    /**
     * @param array<string, mixed> $fingerprintInput
     *
     * @return array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * }
     */
    private static function derivedObservabilitySummary(array $fingerprintInput): array
    {
        $sourceCandidates = $fingerprintInput['sourceCandidates'] ?? [];
        $dotenvCandidates = $fingerprintInput['dotenvCandidates'] ?? [];
        $validationSubjects = $fingerprintInput['compiledConfig']['validationSubjects'] ?? [];

        return [
            'bucket_count' => self::safeCountValue(\count($fingerprintInput)),
            'bucket_names' => self::bucketNames(\array_keys($fingerprintInput)),
            'source_candidate_count' => self::sourceCandidateCount($sourceCandidates)
                + self::listCount($dotenvCandidates),
            'missing_source_candidate_count' => self::missingCandidateCount($sourceCandidates)
                + self::missingCandidateCount($dotenvCandidates),
            'env_overlay_metadata_count' => self::envOverlayMetadataCount($fingerprintInput['envOverlay'] ?? null),
            'validation_subject_validated_count' => self::listCount($validationSubjects['validated'] ?? null),
            'validation_subject_unvalidated_count' => self::listCount($validationSubjects['unvalidated'] ?? null),
        ];
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
        if ($startedAt === null) {
            return 0;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0
                ? $durationMs
                : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * } $summary
     */
    private function safeStartSpan(array $summary): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(
                self::SPAN_FINGERPRINT_CALCULATE,
                self::spanAttributes($summary),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * } $summary
     */
    private function safeEmitObservability(
        ?SpanInterface $span,
        array $summary,
        string $outcome,
        int $durationMs,
    ): void {
        $this->safeFinishSpan($span, $summary);
        $this->safeEmitMetrics($outcome, $durationMs);
        $this->safeLogSummary($summary, $outcome, $durationMs);
    }

    /**
     * @param array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * } $summary
     */
    private function safeFinishSpan(
        ?SpanInterface $span,
        array $summary,
    ): void {
        if ($span === null) {
            return;
        }

        try {
            $span->setAttributes(self::spanAttributes($summary));
        } catch (\Throwable) {
            // Observability is best-effort and must not alter fingerprinting.
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // Observability is best-effort and must not alter fingerprinting.
        }
    }

    private function safeEmitMetrics(string $outcome, int $durationMs): void
    {
        try {
            $labels = [
                'outcome' => self::safeOutcome($outcome),
            ];

            $this->meter->increment(self::METRIC_FINGERPRINT_CALCULATE_TOTAL, 1, $labels);
            $this->meter->observe(self::METRIC_FINGERPRINT_CALCULATE_DURATION_MS, $durationMs, $labels);
        } catch (\Throwable) {
            // Observability is best-effort and must not alter fingerprinting.
        }
    }

    /**
     * @param array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * } $summary
     */
    private function safeLogSummary(
        array $summary,
        string $outcome,
        int $durationMs,
    ): void {
        try {
            $this->logger->info(
                self::LOG_EVENT_FINGERPRINT_CALCULATE,
                [
                    'outcome' => self::safeOutcome($outcome),
                    'duration_ms' => $durationMs,
                    'bucket_count' => $summary['bucket_count'],
                    'bucket_names' => $summary['bucket_names'],
                    'source_candidate_count' => $summary['source_candidate_count'],
                    'missing_source_candidate_count' => $summary['missing_source_candidate_count'],
                    'env_overlay_metadata_count' => $summary['env_overlay_metadata_count'],
                    'validation_subject_validated_count' => $summary['validation_subject_validated_count'],
                    'validation_subject_unvalidated_count' => $summary['validation_subject_unvalidated_count'],
                ],
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter fingerprinting.
        }
    }

    /**
     * @param array{
     *     bucket_count: int,
     *     bucket_names: list<non-empty-string>,
     *     source_candidate_count: int,
     *     missing_source_candidate_count: int,
     *     env_overlay_metadata_count: int,
     *     validation_subject_validated_count: int,
     *     validation_subject_unvalidated_count: int
     * } $summary
     *
     * @return array<string, int>
     */
    private static function spanAttributes(array $summary): array
    {
        return [
            'bucket_count' => $summary['bucket_count'],
            'source_candidate_count' => $summary['source_candidate_count'],
            'missing_source_candidate_count' => $summary['missing_source_candidate_count'],
            'env_overlay_metadata_count' => $summary['env_overlay_metadata_count'],
            'validation_subject_validated_count' => $summary['validation_subject_validated_count'],
            'validation_subject_unvalidated_count' => $summary['validation_subject_unvalidated_count'],
        ];
    }

    private static function safeOutcome(string $outcome): string
    {
        return $outcome === self::OUTCOME_SUCCESS
            ? self::OUTCOME_SUCCESS
            : self::OUTCOME_FAILURE;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function bucketNames(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $names = [];

        foreach ($value as $name) {
            if (!\is_string($name)) {
                continue;
            }

            if (\strlen($name) > self::MAX_BUCKET_NAME_BYTES) {
                continue;
            }

            if (\preg_match(self::SAFE_BUCKET_NAME_PATTERN, $name) !== 1) {
                continue;
            }

            $names[] = $name;

            if (\count($names) >= self::MAX_BUCKET_NAMES) {
                break;
            }
        }

        \usort($names, static fn (string $left, string $right): int => \strcmp($left, $right));

        return \array_values(\array_unique($names));
    }

    private static function sumIntegerMap(mixed $value): int
    {
        if (!\is_array($value)) {
            return 0;
        }

        $sum = 0;

        foreach ($value as $item) {
            if (\is_int($item) && $item > 0) {
                $sum = self::safeCountValue($sum + $item);
            }
        }

        return $sum;
    }

    private static function safeIntFromMap(mixed $map, string $key): int
    {
        if (!\is_array($map)) {
            return 0;
        }

        $value = $map[$key] ?? null;

        return \is_int($value) && $value > 0
            ? self::safeCountValue($value)
            : 0;
    }

    private static function sourceCandidateCount(mixed $sourceCandidates): int
    {
        if (!\is_array($sourceCandidates)) {
            return 0;
        }

        $count = 0;

        foreach ($sourceCandidates as $bucket) {
            $count = self::safeCountValue($count + self::listCount($bucket));
        }

        return $count;
    }

    private static function missingCandidateCount(mixed $value): int
    {
        if (!\is_array($value)) {
            return 0;
        }

        if (\array_is_list($value)) {
            $count = 0;

            foreach ($value as $item) {
                if (\is_array($item) && ($item['exists'] ?? null) === 'false') {
                    ++$count;
                }
            }

            return self::safeCountValue($count);
        }

        $count = 0;

        foreach ($value as $bucket) {
            $count = self::safeCountValue($count + self::missingCandidateCount($bucket));
        }

        return $count;
    }

    private static function envOverlayMetadataCount(mixed $envOverlay): int
    {
        if (!\is_array($envOverlay)) {
            return 0;
        }

        return self::safeCountValue(
            self::listCount($envOverlay['mappings'] ?? null)
            + self::mapCount($envOverlay['sources'] ?? null),
        );
    }

    private static function listCount(mixed $value): int
    {
        return \is_array($value) && \array_is_list($value)
            ? self::safeCountValue(\count($value))
            : 0;
    }

    private static function mapCount(mixed $value): int
    {
        return \is_array($value) && !\array_is_list($value)
            ? self::safeCountValue(\count($value))
            : 0;
    }

    private static function safeCountValue(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return \min($value, self::MAX_SAFE_COUNT);
    }
}
