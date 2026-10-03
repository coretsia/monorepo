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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\Preset\CanonicalPresetNames;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class DependencySyncInstallationCatalogCoversCanonicalPresetsTest extends TestCase
{
    public function testEveryCanonicalPhaseARootExistsInCommittedInstallationCatalog(): void
    {
        $kernelRoot = \dirname(__DIR__, 2);
        $foundationRoot = \dirname($kernelRoot) . '/foundation';
        $applicationRoot = \sys_get_temp_dir()
            . '/coretsia-catalog-presets-'
            . \bin2hex(\random_bytes(8));

        \mkdir($applicationRoot, 0777, true);

        try {
            $foundationConfig = require $foundationRoot . '/config/foundation.php';
            $kernelConfig = require $kernelRoot . '/config/kernel.php';

            self::assertIsArray($foundationConfig);
            self::assertIsArray($kernelConfig);

            $builder = new ContainerBuilder(
                config: [
                    FoundationModule::CONFIG_ROOT => $foundationConfig,
                    KernelModule::CONFIG_ROOT => $kernelConfig,
                ],
            );
            $builder->register(
                new FoundationServiceProvider(),
                new KernelServiceProvider(),
            );
            $container = $builder->build();

            $bootstrapConfigResolver = $container->get(BootstrapConfigResolver::class);
            $orchestrator = $container->get(ModuleResolutionOrchestrator::class);

            self::assertInstanceOf(
                BootstrapConfigResolver::class,
                $bootstrapConfigResolver,
            );
            self::assertInstanceOf(
                ModuleResolutionOrchestrator::class,
                $orchestrator,
            );

            $catalog = new ReleaseInstallationCatalogLoader()->load();

            foreach (CanonicalPresetNames::all() as $preset) {
                $config = $bootstrapConfigResolver->resolve(
                    new BootstrapInput(
                        applicationRoot: $applicationRoot,
                        appTarget: AppTarget::Web,
                        preset: $preset,
                    ),
                    $kernelConfig,
                );
                $selection = $orchestrator->resolveSelection($config);

                foreach ($selection->roots() as $moduleId) {
                    self::assertNotNull(
                        $catalog->entry($moduleId),
                        $preset
                        . ' root missing from catalog: '
                        . $moduleId->value(),
                    );
                }
            }
        } finally {
            @\rmdir($applicationRoot);
        }
    }
}
