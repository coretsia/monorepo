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

use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModulePlanResolverCoordinatesSelectionAndManifestOnlyTest extends TestCase
{
    public function testPureResolverPassesAlreadySelectedRootsAndManifestIntoGraph(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor('core.kernel', ['core.foundation']),
        ]);
        $selection = new ModuleSelection([ModeInfrastructureTestSupport::id('core.kernel')], []);
        $resolver = new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()));
        $plan = $resolver->resolve(app: 'web', manifest: $manifest, selection: $selection);
        self::assertSame('web', $plan->app());
        self::assertSame(['core.foundation', 'core.kernel'], ModeInfrastructureTestSupport::values($plan->enabled()));
        $source = \file_get_contents(new \ReflectionClass(ModulePlanResolver::class)->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'ModePreset',
                'PresetNamespace',
                'ManifestReaderInterface',
                'MeterPortInterface',
                'TracerPortInterface',
                'FilesystemModePresetLoader',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
