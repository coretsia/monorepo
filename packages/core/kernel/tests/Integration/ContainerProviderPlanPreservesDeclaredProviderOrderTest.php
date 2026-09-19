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

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class ContainerProviderPlanPreservesDeclaredProviderOrderTest extends TestCase
{
    public function testPreservesDeclaredProviderOrderWithoutFqcnSorting(): void
    {
        $moduleId = ModuleId::fromString('core.kernel');
        $manifest = new ModuleManifest([
            new ModuleDescriptor(
                id: $moduleId,
                composerName: 'coretsia/core-kernel',
                packageKind: 'runtime',
                moduleClass: null,
                capabilities: [],
                metadata: [
                    'providers' => [
                        KernelServiceProvider::class,
                        FoundationServiceProvider::class,
                    ],
                ],
            ),
        ]);
        $resolution = new ModuleResolution(
            manifest: $manifest,
            plan: new ModulePlan(
                app: 'api',
                preset: 'micro',
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
            ),
        );

        $plan = new ContainerProviderPlanResolver()->resolve($resolution);

        self::assertSame(
            [
                KernelServiceProvider::class,
                FoundationServiceProvider::class,
            ],
            $plan->providerClasses(),
        );
        self::assertSame(
            [
                [
                    'moduleId' => 'core.kernel',
                    'providerClass' => KernelServiceProvider::class,
                    'moduleOrder' => 0,
                    'providerOrder' => 0,
                ],
                [
                    'moduleId' => 'core.kernel',
                    'providerClass' => FoundationServiceProvider::class,
                    'moduleOrder' => 0,
                    'providerOrder' => 1,
                ],
            ],
            $plan->entries(),
        );
    }
}
