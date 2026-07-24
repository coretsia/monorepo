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

namespace Coretsia\Kernel\Artifacts\Verifier;

use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModuleResolution;
use Psr\Log\LoggerInterface;

/**
 * Rebuilds the expected artifact generation in memory and verifies the exact
 * immutable generation selected by the current pointer.
 *
 * @internal
 */
final readonly class CacheVerifier
{
    public const int SCHEMA_VERSION = 1;

    private const string SPAN_CACHE_VERIFY = 'kernel.cache_verify';

    private const string METRIC_CACHE_VERIFY_TOTAL = 'kernel.cache_verify_total';
    private const string METRIC_CACHE_VERIFY_DURATION_MS = 'kernel.cache_verify_duration_ms';

    private const string LOG_EVENT_CACHE_VERIFY = 'kernel.cache.verify';

    private const string ARTIFACT_MODULE_MANIFEST = 'module-manifest';
    private const string ARTIFACT_CONFIG = 'config';
    private const string ARTIFACT_CONTAINER = 'container';
    private const string ARTIFACT_GENERATION = 'artifact-generation';

    private const string OUTCOME_CLEAN = 'clean';
    private const string OUTCOME_DIRTY = 'dirty';
    private const string OUTCOME_INVALID = 'invalid';
    private const string OUTCOME_FAILURE = 'failure';

    private const string STATUS_CLEAN = 'clean';
    private const string STATUS_DIRTY = 'dirty';
    private const string STATUS_INVALID = 'invalid';

    private const string REASON_OK = 'ok';
    private const string REASON_MISSING = 'missing';
    private const string REASON_CHANGED = 'changed';
    private const string REASON_FINGERPRINT_MISMATCH = 'fingerprint_mismatch';
    private const string REASON_INVALID = 'invalid';

    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_SAFE_PATH_BYTES = 512;
    private const int MAX_LOG_ARTIFACTS = 12;

    public function __construct(
        private ConfigKernel $configKernel,
        private ConfigFingerprintInputBuilder $fingerprintInputBuilder,
        private FingerprintCalculator $fingerprintCalculator,
        private ModuleManifestBuilder $moduleManifestBuilder,
        private CompiledConfigBuilder $compiledConfigBuilder,
        private RuntimeContainerGraphCompiler $runtimeContainerGraphCompiler,
        private CompiledContainerBuilder $compiledContainerBuilder,
        private StablePhpArrayDumper $phpArrayDumper,
        private ArtifactGenerationManifestBuilder $generationManifestBuilder,
        private ArtifactGenerationLocator $generationLocator,
        private PhpArtifactReader $artifactReader,
        private ArtifactPathResolver $pathResolver,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
    }

    /**
     * @param array<string,mixed> $kernelConfig
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageDefaultSources
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageRuleSources
     * @param list<non-empty-string> $splitRoots
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $explicitRuleSources
     * @param list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }> $explicitEnvOverlayMappings
     * @param list<array{
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int|null
     * }> $modePresetSourceCandidates
     *
     * @return array{
     *     schemaVersion: int,
     *     outcome: non-empty-string,
     *     clean: bool,
     *     dirty: bool,
     *     invalid: bool,
     *     artifacts: list<array{
     *         name: non-empty-string,
     *         basename: non-empty-string,
     *         path: non-empty-string,
     *         status: non-empty-string,
     *         reason: non-empty-string,
     *         expectedBytes: int,
     *         existingBytes: int|null,
     *         explain: array{
     *             schemaVersion: int,
     *             entries: list<array{
     *                 basename: non-empty-string,
     *                 path: non-empty-string,
     *                 reason: non-empty-string
     *             }>
     *         }
     *     }>,
     *     counts: array{
     *         expected_artifact_count: int,
     *         existing_artifact_count: int,
     *         missing_artifact_count: int,
     *         dirty_artifact_count: int,
     *         invalid_artifact_count: int
     *     }
     * }
     *
     * @throws ConfigInvalidException
     * @throws ContainerDefinitionInvalidException
     * @throws ContainerCompileFailedException
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws ArtifactGenerationPublishException
     */
    public function verify(
        BootstrapConfig $bootstrapConfig,
        ModuleResolution $moduleResolution,
        EnvRepositoryInterface $env,
        array $kernelConfig,
        array $packageDefaultSources,
        array $packageRuleSources,
        array $splitRoots = [],
        array $explicitRuleSources = [],
        array $explicitEnvOverlayMappings = [],
        array $modePresetSourceCandidates = [],
    ): array {
        $modulePlan = $moduleResolution->plan();
        $startedAt = $this->safeStartTimer();
        $span = $this->safeStartSpan();

        $outcome = self::OUTCOME_FAILURE;
        $result = null;

        try {
            $compiledConfig = $this->configKernel->compile(
                bootstrapConfig: $bootstrapConfig,
                modulePlan: $modulePlan,
                env: $env,
                packageDefaultSources: $packageDefaultSources,
                packageRuleSources: $packageRuleSources,
                splitRoots: $splitRoots,
                explicitRuleSources: $explicitRuleSources,
                explicitEnvOverlayMappings: $explicitEnvOverlayMappings,
                explain: false,
            );

            $containerGraph = $this->runtimeContainerGraphCompiler->compile(
                moduleResolution: $moduleResolution,
                compiledConfig: $compiledConfig['config'],
            );

            $fingerprintInput = $this->fingerprintInputBuilder->build(
                bootstrapConfig: $bootstrapConfig,
                modulePlan: $modulePlan,
                containerGraph: $containerGraph,
                env: $env,
                kernelConfig: $kernelConfig,
                compiledConfig: $compiledConfig,
                packageDefaultSources: $packageDefaultSources,
                packageRuleSources: $packageRuleSources,
                splitRoots: $splitRoots,
                explicitRuleSources: $explicitRuleSources,
                modePresetSourceCandidates: $modePresetSourceCandidates,
            );

            $fingerprint = $this->fingerprintCalculator->calculate($fingerprintInput);

            $expected = $this->expectedGeneration(
                bootstrapConfig: $bootstrapConfig,
                modulePlan: $modulePlan,
                compiledConfig: $compiledConfig,
                fingerprint: $fingerprint,
                containerGraph: $containerGraph,
            );

            try {
                $currentGeneration = $this->generationLocator->locate(
                    $this->pathResolver->artifactRoot($bootstrapConfig),
                );
            } catch (ArtifactInvalidException) {
                $currentGeneration = false;
            }

            if ($currentGeneration === false) {
                $artifactResults = self::uniformArtifactResults(
                    expectedArtifacts: $expected['artifacts'],
                    status: self::STATUS_INVALID,
                    reason: self::REASON_INVALID,
                );
            } elseif ($currentGeneration === null) {
                $artifactResults = self::uniformArtifactResults(
                    expectedArtifacts: $expected['artifacts'],
                    status: self::STATUS_DIRTY,
                    reason: self::REASON_MISSING,
                );
            } else {
                $artifactResults = $this->verifyLocatedGeneration(
                    expectedArtifacts: $expected['artifacts'],
                    expectedGenerationId: $expected['generationId'],
                    currentGeneration: $currentGeneration,
                );
            }

            $result = self::result($artifactResults);
            $outcome = $result['outcome'];

            return $result;
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);
            $counts = $result['counts'] ?? self::emptyCounts();

            $this->safeEmitObservability(
                span: $span,
                artifacts: $result['artifacts'] ?? [],
                counts: $counts,
                outcome: $outcome,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * @param array<string,mixed> $compiledConfig
     *
     * @return array{
     *     generationId: ArtifactGenerationId,
     *     artifacts: list<array{
     *         name: non-empty-string,
     *         basename: non-empty-string,
     *         path: non-empty-string,
     *         expectedBytes: string
     *     }>
     * }
     */
    private function expectedGeneration(
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        array $compiledConfig,
        string $fingerprint,
        DefinitionGraph $containerGraph,
    ): array {
        $moduleManifestEnvelope = $this->moduleManifestBuilder->build(
            modulePlan: $modulePlan,
            fingerprint: $fingerprint,
        );

        $configEnvelope = $this->compiledConfigBuilder->build(
            compiledConfig: $compiledConfig,
            fingerprint: $fingerprint,
        );

        $containerEnvelope = $this->compiledContainerBuilder->build(
            graph: $containerGraph,
            fingerprint: $fingerprint,
        );

        $publicationSet = new ArtifactPublicationSet(
            moduleManifestEnvelope: $moduleManifestEnvelope,
            moduleManifestBytes: $this->phpArrayDumper->dumpEnvelope($moduleManifestEnvelope),
            configEnvelope: $configEnvelope,
            configBytes: $this->phpArrayDumper->dumpEnvelope($configEnvelope),
            containerEnvelope: $containerEnvelope,
            containerBytes: $this->phpArrayDumper->dumpEnvelope($containerEnvelope),
        );

        $generationManifestBytes = $this->phpArrayDumper->dumpEnvelope(
            $this->generationManifestBuilder->build($publicationSet),
        );

        return [
            'generationId' => $publicationSet->generationId(),
            'artifacts' => [
                $this->expectedArtifact(
                    bootstrapConfig: $bootstrapConfig,
                    name: self::ARTIFACT_MODULE_MANIFEST,
                    basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
                    bytes: $publicationSet->moduleManifestBytes(),
                ),
                $this->expectedArtifact(
                    bootstrapConfig: $bootstrapConfig,
                    name: self::ARTIFACT_CONFIG,
                    basename: ArtifactGeneration::CONFIG_BASENAME,
                    bytes: $publicationSet->configBytes(),
                ),
                $this->expectedArtifact(
                    bootstrapConfig: $bootstrapConfig,
                    name: self::ARTIFACT_CONTAINER,
                    basename: ArtifactGeneration::CONTAINER_BASENAME,
                    bytes: $publicationSet->containerBytes(),
                ),
                $this->expectedArtifact(
                    bootstrapConfig: $bootstrapConfig,
                    name: self::ARTIFACT_GENERATION,
                    basename: ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
                    bytes: $generationManifestBytes,
                ),
            ],
        ];
    }

    /**
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     expectedBytes: string
     * }
     */
    private function expectedArtifact(
        BootstrapConfig $bootstrapConfig,
        string $name,
        string $basename,
        string $bytes,
    ): array {
        return [
            'name' => self::safeArtifactName($name),
            'basename' => self::safeBasename($basename),
            'path' => self::safeRelativePath(
                $this->pathResolver->relativeCacheDirectory($bootstrapConfig)
                . '/generations/current/'
                . $basename,
            ),
            'expectedBytes' => $bytes,
        ];
    }

    /**
     * @param list<array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     expectedBytes: string
     * }> $expectedArtifacts
     *
     * @return list<array<string, mixed>>
     */
    private function verifyLocatedGeneration(
        array $expectedArtifacts,
        ArtifactGenerationId $expectedGenerationId,
        ArtifactGeneration $currentGeneration,
    ): array {
        $generationMismatch = !$currentGeneration
            ->generationId()
            ->equals($expectedGenerationId);

        $currentPaths = [
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $currentGeneration->moduleManifestPath(),
            ArtifactGeneration::CONFIG_BASENAME => $currentGeneration->configPath(),
            ArtifactGeneration::CONTAINER_BASENAME => $currentGeneration->containerPath(),
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => $currentGeneration->generationManifestPath(),
        ];

        $results = [];

        foreach ($expectedArtifacts as $expectedArtifact) {
            $currentPath = $currentPaths[$expectedArtifact['basename']] ?? null;

            if (!\is_string($currentPath)) {
                $results[] = self::artifactResult(
                    expectedArtifact: $expectedArtifact,
                    status: self::STATUS_INVALID,
                    reason: self::REASON_INVALID,
                    existingBytes: null,
                );

                continue;
            }

            try {
                $currentBytes = $this->artifactReader->readExactBytes(
                    $currentPath,
                );
            } catch (\Throwable) {
                $results[] = self::artifactResult(
                    expectedArtifact: $expectedArtifact,
                    status: self::STATUS_INVALID,
                    reason: self::REASON_INVALID,
                    existingBytes: null,
                );

                continue;
            }

            if ($generationMismatch) {
                $results[] = self::artifactResult(
                    expectedArtifact: $expectedArtifact,
                    status: self::STATUS_DIRTY,
                    reason: self::REASON_FINGERPRINT_MISMATCH,
                    existingBytes: \strlen($currentBytes),
                );

                continue;
            }

            if ($currentBytes !== $expectedArtifact['expectedBytes']) {
                $results[] = self::artifactResult(
                    expectedArtifact: $expectedArtifact,
                    status: self::STATUS_DIRTY,
                    reason: self::REASON_CHANGED,
                    existingBytes: \strlen($currentBytes),
                );

                continue;
            }

            $results[] = self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_CLEAN,
                reason: self::REASON_OK,
                existingBytes: \strlen($currentBytes),
            );
        }

        return $results;
    }

    /**
     * @param list<array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     expectedBytes: string
     * }> $expectedArtifacts
     *
     * @return list<array<string, mixed>>
     */
    private static function uniformArtifactResults(
        array $expectedArtifacts,
        string $status,
        string $reason,
    ): array {
        $results = [];

        foreach ($expectedArtifacts as $expectedArtifact) {
            $results[] = self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: $status,
                reason: $reason,
                existingBytes: null,
            );
        }

        return $results;
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     expectedBytes: string
     * } $expectedArtifact
     *
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     status: non-empty-string,
     *     reason: non-empty-string,
     *     expectedBytes: int,
     *     existingBytes: int|null,
     *     explain: array{
     *         schemaVersion: int,
     *         entries: list<array{
     *             basename: non-empty-string,
     *             path: non-empty-string,
     *             reason: non-empty-string
     *         }>
     *     }
     * }
     */
    private static function artifactResult(
        array $expectedArtifact,
        string $status,
        string $reason,
        ?int $existingBytes,
    ): array {
        $basename = self::safeBasename($expectedArtifact['basename']);
        $path = self::safeRelativePath($expectedArtifact['path']);
        $reason = self::safeReason($reason);

        return [
            'name' => self::safeArtifactName($expectedArtifact['name']),
            'basename' => $basename,
            'path' => $path,
            'status' => self::safeStatus($status),
            'reason' => $reason,
            'expectedBytes' => self::safeCount(\strlen($expectedArtifact['expectedBytes'])),
            'existingBytes' => $existingBytes === null ? null : self::safeCount($existingBytes),
            'explain' => self::artifactExplain(
                basename: $basename,
                path: $path,
                reason: $reason,
            ),
        ];
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     entries: list<array{
     *         basename: non-empty-string,
     *         path: non-empty-string,
     *         reason: non-empty-string
     *     }>
     * }
     */
    private static function artifactExplain(
        string $basename,
        string $path,
        string $reason,
    ): array {
        if ($reason === self::REASON_OK) {
            return [
                'schemaVersion' => self::SCHEMA_VERSION,
                'entries' => [],
            ];
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'entries' => [
                [
                    'basename' => self::safeBasename($basename),
                    'path' => self::safeRelativePath($path),
                    'reason' => self::safeReason($reason),
                ],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     *
     * @return array<string, mixed>
     */
    private static function result(array $artifacts): array
    {
        \usort(
            $artifacts,
            static fn (array $left, array $right): int => \strcmp(
                $left['path'],
                $right['path'],
            )
                ?: \strcmp($left['name'], $right['name'])
                    ?: \strcmp($left['basename'], $right['basename']),
        );

        $counts = self::counts($artifacts);

        $outcome = self::OUTCOME_CLEAN;

        if ($counts['invalid_artifact_count'] > 0) {
            $outcome = self::OUTCOME_INVALID;
        } elseif ($counts['dirty_artifact_count'] > 0 || $counts['missing_artifact_count'] > 0) {
            $outcome = self::OUTCOME_DIRTY;
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'outcome' => $outcome,
            'clean' => $outcome === self::OUTCOME_CLEAN,
            'dirty' => $outcome === self::OUTCOME_DIRTY,
            'invalid' => $outcome === self::OUTCOME_INVALID,
            'artifacts' => $artifacts,
            'counts' => $counts,
        ];
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     *
     * @return array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * }
     */
    private static function counts(array $artifacts): array
    {
        $counts = self::emptyCounts();
        $counts['expected_artifact_count'] = self::safeCount(\count($artifacts));

        foreach ($artifacts as $artifact) {
            $reason = $artifact['reason'] ?? null;
            $status = $artifact['status'] ?? null;
            $existingBytes = $artifact['existingBytes'] ?? null;

            if ($existingBytes !== null || $reason !== self::REASON_MISSING) {
                ++$counts['existing_artifact_count'];
            }

            if ($reason === self::REASON_MISSING) {
                ++$counts['missing_artifact_count'];
            }

            if ($status === self::STATUS_DIRTY) {
                ++$counts['dirty_artifact_count'];
            }

            if ($status === self::STATUS_INVALID) {
                ++$counts['invalid_artifact_count'];
            }
        }

        foreach ($counts as $key => $value) {
            $counts[$key] = self::safeCount($value);
        }

        return $counts;
    }

    /**
     * @return array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * }
     */
    private static function emptyCounts(): array
    {
        return [
            'expected_artifact_count' => 0,
            'existing_artifact_count' => 0,
            'missing_artifact_count' => 0,
            'dirty_artifact_count' => 0,
            'invalid_artifact_count' => 0,
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
        if (!\is_int($startedAt)) {
            return 0;
        }

        try {
            return self::safeCount($this->stopwatch->stop($startedAt));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeStartSpan(): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(
                self::SPAN_CACHE_VERIFY,
                self::spanAttributes(self::emptyCounts()),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     * @param array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * } $counts
     */
    private function safeEmitObservability(
        ?SpanInterface $span,
        array $artifacts,
        array $counts,
        string $outcome,
        int $durationMs,
    ): void {
        $this->safeFinishSpan($span, $counts);
        $this->safeEmitMetrics($outcome, $durationMs);
        $this->safeLogSummary(
            artifacts: $artifacts,
            counts: $counts,
            outcome: $outcome,
            durationMs: $durationMs,
        );
    }

    /**
     * @param array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * } $counts
     */
    private function safeFinishSpan(?SpanInterface $span, array $counts): void
    {
        if ($span === null) {
            return;
        }

        try {
            $span->setAttributes(self::spanAttributes($counts));
        } catch (\Throwable) {
            // Observability is best-effort and must not alter verification.
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // Observability is best-effort and must not alter verification.
        }
    }

    private function safeEmitMetrics(string $outcome, int $durationMs): void
    {
        try {
            $labels = [
                'outcome' => self::safeOutcome($outcome),
            ];

            $this->meter->increment(self::METRIC_CACHE_VERIFY_TOTAL, 1, $labels);
            $this->meter->observe(self::METRIC_CACHE_VERIFY_DURATION_MS, $durationMs, $labels);
        } catch (\Throwable) {
            // Observability is best-effort and must not alter verification.
        }
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     * @param array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * } $counts
     */
    private function safeLogSummary(
        array $artifacts,
        array $counts,
        string $outcome,
        int $durationMs,
    ): void {
        try {
            $this->logger->info(
                self::LOG_EVENT_CACHE_VERIFY,
                [
                    'artifact_count' => $counts['expected_artifact_count'],
                    'artifact_paths' => self::safeLogArtifactPaths($artifacts),
                    'artifact_reasons' => self::safeLogArtifactReasons($artifacts),
                    'dirty_artifact_count' => $counts['dirty_artifact_count'],
                    'duration_ms' => $durationMs,
                    'existing_artifact_count' => $counts['existing_artifact_count'],
                    'invalid_artifact_count' => $counts['invalid_artifact_count'],
                    'missing_artifact_count' => $counts['missing_artifact_count'],
                    'outcome' => self::safeOutcome($outcome),
                ],
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter verification.
        }
    }

    /**
     * @param array{
     *     expected_artifact_count: int,
     *     existing_artifact_count: int,
     *     missing_artifact_count: int,
     *     dirty_artifact_count: int,
     *     invalid_artifact_count: int
     * } $counts
     *
     * @return array<string,int>
     */
    private static function spanAttributes(array $counts): array
    {
        return [
            'dirty_artifact_count' => self::safeCount($counts['dirty_artifact_count']),
            'existing_artifact_count' => self::safeCount($counts['existing_artifact_count']),
            'expected_artifact_count' => self::safeCount($counts['expected_artifact_count']),
            'invalid_artifact_count' => self::safeCount($counts['invalid_artifact_count']),
            'missing_artifact_count' => self::safeCount($counts['missing_artifact_count']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     *
     * @return list<non-empty-string>
     */
    private static function safeLogArtifactPaths(array $artifacts): array
    {
        $paths = [];

        foreach ($artifacts as $artifact) {
            $path = $artifact['path'] ?? null;

            if (!\is_string($path)) {
                continue;
            }

            try {
                $paths[self::safeRelativePath($path)] = true;
            } catch (\Throwable) {
                continue;
            }

            if (\count($paths) >= self::MAX_LOG_ARTIFACTS) {
                break;
            }
        }

        \ksort($paths, \SORT_STRING);

        return \array_keys($paths);
    }

    /**
     * @param list<array<string,mixed>> $artifacts
     *
     * @return list<non-empty-string>
     */
    private static function safeLogArtifactReasons(array $artifacts): array
    {
        $reasons = [];

        foreach ($artifacts as $artifact) {
            $basename = $artifact['basename'] ?? null;
            $reason = $artifact['reason'] ?? null;

            if (!\is_string($basename) || !\is_string($reason)) {
                continue;
            }

            try {
                $reasons[self::safeBasename($basename) . ':' . self::safeReason($reason)] = true;
            } catch (\Throwable) {
                continue;
            }

            if (\count($reasons) >= self::MAX_LOG_ARTIFACTS) {
                break;
            }
        }

        \ksort($reasons, \SORT_STRING);

        return \array_keys($reasons);
    }

    /**
     * @return non-empty-string
     */
    private static function safeArtifactName(string $name): string
    {
        return match ($name) {
            self::ARTIFACT_MODULE_MANIFEST,
            self::ARTIFACT_CONFIG,
            self::ARTIFACT_CONTAINER,
            self::ARTIFACT_GENERATION => $name,
            default => throw new \InvalidArgumentException('cache-verifier-artifact-name-invalid'),
        };
    }

    /**
     * @return non-empty-string
     */
    private static function safeBasename(string $basename): string
    {
        return match ($basename) {
            ArtifactGeneration::MODULE_MANIFEST_BASENAME,
            ArtifactGeneration::CONFIG_BASENAME,
            ArtifactGeneration::CONTAINER_BASENAME,
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => $basename,
            default => throw new \InvalidArgumentException('cache-verifier-basename-invalid'),
        };
    }

    /**
     * @return non-empty-string
     */
    private static function safeRelativePath(string $path): string
    {
        $normalized = \str_replace('\\', '/', $path);

        if (
            $normalized === ''
            || \strlen($normalized) > self::MAX_SAFE_PATH_BYTES
            || self::containsUnsafeBytes($normalized)
            || self::looksLikeAbsolutePath($normalized)
            || \str_contains($normalized, ':')
            || \str_contains($normalized, '://')
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
            throw new \InvalidArgumentException('cache-verifier-relative-path-invalid');
        }

        return $normalized;
    }

    private static function safeStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_CLEAN,
            self::STATUS_DIRTY,
            self::STATUS_INVALID => $status,
            default => self::STATUS_INVALID,
        };
    }

    private static function safeReason(string $reason): string
    {
        return match ($reason) {
            self::REASON_OK,
            self::REASON_MISSING,
            self::REASON_CHANGED,
            self::REASON_FINGERPRINT_MISMATCH,
            self::REASON_INVALID => $reason,
            default => self::REASON_INVALID,
        };
    }

    private static function safeOutcome(string $outcome): string
    {
        return match ($outcome) {
            self::OUTCOME_CLEAN,
            self::OUTCOME_DIRTY,
            self::OUTCOME_INVALID,
            self::OUTCOME_FAILURE => $outcome,
            default => self::OUTCOME_FAILURE,
        };
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
