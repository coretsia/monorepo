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
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsSourceDefinitionProviderAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsHostSupportsDefinitionOnlyProviderThroughSourceAdapterTest extends TestCase
{
    public function testDefinitionOnlyProviderIsAdaptedOnceAndDefinitionsRemainCanonical(): void
    {
        KernelOpsDefinitionOnlyProviderFixture::$instances = 0;
        KernelOpsDefinitionOnlyProviderFixture::$defines = 0;
        KernelOpsDualProviderFixture::$instances = 0;
        KernelOpsDualProviderFixture::$defines = 0;

        $plan = new ContainerProviderPlan([
            [
                'moduleId' => 'core.fixture',
                'providerClass' => KernelOpsDefinitionOnlyProviderFixture::class,
                'moduleOrder' => 0,
                'providerOrder' => 0,
            ],
            [
                'moduleId' => 'core.fixture',
                'providerClass' => KernelOpsDualProviderFixture::class,
                'moduleOrder' => 0,
                'providerOrder' => 1,
            ],
        ]);

        $method = new ReflectionMethod(KernelOpsHostBooter::class, 'sourceProviders');
        $providers = $method->invoke(null, $plan);

        self::assertCount(2, $providers);
        self::assertInstanceOf(KernelOpsSourceDefinitionProviderAdapter::class, $providers[0]);
        self::assertInstanceOf(KernelOpsDualProviderFixture::class, $providers[1]);
        self::assertSame(1, KernelOpsDefinitionOnlyProviderFixture::$instances);
        self::assertSame(1, KernelOpsDualProviderFixture::$instances);

        foreach ($providers as $provider) {
            self::assertInstanceOf(ServiceProviderInterface::class, $provider);
            self::assertInstanceOf(ContainerDefinitionProviderInterface::class, $provider);
        }

        $builder = new ContainerBuilder(config: ['fixture' => true]);
        $builder->registerProviders($providers);
        $container = $builder->build();
        $tagRegistry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);
        self::assertSame(1, KernelOpsDefinitionOnlyProviderFixture::$defines);
        self::assertSame(1, KernelOpsDualProviderFixture::$defines);
        self::assertTrue($container->has(KernelOpsDefinitionOnlyService::class));
        self::assertTrue($container->has('fixture.alias'));
        self::assertSame(
            'fixture-value',
            $container->get(KernelOpsDefinitionOnlyService::class)->parameter,
        );
        self::assertSame(
            KernelOpsDefinitionOnlyService::class,
            $container->get('fixture.alias')::class,
        );
        self::assertSame(
            'fixture-value',
            $container->get('fixture.alias')->parameter,
        );
        self::assertSame(
            [KernelOpsDefinitionOnlyService::class],
            \array_map(
                static fn ($tagged): string => $tagged->id(),
                $tagRegistry->all('fixture.tag'),
            ),
        );
    }
}

final class KernelOpsDefinitionOnlyProviderFixture implements ContainerDefinitionProviderInterface
{
    public static int $instances = 0;
    public static int $defines = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        ++self::$defines;
        $definitions
            ->parameter('fixture.parameter', 'fixture-value')
            ->classService(
                KernelOpsDefinitionOnlyService::class,
                KernelOpsDefinitionOnlyService::class,
                [
                    ContainerValueReference::parameter('fixture.parameter'),
                ],
            )
            ->alias('fixture.alias', KernelOpsDefinitionOnlyService::class)
            ->tag('fixture.tag', KernelOpsDefinitionOnlyService::class);
    }
}

final class KernelOpsDualProviderFixture implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public static int $instances = 0;
    public static int $defines = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        ++self::$defines;
    }
}

final readonly class KernelOpsDefinitionOnlyService
{
    public function __construct(
        public string $parameter,
    ) {
    }
}
