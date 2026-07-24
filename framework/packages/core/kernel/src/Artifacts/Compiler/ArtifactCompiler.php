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

namespace Coretsia\Kernel\Artifacts\Compiler;

use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModuleResolution;

/**
 * Compiles one complete Kernel artifact set and publishes it as one immutable
 * generation.
 *
 * @internal
 */
final readonly class ArtifactCompiler
{
    public const int SCHEMA_VERSION = 1;

    private const string ARTIFACT_MODULE_MANIFEST = 'module-manifest';
    private const string ARTIFACT_CONFIG = 'config';
    private const string ARTIFACT_CONTAINER = 'container';

    private const string REASON_REBUILT = 'rebuilt';

    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_SAFE_PATH_BYTES = 512;

    public function __construct(
        private ConfigKernel $configKernel,
        private ConfigFingerprintInputBuilder $fingerprintInputBuilder,
        private FingerprintCalculator $fingerprintCalculator,
        private ModuleManifestBuilder $moduleManifestBuilder,
        private CompiledConfigBuilder $compiledConfigBuilder,
        private RuntimeContainerGraphCompiler $runtimeContainerGraphCompiler,
        private CompiledContainerBuilder $compiledContainerBuilder,
        private StablePhpArrayDumper $phpArrayDumper,
        private ArtifactGenerationPublisher $generationPublisher,
        private ArtifactPathResolver $pathResolver,
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
     *     rebuilt: true,
     *     reused: false,
     *     reason: non-empty-string,
     *     artifacts: list<array{
     *         name: non-empty-string,
     *         basename: non-empty-string,
     *         path: non-empty-string,
     *         bytes: int
     *     }>,
     *     counts: array{
     *         artifact_count: int,
     *         written_byte_count: int
     *     }
     * }
     *
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws ArtifactGenerationPublishException
     * @throws ConfigInvalidException
     * @throws ContainerDefinitionInvalidException
     * @throws ContainerCompileFailedException
     * @throws JsonFloatForbiddenException
     */
    public function compile(
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
        /*
         * Exactly one ConfigKernel::compile(...) invocation per artifact compile
         * operation. ArtifactCompiler must not compile config again in builders,
         * fingerprinting, writer, or result assembly.
         */
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
            moduleManifestBytes: $this->phpArrayDumper->dumpEnvelope(
                $moduleManifestEnvelope,
            ),
            configEnvelope: $configEnvelope,
            configBytes: $this->phpArrayDumper->dumpEnvelope(
                $configEnvelope,
            ),
            containerEnvelope: $containerEnvelope,
            containerBytes: $this->phpArrayDumper->dumpEnvelope(
                $containerEnvelope,
            ),
        );

        $this->generationPublisher->publish(
            artifactRoot: $this->pathResolver->artifactRoot($bootstrapConfig),
            publicationSet: $publicationSet,
        );

        return self::compileResult(
            artifacts: [
                self::artifactResult(
                    bootstrapConfig: $bootstrapConfig,
                    pathResolver: $this->pathResolver,
                    name: self::ARTIFACT_MODULE_MANIFEST,
                    basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
                    bytes: $publicationSet->moduleManifestBytes(),
                ),
                self::artifactResult(
                    bootstrapConfig: $bootstrapConfig,
                    pathResolver: $this->pathResolver,
                    name: self::ARTIFACT_CONFIG,
                    basename: ArtifactGeneration::CONFIG_BASENAME,
                    bytes: $publicationSet->configBytes(),
                ),
                self::artifactResult(
                    bootstrapConfig: $bootstrapConfig,
                    pathResolver: $this->pathResolver,
                    name: self::ARTIFACT_CONTAINER,
                    basename: ArtifactGeneration::CONTAINER_BASENAME,
                    bytes: $publicationSet->containerBytes(),
                ),
            ],
        );
    }

    /**
     * @return array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     bytes: int
     * }
     */
    private static function artifactResult(
        BootstrapConfig $bootstrapConfig,
        ArtifactPathResolver $pathResolver,
        string $name,
        string $basename,
        string $bytes,
    ): array {
        return [
            'name' => self::safeArtifactName($name),
            'basename' => self::safeBasename($basename),
            'path' => self::safeRelativePath(
                $pathResolver->relativeCacheDirectory($bootstrapConfig)
                . '/generations/current/'
                . $basename,
            ),
            'bytes' => self::safeCount(\strlen($bytes)),
        ];
    }

    /**
     * @param list<array{
     *     name: non-empty-string,
     *     basename: non-empty-string,
     *     path: non-empty-string,
     *     bytes: int
     * }> $artifacts
     *
     * @return array{
     *     schemaVersion: int,
     *     rebuilt: true,
     *     reused: false,
     *     reason: non-empty-string,
     *     artifacts: list<array{
     *         name: non-empty-string,
     *         basename: non-empty-string,
     *         path: non-empty-string,
     *         bytes: int
     *     }>,
     *     counts: array{
     *         artifact_count: int,
     *         written_byte_count: int
     *     }
     * }
     */
    private static function compileResult(array $artifacts): array
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

        $writtenBytes = 0;

        foreach ($artifacts as $artifact) {
            $writtenBytes = self::safeCount(
                $writtenBytes + $artifact['bytes'],
            );
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'rebuilt' => true,
            'reused' => false,
            'reason' => self::REASON_REBUILT,
            'artifacts' => $artifacts,
            'counts' => [
                'artifact_count' => self::safeCount(\count($artifacts)),
                'written_byte_count' => $writtenBytes,
            ],
        ];
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
            default => throw new \InvalidArgumentException('artifact-compiler-artifact-name-invalid'),
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
            ArtifactGeneration::CONTAINER_BASENAME => $basename,
            default => throw new \InvalidArgumentException('artifact-compiler-basename-invalid'),
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
            throw new \InvalidArgumentException('artifact-compiler-relative-path-invalid');
        }

        return $normalized;
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
