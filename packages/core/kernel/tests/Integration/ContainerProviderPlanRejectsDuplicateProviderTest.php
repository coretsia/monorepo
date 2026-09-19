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
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class ContainerProviderPlanRejectsDuplicateProviderTest extends TestCase
{
    public function testRejectsProviderDeclaredByMoreThanOneEnabledModule(): void
    {
        $alpha = ModuleId::fromString('platform.alpha');
        $beta = ModuleId::fromString('platform.beta');
        $manifest = new ModuleManifest([
            self::descriptor($alpha),
            self::descriptor($beta),
        ]);
        $resolution = new ModuleResolution(
            manifest: $manifest,
            plan: new ModulePlan(
                app: 'api',
                preset: 'micro',
                enabled: [
                    $alpha,
                    $beta,
                ],
                disabled: [],
                optionalMissing: [],
                topologicalOrder: [
                    $alpha,
                    $beta,
                ],
                modules: [
                    self::entry($alpha),
                    self::entry($beta),
                ],
            ),
        );

        try {
            new ContainerProviderPlanResolver()->resolve($resolution);

            self::fail('Expected duplicate provider class to be rejected.');
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

    private static function descriptor(ModuleId $moduleId): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: $moduleId,
            composerName: self::composerName($moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'providers' => [
                    KernelServiceProvider::class,
                ],
            ],
        );
    }

    private static function entry(ModuleId $moduleId): ModulePlanEntry
    {
        return new ModulePlanEntry(
            moduleId: $moduleId,
            composerName: self::composerName($moduleId),
        );
    }

    private static function composerName(ModuleId $moduleId): string
    {
        return 'coretsia/' . \str_replace('.', '-', $moduleId->value());
    }
}
