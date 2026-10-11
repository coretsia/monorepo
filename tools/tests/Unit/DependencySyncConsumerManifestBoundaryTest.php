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

namespace Coretsia\Tools\Tests\Unit;

use Coretsia\Tools\Support\ReleaseLine;
use PHPUnit\Framework\TestCase;

final class DependencySyncConsumerManifestBoundaryTest extends TestCase
{
    public function testSkeletonManifestRemainsMinimalAndSyncAddsNoMonorepoRepositoryContract(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $manifest = \json_decode(
            (string) \file_get_contents($repoRoot . '/packages/applications/skeleton/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $require = $manifest['require'] ?? null;

        self::assertIsArray($require);
        self::assertCount(2, $require);
        self::assertSame('^8.4', $require['php'] ?? null);
        self::assertSame(
            ReleaseLine::fromFile($repoRoot . '/tools/release/release-line.json')->publicConstraint(),
            $require['coretsia/framework'] ?? null,
        );
        self::assertArrayNotHasKey('repositories', $manifest);

        $script = \file_get_contents($repoRoot . '/packages/applications/skeleton/bin/dependency-sync.php');

        self::assertIsString($script);
        self::assertStringNotContainsString('repositories', $script);
        self::assertStringNotContainsString('packages/', $script);
        self::assertStringNotContainsString('tools/', $script);
    }
}
