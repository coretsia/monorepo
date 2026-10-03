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

final class ModulePlanSetInvariantsContractTest extends TestCase
{
    public function testRejectsOverlappingEnabledAndExcluded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-plan-enabled-excluded-overlap');
        self::plan(
            enabled: [ModuleId::fromString('core.kernel')],
            excluded: [ModuleId::fromString('core.kernel')],
        );
    }

    public function testRejectsAssociativeEnabledCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-plan-enabled-module-ids-must-be-list');
        self::plan(enabled: ['kernel' => ModuleId::fromString('core.kernel')]);
    }

    public function testRejectsAssociativeExcludedCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-plan-excluded-module-ids-must-be-list');
        self::plan(excluded: ['http' => ModuleId::fromString('platform.http')]);
    }

    public function testDisjointSetsAndMembershipQueries(): void
    {
        $plan = self::plan(excluded: [ModuleId::fromString('platform.http')]);
        self::assertSame(
            ['core.kernel'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $plan->enabled()),
        );
        self::assertSame(
            ['platform.http'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $plan->excluded()),
        );
        self::assertTrue($plan->hasEnabledModule('core.kernel'));
        self::assertFalse($plan->hasEnabledModule('platform.http'));
        self::assertSame(['platform.http'], $plan->toArray()['excluded']);
    }

    private static function plan(?array $enabled = null, array $excluded = []): ModulePlan
    {
        $id = ModuleId::fromString('core.kernel');
        return new ModulePlan(
            app: 'api',
            enabled: $enabled ?? [$id],
            excluded: $excluded,
            topologicalOrder: [$id],
            modules: [new ModulePlanEntry(moduleId: $id, composerName: 'coretsia/core-kernel')],
        );
    }
}
