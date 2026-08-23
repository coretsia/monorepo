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

namespace Coretsia\Kernel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class KernelArtifactsDocsAndRegistryConsistencyContractTest extends TestCase
{
    public function testArtifactsAndFingerprintSsotDoesNotRedefineGlobalEnvelopeLaw(): void
    {
        $source = self::repoFile('docs/ssot/artifacts-and-fingerprint.md');
        $plain = self::markdownPlainText($source);

        self::assertStringContainsString(
            'MUST NOT redefine',
            $plain,
        );

        self::assertStringContainsString(
            'global artifact envelope',
            $plain,
        );

        self::assertStringContainsString(
            'canonical artifact envelope shape',
            $plain,
        );

        self::assertStringContainsString(
            'docs/ssot/artifacts.md',
            $plain,
        );

        self::assertStringNotContainsString(
            'The canonical artifact envelope is defined as:',
            $source,
        );

        self::assertStringNotContainsString(
            '| name | owner | schema',
            \strtolower($source),
        );
    }

    public function testCacheVerifySsotDoesNotRedefineArtifactRegistryRows(): void
    {
        $source = self::repoFile('docs/ssot/cache-verify.md');
        $plain = self::markdownPlainText($source);

        self::assertStringContainsString(
            'MUST NOT redefine',
            $plain,
        );

        self::assertStringContainsString(
            'artifact registry',
            $plain,
        );

        self::assertStringContainsString(
            'docs/ssot/artifacts.md',
            $plain,
        );

        self::assertStringNotContainsString(
            '| module-manifest@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| config@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| container@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| routes@1 |',
            $source,
        );
    }

    public function testCompilerAndVerifierOperationResultContractsAreDocumented(): void
    {
        $artifactSource = self::repoFile('docs/ssot/artifacts-and-fingerprint.md');
        $cacheVerifySource = self::repoFile('docs/ssot/cache-verify.md');

        foreach (
            [
                "'schemaVersion' => 1",
                "'generationId' => '<lowercase-sha256>'",
                "'identity' => 'module-manifest@1'",
                "'identity' => 'config@1'",
                "'identity' => 'container@1'",
                "'identity' => 'artifact-generation@1'",
                'The artifact list MUST contain exactly those four entries in that order.',
                'The compile result MUST NOT contain `rebuilt`, `reused`, `reason`, `counts`, `bytes`, or `path`.',
            ] as $requiredText
        ) {
            self::assertStringContainsString(
                $requiredText,
                $artifactSource,
            );
        }

        self::assertStringNotContainsString(
            'result entries describe the three runtime artifacts supplied to publication',
            $artifactSource,
        );

        foreach (
            [
                '`expectedGenerationId`',
                '`currentGenerationId`',
                'ConfigInvalidException::fromValidationResult',
                'MUST NOT invoke `ConfigValidator`',
            ] as $requiredText
        ) {
            self::assertStringContainsString(
                $requiredText,
                $cacheVerifySource,
            );
        }
    }

    public function testCanonicalConfigSourceOperationBoundaryIsDocumented(): void
    {
        $artifactSource = self::repoFile('docs/ssot/artifacts-and-fingerprint.md');
        $cacheVerifySource = self::repoFile('docs/ssot/cache-verify.md');
        $readme = self::repoFile('framework/packages/core/kernel/README.md');
        $docs = $artifactSource . "\n" . $cacheVerifySource;

        foreach (
            [
                'ConfigSourceSet',
                'ConfigSourceLocationBuilder',
                'KernelArtifactOperation',
            ] as $requiredText
        ) {
            self::assertStringContainsString($requiredText, $docs);
        }

        self::assertStringContainsString(
            'ArtifactCompiler and CacheVerifier consume one already-built ConfigSourceSet',
            $docs,
        );

        foreach (
            [
                'ComposerPackageInstallPathResolver',
                'ConfigSourceLocationBuilder',
                'KernelArtifactOperation',
            ] as $compileHostService
        ) {
            self::assertStringContainsString($compileHostService, $readme);
        }
    }

    public function testObservabilitySsotContainsRegisteredArtifactFingerprintAndCacheVerifyMetricNames(): void
    {
        $source = self::repoFile('docs/ssot/observability.md');

        foreach (
            [
                'kernel.artifacts_write_total',
                'kernel.artifacts_write_duration_ms',
                'kernel.fingerprint_calculate_total',
                'kernel.fingerprint_calculate_duration_ms',
                'kernel.cache_verify_total',
                'kernel.cache_verify_duration_ms',
            ] as $metricName
        ) {
            self::assertStringContainsString($metricName, $source);
        }

        foreach (
            [
                'kernel.artifacts_write',
                'kernel.fingerprint_calculate',
                'kernel.cache_verify',
            ] as $spanName
        ) {
            self::assertStringContainsString($spanName, $source);
        }

        self::assertStringContainsString('outcome', $source);
        self::assertStringNotContainsString('fingerprint` label', $source);
    }

    public function testKernelReadmeNoLongerListsConfigArtifactWritingAsOutOfScope(): void
    {
        $source = self::repoFile('framework/packages/core/kernel/README.md');

        $outOfScope = self::section($source, 'Out of scope:', '## Runtime responsibilities');

        self::assertStringNotContainsString(
            'config artifact writing',
            \strtolower($outOfScope),
        );

        self::assertStringContainsString(
            'Kernel-owned artifact production, fingerprint, and cache verification services:',
            $source,
        );

        self::assertStringContainsString(
            'artifact/fingerprint/container-compile/cache services are registered by `KernelServiceProvider` as factories only',
            $source,
        );

        self::assertStringContainsString(
            'container-compile',
            $source,
        );
    }

    public function testArtifactCacheDirFallbackBelongsToBootstrapPhaseA(): void
    {
        $config = require self::kernelPath('config/kernel.php');
        $rules = require self::kernelPath('config/rules.php');

        self::assertIsArray($config);
        self::assertIsArray($rules);

        self::assertSame(
            'var/cache',
            $config['boot']['default_artifacts_cache_dir'] ?? null,
        );

        self::assertSame(
            'relative-safe-path',
            $rules['keys']['boot']['keys']['default_artifacts_cache_dir']['type'] ?? null,
        );

        self::assertArrayNotHasKey('artifacts', $config);
        self::assertArrayNotHasKey('artifacts', $rules['keys']);
    }

    public function testCompiledContainerUsesGenerationAwareKernelArtifactPathPolicy(): void
    {
        $pathResolver = self::kernelSource('src/Artifacts/Paths/ArtifactPathResolver.php');
        $artifactGeneration = self::kernelSource('src/Artifacts/Generation/ArtifactGeneration.php');
        $artifactCompiler = self::kernelSource('src/Artifacts/Compiler/ArtifactCompiler.php');
        $cacheVerifier = self::kernelSource('src/Artifacts/Verifier/CacheVerifier.php');

        self::assertStringContainsString(
            '$bootstrapConfig->artifactsCacheDir()',
            $pathResolver,
        );

        self::assertStringNotContainsString(
            'kernel.artifacts.cache_dir',
            $pathResolver,
        );

        self::assertStringNotContainsString(
            '$kernelConfig',
            $pathResolver,
        );

        self::assertStringNotContainsString(
            'CONTAINER_BASENAME',
            $pathResolver,
        );

        self::assertStringContainsString(
            "public const string CONTAINER_BASENAME = 'container.php';",
            $artifactGeneration,
        );

        self::assertStringContainsString(
            'public function artifactRoot(',
            $pathResolver,
        );

        self::assertStringContainsString(
            'artifactRoot: '
            . '$this->pathResolver->artifactRoot($bootstrapConfig),',
            $artifactCompiler,
        );

        self::assertStringContainsString(
            'basename: ArtifactGeneration::CONTAINER_BASENAME',
            $artifactCompiler,
        );

        self::assertStringNotContainsString(
            '$pathResolver->relativeCacheDirectory($bootstrapConfig)',
            $artifactCompiler,
        );

        self::assertStringNotContainsString(
            '$this->pathResolver->relativeCacheDirectory($bootstrapConfig)',
            $artifactCompiler,
        );

        self::assertStringNotContainsString(
            ". '/generations/current/'",
            $artifactCompiler,
        );

        self::assertStringContainsString(
            "'generationId' => \$generation->generationId()->value(),",
            $artifactCompiler,
        );

        foreach (
            [
                'module-manifest@1',
                'config@1',
                'container@1',
                'artifact-generation@1',
            ] as $identity
        ) {
            self::assertStringContainsString($identity, $artifactCompiler);
        }

        self::assertStringNotContainsString(
            '$this->pathResolver->containerPath($bootstrapConfig)',
            $artifactCompiler,
        );

        self::assertStringNotContainsString(
            'basename: ArtifactPathResolver::CONTAINER_BASENAME',
            $artifactCompiler,
        );

        self::assertStringContainsString(
            '$this->generationLocator->locate(',
            $cacheVerifier,
        );

        self::assertStringContainsString(
            '$this->pathResolver->artifactRoot($bootstrapConfig)',
            $cacheVerifier,
        );

        self::assertStringContainsString(
            'basename: ArtifactGeneration::CONTAINER_BASENAME',
            $cacheVerifier,
        );

        self::assertStringContainsString(
            '$this->pathResolver->relativeCacheDirectory($bootstrapConfig)',
            $cacheVerifier,
        );

        self::assertStringContainsString(
            ". '/generations/current/'",
            $cacheVerifier,
        );

        self::assertStringContainsString(
            'ArtifactGeneration::CONTAINER_BASENAME'
            . ' => $currentGeneration->containerPath()',
            $cacheVerifier,
        );

        self::assertStringNotContainsString(
            '$this->pathResolver->containerPath($bootstrapConfig)',
            $cacheVerifier,
        );

        self::assertStringNotContainsString(
            'basename: ArtifactPathResolver::CONTAINER_BASENAME',
            $cacheVerifier,
        );
    }

    public function testCompilerAndVerifierAssertCompiledValidationBeforeDownstreamWork(): void
    {
        $artifactCompiler = self::kernelSource('src/Artifacts/Compiler/ArtifactCompiler.php');
        $cacheVerifier = self::kernelSource('src/Artifacts/Verifier/CacheVerifier.php');

        foreach (
            [
                'ArtifactCompiler.php' => $artifactCompiler,
                'CacheVerifier.php' => $cacheVerifier,
            ] as $path => $source
        ) {
            self::assertSame(
                1,
                \substr_count(
                    $source,
                    '$this->configKernel->compile(',
                ),
                $path,
            );
            self::assertStringContainsString(
                'ConfigInvalidException::fromValidationResult(',
                $source,
                $path,
            );
            self::assertStringContainsString(
                "\$compiledConfig['validation']",
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                'ConfigValidator',
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                '->validate(',
                $source,
                $path,
            );
        }

        self::assertValidationGatePrecedes(
            path: 'ArtifactCompiler.php',
            source: $artifactCompiler,
            downstreamTokens: [
                '$this->runtimeContainerGraphCompiler->compile(',
                '$this->fingerprintInputBuilder->build(',
                '$this->fingerprintCalculator->calculate(',
                '$this->moduleManifestBuilder->build(',
                '$this->generationPublisher->publish(',
            ],
        );
        self::assertValidationGatePrecedes(
            path: 'CacheVerifier.php',
            source: $cacheVerifier,
            downstreamTokens: [
                '$this->runtimeContainerGraphCompiler->compile(',
                '$this->fingerprintInputBuilder->build(',
                '$this->fingerprintCalculator->calculate(',
                '$this->expectedGeneration(',
                '$this->generationLocator->locate(',
                '$this->artifactReader->readExactBytes(',
            ],
        );
    }

    public function testCompiledContainerDoesNotIntroduceContainerSpecificKernelConfig(): void
    {
        $config = require self::kernelPath('config/kernel.php');

        self::assertIsArray($config);

        self::assertArrayNotHasKey('artifacts', $config);
        self::assertSame(
            'var/cache',
            $config['boot']['default_artifacts_cache_dir'] ?? null,
        );

        self::assertArrayHasKey('fingerprint', $config);
        self::assertArrayHasKey('skeleton_ignore_prefixes', $config['fingerprint']);

        self::assertArrayNotHasKey('container', $config);
        self::assertArrayNotHasKey('container_compile', $config);
        self::assertArrayNotHasKey('compiled_container', $config);
        self::assertArrayNotHasKey('di', $config);
    }

    public function testCompiledContainerClassesDoNotReadFingerprintConfigurationDirectly(): void
    {
        $sources = [
            'src/Container/ContainerCompiler.php' => self::kernelSource(
                'src/Container/ContainerCompiler.php'
            ),
            'src/Artifacts/Builders/CompiledContainerBuilder.php' => self::kernelSource(
                'src/Artifacts/Builders/CompiledContainerBuilder.php'
            ),
            'src/Container/CompiledContainerFactory.php' => self::kernelSource(
                'src/Container/CompiledContainerFactory.php'
            ),
        ];

        foreach ($sources as $path => $source) {
            self::assertStringNotContainsString(
                'kernel.fingerprint',
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                'skeleton_ignore_prefixes',
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                'KEY_FINGERPRINT',
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                'fingerprintConfig',
                $source,
                $path,
            );
            self::assertStringNotContainsString(
                'kernelConfig',
                $source,
                $path,
            );
        }
    }

    private static function section(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = \strpos($source, $startNeedle);

        self::assertIsInt($start);

        $end = \strpos($source, $endNeedle, $start);

        self::assertIsInt($end);

        return \substr($source, $start, $end - $start);
    }

    /**
     * @param list<non-empty-string> $downstreamTokens
     */
    private static function assertValidationGatePrecedes(
        string $path,
        string $source,
        array $downstreamTokens,
    ): void {
        $validationGate = \strpos(
            $source,
            'if ($compiledConfig[\'validation\']->isFailure())',
        );

        self::assertIsInt($validationGate, $path);

        foreach ($downstreamTokens as $token) {
            $downstream = \strpos($source, $token);

            self::assertIsInt(
                $downstream,
                $path . ': ' . $token,
            );
            self::assertGreaterThan(
                $validationGate,
                $downstream,
                $path . ': ' . $token,
            );
        }
    }

    private static function repoFile(string $relativePath): string
    {
        $path = self::repoRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 6);
    }

    private static function markdownPlainText(string $source): string
    {
        return \str_replace(
            [
                '**',
                '`',
            ],
            '',
            $source,
        );
    }

    /**
     * @return non-empty-string
     */
    private static function kernelPath(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . \ltrim($relativePath, '/');

        if ($path === '' || !\is_file($path)) {
            self::fail('Kernel test fixture source file is missing: ' . $relativePath);
        }

        return $path;
    }

    private static function kernelSource(string $relativePath): string
    {
        $source = \file_get_contents(self::kernelPath($relativePath));

        if (!\is_string($source)) {
            self::fail('Kernel test fixture source file is unreadable: ' . $relativePath);
        }

        return $source;
    }
}
