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
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Module\ModulePlan;
use Psr\Log\LoggerInterface;

/**
 * Verifies Kernel-owned compiled artifact cache state.
 *
 * CacheVerifier rebuilds expected Kernel-owned artifacts in memory and compares
 * them with existing artifact files:
 *
 * - module-manifest.php;
 * - config.php;
 * - container.php.
 *
 * It computes the current deterministic fingerprint input through
 * ConfigFingerprintInputBuilder, calculates the current fingerprint through
 * FingerprintCalculator, rebuilds expected envelopes through artifact builders,
 * dumps expected PHP artifact bytes through StablePhpArrayDumper, reads existing
 * artifacts through PhpArtifactReader, validates existing schemas through
 * ArtifactSchemaValidator, and compares content bytes only.
 *
 * This verifier intentionally does not:
 *
 * - write artifacts;
 * - depend on platform/cli;
 * - print stdout/stderr;
 * - invoke ResetOrchestrator;
 * - start UnitOfWork;
 * - use mtimes/ctimes/permissions/owners as cache semantics;
 * - emit artifact write metrics;
 * - emit fingerprint calculate metrics;
 * - expose raw payloads, raw config values, raw env values, absolute paths,
 *   fingerprints, PHP warning text, stack traces, or previous throwable
 *   messages in result/log/span data.
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

    private const int SCHEMA_VERSION_MODULE_MANIFEST = 1;
    private const int SCHEMA_VERSION_CONFIG = 1;
    private const int SCHEMA_VERSION_CONTAINER = 1;

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
        private ContainerCompiler $containerCompiler,
        private CompiledContainerBuilder $compiledContainerBuilder,
        private StablePhpArrayDumper $phpArrayDumper,
        private PhpArtifactReader $artifactReader,
        private ArtifactSchemaValidator $schemaValidator,
        private ArtifactPathResolver $pathResolver,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
    }

    /**
     * Verifies Kernel-owned artifact cache state.
     *
     * @param array<string,mixed> $kernelConfig Kernel config subtree.
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
     * @param iterable<array<string, mixed>> $containerDescriptors Descriptor-based, closure-free compiled-container input.
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
     * @throws ContainerCompileFailedException
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws \InvalidArgumentException when deterministic expected data cannot
     *                                   be safely represented.
     */
    public function verify(
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        EnvRepositoryInterface $env,
        array $kernelConfig,
        array $packageDefaultSources,
        array $packageRuleSources,
        array $splitRoots = [],
        array $explicitRuleSources = [],
        array $explicitEnvOverlayMappings = [],
        array $modePresetSourceCandidates = [],
        iterable $containerDescriptors = [],
    ): array {
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

            $fingerprintInput = $this->fingerprintInputBuilder->build(
                bootstrapConfig: $bootstrapConfig,
                modulePlan: $modulePlan,
                env: $env,
                kernelConfig: $kernelConfig,
                compiledConfig: $compiledConfig,
                packageDefaultSources: $packageDefaultSources,
                packageRuleSources: $packageRuleSources,
                splitRoots: $splitRoots,
                explicitRuleSources: $explicitRuleSources,
                modePresetSourceCandidates: $modePresetSourceCandidates,
            );

            $currentFingerprint = $this->fingerprintCalculator->calculate($fingerprintInput);

            $containerGraph = $this->containerCompiler->compile($containerDescriptors);

            $expectedArtifacts = $this->expectedArtifacts(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                modulePlan: $modulePlan,
                compiledConfig: $compiledConfig,
                fingerprint: $currentFingerprint,
                containerGraph: $containerGraph,
            );

            $artifactResults = [];

            foreach ($expectedArtifacts as $expectedArtifact) {
                $artifactResults[] = $this->verifyArtifact(
                    expectedArtifact: $expectedArtifact,
                    currentFingerprint: $currentFingerprint,
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
     * @param array<string,mixed> $kernelConfig
     * @param array<string,mixed> $compiledConfig
     *
     * @return list<array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
     * }>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     */
    private function expectedArtifacts(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        ModulePlan $modulePlan,
        array $compiledConfig,
        string $fingerprint,
        DefinitionGraph $containerGraph,
    ): array {
        return [
            $this->expectedModuleManifest(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                modulePlan: $modulePlan,
                fingerprint: $fingerprint,
            ),
            $this->expectedConfig(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                compiledConfig: $compiledConfig,
                fingerprint: $fingerprint,
            ),
            $this->expectedContainer(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                fingerprint: $fingerprint,
                containerGraph: $containerGraph,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     */
    private function expectedModuleManifest(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        ModulePlan $modulePlan,
        string $fingerprint,
    ): array {
        $envelope = $this->moduleManifestBuilder->build(
            modulePlan: $modulePlan,
            fingerprint: $fingerprint,
        );

        return $this->expectedArtifact(
            name: self::ARTIFACT_MODULE_MANIFEST,
            basename: ArtifactPathResolver::MODULE_MANIFEST_BASENAME,
            schemaVersion: self::SCHEMA_VERSION_MODULE_MANIFEST,
            targetPath: $this->pathResolver->moduleManifestPath($bootstrapConfig, $kernelConfig),
            relativePath: $this->pathResolver->relativePath(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                basename: ArtifactPathResolver::MODULE_MANIFEST_BASENAME,
            ),
            envelope: $envelope,
        );
    }

    /**
     * @param array<string,mixed> $kernelConfig
     * @param array<string,mixed> $compiledConfig
     *
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     */
    private function expectedConfig(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        array $compiledConfig,
        string $fingerprint,
    ): array {
        $envelope = $this->compiledConfigBuilder->build(
            compiledConfig: $compiledConfig,
            fingerprint: $fingerprint,
        );

        return $this->expectedArtifact(
            name: self::ARTIFACT_CONFIG,
            basename: ArtifactPathResolver::CONFIG_BASENAME,
            schemaVersion: self::SCHEMA_VERSION_CONFIG,
            targetPath: $this->pathResolver->configPath($bootstrapConfig, $kernelConfig),
            relativePath: $this->pathResolver->relativePath(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                basename: ArtifactPathResolver::CONFIG_BASENAME,
            ),
            envelope: $envelope,
        );
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     */
    private function expectedContainer(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        string $fingerprint,
        DefinitionGraph $containerGraph,
    ): array {
        $envelope = $this->compiledContainerBuilder->build(
            graph: $containerGraph,
            fingerprint: $fingerprint,
        );

        return $this->expectedArtifact(
            name: self::ARTIFACT_CONTAINER,
            basename: ArtifactPathResolver::CONTAINER_BASENAME,
            schemaVersion: self::SCHEMA_VERSION_CONTAINER,
            targetPath: $this->pathResolver->containerPath($bootstrapConfig, $kernelConfig),
            relativePath: $this->pathResolver->relativePath(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                basename: ArtifactPathResolver::CONTAINER_BASENAME,
            ),
            envelope: $envelope,
        );
    }

    /**
     * @param array<int|string,mixed> $envelope
     *
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function expectedArtifact(
        string $name,
        string $basename,
        int $schemaVersion,
        string $targetPath,
        string $relativePath,
        array $envelope,
    ): array {
        return [
            'name' => self::safeArtifactName($name),
            'basename' => self::safeBasename($basename),
            'path' => self::safeRelativePath($relativePath),
            'targetPath' => self::safeTargetPathForInternalUseOnly($targetPath),
            'expectedBytes' => self::normalizeBytes($this->phpArrayDumper->dumpEnvelope($envelope)),
            'schemaVersion' => $schemaVersion,
        ];
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
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
    private function verifyArtifact(
        array $expectedArtifact,
        string $currentFingerprint,
    ): array {
        if (!@\file_exists($expectedArtifact['targetPath'])) {
            return self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_DIRTY,
                reason: self::REASON_MISSING,
                existingBytes: null,
            );
        }

        try {
            $existing = $this->artifactReader->read($expectedArtifact['targetPath']);
            $this->schemaValidator->validateExpected(
                envelope: $existing['envelope'],
                expectedName: $expectedArtifact['name'],
                expectedSchemaVersion: $expectedArtifact['schemaVersion'],
            );
        } catch (ArtifactInvalidException) {
            return self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_INVALID,
                reason: self::REASON_INVALID,
                existingBytes: null,
            );
        } catch (\Throwable) {
            return self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_INVALID,
                reason: self::REASON_INVALID,
                existingBytes: null,
            );
        }

        $storedFingerprint = self::storedFingerprint($existing['envelope']);

        if ($storedFingerprint !== $currentFingerprint) {
            return self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_DIRTY,
                reason: self::REASON_FINGERPRINT_MISMATCH,
                existingBytes: self::safeCount(\strlen($existing['bytes'])),
            );
        }

        if (self::normalizeBytes($existing['bytes']) !== $expectedArtifact['expectedBytes']) {
            return self::artifactResult(
                expectedArtifact: $expectedArtifact,
                status: self::STATUS_DIRTY,
                reason: self::REASON_CHANGED,
                existingBytes: self::safeCount(\strlen($existing['bytes'])),
            );
        }

        return self::artifactResult(
            expectedArtifact: $expectedArtifact,
            status: self::STATUS_CLEAN,
            reason: self::REASON_OK,
            existingBytes: self::safeCount(\strlen($existing['bytes'])),
        );
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     targetPath: non-empty-string,
     *     expectedBytes: string,
     *     schemaVersion: int
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
     * @param array<int|string,mixed> $envelope
     */
    private static function storedFingerprint(array $envelope): ?string
    {
        $meta = $envelope['_meta'] ?? null;

        if (!\is_array($meta)) {
            return null;
        }

        $fingerprint = $meta['fingerprint'] ?? null;

        if (!\is_string($fingerprint)) {
            return null;
        }

        return $fingerprint;
    }

    /**
     * @param list<array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     status: non-empty-string,
     *     reason: non-empty-string,
     *     expectedBytes: int,
     *     existingBytes: int|null,
     *     explain: array<string,mixed>
     * }> $artifacts
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
     *         explain: array<string,mixed>
     *     }>,
     *     counts: array{
     *         expected_artifact_count: int,
     *         existing_artifact_count: int,
     *         missing_artifact_count: int,
     *         dirty_artifact_count: int,
     *         invalid_artifact_count: int
     *     }
     * }
     */
    private static function result(array $artifacts): array
    {
        \usort(
            $artifacts,
            static function (array $left, array $right): int {
                return \strcmp($left['path'], $right['path'])
                    ?: \strcmp($left['name'], $right['name'])
                        ?: \strcmp($left['basename'], $right['basename']);
            },
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

    private static function normalizeBytes(string $bytes): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * @return non-empty-string
     */
    private static function safeArtifactName(string $name): string
    {
        return match ($name) {
            self::ARTIFACT_MODULE_MANIFEST,
            self::ARTIFACT_CONFIG,
            self::ARTIFACT_CONTAINER => $name,
            default => throw new \InvalidArgumentException('cache-verifier-artifact-name-invalid'),
        };
    }

    /**
     * @return non-empty-string
     */
    private static function safeBasename(string $basename): string
    {
        return match ($basename) {
            ArtifactPathResolver::MODULE_MANIFEST_BASENAME,
            ArtifactPathResolver::CONFIG_BASENAME,
            ArtifactPathResolver::CONTAINER_BASENAME => $basename,
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

    /**
     * @return non-empty-string
     */
    private static function safeTargetPathForInternalUseOnly(string $targetPath): string
    {
        if ($targetPath === '') {
            throw new \InvalidArgumentException('cache-verifier-target-path-invalid');
        }

        return $targetPath;
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
