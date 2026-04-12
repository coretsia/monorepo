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

namespace Coretsia\Tools\Testing\Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class MonorepoSmokeTest extends TestCase
{
    public function testHarnessBootsAndCanonicalEntryFilesExist(): void
    {
        $frameworkRoot = $this->frameworkRoot();
        $repoRoot = $this->repoRoot($frameworkRoot);

        self::assertFileExists($repoRoot . '/composer.json');
        self::assertFileExists($frameworkRoot . '/composer.json');
        self::assertFileExists($repoRoot . '/skeleton/composer.json');

        self::assertFileExists($frameworkRoot . '/tools/build/sync_composer_repositories.php');
    }

    private function frameworkRoot(): string
    {
        // framework/tools/testing/tests/Smoke -> framework (levels: Smoke -> tests -> testing -> tools)
        $frameworkRoot = dirname(__DIR__, 4);

        return rtrim(str_replace('\\', '/', $frameworkRoot), '/');
    }

    private function repoRoot(string $frameworkRoot): string
    {
        // <repo>/framework -> <repo>
        return rtrim(str_replace('\\', '/', dirname($frameworkRoot)), '/');
    }
}
