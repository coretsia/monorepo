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

    private const string ARTIFACT_MODULE_MANIFEST_IDENTITY = 'module-manifest@1';
    private const string ARTIFACT_CONFIG_IDENTITY = 'config@1';
    private const string ARTIFACT_CONTAINER_IDENTITY = 'container@1';
    private const string ARTIFACT_GENERATION_IDENTITY = 'artifact-generation@1';

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
     *     generationId: non-empty-string,
     *     artifacts: list<array{
     *         identity: non-empty-string,
     *         basename: non-empty-string
     *     }>
     * }
     *
     * @throws ArtifactPayloadInvalidException
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

        if ($compiledConfig['validation']->isFailure()) {
            throw ConfigInvalidException::fromValidationResult(
                $compiledConfig['validation'],
            );
        }

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

        $publishedGeneration = $this->generationPublisher->publish(
            artifactRoot: $this->pathResolver->artifactRoot($bootstrapConfig),
            publicationSet: $publicationSet,
        );

        return self::compileResult($publishedGeneration);
    }

    /**
     * @return array{
     *     identity: non-empty-string,
     *     basename: non-empty-string
     * }
     */
    private static function artifactResult(
        string $identity,
        string $basename,
    ): array {
        return [
            'identity' => self::safeArtifactIdentity($identity),
            'basename' => self::safeBasename($basename),
        ];
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     generationId: non-empty-string,
     *     artifacts: list<array{
     *         identity: non-empty-string,
     *         basename: non-empty-string
     *     }>
     * }
     */
    private static function compileResult(
        ArtifactGeneration $generation,
    ): array {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'generationId' => $generation->generationId()->value(),
            'artifacts' => [
                self::artifactResult(
                    identity: self::ARTIFACT_MODULE_MANIFEST_IDENTITY,
                    basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
                ),
                self::artifactResult(
                    identity: self::ARTIFACT_CONFIG_IDENTITY,
                    basename: ArtifactGeneration::CONFIG_BASENAME,
                ),
                self::artifactResult(
                    identity: self::ARTIFACT_CONTAINER_IDENTITY,
                    basename: ArtifactGeneration::CONTAINER_BASENAME,
                ),
                self::artifactResult(
                    identity: self::ARTIFACT_GENERATION_IDENTITY,
                    basename: ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
                ),
            ],
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function safeArtifactIdentity(string $identity): string
    {
        return match ($identity) {
            self::ARTIFACT_MODULE_MANIFEST_IDENTITY,
            self::ARTIFACT_CONFIG_IDENTITY,
            self::ARTIFACT_CONTAINER_IDENTITY,
            self::ARTIFACT_GENERATION_IDENTITY => $identity,
            default => throw new \InvalidArgumentException('artifact-compiler-artifact-identity-invalid'),
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
            default => throw new \InvalidArgumentException('artifact-compiler-basename-invalid'),
        };
    }
}
