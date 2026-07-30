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

use Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest extends TestCase
{
    public function testArtifactOnlyBootHardFailsWhenCurrentGenerationContainerArtifactIsMissing(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'missing-container-artifact',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $containerPath = $generation->containerPath();

            self::assertTrue(
                \unlink($containerPath),
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail('Expected invalid artifact generation failure.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertSame(
                    ArtifactRuntimeBootException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
                    $exception->reason(),
                );
                self::assertSame(
                    'CORETSIA_ARTIFACT_RUNTIME_BOOT_FAILED: '
                    . 'artifact-runtime-boot-generation-invalid',
                    $exception->getMessage(),
                );

                self::assertStringNotContainsString(
                    $containerPath,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    \dirname($containerPath),
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    \sys_get_temp_dir(),
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'No such file',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'failed to open stream',
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
