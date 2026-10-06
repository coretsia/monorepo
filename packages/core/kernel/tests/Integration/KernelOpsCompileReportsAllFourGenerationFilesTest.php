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

use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsCompileReportsAllFourGenerationFilesTest extends TestCase
{
    public function testCompileProjectionUsesCanonicalFourArtifactOrderAndShape(): void
    {
        $input = [
            'generationId' => 'generation-001',
            'artifacts' => [
                ['identity' => 'module-manifest@1', 'basename' => 'module-manifest.php', 'path' => '/private/a'],
                ['identity' => 'config@1', 'basename' => 'config.php', 'path' => '/private/b'],
                ['identity' => 'container@1', 'basename' => 'container.php', 'path' => '/private/c'],
                [
                    'identity' => 'artifact-generation@1',
                    'basename' => 'generation-manifest.php',
                    'path' => '/private/d',
                ],
            ],
        ];

        $method = new ReflectionMethod(KernelOpsFacade::class, 'compileData');
        $data = $method->invoke(null, $input);

        self::assertSame(['artifacts', 'generation_id'], \array_keys($data));
        self::assertSame(
            [
                ['basename' => 'module-manifest.php', 'identity' => 'module-manifest@1'],
                ['basename' => 'config.php', 'identity' => 'config@1'],
                ['basename' => 'container.php', 'identity' => 'container@1'],
                ['basename' => 'generation-manifest.php', 'identity' => 'artifact-generation@1'],
            ],
            $data['artifacts'],
        );

        foreach ($data['artifacts'] as $artifact) {
            self::assertSame(['basename', 'identity'], \array_keys($artifact));
        }
    }
}
