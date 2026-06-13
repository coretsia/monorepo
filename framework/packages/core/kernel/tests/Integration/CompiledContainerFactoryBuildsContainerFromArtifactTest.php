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
                containerDescriptors: self::containerDescriptors(),
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
                containerDescriptors: self::containerDescriptors(),
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

    /**
     * @return list<array<string, mixed>>
     */
    private static function containerDescriptors(): array
    {
        return [
            [
                'kind' => 'parameters',
                'values' => [
                    'dependency.value' => 'from-compiled-parameter',
                    'runtime.message' => 'runtime-message',
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                'class' => CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                'arguments' => [
                    [
                        'name' => 'dependency.value',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                'class' => CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
            ],
            [
                'kind' => 'service.class',
                'id' => CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                'class' => CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                'arguments' => [
                    [
                        'id' => CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                        'type' => 'service',
                    ],
                    [
                        'name' => 'runtime.message',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'service.factory.class-method',
                'id' => 'test.compiled.factory.class_product',
                'factoryClass' => CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                'method' => 'makeClassProduct',
                'arguments' => [
                    [
                        'name' => 'runtime.message',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'service.factory.service-method',
                'id' => 'test.compiled.factory.service_product',
                'factoryServiceId' => CompiledContainerFactoryBuildsContainerFromArtifactFactory::class,
                'method' => 'makeServiceProduct',
                'arguments' => [
                    [
                        'id' => CompiledContainerFactoryBuildsContainerFromArtifactDependency::class,
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'alias',
                'alias' => 'test.compiled.main',
                'serviceId' => CompiledContainerFactoryBuildsContainerFromArtifactService::class,
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => CompiledContainerFactoryBuildsContainerFromArtifactService::class,
                'priority' => 50,
                'meta' => [],
            ],
        ];
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
