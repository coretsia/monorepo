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

final class ContainerProviderPlanUsesTopologicalModuleOrderTest extends TestCase
{
    public function testUsesModulePlanTopologicalOrderInsteadOfManifestOrder(): void
    {
        $resolution = self::resolution(
            descriptors: [
                self::descriptor(
                    moduleId: 'platform.alpha',
                    providers: [
                        FoundationServiceProvider::class,
                    ],
                ),
                self::descriptor(
                    moduleId: 'platform.beta',
                    providers: [
                        KernelServiceProvider::class,
                    ],
                ),
            ],
            topologicalOrder: [
                'platform.beta',
                'platform.alpha',
            ],
        );

        $plan = new ContainerProviderPlanResolver()->resolve($resolution);

        self::assertSame(
            [
                [
                    'moduleId' => 'platform.beta',
                    'providerClass' => KernelServiceProvider::class,
                    'moduleOrder' => 0,
                    'providerOrder' => 0,
                ],
                [
                    'moduleId' => 'platform.alpha',
                    'providerClass' => FoundationServiceProvider::class,
                    'moduleOrder' => 1,
                    'providerOrder' => 0,
                ],
            ],
            $plan->entries(),
        );
    }

    /**
     * @param list<ModuleDescriptor> $descriptors
     * @param list<string> $topologicalOrder
     */
    private static function resolution(
        array $descriptors,
        array $topologicalOrder,
    ): ModuleResolution {
        $manifest = new ModuleManifest($descriptors);
        $moduleEntries = [];

        foreach ($manifest->modules() as $descriptor) {
            $composerName = $descriptor->composerName();

            if (!\is_string($composerName)) {
                throw new \LogicException('test-module-composer-name-missing');
            }

            $moduleEntries[] = new ModulePlanEntry(
                moduleId: $descriptor->id(),
                composerName: $composerName,
            );
        }

        return new ModuleResolution(
            manifest: $manifest,
            plan: new ModulePlan(
                app: 'api',
                preset: 'micro',
                enabled: self::moduleIds($manifest->ids()),
                disabled: [],
                optionalMissing: [],
                topologicalOrder: self::moduleIds($topologicalOrder),
                modules: $moduleEntries,
            ),
        );
    }

    /**
     * @param list<string> $providers
     */
    private static function descriptor(
        string $moduleId,
        array $providers,
    ): ModuleDescriptor {
        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: 'coretsia/' . \str_replace('.', '-', $moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'providers' => $providers,
            ],
        );
    }

    /**
     * @param list<string> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function moduleIds(array $moduleIds): array
    {
        return \array_map(
            static fn (string $moduleId): ModuleId => ModuleId::fromString($moduleId),
            $moduleIds,
        );
    }
}
