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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Platform\Tracing\Provider\TracingServiceProvider;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;

final class TracingModuleSelectionAndFallbackTest extends TestCase
{
    public function testUnselectedAndExplicitlyExcludedTracingKeepTheFoundationFallback(): void
    {
        foreach ([[], ['platform.tracing']] as $excluded) {
            $resolution = self::resolution(['core.foundation'], $excluded);
            $plan = new ContainerProviderPlanResolver()->resolve($resolution);

            self::assertSame([FoundationServiceProvider::class], $plan->providerClasses());
            self::assertFalse($resolution->plan()->hasEnabledModule('platform.tracing'));
            self::assertSame($excluded !== [], $resolution->plan()->hasExcludedModule('platform.tracing'));

            $builder = new ContainerBuilder(['foundation' => []]);
            $builder->registerProviders([new FoundationServiceProvider()]);
            $container = $builder->build();

            self::assertInstanceOf(NoopContextPropagation::class, $container->get(ContextPropagationInterface::class));
            self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
            self::assertSame([], $container->get(ContextPropagationInterface::class)->extract([
                'traceparent' => 'invalid',
            ]));
        }
    }

    public function testExplicitSelectionUsesKernelModulePlanAndDoesNotReadTracingMode(): void
    {
        $resolution = self::resolution(['platform.tracing']);
        $plan = new ContainerProviderPlanResolver()->resolve($resolution);

        self::assertSame(
            ['core.foundation', 'platform.tracing'],
            \array_map(
                static fn (ModuleId $id): string => $id->value(),
                $resolution->plan()->topologicalOrder(),
            )
        );
        self::assertSame([
            FoundationServiceProvider::class,
            TracingServiceProvider::class,
        ], $plan->providerClasses());
        self::assertSame([
            [
                'moduleId' => 'core.foundation',
                'providerClass' => FoundationServiceProvider::class,
                'moduleOrder' => 0,
                'providerOrder' => 0,
            ],
            [
                'moduleId' => 'platform.tracing',
                'providerClass' => TracingServiceProvider::class,
                'moduleOrder' => 1,
                'providerOrder' => 0,
            ],
        ], $plan->entries());

        // No tracing configuration subtree is supplied: module selection owns the binding.
        $builder = new ContainerBuilder(['foundation' => []]);
        $builder->registerProviders([new FoundationServiceProvider(), new TracingServiceProvider()]);
        $container = $builder->build();

        $propagation = $container->get(ContextPropagationInterface::class);
        self::assertInstanceOf(W3cTraceContextPropagation::class, $propagation);
        self::assertSame($propagation, $container->get(ContextPropagationInterface::class));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertSame([], $propagation->extract(['traceparent' => 'invalid']));

        // Intentionally bypass Kernel configuration validation here: even a
        // caller-supplied tracing selector must not change provider wiring.
        $ignoredSelector = new ContainerBuilder([
            'foundation' => [],
            'tracing' => ['propagation' => ['mode' => 'noop']],
        ]);
        $ignoredSelector->registerProviders([
            new FoundationServiceProvider(),
            new TracingServiceProvider(),
        ]);

        self::assertInstanceOf(
            W3cTraceContextPropagation::class,
            $ignoredSelector->build()->get(ContextPropagationInterface::class),
        );
    }

    public function testSelectionOnlyOverridesPropagationWithoutAdditionalTagsOrServices(): void
    {
        $foundation = new ContainerBuilder(['foundation' => []]);
        $foundation->registerProviders([new FoundationServiceProvider()]);
        $selected = new ContainerBuilder(['foundation' => []]);
        $selected->registerProviders([new FoundationServiceProvider(), new TracingServiceProvider()]);

        self::assertSame($foundation->serviceIds(), $selected->serviceIds());
        self::assertSame($foundation->tagRegistry()->tagNames(), $selected->tagRegistry()->tagNames());
        foreach ($foundation->tagRegistry()->tagNames() as $tag) {
            self::assertSame(
                \array_map(
                    static fn (TaggedService $service): array => [
                        $service->id(),
                        $service->priority(),
                        $service->meta(),
                    ],
                    $foundation->tagRegistry()->all($tag),
                ),
                \array_map(
                    static fn (TaggedService $service): array => [
                        $service->id(),
                        $service->priority(),
                        $service->meta(),
                    ],
                    $selected->tagRegistry()->all($tag),
                ),
            );
        }

        self::assertInstanceOf(
            NoopContextPropagation::class,
            $foundation->build()->get(ContextPropagationInterface::class),
        );
        self::assertInstanceOf(
            W3cTraceContextPropagation::class,
            $selected->build()->get(ContextPropagationInterface::class),
        );
    }

    public function testTracingCannotBeSelectedWhenRequiredFoundationIsExcluded(): void
    {
        $this->expectException(ModuleConflictException::class);
        self::resolution(['platform.tracing'], ['core.foundation']);
    }

    /**
     * @param list<string> $roots
     * @param list<string> $excluded
     */
    private static function resolution(array $roots, array $excluded = []): ModuleResolution
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
            $versions[$name] = [
                'type' => $metadata['type'],
                'extra' => $metadata['extra'],
            ];
        }

        $manifest = new ComposerManifestReader(new ComposerInstalledMetadataProvider([
            [
                'root' => ['name' => 'coretsia/selection-test', 'type' => 'project', 'extra' => []],
                'versions' => $versions,
            ],
        ]))->read();

        $selection = new ModuleSelection(
            \array_map(static fn (string $id): ModuleId => ModuleId::fromString($id), $roots),
            \array_map(static fn (string $id): ModuleId => ModuleId::fromString($id), $excluded),
        );
        $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve('api', $manifest, $selection);

        return new ModuleResolution($manifest, $plan);
    }
}
