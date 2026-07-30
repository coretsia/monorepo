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
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolution;
use PHPUnit\Framework\TestCase;

final class ContainerProviderPlanRejectsNonDefinitionProviderTest extends TestCase
{
    public function testRejectsClassThatDoesNotImplementDefinitionProviderInterface(): void
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
                        ModulePlanResolver::class,
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

        try {
            new ContainerProviderPlanResolver()->resolve($resolution);

            self::fail('Expected non-definition provider class to be rejected.');
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame(
                ContainerDefinitionInvalidException::REASON_PROVIDER_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
        }
    }
}
