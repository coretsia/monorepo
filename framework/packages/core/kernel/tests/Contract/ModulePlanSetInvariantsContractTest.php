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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModulePlanSetInvariantsContractTest extends TestCase
{
    /**
     * @param list<ModuleId> $enabled
     * @param list<ModuleId> $disabled
     * @param list<ModuleId> $optionalMissing
     */
    #[DataProvider('overlappingModuleIdSetsProvider')]
    public function testModulePlanRejectsOverlappingModuleIdSets(
        array $enabled,
        array $disabled,
        array $optionalMissing,
        string $expectedReason,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedReason);

        new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: $enabled,
            disabled: $disabled,
            optionalMissing: $optionalMissing,
            topologicalOrder: [
                self::moduleId('core.kernel'),
            ],
            modules: [
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.kernel'),
                    composerName: 'coretsia/core-kernel',
                ),
            ],
        );
    }

    /**
     * @return iterable<string, array{
     *     enabled: list<ModuleId>,
     *     disabled: list<ModuleId>,
     *     optionalMissing: list<ModuleId>,
     *     expectedReason: string
     * }>
     */
    public static function overlappingModuleIdSetsProvider(): iterable
    {
        yield 'enabled-disabled-overlap' => [
            'enabled' => [
                self::moduleId('core.kernel'),
            ],
            'disabled' => [
                self::moduleId('core.kernel'),
            ],
            'optionalMissing' => [],
            'expectedReason' => 'module-plan-enabled-disabled-overlap',
        ];

        yield 'enabled-optional-missing-overlap' => [
            'enabled' => [
                self::moduleId('core.kernel'),
            ],
            'disabled' => [],
            'optionalMissing' => [
                self::moduleId('core.kernel'),
            ],
            'expectedReason' => 'module-plan-enabled-optional-missing-overlap',
        ];

        yield 'disabled-optional-missing-overlap' => [
            'enabled' => [
                self::moduleId('core.kernel'),
            ],
            'disabled' => [
                self::moduleId('platform.http'),
            ],
            'optionalMissing' => [
                self::moduleId('platform.http'),
            ],
            'expectedReason' => 'module-plan-disabled-optional-missing-overlap',
        ];
    }

    public function testModulePlanAllowsPairwiseDisjointModuleIdSets(): void
    {
        $plan = new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [
                self::moduleId('core.kernel'),
            ],
            disabled: [
                self::moduleId('platform.http'),
            ],
            optionalMissing: [
                self::moduleId('platform.tracing'),
            ],
            topologicalOrder: [
                self::moduleId('core.kernel'),
            ],
            modules: [
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.kernel'),
                    composerName: 'coretsia/core-kernel',
                ),
            ],
        );

        self::assertSame(
            [
                'core.kernel',
            ],
            self::moduleIdValues($plan->enabled()),
        );

        self::assertSame(
            [
                'platform.http',
            ],
            self::moduleIdValues($plan->disabled()),
        );

        self::assertSame(
            [
                'platform.tracing',
            ],
            self::moduleIdValues($plan->optionalMissing()),
        );
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }
}
