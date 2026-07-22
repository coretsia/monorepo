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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

final class CompiledContainerFactoryBuildsContainerFromArtifactTest extends TestCase
{
    public function testBuildsRuntimeContainerFromGeneratedCompiledContainerArtifact(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('runtime-container-from-artifact');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    CompiledContainerFactoryBuildsContainerFromArtifactProvider::class,
                ]),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

            $service = $container->get(CompiledContainerFactoryBuildsContainerFromArtifactService::class);

            self::assertInstanceOf(
                CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                $service,
            );
            self::assertSame('from-compiled-parameter', $service->dependency->value);
            self::assertSame('runtime-message', $service->message);

            $alias = $container->get('test.compiled.main');

            self::assertSame(
                $service,
                $alias,
                'Compiled aliases must delegate to the compiled target service.',
            );

            $classFactoryProduct = $container->get('test.compiled.factory.class_product');

            self::assertInstanceOf(
                CompiledContainerFactoryBuildsContainerFromArtifactProduct::class,
                $classFactoryProduct,
            );
            self::assertSame('class-factory:runtime-message', $classFactoryProduct->value);

            $serviceFactoryProduct = $container->get('test.compiled.factory.service_product');

            self::assertInstanceOf(
                CompiledContainerFactoryBuildsContainerFromArtifactProduct::class,
                $serviceFactoryProduct,
            );
            self::assertSame('service-factory:from-compiled-parameter', $serviceFactoryProduct->value);

            self::assertInstanceOf(
                TagRegistry::class,
                $container->get(TagRegistry::class),
                'Compiled runtime container must expose the TagRegistry runtime support instance.',
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testRuntimeContainerUsesAlreadyReadConfigPayloadSnapshot(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('runtime-container-config-payload');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    CompiledContainerFactoryBuildsContainerFromArtifactProvider::class,
                ]),
            );

            $configPayload = ArtifactPipelineTestSupport::configPayloadFromArtifact($root);

            self::assertArrayHasKey('config', $configPayload);

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                skeletonRoot: $root,
                configPayload: $configPayload,
            );

            $service = $container->get(CompiledContainerFactoryBuildsContainerFromArtifactService::class);

            self::assertInstanceOf(
                CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                $service,
            );
            self::assertSame('runtime-message', $service->message);
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}

final class CompiledContainerFactoryBuildsContainerFromArtifactProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->parameter(
                'dependency.value',
                'from-compiled-parameter',
            )
            ->parameter(
                'runtime.message',
                'runtime-message',
            )
            ->classService(
                id: CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                class: CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                arguments: [
                    ContainerValueReference::parameter('dependency.value'),
                ],
            )
            ->classService(
                CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
            )
            ->classService(
                id: CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                class: CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                arguments: [
                    ContainerValueReference::service(
                        CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                    ),
                    ContainerValueReference::parameter('runtime.message'),
                ],
            )
            ->classMethodFactory(
                id: 'test.compiled.factory.class_product',
                factoryClass: CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                method: 'makeClassProduct',
                arguments: [
                    ContainerValueReference::parameter('runtime.message'),
                ],
            )
            ->serviceMethodFactory(
                id: 'test.compiled.factory.service_product',
                factoryServiceId: CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                method: 'makeServiceProduct',
                arguments: [
                    ContainerValueReference::service(
                        CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                    ),
                ],
            )
            ->alias(
                'test.compiled.main',
                CompiledContainerFactoryBuildsContainerFromArtifactService::class,
            )
            ->tag(
                tag: 'kernel.reset',
                serviceId: CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                priority: 50,
            );
    }
}

final readonly class CompiledContainerFactoryBuildsContainerFromArtifactDependency
{
    public function __construct(
        public string $value,
    ) {
    }
}

final readonly class CompiledContainerFactoryBuildsContainerFromArtifactService
{
    public function __construct(
        public CompiledContainerFactoryBuildsContainerFromArtifactDependency $dependency,
        public string $message,
    ) {
    }
}

final readonly class CompiledContainerFactoryBuildsContainerFromArtifactProduct
{
    public function __construct(
        public string $value,
    ) {
    }
}

final class CompiledContainerFactoryBuildsContainerFromArtifactFactory
{
    public static function makeClassProduct(string $message): CompiledContainerFactoryBuildsContainerFromArtifactProduct
    {
        return new CompiledContainerFactoryBuildsContainerFromArtifactProduct(
            'class-factory:' . $message,
        );
    }

    public function makeServiceProduct(
        CompiledContainerFactoryBuildsContainerFromArtifactDependency $dependency,
    ): CompiledContainerFactoryBuildsContainerFromArtifactProduct {
        return new CompiledContainerFactoryBuildsContainerFromArtifactProduct(
            'service-factory:' . $dependency->value,
        );
    }
}
