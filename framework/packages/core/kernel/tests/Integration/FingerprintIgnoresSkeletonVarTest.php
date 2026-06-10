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

final class FingerprintIgnoresSkeletonVarTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot('fingerprint-ignores-skeleton-var');

        ArtifactPipelineTestSupport::writeRootConfig(
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testFingerprintIgnoresSkeletonVarCacheAndMaintenanceChanges(): void
    {
        $before = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        \mkdir($this->skeletonRoot . '/var/cache/web', 0777, true);
        \mkdir($this->skeletonRoot . '/var/maintenance', 0777, true);

        \file_put_contents(
            $this->skeletonRoot . '/var/cache/web/generated-noise.txt',
            "ignored-cache-noise\n",
        );

        \file_put_contents(
            $this->skeletonRoot . '/var/maintenance/maintenance-noise.txt',
            "ignored-maintenance-noise\n",
        );

        $after = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        self::assertSame($before, $after);
    }
}
