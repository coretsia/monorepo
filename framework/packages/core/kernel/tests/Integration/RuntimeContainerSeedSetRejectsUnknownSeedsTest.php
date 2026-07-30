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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerSeedSetRejectsUnknownSeedsTest extends TestCase
{
    public function testAcceptsOnlyTheExactEntrypointOwnedSeedMap(): void
    {
        $instances = self::validInstances();
        $seeds = new RuntimeContainerSeedSet($instances);
        $expectedIds = [
            ConfigRepositoryInterface::class,
            ModulePlan::class,
            RuntimePathContext::class,
        ];

        \sort($expectedIds, \SORT_STRING);

        self::assertSame($expectedIds, $seeds->ids());

        foreach ($expectedIds as $id) {
            self::assertSame(
                $instances[$id],
                $seeds->instances()[$id],
            );
        }
    }

    public function testRejectsUnknownSeedId(): void
    {
        $instances = self::validInstances();
        $instances[\stdClass::class] = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'runtime-container-seed-set-ids-invalid',
        );

        new RuntimeContainerSeedSet($instances);
    }

    public function testRejectsMissingSeedId(): void
    {
        $instances = self::validInstances();
        unset($instances[ModulePlan::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'runtime-container-seed-set-ids-invalid',
        );

        new RuntimeContainerSeedSet($instances);
    }

    public function testRejectsListShapedSeedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'runtime-container-seed-set-ids-invalid',
        );

        new RuntimeContainerSeedSet(
            \array_values(self::validInstances()),
        );
    }

    public function testRejectsObjectThatDoesNotMatchItsServiceId(): void
    {
        $instances = self::validInstances();
        $instances[ConfigRepositoryInterface::class] = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'runtime-container-seed-set-instance-invalid',
        );

        new RuntimeContainerSeedSet($instances);
    }

    /**
     * @return array<class-string, object>
     */
    private static function validInstances(): array
    {
        $moduleId = ModuleId::fromString('core.kernel');
        $root = self::runtimeRoot();

        return [
            ConfigRepositoryInterface::class => new ArrayConfigRepository([
                'kernel' => [
                    'runtime' => [],
                ],
            ]),
            ModulePlan::class => new ModulePlan(
                app: 'worker',
                preset: 'default',
                enabled: [
                    $moduleId,
                ],
                disabled: [],
                optionalMissing: [],
                topologicalOrder: [
                    $moduleId,
                ],
                modules: [
                    new ModulePlanEntry(
                        moduleId: $moduleId,
                        composerName: 'coretsia/core-kernel',
                    ),
                ],
                warnings: [],
            ),
            RuntimePathContext::class => new RuntimePathContext(
                skeletonRoot: $root,
                artifactRoot: $root . '/var/cache/worker',
            ),
        ];
    }

    private static function runtimeRoot(): string
    {
        return \rtrim(
            \str_replace('\\', '/', \sys_get_temp_dir()),
            '/',
        ) . '/coretsia-runtime-seed-set';
    }
}
