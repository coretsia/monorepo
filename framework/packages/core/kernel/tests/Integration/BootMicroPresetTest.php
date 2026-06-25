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

final class BootMicroPresetTest extends TestCase
{
    public function testBootsMicroPresetThroughCompiledArtifacts(): void
    {
        $result = AppBuilder::bootMicro($this);

        try {
            $skeletonRoot = $result->skeletonRoot();
            $modulePlan = $result->modulePlan();
            $artifactPaths = $result->artifactPaths();

            self::assertFalse(
                \is_file($skeletonRoot . '/config/modes/micro.php'),
                'micro skeleton preset override fixture must not exist',
            );

            self::assertSame('micro', $modulePlan->preset());
            self::assertSame('web', $modulePlan->app());

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                self::moduleIdValues($modulePlan->enabled()),
            );

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                self::moduleIdValues($modulePlan->topologicalOrder()),
            );

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                \array_keys($modulePlan->modules()),
            );

            foreach (
                [
                    'module-manifest.php',
                    'config.php',
                    'container.php',
                ] as $basename
            ) {
                self::assertArrayHasKey($basename, $artifactPaths);

                self::assertTrue(
                    \is_file($artifactPaths[$basename]),
                    $basename . ' artifact must exist',
                );
            }

            self::assertInstanceOf(ContainerInterface::class, $result->container());
        } finally {
            AppBuilder::removeTree($result->skeletonRoot());
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }
}
