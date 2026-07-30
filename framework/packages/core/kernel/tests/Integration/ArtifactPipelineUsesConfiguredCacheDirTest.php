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

namespace Coretsia\Kernel\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ArtifactPipelineUsesConfiguredCacheDirTest extends TestCase
{
    public function testCompilerAndVerifierUseResolvedArtifactsCacheDirectory(): void
    {
        $skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot(
            'configured-artifacts-cache-dir',
        );

        $artifactsCacheDir = 'var/artifacts_cache';

        try {
            $compileResult = ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $skeletonRoot,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                artifactsCacheDir: $artifactsCacheDir,
            );

            $paths = ArtifactPipelineTestSupport::currentArtifactPaths(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame(
                [
                    'schemaVersion',
                    'generationId',
                    'artifacts',
                ],
                \array_keys($compileResult),
            );
            self::assertSame(1, $compileResult['schemaVersion']);
            self::assertIsString($compileResult['generationId']);
            self::assertMatchesRegularExpression(
                '/\A[a-f0-9]{64}\z/',
                $compileResult['generationId'],
            );
            self::assertSame(
                [
                    [
                        'identity' => 'module-manifest@1',
                        'basename' => 'module-manifest.php',
                    ],
                    [
                        'identity' => 'config@1',
                        'basename' => 'config.php',
                    ],
                    [
                        'identity' => 'container@1',
                        'basename' => 'container.php',
                    ],
                    [
                        'identity' => 'artifact-generation@1',
                        'basename' => 'generation-manifest.php',
                    ],
                ],
                $compileResult['artifacts'],
            );

            self::assertSame(
                [
                    'config.php',
                    'container.php',
                    'generation-manifest.php',
                    'module-manifest.php',
                ],
                \array_keys($paths),
            );

            foreach ($paths as $path) {
                self::assertFileExists($path);
            }

            $configuredArtifactRoot =
                ArtifactPipelineTestSupport::artifactRoot(
                    skeletonRoot: $skeletonRoot,
                    artifactsCacheDir: $artifactsCacheDir,
                );

            self::assertFileExists($configuredArtifactRoot . '/current');
            self::assertFileExists($configuredArtifactRoot . '/generation.lock');
            self::assertDirectoryExists($configuredArtifactRoot . '/generations');

            $currentGeneration = ArtifactPipelineTestSupport::currentGeneration(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame(
                $compileResult['generationId'],
                $currentGeneration->generationId()->value(),
            );

            self::assertDirectoryDoesNotExist(
                ArtifactPipelineTestSupport::artifactRoot($skeletonRoot),
            );

            $verifyResult = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame(1, $verifyResult['schemaVersion']);
            self::assertSame('clean', $verifyResult['outcome']);
            self::assertTrue($verifyResult['clean']);
            self::assertFalse($verifyResult['dirty']);
            self::assertFalse($verifyResult['invalid']);
            self::assertSame(
                $compileResult['generationId'],
                $verifyResult['expectedGenerationId'],
            );
            self::assertSame(
                $compileResult['generationId'],
                $verifyResult['currentGenerationId'],
            );
            self::assertCount(4, $verifyResult['artifacts']);
        } finally {
            ArtifactPipelineTestSupport::removeTree($skeletonRoot);
        }
    }
}
