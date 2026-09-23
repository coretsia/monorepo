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

final class ModulePlanShapeContractTest extends TestCase
{
    public function testModulePlanExportsExactCanonicalPayloadAndPreservesDependencyOrder(): void
    {
        $foundation = ModuleId::fromString('core.foundation');
        $kernel = ModuleId::fromString('core.kernel');
        $cli = ModuleId::fromString('platform.cli');
        $plan = new ModulePlan(
            app: 'api',
            enabled: [$kernel, $cli, $foundation],
            excluded: [ModuleId::fromString('platform.http')],
            topologicalOrder: [$foundation, $cli, $kernel],
            modules: [
                new ModulePlanEntry(moduleId: $kernel, composerName: 'coretsia/core-kernel', requires: [$foundation]),
                new ModulePlanEntry(moduleId: $cli, composerName: 'coretsia/platform-cli'),
                new ModulePlanEntry(moduleId: $foundation, composerName: 'coretsia/core-foundation'),
            ],
        );

        $payload = $plan->toArray();
        self::assertSame(
            ['app', 'enabled', 'excluded', 'modules', 'schemaVersion', 'topologicalOrder'],
            \array_keys($payload),
        );
        self::assertSame('api', $payload['app']);
        self::assertSame(ModulePlan::SCHEMA_VERSION, $payload['schemaVersion']);
        self::assertSame(['core.foundation', 'core.kernel', 'platform.cli'], $payload['enabled']);
        self::assertSame(['platform.http'], $payload['excluded']);
        self::assertSame(['core.foundation', 'platform.cli', 'core.kernel'], $payload['topologicalOrder']);
        self::assertSame(['core.foundation', 'core.kernel', 'platform.cli'], \array_keys($payload['modules']));
        self::assertSame(
            ['composerName', 'conflicts', 'moduleId', 'requires'],
            \array_keys($payload['modules']['core.kernel']),
        );
        self::assertSame(['core.foundation'], $payload['modules']['core.kernel']['requires']);
    }
}
