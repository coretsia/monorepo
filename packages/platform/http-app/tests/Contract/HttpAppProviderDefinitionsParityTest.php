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

namespace Coretsia\Platform\HttpApp\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\HttpApp\ActionInvokerInterface;
use Coretsia\Contracts\HttpApp\ArgumentResolverInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
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
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Error\HttpAppProblemMapper;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouteMiddlewareSelector;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class HttpAppProviderDefinitionsParityTest extends TestCase
{
    #[DataProvider('flagPolicies')]
    public function testExactProviderDescriptorsRequiredServicesAndConstructorDependencies(
        bool $allowRequest,
        bool $allowRouteParams,
        bool $jsonEnabled,
        bool $htmlEnabled,
        bool $tracingEnabled,
        bool $metricsEnabled,
        bool $loggingEnabled,
    ): void {
        $config = self::config();
        $config['http_app']['arguments']['allow_request'] = $allowRequest;
        $config['http_app']['arguments']['allow_route_params'] = $allowRouteParams;
        $config['http_app']['response']['json']['enabled'] = $jsonEnabled;
        $config['http_app']['response']['html']['enabled'] = $htmlEnabled;
        $config['http_app']['tracing']['enabled'] = $tracingEnabled;
        $config['http_app']['metrics']['enabled'] = $metricsEnabled;
        $config['http_app']['logging']['enabled'] = $loggingEnabled;
        $provider = new HttpAppServiceProvider();
        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, $provider);
        $set = self::definitions([$provider], $config);
        $ref = static fn (string $id): array => ContainerValueReference::service($id)->toArray();
        $expected = [];
        $classes = [
            HandlerResolver::class => [$ref(Container::class), $ref(ConfigRepositoryInterface::class)],
            ArgumentResolver::class => [
                $ref(HandlerResolver::class),
                $ref(Container::class),
                $allowRequest,
                $allowRouteParams,
                $ref(ConfigRepositoryInterface::class),
            ],
            ActionInvoker::class => [$ref(HandlerResolver::class), $ref(ArgumentResolver::class)],
            ActionResponseMapper::class => [
                $ref(ResponseFactoryInterface::class),
                $ref(StreamFactoryInterface::class),
                $jsonEnabled,
                $htmlEnabled,
            ],
            RouterMiddleware::class => [$ref(RouterInterface::class)],
            HttpAppProblemMapper::class => [],
            RouteMiddlewareSelector::class => [$ref(ConfigRepositoryInterface::class)],
            ActionDispatchMiddleware::class => [
                $ref(Container::class),
                $ref(RouteMiddlewareSelector::class),
                $ref(ActionInvoker::class),
                $ref(ActionResponseMapper::class),
                $ref(TracerPortInterface::class),
                $ref(MeterPortInterface::class),
                $ref(LoggerInterface::class),
                $ref(Stopwatch::class),
                $tracingEnabled,
                $metricsEnabled,
                $loggingEnabled,
            ],
        ];
        foreach ($classes as $class => $arguments) {
            $expected[] = [
                'arguments' => $arguments,
                'class' => $class,
                'id' => $class,
                'kind' => 'service.class',
                'shared' => true,
            ];
            $constructor = new \ReflectionClass($class)->getConstructor();
            if ($constructor === null) {
                self::assertSame([], $arguments);
                continue;
            }
            self::assertSame(\count($arguments), $constructor->getNumberOfParameters());
            self::assertSame(\count($arguments), $constructor->getNumberOfRequiredParameters());
            foreach ($constructor->getParameters() as $i => $parameter) {
                self::assertFalse($parameter->isOptional());
                self::assertFalse($parameter->allowsNull());
                self::assertSame(
                    \is_bool($arguments[$i]) ? 'bool' : $arguments[$i]['id'],
                    (string) $parameter->getType(),
                );
            }
        }
        $expected[] = [
            'alias' => ArgumentResolverInterface::class,
            'kind' => 'alias',
            'serviceId' => ArgumentResolver::class,
        ];
        $expected[] = [
            'alias' => ActionInvokerInterface::class,
            'kind' => 'alias',
            'serviceId' => ActionInvoker::class,
        ];
        foreach (self::tags() as $tag => [$id, $priority]) {
            $expected[] = ['kind' => 'tag', 'meta' => [], 'priority' => $priority, 'serviceId' => $id, 'tag' => $tag];
        }
        self::assertSame($expected, $set->toDescriptorStream());
        $required = [
            'action.alias',
            'action.second',
            HttpAppParityArgument::class,
            'route.z',
            'route.a',
            ConfigRepositoryInterface::class,
            Container::class,
            RouterInterface::class,
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
            TracerPortInterface::class,
            MeterPortInterface::class,
            LoggerInterface::class,
            Stopwatch::class,
        ];
        \sort($required, \SORT_STRING);
        self::assertSame($required, $set->requiredServiceIds());
        self::assertSame(
            $expected,
            ContainerDefinitionSet::fromValidatedState($expected, $required)->toDescriptorStream(),
        );
        self::assertNoRuntimeValues($set->toDescriptorStream());
        $changed = $config;
        $changed['http_app']['handlers'] = [['action_id' => 'another.action', 'service_id' => 'another.service']];
        $changed['http_app']['arguments']['allow_container_types'] = ['App\\OtherType'];
        $changed['http_app']['route_middleware'] = ['another.middleware'];
        // Dynamic configuration changes required ids only, never constructor argument maps.
        $changedSet = self::definitions([$provider], $changed);
        self::assertSame($expected, $changedSet->toDescriptorStream());
        self::assertNotSame($required, $changedSet->requiredServiceIds());
    }

    /** @return iterable<string, array{bool, bool, bool, bool, bool, bool, bool}> */
    public static function flagPolicies(): iterable
    {
        yield 'all-enabled' => [true, true, true, true, true, true, true];
        yield 'all-disabled' => [false, false, false, false, false, false, false];
        yield 'request-only' => [true, false, false, false, false, false, false];
        yield 'route-only' => [false, true, false, false, false, false, false];
        yield 'json-only' => [false, false, true, false, false, false, false];
        yield 'html-only' => [false, false, false, true, false, false, false];
        yield 'tracing-only' => [false, false, false, false, true, false, false];
        yield 'metrics-only' => [false, false, false, false, false, true, false];
        yield 'logging-only' => [false, false, false, false, false, false, true];
    }

    #[DataProvider('missingBindings')]
    public function testMissingConfiguredBindingsFailOnlyAtCanonicalMergedGraphValidation(string $missing): void
    {
        $config = self::config();
        $config['foundation']['container']['autowire_concrete'] = true;
        $config['foundation']['container']['allow_reflection_for_concrete'] = true;
        $providers = self::providers($missing);
        $appSet = self::definitions([new HttpAppServiceProvider()], $config);
        $baseSet = self::definitions(\array_slice($providers, 0, 3), $config);
        $fixtureSet = self::definitions([$providers[4]], $config);
        $set = ContainerDefinitionSet::merge($baseSet, $appSet, $fixtureSet);
        // Source registration/application must not perform final graph completeness validation.
        $repository = new ArrayConfigRepository($config, []);
        $source = new ContainerBuilder($config)
            ->instance(ConfigRepositoryInterface::class, $repository)
            ->registerProviders($providers)->build();
        $applied = new ContainerBuilder($config)
            ->instance(ConfigRepositoryInterface::class, $repository)
            ->applyDefinitions($set)->build();
        self::assertFalse(\in_array($missing, $applied->serviceIds(), true));
        self::assertContains(ActionDispatchMiddleware::class, $applied->serviceIds());
        self::assertContains($missing, $appSet->requiredServiceIds());
        self::assertFalse(\in_array($missing, $source->serviceIds(), true));
        self::assertContains(ActionDispatchMiddleware::class, $source->serviceIds());
        $counter = $source->get(HttpAppParityCounter::class);
        self::assertSame(0, $counter->actions);
        self::assertSame(0, $counter->middleware);
        $graph = self::compile($set);
        try {
            new ContainerGraphCompletenessValidator()->validate($graph, $set);
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame('required-service-invalid', $exception->reason());
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('missing-http-app-configured-binding-accepted');
    }

    /** @return iterable<string, array{string}> */
    public static function missingBindings(): iterable
    {
        yield 'handler-alias' => ['action.alias'];
        yield 'direct-handler' => ['action.second'];
        yield 'concrete-argument' => [HttpAppParityArgument::class];
        yield 'first-route-middleware' => ['route.z'];
        yield 'second-route-middleware' => ['route.a'];
    }

    #[DataProvider('runtimePolicies')]
    public function testSourceAndCompiledCompleteGraphsResolveEquivalentServicesLazily(bool $enabled): void
    {
        $config = self::config();
        $config['http_app']['arguments']['allow_request'] = $enabled;
        $config['http_app']['response']['json']['enabled'] = $enabled;
        $config['http_app']['response']['html']['enabled'] = !$enabled;
        $config['http_app']['tracing']['enabled'] = $enabled;
        $config['http_app']['metrics']['enabled'] = !$enabled;
        $config['http_app']['logging']['enabled'] = $enabled;
        $providers = self::providers(null);
        $repository = new ArrayConfigRepository($config, []);
        // The graph models provider contributions and compatible transport bindings,
        // not a complete Kernel boot. Unrelated runtime entrypoint seeds are inert.
        $seeds = new RuntimeContainerSeedSet([
            ConfigRepositoryInterface::class => $repository,
            ModulePlan::class => new ModulePlan('web', [], [], [], []),
            RuntimePathContext::class => new RuntimePathContext('/coretsia-test', '/coretsia-test/var/cache'),
            RuntimeDiagnosticsInput::class => new RuntimeDiagnosticsInput(
                appEnv: 'test',
                artifactGenerationLoaded: false,
            ),
        ]);
        $builder = new ContainerBuilder($config);
        foreach ($seeds->instances() as $id => $instance) {
            $builder->instance($id, $instance);
        }
        $builder->registerProviders($providers);
        $source = $builder->build();
        $set = self::definitions($providers, $config);
        $graph = self::compile($set);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);
        $payload = $graph->toArray();
        $appSet = self::definitions([new HttpAppServiceProvider()], $config);
        foreach ($appSet->toDescriptorStream() as $operation) {
            if ($operation['kind'] === 'service.class') {
                self::assertSame([
                    'arguments' => $operation['arguments'],
                    'construction' => ['class' => $operation['class']],
                    'id' => $operation['id'],
                    'shared' => true,
                    'type' => 'class',
                ], $payload['services'][$operation['id']]);
            }
            if ($operation['kind'] === 'alias') {
                self::assertSame($operation['serviceId'], $payload['aliases'][$operation['alias']]);
            }
        }
        foreach (self::tags() as $tag => [$id, $priority]) {
            self::assertSame([['id' => $id, 'meta' => [], 'priority' => $priority]], $payload['tags'][$tag]);
        }
        $envelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$payload],
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            $seeds,
        );
        self::assertSame($source->serviceIds(), $compiled->serviceIds());
        $expectedIds = [
            ...\array_keys($payload['services']),
            ...\array_keys($payload['aliases']),
            ...$seeds->ids(),
            TagRegistry::class,
        ];
        \sort($expectedIds, \SORT_STRING);
        self::assertSame($expectedIds, $source->serviceIds());
        foreach ([$source, $compiled] as $container) {
            self::assertSame($repository, $container->get(ConfigRepositoryInterface::class));
            $counter = $container->get(HttpAppParityCounter::class);
            self::assertSame(0, $counter->actions);
            self::assertSame(0, $counter->middleware);
            $resolver = $container->get(HandlerResolver::class);
            $argumentResolver = $container->get(ArgumentResolverInterface::class);
            $invoker = $container->get(ActionInvokerInterface::class);
            $selector = $container->get(RouteMiddlewareSelector::class);
            $router = $container->get(RouterMiddleware::class);
            $dispatcher = $container->get(ActionDispatchMiddleware::class);
            $mapper = $container->get(ActionResponseMapper::class);
            self::assertSame($resolver, $container->get(HandlerResolver::class));
            self::assertSame($argumentResolver, $container->get(ArgumentResolver::class));
            self::assertSame($invoker, $container->get(ActionInvoker::class));
            self::assertSame($dispatcher, $container->get(ActionDispatchMiddleware::class));
            self::assertSame($selector, $container->get(RouteMiddlewareSelector::class));
            self::assertSame($router, $container->get(RouterMiddleware::class));
            self::assertSame($mapper, $container->get(ActionResponseMapper::class));
            self::assertSame($container, new \ReflectionProperty($resolver, 'container')->getValue($resolver));
            self::assertSame(
                $container,
                new \ReflectionProperty($argumentResolver, 'container')->getValue($argumentResolver),
            );
            self::assertSame(
                $resolver,
                new \ReflectionProperty($argumentResolver, 'handlerResolver')->getValue($argumentResolver),
            );
            self::assertSame(0, $counter->actions);
            self::assertSame(0, $counter->middleware);
            self::assertInstanceOf(HttpAppParityRouter::class, $container->get(RouterInterface::class));
            self::assertInstanceOf(
                HttpAppParityResponseFactory::class,
                $container->get(ResponseFactoryInterface::class),
            );
            self::assertInstanceOf(HttpAppParityStreamFactory::class, $container->get(StreamFactoryInterface::class));
            self::assertInstanceOf(NoopTracer::class, $container->get(TracerPortInterface::class));
            self::assertInstanceOf(NoopMeter::class, $container->get(MeterPortInterface::class));
            self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
            $errorHandler = $container->get(ErrorHandlerInterface::class);
            self::assertInstanceOf(ErrorHandler::class, $errorHandler);
            self::assertSame($errorHandler, $container->get(ErrorHandler::class));
            foreach (
                [
                    [$argumentResolver, 'allowRequest', $enabled],
                    [$argumentResolver, 'allowRouteParams', true],
                    [$mapper, 'jsonEnabled', $enabled],
                    [$mapper, 'htmlEnabled', !$enabled],
                    [$dispatcher, 'tracingEnabled', $enabled],
                    [$dispatcher, 'metricsEnabled', !$enabled],
                    [$dispatcher, 'loggingEnabled', $enabled],
                ] as [$service, $property, $flag]
            ) {
                self::assertSame($flag, new \ReflectionProperty($service, $property)->getValue($service));
            }
            self::assertSame(
                $container->get(RouterInterface::class),
                new \ReflectionProperty($router, 'router')->getValue($router),
            );
            foreach (
                [
                    'tracer' => TracerPortInterface::class,
                    'meter' => MeterPortInterface::class,
                    'logger' => LoggerInterface::class,
                    'stopwatch' => Stopwatch::class,
                ] as $property => $id
            ) {
                self::assertSame(
                    $container->get($id),
                    new \ReflectionProperty($dispatcher, $property)->getValue($dispatcher),
                );
            }
            foreach (
                [
                    'responses' => ResponseFactoryInterface::class,
                    'streams' => StreamFactoryInterface::class,
                ] as $property => $id
            ) {
                self::assertSame($container->get($id), new \ReflectionProperty($mapper, $property)->getValue($mapper));
            }
            $match = new RouteMatch(
                'items',
                '/items/{slug}',
                '_Show.item-edit:detail',
                ['slug' => 'a%2Fb'],
                ['http_app_middleware' => ['route.a', 'route.z']],
            );
            self::assertSame(['route.a', 'route.z'], $selector->select($match));
            $first = $resolver->resolve('_Show.item-edit:detail');
            $second = $resolver->resolve('_Show.item-edit:detail');
            self::assertInstanceOf(HttpAppParityAction::class, $first);
            self::assertInstanceOf(HttpAppParityAction::class, $second);
            self::assertNotSame($first, $second);
            self::assertSame(2, $counter->actions);
            $arguments = $argumentResolver->resolve('_Show.item-edit:detail', $match);
            self::assertSame([
                'slug' => 'a%2Fb',
                'service' => $container->get(HttpAppParityArgument::class),
            ], $arguments);
            self::assertSame('a%2Fb:4', $invoker->invoke('_Show.item-edit:detail', $arguments));
            self::assertSame('a%2Fb:5', $invoker->invoke('second.action', $arguments));
            self::assertSame(5, $counter->actions);
            self::assertSame(0, $counter->middleware);
            $routeFirst = $container->get('route.a');
            $routeSecond = $container->get('route.a');
            self::assertInstanceOf(HttpAppParityMiddleware::class, $routeFirst);
            self::assertInstanceOf(HttpAppParityMiddleware::class, $routeSecond);
            self::assertNotSame($routeFirst, $routeSecond);
            self::assertSame(2, $counter->middleware);
            $registry = $container->get(TagRegistry::class);
            foreach (self::tags() as $tag => [$id, $priority]) {
                $entries = $registry->all($tag);
                self::assertCount(1, $entries);
                self::assertSame($id, $entries[0]->id());
                self::assertSame($priority, $entries[0]->priority());
                self::assertSame([], $entries[0]->meta());
            }
            // Foundation owns its own reset contributions; HttpApp adds none.
            foreach ($registry->all(ReservedTags::KERNEL_RESET) as $entry) {
                self::assertFalse(\str_starts_with($entry->id(), 'Coretsia\\Platform\\HttpApp\\'));
            }
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function runtimePolicies(): iterable
    {
        yield 'request-json-tracing-logging' => [true];
        yield 'html-metrics' => [false];
    }

    /** @return array<string, array{string, int}> */
    private static function tags(): array
    {
        return [
            ReservedTags::HTTP_MIDDLEWARE_APP => [RouterMiddleware::class, 100],
            ReservedTags::HTTP_MIDDLEWARE_ROUTE => [ActionDispatchMiddleware::class, -1000],
            ReservedTags::ERROR_MAPPER => [HttpAppProblemMapper::class, 800],
        ];
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $app['handlers'] = [
            ['action_id' => '_Show.item-edit:detail', 'service_id' => 'action.alias'],
            ['action_id' => 'second.action', 'service_id' => 'action.second'],
        ];
        $app['arguments']['allow_container_types'] = [HttpAppParityArgument::class];
        $app['route_middleware'] = ['route.z', 'route.a'];

        return [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'errors' => require \dirname(__DIR__, 4) . '/platform/errors/config/errors.php',
            'http_app' => $app,
        ];
    }

    /** @return list<ServiceProviderInterface&ContainerDefinitionProviderInterface> */
    private static function providers(?string $missing): array
    {
        return [
            new FoundationServiceProvider(),
            new RedactionServiceProvider(),
            new ErrorsServiceProvider(),
            new HttpAppServiceProvider(),
            new HttpAppParityFixtureProvider($missing),
        ];
    }

    /**
     * @param list<ContainerDefinitionProviderInterface> $providers
     * @param array<string, mixed> $config
     */
    private static function definitions(array $providers, array $config): ContainerDefinitionSet
    {
        $builder = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        foreach ($providers as $provider) {
            $provider->define($builder, $context);
        }

        return $builder->build();
    }

    private static function compile(ContainerDefinitionSet $set): DefinitionGraph
    {
        return new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($set);
    }

    private static function assertNoRuntimeValues(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::assertNoRuntimeValues($item);
            }

            return;
        }

        self::assertTrue($value === null || \is_string($value) || \is_int($value) || \is_bool($value));
    }
}

/** Transport and application services contributed after HttpApp, not replacement runtime wiring. */
final class HttpAppParityFixtureProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public function __construct(private readonly ?string $missing)
    {
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $counter = ContainerValueReference::service(HttpAppParityCounter::class);
        $definitions
            ->classService(RouterInterface::class, HttpAppParityRouter::class)
            ->classService(ResponseFactoryInterface::class, HttpAppParityResponseFactory::class)
            ->classService(StreamFactoryInterface::class, HttpAppParityStreamFactory::class)
            ->classService(HttpAppParityCounter::class, HttpAppParityCounter::class)
            ->classService('action.target', HttpAppParityAction::class, [$counter], shared: false);
        if ($this->missing !== 'action.alias') {
            $definitions->alias('action.alias', 'action.target');
        }
        if ($this->missing !== 'action.second') {
            $definitions->classService('action.second', HttpAppParityAction::class, [$counter], shared: false);
        }
        if ($this->missing !== HttpAppParityArgument::class) {
            $definitions->classService(HttpAppParityArgument::class, HttpAppParityArgument::class);
        }
        foreach (['route.z', 'route.a'] as $id) {
            if ($this->missing !== $id) {
                $definitions->classService($id, HttpAppParityMiddleware::class, [$counter], shared: false);
            }
        }
    }
}

final class HttpAppParityCounter
{
    public int $actions = 0;
    public int $middleware = 0;
}

final class HttpAppParityArgument
{
}

final class HttpAppParityAction
{
    private readonly int $identity;

    public function __construct(HttpAppParityCounter $counter)
    {
        $this->identity = ++$counter->actions;
    }

    public function __invoke(string $slug, HttpAppParityArgument $service): string
    {
        return $slug . ':' . $this->identity;
    }
}

final class HttpAppParityMiddleware implements MiddlewareInterface
{
    public function __construct(HttpAppParityCounter $counter)
    {
        ++$counter->middleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('unexpected-route-middleware-execution');
    }
}

final class HttpAppParityRouter implements RouterInterface
{
    public function match(string $method, string $path, ?string $host = null): ?RouteMatch
    {
        throw new \LogicException('unexpected-matching');
    }

    public function allowedMethods(string $path, ?string $host = null): array
    {
        throw new \LogicException('unexpected-method-enumeration');
    }
}

final class HttpAppParityResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('unexpected-response-construction');
    }
}

final class HttpAppParityStreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('unexpected-stream-construction');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('unexpected-stream-file');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('unexpected-stream-resource');
    }
}
