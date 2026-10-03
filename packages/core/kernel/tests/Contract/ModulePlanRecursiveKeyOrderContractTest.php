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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use PHPUnit\Framework\TestCase;

final class ModulePlanRecursiveKeyOrderContractTest extends TestCase
{
    public function testModulePlanExportKeepsCanonicalRecursiveKeyOrder(): void
    {
        $plan = new ModulePlan(
            app: 'api',
            enabled: [
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
                self::moduleId('core.foundation'),
            ],
            excluded: [
                self::moduleId('platform.http'),
            ],
            topologicalOrder: [
                self::moduleId('core.foundation'),
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
            ],
            modules: [
                new ModulePlanEntry(
                    moduleId: self::moduleId('platform.cli'),
                    composerName: 'coretsia/platform-cli',
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.kernel'),
                    composerName: 'coretsia/core-kernel',
                    requires: [
                        self::moduleId('core.foundation'),
                    ],
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.foundation'),
                    composerName: 'coretsia/core-foundation',
                ),
            ],
        );

        $payload = $plan->toArray();

        self::assertSame(
            ['app', 'enabled', 'excluded', 'modules', 'schemaVersion', 'topologicalOrder'],
            \array_keys($payload),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            \array_keys($payload['modules']),
        );

        foreach ($payload['modules'] as $moduleEntry) {
            self::assertSame(
                [
                    'composerName',
                    'conflicts',
                    'moduleId',
                    'requires',
                ],
                \array_keys($moduleEntry),
            );
        }

        self::assertSame(
            [
                'composerName' => 'coretsia/core-foundation',
                'conflicts' => [],
                'moduleId' => 'core.foundation',
                'requires' => [],
            ],
            $payload['modules']['core.foundation'],
        );

        self::assertSame(
            [
                'composerName' => 'coretsia/core-kernel',
                'conflicts' => [],
                'moduleId' => 'core.kernel',
                'requires' => [
                    'core.foundation',
                ],
            ],
            $payload['modules']['core.kernel'],
        );

        self::assertSame(
            [
                'composerName' => 'coretsia/platform-cli',
                'conflicts' => [],
                'moduleId' => 'platform.cli',
                'requires' => [],
            ],
            $payload['modules']['platform.cli'],
        );
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }
}
