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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TracingPropagationApplicationUsageSmokeTest extends TestCase
{
    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03';

    public function testExplicitApiSelectionExtractsAndInjectsWithoutRecordingOrContextMutation(): void
    {
        [$builder, $resolution] = self::applicationBuilder(true);
        $container = $builder->build();
        $propagation = $container->get(ContextPropagationInterface::class);
        $store = $container->get(ContextStore::class);
        $tracer = $container->get(TracerPortInterface::class);

        self::assertSame('api', $resolution->plan()->app());
        self::assertTrue($resolution->plan()->hasEnabledModule('platform.tracing'));
        self::assertInstanceOf(W3cTraceContextPropagation::class, $propagation);
        self::assertInstanceOf(NoopTracer::class, $tracer);
        self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));
        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertSame([], $store->all());

        $expected = [
            'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
            'parent_id' => '00f067aa0ba902b7',
            'trace_flags' => '03',
        ];
        $incoming = [
            'traceparent' => self::TRACEPARENT,
            'tracestate' => 'private=opaque',
        ];
        $outbound = ['x-request-id' => ['caller-owned']];
        $initialIncoming = $incoming;
        $initialOutbound = $outbound;

        $logPath = \tempnam(\sys_get_temp_dir(), 'coretsia-tracing-');
        if ($logPath === false) {
            self::fail('Cannot allocate a temporary error log capture.');
        }

        $previousErrorLog = \ini_get('error_log');
        \ini_set('error_log', $logPath);
        \ob_start();
        try {
            self::assertSame($logPath, \ini_get('error_log'));
            for ($iteration = 0; $iteration < 8; $iteration++) {
                $context = $propagation->extract($incoming);
                self::assertSame($expected, $context);
                self::assertSame([
                    'x-request-id' => ['caller-owned'],
                    'traceparent' => self::TRACEPARENT,
                ], $propagation->inject($outbound, $context));
                self::assertSame([], $store->all());
                self::assertNull($tracer->currentSpan());
            }
        } finally {
            $output = \ob_get_clean();
            \ini_set('error_log', (string) $previousErrorLog);
            $logged = \file_get_contents($logPath);
            \unlink($logPath);
        }

        self::assertSame('', $output);
        self::assertSame('', $logged);
        self::assertSame($initialIncoming, $incoming);
        self::assertSame($initialOutbound, $outbound);

        [$fallbackBuilder] = self::applicationBuilder(false);
        self::assertSame($fallbackBuilder->tagRegistry()->tagNames(), $builder->tagRegistry()->tagNames());
        foreach ($fallbackBuilder->tagRegistry()->tagNames() as $tag) {
            self::assertSame(
                \array_map(
                    static fn (TaggedService $service): array => [
                        $service->id(),
                        $service->priority(),
                        $service->meta(),
                    ],
                    $fallbackBuilder->tagRegistry()->all($tag),
                ),
                \array_map(
                    static fn (TaggedService $service): array => [
                        $service->id(),
                        $service->priority(),
                        $service->meta(),
                    ],
                    $builder->tagRegistry()->all($tag),
                ),
            );
        }
    }

    public function testExcludedTracingPreservesFoundationNoopAndDoesNotSanitizeHeaders(): void
    {
        [$builder, $resolution] = self::applicationBuilder(false);
        $container = $builder->build();
        $propagation = $container->get(ContextPropagationInterface::class);
        $carrier = ['traceparent' => 'invalid', 'tracestate' => 'caller-owned'];

        self::assertSame('api', $resolution->plan()->app());
        self::assertFalse($resolution->plan()->hasEnabledModule('platform.tracing'));
        self::assertTrue($resolution->plan()->hasExcludedModule('platform.tracing'));
        self::assertInstanceOf(NoopContextPropagation::class, $propagation);
        self::assertSame([], $propagation->extract($carrier));
        self::assertSame($carrier, $propagation->inject($carrier));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertSame([], $container->get(ContextStore::class)->all());
    }

    /**
     * @return array{ContainerBuilder, ModuleResolution}
     */
    private static function applicationBuilder(bool $enabled): array
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
            [
                'root' => ['name' => 'coretsia/usage-smoke-test', 'type' => 'project', 'extra' => []],
                'versions' => $versions,
            ],
        ]))->read();
        $selection = new ModuleSelection(
            [ModuleId::fromString($enabled ? 'platform.tracing' : 'core.foundation')],
            $enabled ? [] : [ModuleId::fromString('platform.tracing')],
        );
        $modulePlan = new ModuleGraphResolver(new TopologicalSorter())->resolve('api', $manifest, $selection);
        $resolution = new ModuleResolution($manifest, $modulePlan);
        $providerClasses = new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses();
        $builder = new ContainerBuilder(['foundation' => []]);
        $providers = [];
        foreach ($providerClasses as $providerClass) {
            /** @var class-string<ServiceProviderInterface> $providerClass */
            $providers[] = new $providerClass();
        }
        $builder->registerProviders($providers);

        return [$builder, $resolution];
    }
}
