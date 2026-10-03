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

use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use PHPUnit\Framework\TestCase;

final class DependencySyncBootstrapCompatibilityTest extends TestCase
{
    public function testFacadeReusesKernelCompositionRootInsteadOfReconstructingPhaseAGraph(): void
    {
        $sync = ProjectDependencySync::create();

        self::assertInstanceOf(
            ProjectDependencySync::class,
            $sync,
        );

        $path = new \ReflectionClass(ProjectDependencySync::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('ContainerBuilder', $source);
        self::assertStringContainsString('KernelServiceFactory', $source);
        self::assertStringContainsString(
            'new FoundationModule()->providers()',
            $source,
        );
        self::assertStringContainsString(
            'new KernelModule()->providers()',
            $source,
        );
        self::assertStringContainsString(
            'self::baselineSourceProviders()',
            $source,
        );
        self::assertStringContainsString(
            '$builder->registerProviders(',
            $source,
        );
        self::assertStringContainsString(
            '$container->get(BootstrapConfigResolver::class)',
            $source,
        );
        self::assertStringContainsString(
            '$container->get(ModuleResolutionOrchestrator::class)',
            $source,
        );
        self::assertStringNotContainsString(
            'new BootstrapConfigResolver(',
            $source,
        );
        self::assertStringNotContainsString(
            'new ModuleResolutionOrchestrator(',
            $source,
        );
        self::assertFalse(
            \class_exists(
                'Coretsia\\Kernel\\DependencySync\\ProjectDependencySyncFactory',
            ),
        );
        self::assertTrue(
            \method_exists(
                KernelServiceFactory::class,
                'modePresetLoaderFactory',
            ),
        );
    }
}
