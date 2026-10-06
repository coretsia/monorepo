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
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlan;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsSourceDefinitionProviderAdapter;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsHostComposesEnabledProvidersInCanonicalPlanOrderTest extends TestCase
{
    public function testBaselineAndSourceProviderOrderAndSingleBatchSemanticsAreCanonical(): void
    {
        self::assertSame(
            [FoundationServiceProvider::class],
            new FoundationModule()->providers(),
        );
        self::assertSame(
            [KernelServiceProvider::class],
            new KernelModule()->providers(),
        );

        $source = self::source();

        self::assertMatchesRegularExpression(
            '/return\s*\[\s*\.\.\.new FoundationModule\(\)->providers\(\),\s*\.\.\.new KernelModule\(\)->providers\(\),\s*];/s',
            $source,
        );
        self::assertSame(2, \substr_count($source, 'new ContainerBuilder('));
        self::assertSame(2, \substr_count($source, '->registerProviders('));
        self::assertStringNotContainsString('FoundationServiceProvider::class', $source);
        self::assertStringNotContainsString('KernelServiceProvider::class', $source);

        foreach (
            [
                new FoundationServiceProvider(),
                new KernelServiceProvider(),
            ] as $baselineProvider
        ) {
            self::assertInstanceOf(ServiceProviderInterface::class, $baselineProvider);
            self::assertInstanceOf(ContainerDefinitionProviderInterface::class, $baselineProvider);
        }

        self::assertStringContainsString(
            '$seedBuilder = new ContainerBuilder(config: $seedConfig);',
            $source,
        );
        self::assertStringContainsString(
            '$finalBuilder = new ContainerBuilder(config: $completeConfig);',
            $source,
        );
        self::assertSame(
            1,
            \substr_count(
                $source,
                '$baselineProviders = self::baselineProviders($baselineProviderClasses);',
            ),
        );
        self::assertSame(
            1,
            \substr_count(
                $source,
                '$seedBuilder->registerProviders($baselineProviders);',
            ),
        );
        self::assertStringContainsString(
            '$provider = new $providerClass();',
            $source,
        );
        self::assertStringContainsString(
            '!$provider instanceof ContainerDefinitionProviderInterface',
            $source,
        );

        foreach (
            [
                'RuntimePathContext::class',
                'KernelOpsExecutionServices::class',
                'KernelOpsFacade::class',
                'KernelOpsInterface::class',
            ] as $factoryId
        ) {
            self::assertStringContainsString($factoryId, $source);
        }

        foreach (
            [
                'KernelOpsHostInput::class',
                'BootstrapConfig::class',
                'EnvRepositoryInterface::class',
                'ConfigRepositoryInterface::class',
                'ModulePlan::class',
            ] as $seedId
        ) {
            self::assertStringContainsString(
                '->instance(' . $seedId,
                $source,
            );
        }

        $register = \strpos($source, '$finalBuilder->registerProviders($sourceProviders)');
        $guard = \strpos($source, 'self::assertNoHostOwnedTagTargets($finalBuilder)');
        $factories = \strpos($source, 'self::installHostOwnedFactories(');
        $instances = \strpos($source, '->instance(KernelOpsHostInput::class');
        $build = \strpos($source, '$container = $finalBuilder->build()');

        foreach ([$register, $guard, $factories, $instances, $build] as $position) {
            self::assertIsInt($position);
        }

        self::assertTrue($register < $guard);
        self::assertTrue($guard < $factories);
        self::assertTrue($factories < $instances);
        self::assertTrue($instances < $build);

        $containerBuilderSource = self::containerBuilderSource();

        self::assertMatchesRegularExpression(
            '/public function factory\(.*?return \$this->set\(/s',
            $containerBuilderSource,
        );
        self::assertMatchesRegularExpression(
            '/public function set\(.*?' .
            '\$this->definitions\[\$id] = \$definition;.*?' .
            'unset\(\$this->instances\[\$id]\);/s',
            $containerBuilderSource,
        );
        self::assertMatchesRegularExpression(
            '/public function instance\(.*?' .
            'unset\(\$this->definitions\[\$id], \$this->definitionShared\[\$id]\);.*?' .
            '\$this->instances\[\$id] = \$instance;/s',
            $containerBuilderSource,
        );

        KernelOpsPlanDefinitionOnlyProvider::$instances = 0;
        KernelOpsPlanDualProvider::$instances = 0;
        KernelOpsPlanDefinitionOnlyProvider::$defines = 0;
        KernelOpsPlanDualProvider::$defines = 0;

        $plan = new ContainerProviderPlan([
            [
                'moduleId' => 'core.fixture',
                'providerClass' => KernelOpsPlanDefinitionOnlyProvider::class,
                'moduleOrder' => 0,
                'providerOrder' => 0,
            ],
            [
                'moduleId' => 'core.fixture',
                'providerClass' => KernelOpsPlanDualProvider::class,
                'moduleOrder' => 0,
                'providerOrder' => 1,
            ],
        ]);

        $sourceProviders = new ReflectionMethod(KernelOpsHostBooter::class, 'sourceProviders');
        $providers = $sourceProviders->invoke(null, $plan);

        self::assertInstanceOf(KernelOpsSourceDefinitionProviderAdapter::class, $providers[0]);
        self::assertInstanceOf(KernelOpsPlanDualProvider::class, $providers[1]);
        self::assertSame(1, KernelOpsPlanDefinitionOnlyProvider::$instances);
        self::assertSame(1, KernelOpsPlanDualProvider::$instances);

        foreach ($providers as $provider) {
            self::assertInstanceOf(ServiceProviderInterface::class, $provider);
            self::assertInstanceOf(ContainerDefinitionProviderInterface::class, $provider);
        }

        $builder = new ContainerBuilder();
        $builder->registerProviders($providers);

        $container = $builder->build();
        $tagRegistry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $tagRegistry);

        self::assertSame(1, KernelOpsPlanDefinitionOnlyProvider::$defines);
        self::assertSame(1, KernelOpsPlanDualProvider::$defines);
        self::assertSame(
            [KernelOpsPlanTaggedService::class],
            \array_map(
                static fn ($tagged): string => $tagged->id(),
                $tagRegistry->all('fixture.command'),
            ),
        );
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');
        self::assertIsString($source);

        return $source;
    }

    private static function containerBuilderSource(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../../foundation/src/Container/ContainerBuilder.php');

        self::assertIsString($source);

        return $source;
    }
}

final class KernelOpsPlanDefinitionOnlyProvider implements ContainerDefinitionProviderInterface
{
    public static int $instances = 0;
    public static int $defines = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        ++self::$defines;
        $definitions
            ->classService(KernelOpsPlanTaggedService::class, KernelOpsPlanTaggedService::class)
            ->tag('fixture.command', KernelOpsPlanTaggedService::class);
    }
}

final class KernelOpsPlanDualProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
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

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        ++self::$defines;
    }
}

final class KernelOpsPlanTaggedService
{
}
