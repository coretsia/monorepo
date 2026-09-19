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

final class CacheVerifyIgnoresMtimeAndPermissionsTest extends TestCase
{
    private string $applicationRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationRoot = ArtifactPipelineTestSupport::temporaryRoot('cache-verify-ignores-metadata');

        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->applicationRoot);

        parent::tearDown();
    }

    public function testTouchingOnlyMtimeKeepsCacheClean(): void
    {
        self::assertClean();

        foreach (ArtifactPipelineTestSupport::currentArtifactPaths($this->applicationRoot) as $path) {
            self::assertTrue(\touch($path, \time() + 3600));
        }

        self::assertClean();
    }

    public function testChangingOnlyPermissionsKeepsCacheClean(): void
    {
        self::assertClean();

        foreach (ArtifactPipelineTestSupport::currentArtifactPaths($this->applicationRoot) as $path) {
            @\chmod($path, 0600);
        }

        self::assertClean();

        foreach (ArtifactPipelineTestSupport::currentArtifactPaths($this->applicationRoot) as $path) {
            @\chmod($path, 0644);
        }

        self::assertClean();
    }

    public function testBytesUnchangedRemainTheOnlyCleanDirtyCriterion(): void
    {
        $before = ArtifactPipelineTestSupport::artifactBytes($this->applicationRoot);

        foreach (ArtifactPipelineTestSupport::currentArtifactPaths($this->applicationRoot) as $path) {
            self::assertTrue(\touch($path, \time() + 7200));
            @\chmod($path, 0600);
        }

        $after = ArtifactPipelineTestSupport::artifactBytes($this->applicationRoot);

        self::assertSame($before, $after);
        self::assertClean();
    }

    private function assertClean(): void
    {
        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
        );

        self::assertSame('clean', $result['outcome']);
        self::assertTrue($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertFalse($result['invalid']);
        self::assertSame(0, $result['counts']['dirty_artifact_count']);
        self::assertSame(0, $result['counts']['invalid_artifact_count']);
        self::assertSame(0, $result['counts']['missing_artifact_count']);
    }
}
