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

final class ModuleResolutionOrchestratorLoadsApplicationOnlyCustomPresetTest extends TestCase
{
    public function testLoadsApplicationOnlyCustomPresetSelectedByBootstrapPreset(): void
    {
        $result = AppBuilder::resolveApplicationOnlyPreset($this);

        try {
            $applicationRoot = $result->applicationRoot();
            $modulePlan = $result->modulePlan();

            self::assertTrue(
                \is_file($applicationRoot . '/config/modes/worker-only.php'),
                'worker-only application preset fixture must exist',
            );

            self::assertFalse(
                \is_file(\dirname(__DIR__, 2) . '/resources/modes/worker-only.php'),
                'worker-only Kernel package default preset fixture must not exist',
            );

            self::assertFalse(
                \is_file($applicationRoot . '/config/modules.php'),
                'application-root module selection fixture must not be required',
            );

            self::assertFalse(
                \is_file($applicationRoot . '/apps/web/config/modules.php'),
                'app-local module selection fixture must not be required',
            );
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
        } finally {
            AppBuilder::removeTree($result->applicationRoot());
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
