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

final class ArtifactsRerunNoDiffTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot('artifacts-rerun-no-diff');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testArtifactCompileRerunProducesIdenticalBytes(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $firstBytes = ArtifactPipelineTestSupport::artifactBytes($this->skeletonRoot);

        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $secondBytes = ArtifactPipelineTestSupport::artifactBytes($this->skeletonRoot);

        self::assertSame($firstBytes, $secondBytes);
    }

    public function testArtifactCompileWritesOnlyKernelOwnedArtifacts(): void
    {
        $result = ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        self::assertSame(1, $result['schemaVersion']);
        self::assertTrue($result['rebuilt']);
        self::assertFalse($result['reused']);
        self::assertSame('rebuilt', $result['reason']);
        self::assertSame(3, $result['counts']['artifact_count']);

        self::assertSame(
            [
                'config.php',
                'container.php',
                'module-manifest.php',
            ],
            \array_keys(ArtifactPipelineTestSupport::artifactBytes($this->skeletonRoot)),
        );

        self::assertFileDoesNotExist($this->skeletonRoot . '/var/cache/web/routes.php');
    }
}
