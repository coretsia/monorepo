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
                    'var/artifacts_cache/web/' . 'generations/current/' . 'config.php',
                    'var/artifacts_cache/web/' . 'generations/current/' . 'container.php',
                    'var/artifacts_cache/web/' . 'generations/current/' . 'module-manifest.php',
                ],
                \array_column(
                    $compileResult['artifacts'],
                    'path',
                ),
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

            self::assertDirectoryDoesNotExist(
                ArtifactPipelineTestSupport::artifactRoot($skeletonRoot),
            );

            $verifyResult = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame('clean', $verifyResult['outcome']);
            self::assertTrue($verifyResult['clean']);
            self::assertFalse($verifyResult['dirty']);
            self::assertFalse($verifyResult['invalid']);
        } finally {
            ArtifactPipelineTestSupport::removeTree($skeletonRoot);
        }
    }
}
