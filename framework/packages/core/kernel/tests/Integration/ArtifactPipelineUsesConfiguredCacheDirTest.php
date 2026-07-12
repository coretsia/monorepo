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

            $paths = ArtifactPipelineTestSupport::artifactPaths(
                skeletonRoot: $skeletonRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame(
                [
                    'var/artifacts_cache/web/config.php',
                    'var/artifacts_cache/web/container.php',
                    'var/artifacts_cache/web/module-manifest.php',
                ],
                \array_column(
                    $compileResult['artifacts'],
                    'path',
                ),
            );

            foreach ($paths as $path) {
                self::assertFileExists($path);
            }

            foreach (
                ArtifactPipelineTestSupport::artifactPaths($skeletonRoot) as $defaultPath
            ) {
                self::assertFileDoesNotExist($defaultPath);
            }

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
