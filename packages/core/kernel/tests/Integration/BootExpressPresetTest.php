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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Tests\Support\AppBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class BootExpressPresetTest extends TestCase
{
    public function testExpressPresetBootsWithCurrentlyImplementedModuleSubset(): void
    {
        $result = AppBuilder::bootExpress($this);
        try {
            $plan = $result->modulePlan();
            self::assertSame('web', $plan->app());
            self::assertSame(
                ['core.foundation', 'core.kernel'],
                \array_map(
                    static fn (ModuleId $id): string => $id->value(),
                    $plan->enabled(),
                )
            );
            self::assertSame(
                ['core.foundation', 'core.kernel'],
                \array_map(
                    static fn (ModuleId $id): string => $id->value(),
                    $plan->topologicalOrder(),
                )
            );
            self::assertNotContains('platform.http', $plan->toArray()['enabled']);
            foreach (['module-manifest.php', 'config.php', 'container.php', 'generation-manifest.php'] as $name) {
                self::assertArrayHasKey($name, $result->artifactPaths());
                self::assertFileExists($result->artifactPaths()[$name]);
            }
            self::assertInstanceOf(ContainerInterface::class, $result->container());
        } finally {
            AppBuilder::removeTree($result->applicationRoot());
        }
    }
}
