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

namespace Coretsia\Platform\Tracing\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Tracing\Provider\TracingServiceProvider;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TracingProviderSourceCompiledParityTest extends TestCase
{
    public function testTracingProviderDeclaresExactlyOneDirectClassBinding(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new TracingServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $operations = $definitions->build()->toDescriptorStream();

        self::assertSame([
            [
                'arguments' => [],
                'class' => W3cTraceContextPropagation::class,
                'id' => ContextPropagationInterface::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
        ], $operations);
    }

    public function testCompiledGraphChangesOnlyTheExistingContextPropagationBinding(): void
    {
        $compiler = self::compiler();
        $config = ['foundation' => []];
        $foundation = self::resolution(false);
        $tracing = self::resolution(true);
        $baselineGraph = $compiler->compile($foundation, $config)->toArray();
        $selectedGraph = $compiler->compile($tracing, $config)->toArray();

        self::assertSame($baselineGraph['aliases'], $selectedGraph['aliases']);
        self::assertSame($baselineGraph['parameters'], $selectedGraph['parameters']);
        self::assertSame($baselineGraph['tags'], $selectedGraph['tags']);
        self::assertSame(\array_keys($baselineGraph['services']), \array_keys($selectedGraph['services']));

        $baselineServices = $baselineGraph['services'];
        $baselineServices[ContextPropagationInterface::class] = $selectedGraph['services'][ContextPropagationInterface::class];
        self::assertSame($baselineServices, $selectedGraph['services']);
        self::assertSame(
            NoopContextPropagation::class,
            $baselineGraph['services'][ContextPropagationInterface::class]['construction']['class'],
        );
        self::assertSame(
            W3cTraceContextPropagation::class,
            $selectedGraph['services'][ContextPropagationInterface::class]['construction']['class'],
        );
        self::assertSame('class', $selectedGraph['services'][ContextPropagationInterface::class]['type']);
        self::assertSame([], $selectedGraph['services'][ContextPropagationInterface::class]['arguments']);

        self::assertSame(
            [FoundationServiceProvider::class, TracingServiceProvider::class],
            new ContainerProviderPlanResolver()->resolve($tracing)->providerClasses(),
        );
        self::assertSame(
            $selectedGraph,
            $compiler->compile($tracing, $config)->toArray(),
        );
    }

    public function testSourceAndCompiledContainersResolveTheSameSelectedAndFallbackImplementations(): void
    {
        foreach ([false, true] as $enabled) {
            $resolution = self::resolution($enabled);
            $providerClasses = new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses();
            $builder = new ContainerBuilder(['foundation' => []]);
            $providers = [];
            foreach ($providerClasses as $providerClass) {
                /** @var class-string<ServiceProviderInterface> $providerClass */
                $providers[] = new $providerClass();
            }
            $builder->registerProviders($providers);
            $source = $builder->build();
            $graph = self::compiler()->compile($resolution, ['foundation' => []]);

            $seeds = new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository(['foundation' => []]),
                ModulePlan::class => $resolution->plan(),
                RuntimePathContext::class => new RuntimePathContext('/coretsia-test', '/coretsia-test/var/cache'),
            ]);
            $envelope = new ArtifactEnvelopeFactory()->container(
                \str_repeat('a', 64),
                ['kind' => 'compiled', 'compiled' => true, ...$graph->toArray()],
            );
            $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
                $envelope,
                $seeds,
            );

            $expected = $enabled ? W3cTraceContextPropagation::class : NoopContextPropagation::class;
            self::assertInstanceOf($expected, $source->get(ContextPropagationInterface::class));
            self::assertInstanceOf($expected, $compiled->get(ContextPropagationInterface::class));
            $sourcePropagation = $source->get(ContextPropagationInterface::class);
            $compiledPropagation = $compiled->get(ContextPropagationInterface::class);
            $carrier = [
                'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
            ];
            $extracted = $sourcePropagation->extract($carrier);
            self::assertSame($extracted, $compiledPropagation->extract($carrier));
            self::assertSame(
                $sourcePropagation->inject(['x-request-id' => 'safe'], $extracted),
                $compiledPropagation->inject(['x-request-id' => 'safe'], $extracted),
            );
            self::assertInstanceOf(NoopTracer::class, $source->get(TracerPortInterface::class));
            self::assertInstanceOf(NoopTracer::class, $compiled->get(TracerPortInterface::class));
        }
    }

    private static function compiler(): RuntimeContainerGraphCompiler
    {
        return new RuntimeContainerGraphCompiler(
            providerPlanResolver: new ContainerProviderPlanResolver(),
            containerCompiler: new ContainerCompiler(
                tracer: new NoopTracer(),
                meter: new NoopMeter(),
                logger: new NullLogger(),
                stopwatch: new Stopwatch(),
            ),
            completenessValidator: new ContainerGraphCompletenessValidator(),
        );
    }

    private static function resolution(bool $enabled): ModuleResolution
    {
        $repositoryRoot = \dirname(__DIR__, 5);
        $versions = [];
        foreach (
            [
                'coretsia/core-foundation' => 'packages/core/foundation/composer.json',
                'coretsia/platform-tracing' => 'packages/platform/tracing/composer.json',
            ] as $name => $path
        ) {
            $metadata = \json_decode(
                (string) \file_get_contents($repositoryRoot . '/' . $path),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
            $versions[$name] = ['type' => $metadata['type'], 'extra' => $metadata['extra']];
        }

        $manifest = new ComposerManifestReader(new ComposerInstalledMetadataProvider([
            ['root' => ['name' => 'coretsia/parity-test', 'type' => 'project', 'extra' => []], 'versions' => $versions],
        ]))->read();
        $selected = $enabled ? 'platform.tracing' : 'core.foundation';
        $selection = new ModuleSelection([ModuleId::fromString($selected)], []);
        $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve('api', $manifest, $selection);

        return new ModuleResolution($manifest, $plan);
    }
}
