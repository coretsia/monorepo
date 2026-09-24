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

use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverUsesSelectionOnlyTest extends TestCase
{
    public function testUnselectedInvalidDescriptorFailsBeforeMissingSelectedRoot(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor('platform.worker', ['platform.worker']),
        ]);

        $this->expectException(ModuleManifestInvalidException::class);
        $this->expectExceptionMessage('module-manifest-dependency-metadata-invalid');

        new ModuleGraphResolver(new TopologicalSorter())->resolve(
            app: 'api',
            installed: $manifest,
            selection: new ModuleSelection(
                roots: [ModeInfrastructureTestSupport::id('platform.http')],
                excluded: [],
            ),
        );
    }

    public function testOnlySelectionNotPresetDeterminesEffectiveGraphRoots(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor('core.kernel', ['core.foundation']),
            ModeInfrastructureTestSupport::descriptor('platform.worker'),
        ]);
        $selection = new ModuleSelection(
            roots: [ModeInfrastructureTestSupport::id('core.kernel')],
            excluded: [ModeInfrastructureTestSupport::id('platform.worker')]
        );
        $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve(
            app: 'api',
            installed: $manifest,
            selection: $selection,
        );
        self::assertSame(['core.foundation', 'core.kernel'], ModeInfrastructureTestSupport::values($plan->enabled()));
        self::assertSame(['platform.worker'], ModeInfrastructureTestSupport::values($plan->excluded()));
        self::assertSame(
            ['core.foundation', 'core.kernel'],
            ModeInfrastructureTestSupport::values($plan->topologicalOrder())
        );
        self::assertSame(
            ModuleSelection::class,
            new \ReflectionMethod(ModuleGraphResolver::class, 'resolve')->getParameters()[2]->getType()->getName(),
        );
    }
}
