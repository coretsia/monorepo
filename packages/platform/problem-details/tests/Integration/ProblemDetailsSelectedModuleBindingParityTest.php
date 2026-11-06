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

namespace Coretsia\Platform\ProblemDetails\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Logging\NoopLogger;
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
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class ProblemDetailsSelectedModuleBindingParityTest extends TestCase
{
    public function testExplicitSelectionClosesRuntimeDependenciesAndContributesOneMiddlewareProvider(): void
    {
        $selected = self::resolution(true);
        $unselected = self::resolution(false);
        $providerResolver = new ContainerProviderPlanResolver();

        self::assertTrue($selected->plan()->hasEnabledModule('platform.problem-details'));
        self::assertFalse($unselected->plan()->hasEnabledModule('platform.problem-details'));
        self::assertSame([
            'core.foundation',
            'platform.redaction',
            'platform.errors',
            'platform.problem-details',
        ], self::moduleIds($selected));
        self::assertSame([
            'core.foundation',
            'platform.redaction',
            'platform.errors',
        ], self::moduleIds($unselected));
        self::assertSame([
            FoundationServiceProvider::class,
            RedactionServiceProvider::class,
            ErrorsServiceProvider::class,
            ProblemDetailsServiceProvider::class,
        ], $providerResolver->resolve($selected)->providerClasses());
        self::assertSame([
            FoundationServiceProvider::class,
            RedactionServiceProvider::class,
            ErrorsServiceProvider::class,
        ], $providerResolver->resolve($unselected)->providerClasses());
    }

    public function testSelectedModuleWithoutHttpFactoriesIsRejectedByKernelCompilation(): void
    {
        $resolution = self::resolution(true);
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext(self::config());

        foreach (new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses() as $class) {
            new $class()->define($definitions, $context);
        }

        try {
            new ContainerCompiler(
                new NoopTracer(),
                new NoopMeter(),
                new NoopLogger(),
                new Stopwatch(),
            )->compile($definitions->build());
        } catch (ContainerCompileFailedException $exception) {
            self::assertSame(ContainerCompileFailedException::REASON_GRAPH_INVALID, $exception->reason());

            return;
        }

        self::fail('Selected problem-details must not compile without HTTP response and stream factories.');
    }

    public function testSourceAndCompiledContainersHaveEquivalentSelectedAndUnselectedBindings(): void
    {
        foreach ([false, true] as $selected) {
            $resolution = self::resolution($selected);
            [$source, $compiled] = self::containers($resolution);

            foreach ([$source, $compiled] as $container) {
                $handler = $container->get(ErrorHandlerInterface::class);
                self::assertInstanceOf(ErrorHandler::class, $handler);
                self::assertSame($handler, $container->get(ErrorHandler::class));
                self::assertInstanceOf(
                    ResponseFactoryInterface::class,
                    $container->get(ResponseFactoryInterface::class),
                );
                self::assertInstanceOf(StreamFactoryInterface::class, $container->get(StreamFactoryInterface::class));
                self::assertSame(
                    $selected,
                    \in_array(HttpErrorHandlingMiddleware::class, $container->serviceIds(), true),
                );
                self::assertSame(
                    $selected,
                    \in_array(ErrorDescriptorToProblemDetailsAdapter::class, $container->serviceIds(), true),
                );
                self::assertSame(
                    $selected,
                    \in_array(ProblemDetailsJsonRenderer::class, $container->serviceIds(), true),
                );

                $registrations = $container->get(TagRegistry::class)->all(
                    ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                );
                self::assertCount($selected ? 1 : 0, $registrations);
                if (!$selected) {
                    continue;
                }

                self::assertSame(HttpErrorHandlingMiddleware::class, $registrations[0]->id());
                self::assertSame(1000, $registrations[0]->priority());
                self::assertSame([], $registrations[0]->meta());
                $middleware = $container->get(HttpErrorHandlingMiddleware::class);
                self::assertInstanceOf(HttpErrorHandlingMiddleware::class, $middleware);
                self::assertSame($middleware, $container->get(HttpErrorHandlingMiddleware::class));
                self::assertSame(
                    'application/problem+json',
                    $container->get(ProblemDetailsJsonRenderer::class)->contentType(),
                );
            }
        }
    }

    public function testProviderDescriptorsReferenceOnlyCanonicalPortsAndOneMiddlewareTag(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new ProblemDetailsServiceProvider()->define($definitions, new ContainerDefinitionContext(self::config()));
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();

        self::assertSame([
            ErrorHandlerInterface::class,
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
        ], $set->requiredServiceIds());
        self::assertCount(4, $operations);
        $tags = \array_values(
            \array_filter(
                $operations,
                static fn (array $operation): bool => $operation['kind'] === 'tag',
            ),
        );
        self::assertSame([
            [
                'kind' => 'tag',
                'meta' => [],
                'priority' => 1000,
                'serviceId' => HttpErrorHandlingMiddleware::class,
                'tag' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ],
        ], $tags);
        foreach ($operations as $operation) {
            if ($operation['kind'] !== 'service.class') {
                continue;
            }
            self::assertContains($operation['id'], [
                ErrorDescriptorToProblemDetailsAdapter::class,
                ProblemDetailsJsonRenderer::class,
                HttpErrorHandlingMiddleware::class,
            ]);
            self::assertTrue($operation['shared']);
        }
    }

    /**
     * @return array{Container, Container}
     */
    private static function containers(ModuleResolution $resolution): array
    {
        $config = self::config();
        $providers = self::providers($resolution);
        $sourceBuilder = new ContainerBuilder($config);
        $sourceBuilder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $sourceBuilder->registerProviders($providers);
        $source = $sourceBuilder->build();

        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        foreach ($providers as $provider) {
            $provider->define($definitions, $context);
        }
        $set = $definitions->build();
        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($set);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);
        $envelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$graph->toArray()],
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
                ModulePlan::class => $resolution->plan(),
                RuntimePathContext::class => new RuntimePathContext('/coretsia-test', '/coretsia-test/var/cache'),
            ]),
        );

        return [$source, $compiled];
    }

    /**
     * @return list<ServiceProviderInterface&ContainerDefinitionProviderInterface>
     */
    private static function providers(ModuleResolution $resolution): array
    {
        $providers = [];
        foreach (new ContainerProviderPlanResolver()->resolve($resolution)->providerClasses() as $class) {
            $providers[] = new $class();
        }
        // The compatible HTTP target is represented only by transport factories.
        // This does not simulate or claim the platform/http middleware pipeline.
        $providers[] = new ProblemDetailsTestHttpFactoriesProvider();

        return $providers;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function config(): array
    {
        return [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'errors' => require \dirname(__DIR__, 4) . '/platform/errors/config/errors.php',
            'problem_details' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function moduleIds(ModuleResolution $resolution): array
    {
        return \array_map(static fn (ModuleId $id): string => $id->value(), $resolution->plan()->topologicalOrder());
    }

    private static function resolution(bool $selected): ModuleResolution
    {
        $root = \dirname(__DIR__, 5);
        $versions = [];
        foreach (
            [
                'coretsia/core-foundation' => 'packages/core/foundation/composer.json',
                'coretsia/platform-redaction' => 'packages/platform/redaction/composer.json',
                'coretsia/platform-errors' => 'packages/platform/errors/composer.json',
                'coretsia/platform-problem-details' => 'packages/platform/problem-details/composer.json',
            ] as $name => $path
        ) {
            $composer = \json_decode((string) \file_get_contents($root . '/' . $path), true, 512, \JSON_THROW_ON_ERROR);
            $versions[$name] = ['type' => $composer['type'], 'extra' => $composer['extra']];
        }
        $manifest = new ComposerManifestReader(new ComposerInstalledMetadataProvider([
            [
                'root' => ['name' => 'coretsia/problem-details-parity-test', 'type' => 'project', 'extra' => []],
                'versions' => $versions,
            ],
        ]))->read();
        $selection = new ModuleSelection([
            ModuleId::fromString($selected ? 'platform.problem-details' : 'platform.errors'),
        ], []);
        $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve('api', $manifest, $selection);

        return new ModuleResolution($manifest, $plan);
    }
}

/**
 * Test-local compatible HTTP factory bindings. HTTP pipeline ownership remains external.
 */
final class ProblemDetailsTestHttpFactoriesProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $definitions
            ->classService(ResponseFactoryInterface::class, ProblemDetailsTestResponseFactory::class)
            ->classService(StreamFactoryInterface::class, ProblemDetailsTestStreamFactory::class);
    }
}

final class ProblemDetailsTestResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('Transport-only test fixture does not construct responses.');
    }
}

final class ProblemDetailsTestStreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('Transport-only test fixture does not create streams.');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('Transport-only test fixture does not read files.');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('Transport-only test fixture does not read resources.');
    }
}
