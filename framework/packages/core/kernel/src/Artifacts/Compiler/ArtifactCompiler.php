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
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Module\ModulePlan;

/**
 * Orchestrates Kernel-owned artifact generation.
 *
 * ArtifactCompiler is the compile-side orchestration boundary for Kernel-owned
 * artifacts:
 *
 * - module-manifest.php;
 * - config.php;
 * - container.php.
 *
 * `container.php` uses the same Kernel artifact path policy as the other
 * Kernel-owned artifacts. Artifact paths remain resolved by ArtifactPathResolver
 * from the existing `kernel.artifacts.cache_dir` key; this compiler does not
 * introduce container-specific artifact path configuration.
 *
 * Fingerprint exclusion policy remains owned by ConfigFingerprintInputBuilder
 * and FingerprintCalculator. Compiled-container compiler/builder/runtime boot
 * services do not read `kernel.fingerprint.*` configuration directly.
 *
 * It receives already resolved Phase A / module inputs and explicit config
 * source candidate arrays. It invokes ConfigKernel::compile(...) exactly once
 * per compile operation, builds deterministic fingerprint input through
 * ConfigFingerprintInputBuilder, calculates the fingerprint through
 * FingerprintCalculator, builds canonical envelopes through artifact builders,
 * and writes artifacts through ArtifactWriter.
 *
 * This compiler intentionally does not:
 *
 * - resolve BootstrapConfig;
 * - resolve ModulePlan;
 * - build EnvRepositoryInterface;
 * - discover config files;
 * - re-run config compile internally;
 * - calculate fingerprint input ad hoc;
 * - write files directly;
 * - read existing artifacts;
 * - verify cache clean/dirty state;
 * - reuse existing compiled config artifacts;
 * - emit artifact write metrics;
 * - emit fingerprint calculation metrics;
 * - depend on platform/cli;
 * - print stdout/stderr;
 * - invoke reset lifecycle;
 * - start UnitOfWork.
 *
 * Reuse policy:
 *
 * Current compile-side behavior always rebuilds and rewrites Kernel-owned
 * artifacts. It therefore never reuses an existing compiled config or compiled
 * container artifact without a verified matching fingerprint. Stored-fingerprint
 * comparison and clean/dirty/invalid decisions belong to CacheVerifier.
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
        private ContainerCompiler $containerCompiler,
        private CompiledContainerBuilder $compiledContainerBuilder,
        private ArtifactWriter $artifactWriter,
        private ArtifactPathResolver $pathResolver,
    ) {
    }

    /**
     * Compiles and writes all Kernel-owned artifacts.
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
     * @throws ArtifactWriteFailedException
     * @throws ConfigInvalidException
     * @throws ContainerCompileFailedException
     * @throws JsonFloatForbiddenException
     */
    public function compile(
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

        $fingerprint = $this->fingerprintCalculator->calculate($fingerprintInput);

        $containerGraph = $this->containerCompiler->compile($containerDescriptors);

        $writes = [
            $this->writeModuleManifest(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                modulePlan: $modulePlan,
                fingerprint: $fingerprint,
            ),
            $this->writeCompiledConfig(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                compiledConfig: $compiledConfig,
                fingerprint: $fingerprint,
            ),
            $this->writeCompiledContainer(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: $kernelConfig,
                fingerprint: $fingerprint,
                containerGraph: $containerGraph,
            ),
        ];

        return self::compileResult($writes);
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return array{name: non-empty-string, basename: non-empty-string, path: non-empty-string, bytes: int}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws ArtifactWriteFailedException
     */
    private function writeModuleManifest(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        ModulePlan $modulePlan,
        string $fingerprint,
    ): array {
        $envelope = $this->moduleManifestBuilder->build(
            modulePlan: $modulePlan,
            fingerprint: $fingerprint,
        );

        return self::writeResult(
            name: self::ARTIFACT_MODULE_MANIFEST,
            write: $this->artifactWriter->writePhpEnvelope(
                targetPath: $this->pathResolver->moduleManifestPath($bootstrapConfig, $kernelConfig),
                relativePath: $this->pathResolver->relativePath(
                    bootstrapConfig: $bootstrapConfig,
                    kernelConfig: $kernelConfig,
                    basename: ArtifactPathResolver::MODULE_MANIFEST_BASENAME,
                ),
                envelope: $envelope,
            ),
        );
    }

    /**
     * @param array<string,mixed> $kernelConfig
     * @param array<string,mixed> $compiledConfig
     *
     * @return array{name: non-empty-string, basename: non-empty-string, path: non-empty-string, bytes: int}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws ArtifactWriteFailedException
     */
    private function writeCompiledConfig(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        array $compiledConfig,
        string $fingerprint,
    ): array {
        $envelope = $this->compiledConfigBuilder->build(
            compiledConfig: $compiledConfig,
            fingerprint: $fingerprint,
        );

        return self::writeResult(
            name: self::ARTIFACT_CONFIG,
            write: $this->artifactWriter->writePhpEnvelope(
                targetPath: $this->pathResolver->configPath($bootstrapConfig, $kernelConfig),
                relativePath: $this->pathResolver->relativePath(
                    bootstrapConfig: $bootstrapConfig,
                    kernelConfig: $kernelConfig,
                    basename: ArtifactPathResolver::CONFIG_BASENAME,
                ),
                envelope: $envelope,
            ),
        );
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return array{name: non-empty-string, basename: non-empty-string, path: non-empty-string, bytes: int}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     * @throws ArtifactPathInvalidException
     * @throws ArtifactWriteFailedException
     */
    private function writeCompiledContainer(
        BootstrapConfig $bootstrapConfig,
        array $kernelConfig,
        string $fingerprint,
        DefinitionGraph $containerGraph,
    ): array {
        $envelope = $this->compiledContainerBuilder->build(
            graph: $containerGraph,
            fingerprint: $fingerprint,
        );

        return self::writeResult(
            name: self::ARTIFACT_CONTAINER,
            write: $this->artifactWriter->writePhpEnvelope(
                targetPath: $this->pathResolver->containerPath($bootstrapConfig, $kernelConfig),
                relativePath: $this->pathResolver->relativePath(
                    bootstrapConfig: $bootstrapConfig,
                    kernelConfig: $kernelConfig,
                    basename: ArtifactPathResolver::CONTAINER_BASENAME,
                ),
                envelope: $envelope,
            ),
        );
    }

    /**
     * @param array{basename: non-empty-string, bytes: int, path: non-empty-string} $write
     *
     * @return array{name: non-empty-string, basename: non-empty-string, path: non-empty-string, bytes: int}
     */
    private static function writeResult(string $name, array $write): array
    {
        return [
            'name' => self::safeArtifactName($name),
            'basename' => self::safeBasename($write['basename']),
            'path' => self::safeRelativePath($write['path']),
            'bytes' => self::safeCount($write['bytes']),
        ];
    }

    /**
     * @param list<array{name: non-empty-string, basename: non-empty-string, path: non-empty-string, bytes: int}> $writes
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
    private static function compileResult(array $writes): array
    {
        \usort(
            $writes,
            static function (array $left, array $right): int {
                return \strcmp($left['path'], $right['path'])
                    ?: \strcmp($left['name'], $right['name'])
                        ?: \strcmp($left['basename'], $right['basename']);
            },
        );

        $writtenBytes = 0;

        foreach ($writes as $write) {
            $writtenBytes = self::safeCount($writtenBytes + $write['bytes']);
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'rebuilt' => true,
            'reused' => false,
            'reason' => self::REASON_REBUILT,
            'artifacts' => $writes,
            'counts' => [
                'artifact_count' => self::safeCount(\count($writes)),
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
            ArtifactPathResolver::MODULE_MANIFEST_BASENAME,
            ArtifactPathResolver::CONFIG_BASENAME,
            ArtifactPathResolver::CONTAINER_BASENAME => $basename,
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
