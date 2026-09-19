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

final class FingerprintDoesNotDependOnArtifactsCacheDirTest extends TestCase
{
    public function testChangingOnlyArtifactsCacheDirectoryDoesNotChangeFingerprint(): void
    {
        $applicationRoot = ArtifactPipelineTestSupport::temporaryRoot(
            'fingerprint-artifacts-cache-dir',
        );

        try {
            ArtifactPipelineTestSupport::writeRootConfig(
                applicationRoot: $applicationRoot,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $defaultFingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                applicationRoot: $applicationRoot,
                artifactsCacheDir: 'var/cache',
            );

            $customFingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                applicationRoot: $applicationRoot,
                artifactsCacheDir: 'var/artifacts_cache',
            );

            self::assertSame(
                $defaultFingerprint,
                $customFingerprint,
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($applicationRoot);
        }
    }

    public function testResolvedArtifactsCacheDirectoryIsAlwaysIgnoredAsGeneratedOutput(): void
    {
        $applicationRoot = ArtifactPipelineTestSupport::temporaryRoot(
            'fingerprint-custom-artifact-noise',
        );

        $artifactsCacheDir = 'var/artifacts_cache';

        try {
            ArtifactPipelineTestSupport::writeRootConfig(
                applicationRoot: $applicationRoot,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $before = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                applicationRoot: $applicationRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            $directory = $applicationRoot
                . '/'
                . $artifactsCacheDir
                . '/web';

            \mkdir($directory, 0777, true);

            \file_put_contents(
                $directory . '/generated-noise.txt',
                "generated artifact noise\n",
            );

            $after = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                applicationRoot: $applicationRoot,
                artifactsCacheDir: $artifactsCacheDir,
            );

            self::assertSame($before, $after);
        } finally {
            ArtifactPipelineTestSupport::removeTree($applicationRoot);
        }
    }
}
