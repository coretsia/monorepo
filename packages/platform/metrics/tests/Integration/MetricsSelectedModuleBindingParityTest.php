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

namespace Coretsia\Platform\Metrics\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
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
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;
use Coretsia\Platform\Metrics\Provider\MetricsServiceProvider;
use PHPUnit\Framework\TestCase;

final class MetricsSelectedModuleBindingParityTest extends TestCase
{
    public function testInstalledButUnselectedMetricsRetainsFoundationOnlyProviderPlan(): void
    {
        $resolution = self::resolution(false);

        self::assertFalse($resolution->plan()->hasEnabledModule('platform.metrics'));
        self::assertSame(
            ['core.foundation'],
            \array_map(
                static fn (ModuleId $id): string => $id->value(),
                $resolution->plan()->topologicalOrder(),
            ),
        );
        self::assertSame(
            [FoundationServiceProvider::class],
            new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses(),
        );
    }

    public function testExplicitSelectionEnablesMetricsAfterRequiredFoundation(): void
    {
        $resolution = self::resolution(true);
        $plan = new ContainerProviderPlanResolver()->resolve($resolution);

        self::assertTrue($resolution->plan()->hasEnabledModule('platform.metrics'));
        self::assertSame(
            ['core.foundation', 'platform.metrics'],
            \array_map(
                static fn (ModuleId $id): string => $id->value(),
                $resolution->plan()->topologicalOrder(),
            ),
        );
        self::assertSame([
            FoundationServiceProvider::class,
            MetricsServiceProvider::class,
        ], $plan->providerClasses());
        self::assertSame([
            [
                'moduleId' => 'core.foundation',
                'providerClass' => FoundationServiceProvider::class,
                'moduleOrder' => 0,
                'providerOrder' => 0,
            ],
            [
                'moduleId' => 'platform.metrics',
                'providerClass' => MetricsServiceProvider::class,
                'moduleOrder' => 1,
                'providerOrder' => 0,
            ],
        ], $plan->entries());
    }

    public function testMetricsProviderContributesOnlyTwoDirectClassBindings(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new MetricsServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(['foundation' => [], 'metrics' => []]),
        );

        self::assertSame([
            [
                'arguments' => [],
                'class' => DefaultMeter::class,
                'id' => MeterPortInterface::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
            [
                'arguments' => [],
                'class' => NullMetricsRenderer::class,
                'id' => MetricsRendererInterface::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
        ], $definitions->build()->toDescriptorStream());
    }

    public function testCompiledSelectionChangesOnlyMeterAndAddsNullRenderer(): void
    {
        $compiler = self::compiler();
        $config = self::config();
        $foundation = self::resolution(false);
        $metrics = self::resolution(true);
        $baseline = $compiler->compile($foundation, $config)->toArray();
        $selected = $compiler->compile($metrics, $config)->toArray();

        self::assertSame($baseline['aliases'], $selected['aliases']);
        self::assertSame($baseline['parameters'], $selected['parameters']);
        self::assertSame($baseline['tags'], $selected['tags']);
        self::assertArrayNotHasKey(MetricsRendererInterface::class, $baseline['services']);

        $expectedServices = $baseline['services'];
        $expectedServices[MeterPortInterface::class] = $selected['services'][MeterPortInterface::class];
        $expectedServices[MetricsRendererInterface::class] = $selected['services'][MetricsRendererInterface::class];
        \ksort($expectedServices, \SORT_STRING);
        self::assertSame($expectedServices, $selected['services']);

        self::assertSame(
            NoopMeter::class,
            $baseline['services'][MeterPortInterface::class]['construction']['class'],
        );
        self::assertSame(
            DefaultMeter::class,
            $selected['services'][MeterPortInterface::class]['construction']['class'],
        );
        self::assertSame(
            NullMetricsRenderer::class,
            $selected['services'][MetricsRendererInterface::class]['construction']['class'],
        );

        foreach ([MeterPortInterface::class, MetricsRendererInterface::class] as $serviceId) {
            self::assertSame('class', $selected['services'][$serviceId]['type']);
            self::assertSame([], $selected['services'][$serviceId]['arguments']);
            self::assertTrue($selected['services'][$serviceId]['shared']);
        }

        self::assertSame($selected, $compiler->compile($metrics, $config)->toArray());
    }

    public function testSourceAndCompiledContainersPreserveSelectedAndFallbackBindings(): void
    {
        foreach ([false, true] as $selected) {
            $resolution = self::resolution($selected);
            [$source, $compiled] = self::containers($resolution);
            $expectedMeter = $selected ? DefaultMeter::class : NoopMeter::class;

            foreach ([$source, $compiled] as $container) {
                self::assertInstanceOf($expectedMeter, $container->get(MeterPortInterface::class));
                self::assertSame(
                    $selected,
                    $container->has(MetricsRendererInterface::class),
                );
                self::assertSame(
                    $container->get(MeterPortInterface::class),
                    $container->get(MeterPortInterface::class),
                );

                if ($selected) {
                    $renderer = $container->get(MetricsRendererInterface::class);
                    self::assertInstanceOf(NullMetricsRenderer::class, $renderer);
                    self::assertSame($renderer, $container->get(MetricsRendererInterface::class));
                    self::assertSame('text/plain; charset=utf-8', $renderer->contentType());
                    self::assertSame('', $renderer->render());
                }
            }
        }
    }

    public function testSelectedMetersDiscardAdmittedAndRejectedSamplesWithoutSideEffects(): void
    {
        [$source, $compiled] = self::containers(self::resolution(true));

        self::assertTrue(AllowedLabelSet::accepts(['outcome' => 'ok']));
        self::assertFalse(AllowedLabelSet::accepts(['token' => 'forbidden']));
        self::assertFalse(AllowedLabelSet::accepts(['outcome' => new \stdClass()]));

        $meterClass = new \ReflectionClass(DefaultMeter::class);
        $meterFile = $meterClass->getFileName();

        self::assertIsString($meterFile);
        $meterLines = \file($meterFile);
        self::assertIsArray($meterLines);

        foreach (['increment', 'observe'] as $operation) {
            $method = $meterClass->getMethod($operation);
            $firstLine = $method->getStartLine();
            $lastLine = $method->getEndLine();

            self::assertIsInt($firstLine);
            self::assertIsInt($lastLine);

            $methodSource = \implode(
                '',
                \array_slice(
                    $meterLines,
                    $firstLine - 1,
                    $lastLine - $firstLine + 1,
                ),
            );

            self::assertStringContainsString(
                'if (!AllowedLabelSet::accepts($labels)) {',
                $methodSource,
            );
        }

        foreach ([$source, $compiled] as $container) {
            $meter = $container->get(MeterPortInterface::class);
            $renderer = $container->get(MetricsRendererInterface::class);
            $context = $container->get(ContextStore::class);
            $warnings = [];

            self::assertInstanceOf(DefaultMeter::class, $meter);
            self::assertSame([], $context->all());

            \set_error_handler(
                static function (int $severity, string $message) use (&$warnings): bool {
                    $warnings[] = [$severity, $message];

                    return true;
                },
            );
            \ob_start();

            try {
                for ($attempt = 0; $attempt < 32; ++$attempt) {
                    $meter->increment('kernel.operation_total', 1, [
                        'operation' => 'config.validate',
                        'outcome' => 'success',
                    ]);
                    $meter->observe('kernel.operation_duration_ms', $attempt, [
                        'operation' => 'config.validate',
                        'outcome' => 'success',
                    ]);
                    $meter->increment('kernel.operation_total', 1, ['token' => 'forbidden']);
                    $meter->observe('kernel.operation_duration_ms', $attempt, ['outcome' => []]);
                    self::assertSame('', $renderer->render());
                }
            } finally {
                $output = (string) \ob_get_clean();
                \restore_error_handler();
            }

            self::assertSame([], $warnings);
            self::assertSame('', $output);
            self::assertSame([], $context->all());
            self::assertSame('', $renderer->render());
        }
    }

    public function testMetricsBaselineIntroducesNoMutableStateOrResetOwner(): void
    {
        foreach ([DefaultMeter::class, NullMetricsRenderer::class, AllowedLabelSet::class] as $class) {
            $reflection = new \ReflectionClass($class);

            self::assertTrue($reflection->isFinal());
            self::assertNull($reflection->getConstructor());
            self::assertSame([], $reflection->getProperties());
            self::assertSame([], $reflection->getStaticProperties());
            self::assertFalse($reflection->hasMethod('reset'));
            self::assertFalse($reflection->hasMethod('snapshot'));
        }
    }

    public function testMetricsSelectionRejectsExcludingRequiredFoundation(): void
    {
        $this->expectException(ModuleConflictException::class);
        self::resolution(true, true);
    }

    /**
     * @return array{0: Container, 1: Container}
     */
    private static function containers(ModuleResolution $resolution): array
    {
        $config = self::config();
        $providers = [];

        foreach (new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses() as $providerClass) {
            /** @var class-string<ServiceProviderInterface> $providerClass */
            $providers[] = new $providerClass();
        }

        $builder = new ContainerBuilder($config);
        $builder->registerProviders($providers);
        $source = $builder->build();
        $graph = self::compiler()->compile($resolution, $config);
        $seeds = new RuntimeContainerSeedSet([
            ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
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

        return [$source, $compiled];
    }

    private static function compiler(): RuntimeContainerGraphCompiler
    {
        return new RuntimeContainerGraphCompiler(
            providerPlanResolver: new ContainerProviderPlanResolver(),
            containerCompiler: new ContainerCompiler(
                tracer: new NoopTracer(),
                meter: new NoopMeter(),
                logger: new NoopLogger(),
                stopwatch: new Stopwatch(),
            ),
            completenessValidator: new ContainerGraphCompletenessValidator(),
        );
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function config(): array
    {
        // An installed config root must not opt a module into the target-local plan.
        return ['foundation' => [], 'metrics' => []];
    }

    private static function resolution(bool $enabled, bool $excludeFoundation = false): ModuleResolution
    {
        $repositoryRoot = \dirname(__DIR__, 5);
        $versions = [];

        foreach (
            [
                'coretsia/core-foundation' => 'packages/core/foundation/composer.json',
                'coretsia/platform-metrics' => 'packages/platform/metrics/composer.json',
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
                'root' => ['name' => 'coretsia/metrics-parity-test', 'type' => 'project', 'extra' => []],
                'versions' => $versions,
            ],
        ]))->read();
        $selected = $enabled ? 'platform.metrics' : 'core.foundation';
        $selection = new ModuleSelection(
            [ModuleId::fromString($selected)],
            $excludeFoundation ? [ModuleId::fromString('core.foundation')] : [],
        );
        $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve('api', $manifest, $selection);

        return new ModuleResolution($manifest, $plan);
    }
}
