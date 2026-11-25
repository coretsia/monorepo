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
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\ProjectPackagePlanner;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\Preset\CanonicalPresetNames;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class DependencySyncInstallationCatalogCoversCanonicalPresetsTest extends TestCase
{
    public function testHttpRequiresRedactionInCommittedInstallationCatalog(): void
    {
        $catalog = new ReleaseInstallationCatalogLoader()->load();
        $http = ModuleId::fromString('platform.http');
        $entry = $catalog->entry($http);

        self::assertNotNull($entry);
        self::assertSame('coretsia/platform-http', $entry->composerName());
        self::assertSame(
            ['core.foundation', 'core.kernel', 'platform.redaction'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $entry->requires()),
        );
        $redaction = $catalog->entry(ModuleId::fromString('platform.redaction'));
        self::assertNotNull($redaction);
        self::assertSame('coretsia/platform-redaction', $redaction->composerName());

        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $selection = new ModuleSelection([$http], []);
        $entries = $catalog->entries();

        foreach ([$entries, \array_reverse($entries)] as $available) {
            $graph = $resolver->resolveEntries($available, $selection);
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.http', 'platform.redaction'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $graph['enabled']),
            );
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.redaction', 'platform.http'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $graph['topologicalOrder']),
            );
        }
    }

    public function testRoutingDependencyClosureInCommittedInstallationCatalog(): void
    {
        $catalog = new ReleaseInstallationCatalogLoader()->load();
        $routing = ModuleId::fromString('platform.routing');
        $entry = $catalog->entry($routing);

        self::assertNotNull($entry);
        self::assertSame('coretsia/platform-routing', $entry->composerName());
        self::assertSame(
            ['core.foundation', 'core.kernel', 'platform.redaction'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $entry->requires()),
        );
        self::assertSame([], $entry->conflicts());

        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $selection = new ModuleSelection([$routing], []);
        $entries = $catalog->entries();

        foreach ([$entries, \array_reverse($entries)] as $available) {
            $graph = $resolver->resolveEntries($available, $selection);
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.redaction', 'platform.routing'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $graph['enabled']),
            );
            self::assertSame(
                ['core.foundation', 'core.kernel', 'platform.redaction', 'platform.routing'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $graph['topologicalOrder']),
            );
        }
    }

    public function testInstalledRoutingDoesNotImplyEnabledRouting(): void
    {
        $catalog = new ReleaseInstallationCatalogLoader()->load();
        $descriptors = [];

        foreach ($catalog->entries() as $entry) {
            $descriptors[] = new ModuleDescriptor(
                id: $entry->moduleId(),
                composerName: $entry->composerName(),
                packageKind: 'runtime',
                moduleClass: null,
                capabilities: [],
                metadata: [
                    'requires' => \array_map(static fn (ModuleId $id): string => $id->value(), $entry->requires()),
                    'conflicts' => \array_map(static fn (ModuleId $id): string => $id->value(), $entry->conflicts()),
                ],
            );
        }

        $manifest = new ModuleManifest($descriptors);
        self::assertTrue($manifest->has('platform.routing'));

        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $http = ModuleId::fromString('platform.http');
        $routing = ModuleId::fromString('platform.routing');

        $withoutRouting = $resolver->resolve('web', $manifest, new ModuleSelection([$http], []));
        $withRouting = $resolver->resolve('web', $manifest, new ModuleSelection([$http, $routing], []));

        self::assertTrue($withoutRouting->hasEnabledModule($http));
        self::assertFalse($withoutRouting->hasEnabledModule($routing));
        self::assertTrue($withRouting->hasEnabledModule($routing));
    }

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

            $catalogLoader = new ReleaseInstallationCatalogLoader();
            $catalog = $catalogLoader->load();

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

                self::assertContains(
                    'platform.routing',
                    \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots()),
                );

                foreach ($selection->roots() as $moduleId) {
                    self::assertNotNull(
                        $catalog->entry($moduleId),
                        $preset
                        . ' root missing from catalog: '
                        . $moduleId->value(),
                    );
                }
            }

            $sourceResolver = $bootstrapConfigResolver->sourceResolver();
            $planner = new ProjectPackagePlanner(
                $bootstrapConfigResolver,
                $orchestrator,
                new ModuleGraphResolver(new TopologicalSorter()),
                new ModuleIdSetNormalizer(),
                $catalogLoader,
                $kernelConfig,
                $sourceResolver,
            );
            $intent = new ProjectInstallationIntent(
                new ProjectApplicationSet([AppTarget::Web]),
                ['web' => 'micro'],
            );
            $snapshot = $sourceResolver->resolveApplicationRoot($applicationRoot, $kernelConfig);
            $first = $planner->plan($applicationRoot, $intent, $snapshot);
            $second = $planner->plan($applicationRoot, $intent, $snapshot);

            self::assertContains('platform.redaction', $first->enabledModuleIdsByTarget()['web']);
            self::assertContains('platform.redaction', $first->unionModuleIds());
            self::assertContains('platform.routing', $first->enabledModuleIdsByTarget()['web']);
            self::assertContains('platform.routing', $first->unionModuleIds());
            self::assertSame(
                $catalog->publicConstraint(),
                $first->desiredRootRequirements()['coretsia/platform-routing'] ?? null,
            );
            self::assertSame(
                $catalog->publicConstraint(),
                $first->desiredRootRequirements()['coretsia/platform-redaction'] ?? null,
            );
            self::assertTrue($first->equals($second));
        } finally {
            @\rmdir($applicationRoot);
        }
    }
}
