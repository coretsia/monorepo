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

        $outOfScope = self::section($source, '**Out of scope:**', '## Runtime responsibilities');

        self::assertStringNotContainsString(
            'config artifact writing',
            \strtolower($outOfScope),
        );

        self::assertStringContainsString(
            'Kernel-owned artifact production',
            $source,
        );

        self::assertStringContainsString(
            'Kernel-owned cache verification',
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

    public function testCompiledContainerReusesExistingKernelArtifactPathPolicy(): void
    {
        $pathResolver = self::kernelSource('src/Artifacts/Paths/ArtifactPathResolver.php');
        $artifactCompiler = self::kernelSource('src/Artifacts/Compiler/ArtifactCompiler.php');
        $cacheVerifier = self::kernelSource('src/Artifacts/Verifier/CacheVerifier.php');

        self::assertStringContainsString(
            "private const string KEY_ARTIFACTS = 'artifacts';",
            $pathResolver,
        );
        self::assertStringContainsString(
            "private const string KEY_CACHE_DIR = 'cache_dir';",
            $pathResolver,
        );
        self::assertStringContainsString(
            "private const string CANONICAL_CACHE_DIR = 'var/cache';",
            $pathResolver,
        );
        self::assertStringContainsString(
            "public const string CONTAINER_BASENAME = 'container.php';",
            $pathResolver,
        );
        self::assertStringContainsString(
            'public function containerPath(',
            $pathResolver,
        );

        self::assertStringContainsString(
            '$this->pathResolver->containerPath($bootstrapConfig, $kernelConfig)',
            $artifactCompiler,
        );
        self::assertStringContainsString(
            'basename: ArtifactPathResolver::CONTAINER_BASENAME',
            $artifactCompiler,
        );

        self::assertStringContainsString(
            '$this->pathResolver->containerPath($bootstrapConfig, $kernelConfig)',
            $cacheVerifier,
        );
        self::assertStringContainsString(
            'basename: ArtifactPathResolver::CONTAINER_BASENAME',
            $cacheVerifier,
        );
    }

    public function testCompiledContainerDoesNotIntroduceContainerSpecificKernelConfig(): void
    {
        $config = require self::kernelPath('config/kernel.php');

        self::assertIsArray($config);

        self::assertArrayHasKey('artifacts', $config);
        self::assertArrayHasKey('cache_dir', $config['artifacts']);
        self::assertSame('var/cache', $config['artifacts']['cache_dir']);

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
            'src/Container/ContainerCompiler.php' => self::kernelSource('src/Container/ContainerCompiler.php'),
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
