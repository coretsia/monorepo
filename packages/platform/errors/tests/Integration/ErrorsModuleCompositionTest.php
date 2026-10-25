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

namespace Coretsia\Platform\Errors\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Module\ErrorsModule;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ErrorsModuleCompositionTest extends TestCase
{
    public function testKernelModulePlanClosesErrorsDependenciesAndDoesNotImplicitlyEnableInstalledModules(): void
    {
        $manifest = self::manifest(true);
        $resolver = new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()));
        $plan = $resolver->resolve(
            'console',
            $manifest,
            new ModuleSelection([ModuleId::fromString('platform.errors')], []),
        );
        self::assertSame(
            ['core.foundation', 'platform.errors', 'platform.redaction'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $plan->enabled())
        );
        $topology = \array_map(static fn (ModuleId $id): string => $id->value(), $plan->topologicalOrder());
        self::assertLessThan(
            \array_search('platform.errors', $topology, true),
            \array_search('core.foundation', $topology, true),
        );
        self::assertLessThan(
            \array_search('platform.errors', $topology, true),
            \array_search('platform.redaction', $topology, true),
        );
        self::assertSame([ErrorsServiceProvider::class], new ErrorsModule()->providers());
        $composer = \json_decode(
            (string) \file_get_contents(\dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertSame(['core.foundation', 'platform.redaction'], $composer['extra']['coretsia']['requires']);
        $installedButUnselected = $resolver->resolve('console', $manifest, new ModuleSelection([], []));
        self::assertSame([], $installedButUnselected->enabled());
    }

    public function testMissingRedactionModuleFailsKernelDependencyClosureDeterministically(): void
    {
        $resolver = new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()));
        try {
            $resolver->resolve(
                'console',
                self::manifest(false),
                new ModuleSelection([ModuleId::fromString('platform.errors')], []),
            );
            self::fail('Missing platform.redaction must fail canonical module composition.');
        } catch (ModuleRequiredMissingException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    public function testFullSourceCompositionHasOneHandlerAndOneCanonicalRedactorWithFoundationNoops(): void
    {
        $container = self::sourceContainer();
        self::assertInstanceOf(NoopErrorReporter::class, $container->get(ErrorReporterPortInterface::class));
        self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
        self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));
        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertInstanceOf(
            DefaultSensitiveDataRedactor::class,
            $container->get(SensitiveDataRedactorInterface::class),
        );
        $handler = $container->get(ErrorHandlerInterface::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        self::assertSame($handler, $container->get(ErrorHandler::class));
        self::assertSame($handler, $container->get(ErrorHandlerInterface::class));
        self::assertSame(
            $container->get(SensitiveDataRedactorInterface::class),
            $container->get(DefaultSensitiveDataRedactor::class),
        );
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $handler->handle(new \RuntimeException('PRIVATE'))->code());
    }

    public function testMissingMandatoryPortsFailResolutionWithoutPackageLocalFallbackBindings(): void
    {
        foreach (
            [
                SensitiveDataRedactorInterface::class,
                ErrorReporterPortInterface::class,
                CorrelationIdProviderInterface::class,
            ] as $missing
        ) {
            $builder = new ContainerBuilder(self::config());
            $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository(self::config()));
            $supplied = [
                SensitiveDataRedactorInterface::class => $this->createStub(SensitiveDataRedactorInterface::class),
                ErrorReporterPortInterface::class => new NoopErrorReporter(),
                CorrelationIdProviderInterface::class => $this->createStub(CorrelationIdProviderInterface::class),
                TracerPortInterface::class => new NoopTracer(),
                MeterPortInterface::class => new NoopMeter(),
                LoggerInterface::class => new NoopLogger(),
                Stopwatch::class => new Stopwatch(),
            ];
            unset($supplied[$missing]);
            foreach ($supplied as $id => $service) {
                $builder->instance($id, $service);
            }
            $builder->registerProviders([new ErrorsServiceProvider()]);
            $container = $builder->build();
            self::assertFalse($container->has($missing), $missing);
            try {
                $container->get(ErrorHandlerInterface::class);
                self::fail('Missing mandatory port must fail resolution: ' . $missing);
            } catch (ContainerException $exception) {
                self::assertSame(
                    'container-definition-service-reference-failed',
                    $exception->reason(),
                    $missing,
                );
                self::assertSame(
                    'container-definition-service-reference-failed',
                    $exception->getMessage(),
                    $missing,
                );
            }
        }
        $definitions = new ContainerDefinitionBuilder();
        new ErrorsServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $operations = $definitions->build()->toDescriptorStream();
        foreach ($operations as $operation) {
            foreach (
                [
                    SensitiveDataRedactorInterface::class,
                    ErrorReporterPortInterface::class,
                    CorrelationIdProviderInterface::class,
                ] as $port
            ) {
                self::assertNotSame($port, $operation['id'] ?? null);
                self::assertNotSame($port, $operation['alias'] ?? null);
            }
        }
    }

    public function testCompiledRuntimeAndSourceResolveSameCanonicalTypesAndNormalizationBehavior(): void
    {
        $config = self::config();
        $context = new ContainerDefinitionContext($config);
        $builder = new ContainerDefinitionBuilder();
        foreach (self::providers() as $provider) {
            $provider->define($builder, $context);
        }

        $builder->classService(
            ErrorsModuleCompositionOwnerMapper::class,
            ErrorsModuleCompositionOwnerMapper::class,
        );
        $builder->tag(
            ReservedTags::ERROR_MAPPER,
            ErrorsModuleCompositionOwnerMapper::class,
        );

        $graph = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())
            ->compile($builder->build())->toArray();
        $compiledEnvelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$graph],
        );
        $resolver = new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()));
        $plan = $resolver->resolve(
            'console',
            self::manifest(true),
            new ModuleSelection([ModuleId::fromString('platform.errors')], []),
        );
        $seeds = new RuntimeContainerSeedSet([
            ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
            ModulePlan::class => $plan,
            RuntimePathContext::class => new RuntimePathContext('/tmp/coretsia-app', '/tmp/coretsia-artifacts'),
        ]);
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $compiledEnvelope,
            $seeds,
        );
        $source = self::sourceContainer();
        $source->get(TagRegistry::class)->add(
            ReservedTags::ERROR_MAPPER,
            ErrorsModuleCompositionOwnerMapper::class,
        );

        foreach (
            [
                ErrorHandlerInterface::class,
                SensitiveDataRedactorInterface::class,
                ErrorReporterPortInterface::class,
                CorrelationIdProviderInterface::class,
            ] as $id
        ) {
            self::assertSame($source->get($id)::class, $compiled->get($id)::class);
        }
        self::assertSame($compiled->get(ErrorHandlerInterface::class), $compiled->get(ErrorHandler::class));
        self::assertSame(
            $compiled->get(SensitiveDataRedactorInterface::class),
            $compiled->get(DefaultSensitiveDataRedactor::class),
        );
        $throwable = new \RuntimeException('SYNTHETIC_PRIVATE');
        self::assertSame(
            'COMPOSITION_MAPPED',
            $source->get(ErrorHandlerInterface::class)->handle($throwable)->code(),
        );
        self::assertSame(
            'COMPOSITION_MAPPED',
            $compiled->get(ErrorHandlerInterface::class)->handle($throwable)->code(),
        );
        self::assertSame(
            $source->get(ErrorHandlerInterface::class)->handle($throwable)->toArray(),
            $compiled->get(ErrorHandlerInterface::class)->handle($throwable)->toArray(),
        );
    }

    private static function config(): array
    {
        return [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'errors' => require \dirname(__DIR__, 2) . '/config/errors.php',
        ];
    }

    private static function providers(): array
    {
        return [new FoundationServiceProvider(), new RedactionServiceProvider(), new ErrorsServiceProvider()];
    }

    private static function sourceContainer(): Container
    {
        $config = self::config();
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $builder->registerProviders(self::providers());
        return $builder->build();
    }

    private static function manifest(bool $withRedaction): ModuleManifest
    {
        $descriptors = [
            ModuleDescriptor::fromLayerAndSlug(
                'core',
                'foundation',
                composerName: 'coretsia/core-foundation',
                metadata: ['requires' => [], 'conflicts' => []],
            ),
            ModuleDescriptor::fromLayerAndSlug(
                'platform',
                'errors',
                composerName: 'coretsia/platform-errors',
                packageKind: 'runtime',
                moduleClass: ErrorsModule::class,
                metadata: ['requires' => ['core.foundation', 'platform.redaction'], 'conflicts' => []],
            ),
        ];
        if ($withRedaction) {
            $descriptors[] = ModuleDescriptor::fromLayerAndSlug(
                'platform',
                'redaction',
                composerName: 'coretsia/platform-redaction',
                metadata: ['requires' => ['core.foundation'], 'conflicts' => []],
            );
        }
        return new ModuleManifest($descriptors);
    }
}

final class ErrorsModuleCompositionOwnerMapper implements ExceptionMapperInterface
{
    public function map(\Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        return new ErrorDescriptor('COMPOSITION_MAPPED', 'Operation failed.');
    }
}
