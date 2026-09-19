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

final class FingerprintIgnoresApplicationVarTest extends TestCase
{
    private string $applicationRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationRoot = ArtifactPipelineTestSupport::temporaryRoot('fingerprint-ignores-application-var');

        ArtifactPipelineTestSupport::writeRootConfig(
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->applicationRoot);

        parent::tearDown();
    }

    public function testFingerprintIgnoresApplicationVarCacheAndMaintenanceChanges(): void
    {
        $before = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
        );

        \mkdir($this->applicationRoot . '/var/cache/web', 0777, true);
        \mkdir($this->applicationRoot . '/var/maintenance', 0777, true);

        \file_put_contents(
            $this->applicationRoot . '/var/cache/web/generated-noise.txt',
            "ignored-cache-noise\n",
        );

        \file_put_contents(
            $this->applicationRoot . '/var/maintenance/maintenance-noise.txt',
            "ignored-maintenance-noise\n",
        );

        $after = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
        );

        self::assertSame($before, $after);
    }
}
