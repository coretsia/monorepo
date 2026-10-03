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

final class ModulePlanDoesNotExposeLegacySelectionStateTest extends TestCase
{
    public function testRuntimePlanContainsOnlyResolvedSelectionAndGraphState(): void
    {
        $id = ModuleId::fromString('core.kernel');
        $plan = new ModulePlan(
            app: 'web',
            enabled: [$id],
            excluded: [],
            topologicalOrder: [$id],
            modules: [new ModulePlanEntry(moduleId: $id, composerName: 'coretsia/core-kernel')],
        );
        self::assertSame(
            ['app', 'enabled', 'excluded', 'modules', 'schemaVersion', 'topologicalOrder'],
            \array_keys($plan->toArray()),
        );
        foreach (['preset', 'optionalMissing', 'disabled', 'warnings', 'moduleOverrides', 'selection'] as $legacy) {
            self::assertArrayNotHasKey($legacy, $plan->toArray());
            self::assertFalse(\method_exists($plan, $legacy));
        }
        self::assertTrue(new \ReflectionClass($plan)->isReadOnly());
    }
}
