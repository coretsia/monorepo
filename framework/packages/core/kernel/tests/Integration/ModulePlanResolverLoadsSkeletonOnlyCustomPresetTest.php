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

final class ModulePlanResolverLoadsSkeletonOnlyCustomPresetTest extends TestCase
{
    public function testLoadsSkeletonOnlyCustomPresetSelectedByBootstrapPreset(): void
    {
        $result = AppBuilder::resolveSkeletonOnlyPreset($this);

        try {
            $skeletonRoot = $result->skeletonRoot();
            $modulePlan = $result->modulePlan();

            self::assertTrue(
                \is_file($skeletonRoot . '/config/modes/worker-only.php'),
                'worker-only skeleton preset fixture must exist',
            );

            self::assertFalse(
                \is_file(\dirname(__DIR__, 2) . '/resources/modes/worker-only.php'),
                'worker-only framework default preset fixture must not exist',
            );

            self::assertFalse(
                \is_file($skeletonRoot . '/config/modules.php'),
                'skeleton module selection fixture must not be required',
            );

            self::assertFalse(
                \is_file($skeletonRoot . '/apps/web/config/modules.php'),
                'app-local module selection fixture must not be required',
            );

            self::assertSame('worker-only', $modulePlan->preset());
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

            self::assertSame([], self::moduleIdValues($modulePlan->optionalMissing()));
            self::assertSame([], $modulePlan->warnings());

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                \array_keys($modulePlan->modules()),
            );
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
