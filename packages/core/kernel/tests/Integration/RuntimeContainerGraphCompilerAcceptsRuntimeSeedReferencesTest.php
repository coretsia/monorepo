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
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class RuntimeContainerGraphCompilerAcceptsRuntimeSeedReferencesTest extends TestCase
{
    public function testAcceptsCanonicalRuntimeSeedsAsExternalReferences(): void
    {
        $payload = ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this)->compile(
            moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                RuntimeContainerGraphCompilerRuntimeSeedReferencesProvider::class,
            ]),
            compiledConfig: ArtifactPipelineTestSupport::defaultConfig(),
        )->toArray();

        self::assertArrayHasKey(
            RuntimeContainerGraphCompilerRuntimeSeedReferencesSubject::class,
            $payload['services'],
        );

        self::assertSame(
            [
                Container::class,
                ContainerInterface::class,
                TagRegistry::class,
                ConfigRepositoryInterface::class,
                ModulePlan::class,
                RuntimePathContext::class,
            ],
            \array_column(
                $payload['services'][RuntimeContainerGraphCompilerRuntimeSeedReferencesSubject::class]['arguments'],
                'id',
            ),
        );
    }
}

final class RuntimeContainerGraphCompilerRuntimeSeedReferencesProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $runtimeSeedIds = [
            Container::class,
            ContainerInterface::class,
            TagRegistry::class,
            ConfigRepositoryInterface::class,
            ModulePlan::class,
            RuntimePathContext::class,
        ];

        $definitions->classService(
            id: RuntimeContainerGraphCompilerRuntimeSeedReferencesSubject::class,
            class: RuntimeContainerGraphCompilerRuntimeSeedReferencesSubject::class,
            arguments: \array_map(
                static fn (
                    string $serviceId
                ): ContainerValueReference => ContainerValueReference::service(
                    $serviceId,
                ),
                $runtimeSeedIds,
            ),
        );

        foreach ($runtimeSeedIds as $serviceId) {
            $definitions->requireService($serviceId);
        }
    }
}

final class RuntimeContainerGraphCompilerRuntimeSeedReferencesSubject
{
}
