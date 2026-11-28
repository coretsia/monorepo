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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouteMiddlewareSelector;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareManualAppendAndDedupeTest extends TestCase
{
    public function testTaggedFirstThenManualAppendPreservesFoundationOrderAndFirstOccurrence(): void
    {
        $tags = new TagRegistry();
        $tag = ReservedTags::HTTP_MIDDLEWARE_SYSTEM;
        $tags->add($tag, ManualMiddlewareGamma::class, 10);
        $tags->add($tag, ManualMiddlewareBeta::class, 50);
        $tags->add($tag, ManualMiddlewareAlpha::class, 50);
        $tags->add($tag, ManualMiddlewareBeta::class, 999); // First tag registration wins.

        $http = self::httpConfig();
        $http['middleware']['system'] = [
            ManualMiddlewareAlpha::class,
            ManualMiddlewareDelta::class,
            ManualMiddlewareDelta::class,
            ManualMiddlewareGamma::class,
        ];
        $http['middleware']['app'] = [ManualMiddlewareEpsilon::class];

        self::assertSame(
            [
                ManualMiddlewareAlpha::class,
                ManualMiddlewareBeta::class,
                ManualMiddlewareGamma::class,
            ],
            \array_map(static fn ($entry): string => $entry->id(), $tags->all($tag)),
        );
        self::assertSame(
            [
                ManualMiddlewareAlpha::class,
                ManualMiddlewareBeta::class,
                ManualMiddlewareGamma::class,
                ManualMiddlewareDelta::class,
                ManualMiddlewareEpsilon::class,
            ],
            new HttpMiddlewareStackBuilder($tags)->build($http),
        );
    }

    public function testDisabledTaggedServiceDoesNotBlockManualParticipation(): void
    {
        $http = self::httpConfig();
        $http['metrics']['enabled'] = false;
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $tags = new TagRegistry();
        $tags->add(
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ManualMiddlewareAlpha::class,
            20,
            ['toggle' => 'http.metrics.enabled'],
        );

        self::assertSame(
            [ManualMiddlewareAlpha::class],
            new HttpMiddlewareStackBuilder($tags)->build($http),
        );
    }

    public function testCrossSlotDuplicateFailsWithFixedReason(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM, ManualMiddlewareAlpha::class);
        $http = self::httpConfig();
        $http['middleware']['route'] = [ManualMiddlewareAlpha::class];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-cross-slot-duplicate');
        new HttpMiddlewareStackBuilder($tags)->build($http);
    }

    public function testExplicitlyRegisteredManualServiceUsesOnlyCanonicalContainer(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $service = new ManualMiddlewareAlpha();
        $builder = new ContainerBuilder(self::containerConfig(false));
        $builder->instance(ManualMiddlewareAlpha::class, $service);
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $pipeline = self::pipeline($builder->build(), $http, $terminal);

        // Verify the same instance is resolved by the canonical PSR-11 container.
        self::assertSame($service, $builder->build()->get(ManualMiddlewareAlpha::class));
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame(1, $service->calls);
    }

    #[DataProvider('autowiringSettings')]
    public function testUnregisteredConcreteClassRequiresFoundationAutowiring(
        bool $enabled,
        bool $reflection,
        bool $shouldResolve,
    ): void {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareBeta::class];
        $container = new ContainerBuilder(self::containerConfig($enabled, $reflection))->build();

        if (!$shouldResolve) {
            $this->expectException(ContainerExceptionInterface::class);
            self::pipeline($container, $http);

            return;
        }

        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturn($response);
        $pipeline = self::pipeline($container, $http, $terminal);
        self::assertInstanceOf(Pipeline::class, $pipeline);
        self::assertSame($response, $pipeline->handle($request));
    }

    /** @return iterable<string, array{bool, bool, bool}> */
    public static function autowiringSettings(): iterable
    {
        yield 'off' => [false, false, false];
        yield 'reflection-off' => [true, false, false];
        yield 'concrete-off' => [false, true, false];
        yield 'enabled' => [true, true, true];
    }

    #[DataProvider('unresolvableManualIds')]
    public function testUnresolvableManualServiceIdsFailDeterministically(string $id): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [$id];
        $container = new ContainerBuilder(self::containerConfig(true, true))->build();

        $this->expectException(ContainerExceptionInterface::class);
        self::pipeline($container, $http);
    }

    /** @return iterable<string, array{string}> */
    public static function unresolvableManualIds(): iterable
    {
        yield 'nonexistent' => ['Coretsia\\Tests\\ThisMiddlewareDoesNotExist'];
        yield 'abstract' => [ManualAbstractMiddleware::class];
        yield 'interface' => [MiddlewareInterface::class];
        yield 'unresolvable-constructor' => [ManualMiddlewareNeedsScalar::class];
    }

    public function testIncompatibleManualServiceIsRejectedAfterCanonicalResolution(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $builder = new ContainerBuilder(self::containerConfig(false));
        $builder->instance(ManualMiddlewareAlpha::class, new \stdClass());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-instance-invalid');
        self::pipeline($builder->build(), $http);
    }

    public function testSourceAndCompiledRuntimeResolveTheSameManualMiddleware(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $config = self::containerConfig(false);
        $config['http'] = $http;
        $definitions = new ContainerDefinitionBuilder()
            ->classService(ManualMiddlewareAlpha::class, ManualMiddlewareAlpha::class)
            ->build();
        $source = new ContainerBuilder($config)->applyDefinitions($definitions)->build();

        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($definitions);
        $envelope = new CompiledContainerBuilder(new ArtifactEnvelopeFactory())->build(
            $graph,
            \str_repeat('a', 64),
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config, []),
                ModulePlan::class => new ModulePlan('web', [], [], [], []),
                RuntimePathContext::class => new RuntimePathContext('/app', '/app/var/cache'),
                RuntimeDiagnosticsInput::class => new RuntimeDiagnosticsInput(
                    appEnv: 'test',
                    artifactGenerationLoaded: false,
                ),
            ]),
        );

        foreach ([$source, $compiled] as $container) {
            $middleware = $container->get(ManualMiddlewareAlpha::class);
            self::assertInstanceOf(ManualMiddlewareAlpha::class, $middleware);

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);
            $terminal = $this->createMock(RequestHandlerInterface::class);
            $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);

            $pipeline = self::pipeline($container, $http, $terminal);
            self::assertSame($response, $pipeline->handle($request));
            self::assertSame(1, $middleware->calls);
        }

        // Compiled hydration preserves the same explicit-service resolution when
        // the global Foundation autowiring switch is disabled.
        self::assertSame(
            $source->get(ManualMiddlewareAlpha::class)::class,
            $compiled->get(ManualMiddlewareAlpha::class)::class,
        );
    }

    public function testSourceAndCompiledConcreteAutowiringPolicyHasTheSameResult(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareBeta::class];

        foreach ([false, true] as $enabled) {
            $config = self::containerConfig($enabled, $enabled);
            $config['http'] = $http;
            $definitions = new ContainerDefinitionBuilder()->build();
            $source = new ContainerBuilder($config)->build();
            $compiled = self::compiledEmptyContainer($definitions, $config);

            foreach ([$source, $compiled] as $container) {
                if ($enabled) {
                    self::assertInstanceOf(ManualMiddlewareBeta::class, $container->get(ManualMiddlewareBeta::class));
                    self::assertInstanceOf(
                        Pipeline::class,
                        self::pipeline($container, $http),
                    );
                } else {
                    try {
                        self::pipeline($container, $http);
                        self::fail(
                            'Unregistered concrete middleware must not enter the pipeline with autowiring disabled.',
                        );
                    } catch (ContainerExceptionInterface) {
                        self::assertTrue(true);
                    }
                }
            }
        }
    }

    public function testHttpAppDispatchFollowsAllEffectiveOrdinaryMiddleware(): void
    {
        $http = self::httpConfig();
        $http['metrics']['enabled'] = false;
        $http['middleware']['app'] = [ManualMiddlewareBeta::class];
        $http['middleware']['route'] = [ManualMiddlewareNeedsScalar::class];
        $tags = self::httpAppTags();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST, 'app.system-post', 10, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP, ManualMiddlewareAlpha::class, 99, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE, ManualMiddlewareGamma::class, 10, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, ManualMiddlewareDelta::class, 10, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, ManualMiddlewareEpsilon::class, -1001, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, 'app.disabled', 50, ['toggle' => 'http.metrics.enabled']);
        $tagOrder = [
            'app.disabled',
            ManualMiddlewareDelta::class,
            ActionDispatchMiddleware::class,
            ManualMiddlewareEpsilon::class,
        ];
        $routeIds = static fn (): array => \array_map(
            static fn ($entry): string => $entry->id(),
            $tags->all(ReservedTags::HTTP_MIDDLEWARE_ROUTE),
        );
        self::assertSame($tagOrder, $routeIds());
        $ordinary = [
            ManualMiddlewareAlpha::class,
            ManualMiddlewareBeta::class,
            ManualMiddlewareGamma::class,
            ManualMiddlewareDelta::class,
            ManualMiddlewareEpsilon::class,
            ManualMiddlewareNeedsScalar::class,
        ];
        $expected = [
            HttpErrorHandlingMiddleware::class,
            'app.system-post',
            RouterMiddleware::class,
            ...$ordinary,
            ActionDispatchMiddleware::class,
        ];
        self::assertSame($expected, new HttpMiddlewareStackBuilder($tags)->build($http));
        self::assertSame($tagOrder, $routeIds());

        $events = [];
        $response = $this->createStub(ResponseInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with('GET', '/items', 'example.test')->willReturnCallback(
            static function () use (&$events): RouteMatch {
                $events[] = 'router';

                return new RouteMatch('show', '/items', 'action.show');
            },
        );
        $router->expects(self::never())->method('allowedMethods');
        $action = new class($response, static function () use (&$events): void {
            $events[] = 'action';
        }) {
            public function __construct(private ResponseInterface $response, private \Closure $record)
            {
            }

            public function __invoke(): ResponseInterface
            {
                ($this->record)();

                return $this->response;
            }
        };
        $actions = new ContainerBuilder(self::containerConfig(false))->instance('test.action', $action)->build();
        $services = $this->roleInstances(
            $router,
            $actions,
            [['action_id' => 'action.show', 'service_id' => 'test.action']],
        );
        $request = $this->createStub(ServerRequestInterface::class);
        $matched = $this->createStub(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/items');
        $uri->method('getHost')->willReturn('example.test');
        foreach ([$request, $matched] as $current) {
            $current->method('getMethod')->willReturn('GET');
            $current->method('getUri')->willReturn($uri);
        }
        $attributes = [];
        $request->method('getAttributes')->willReturn([]);
        $request->method('withAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes, $matched): ServerRequestInterface {
                self::assertSame(RouteDispatchState::ATTRIBUTE, $key);
                self::assertInstanceOf(RouteDispatchState::class, $value);
                $attributes[$key] = $value;

                return $matched;
            },
        );
        $matched->method('getAttributes')->willReturnCallback(static function () use (&$attributes): array {
            return $attributes;
        });
        $outer = $this->createMock(MiddlewareInterface::class);
        $outer->expects(self::once())->method('process')->willReturnCallback(
            static function (ServerRequestInterface $effective, RequestHandlerInterface $next) use (
                &$events,
                $request,
            ): ResponseInterface {
                self::assertSame($request, $effective);
                $events[] = 'app.system-post';

                return $next->handle($effective);
            },
        );
        $services['app.system-post'] = $outer;
        foreach ($ordinary as $id) {
            $middleware = $this->createMock(MiddlewareInterface::class);
            $middleware->expects(self::once())->method('process')->willReturnCallback(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$events,
                    $id,
                    $matched,
                ): ResponseInterface {
                    self::assertSame($matched, $request);
                    $events[] = $id;

                    return $next->handle($request);
                },
            );
            $services[$id] = $middleware;
        }
        $container = $this->recordingContainer($expected, static fn (string $id): mixed => $services[$id]);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = self::pipeline($container, $http, $terminal, $tags, self::httpAppPlan());
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame(['app.system-post', 'router', ...$ordinary, 'action'], $events);
        self::assertSame($tagOrder, $routeIds());
    }

    public function testModuleSelectionAndRequiredErrorBoundaryAreValidatedBeforeResolution(): void
    {
        foreach (
            [
                [new TagRegistry(), self::httpAppPlan(), 'http-middleware-app-dispatch-binding-invalid'],
                [
                    self::httpAppTags(),
                    new ModulePlan('web', [], [], [], []),
                    'http-middleware-app-dispatch-binding-invalid',
                ],
                [self::httpAppTags(false), self::httpAppPlan(), 'http-middleware-app-error-boundary-invalid'],
            ] as [$tags, $plan, $reason]
        ) {
            $container = $this->createMock(ContainerInterface::class);
            $container->expects(self::never())->method('get');
            $container->expects(self::never())->method('has');
            self::assertPipelineRejected($container, $tags, $plan, $reason);
        }
    }

    public function testResolvedCanonicalRoleIdentitiesAndInstanceFailuresKeepDistinctReasons(): void
    {
        $roles = $this->roleInstances(
            $this->createStub(RouterInterface::class),
            new ContainerBuilder(self::containerConfig(false))->build(),
            [],
        );
        $ids = \array_keys($roles);
        foreach ($ids as $index => $id) {
            $reason = $id === HttpErrorHandlingMiddleware::class
                ? 'http-middleware-app-error-boundary-invalid'
                : 'http-middleware-app-dispatch-binding-invalid';
            $services = $roles;
            $services[$id] = new ManualMiddlewareAlpha();
            self::assertPipelineRejected(
                $this->recordingContainer($ids, static fn (string $id): mixed => $services[$id]),
                self::httpAppTags(),
                self::httpAppPlan(),
                $reason,
            );
            $services[$id] = new \stdClass();
            self::assertPipelineRejected(
                $this->recordingContainer(
                    \array_slice($ids, 0, $index + 1),
                    static fn (string $id): mixed => $services[$id],
                ),
                self::httpAppTags(),
                self::httpAppPlan(),
                'http-middleware-instance-invalid',
            );
            foreach ([$reason, 'http-middleware-instance-invalid'] as $message) {
                $failure = new \InvalidArgumentException($message);
                $container = $this->recordingContainer(
                    \array_slice($ids, 0, $index + 1),
                    static function (string $serviceId) use ($id, $roles, $failure): mixed {
                        return $serviceId === $id ? throw $failure : $roles[$serviceId];
                    },
                );
                try {
                    self::pipeline(
                        $container,
                        self::httpConfig(),
                        tags: self::httpAppTags(),
                        plan: self::httpAppPlan(),
                    );
                    self::fail('Expected the original service resolution failure.');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame($failure, $exception);
                }
            }
        }
    }

    public function testAlternativeServiceIdsCannotBypassCanonicalRoleAdmission(): void
    {
        $roles = $this->roleInstances(
            $this->createStub(RouterInterface::class),
            new ContainerBuilder(self::containerConfig(false))->build(),
            [],
        );
        foreach (\array_keys($roles) as $id) {
            $builder = new ContainerBuilder(self::containerConfig(false));
            foreach ($roles as $role => $instance) {
                $builder->instance($role, $instance);
            }
            $source = $builder->applyDefinitions(
                new ContainerDefinitionBuilder()
                    ->alias(ManualMiddlewareAlpha::class, $id)
                    ->build(),
            )->build();
            $tags = self::httpAppTags();
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, ManualMiddlewareAlpha::class, 0, []);
            $container = $this->recordingContainer([
                HttpErrorHandlingMiddleware::class,
                RouterMiddleware::class,
                ManualMiddlewareAlpha::class,
                ActionDispatchMiddleware::class,
            ], static fn (string $serviceId): mixed => $source->get($serviceId));
            self::assertPipelineRejected(
                $container,
                $tags,
                self::httpAppPlan(),
                $id === HttpErrorHandlingMiddleware::class
                    ? 'http-middleware-app-error-boundary-invalid'
                    : 'http-middleware-app-dispatch-binding-invalid',
            );

            if ($id === HttpErrorHandlingMiddleware::class) {
                continue;
            }

            $tags = new TagRegistry();
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, ManualMiddlewareAlpha::class, 0, []);
            self::assertPipelineRejected(
                $this->recordingContainer(
                    [ManualMiddlewareAlpha::class],
                    static fn (string $serviceId): mixed => $source->get($serviceId),
                ),
                $tags,
                new ModulePlan('web', [], [], [], []),
                'http-middleware-app-dispatch-binding-invalid',
            );
        }
    }

    public function testProblemDetailsParticipationRemainsOptionalWithoutHttpApp(): void
    {
        $roles = $this->roleInstances(
            $this->createStub(RouterInterface::class),
            new ContainerBuilder(self::containerConfig(false))->build(),
            [],
        );
        $boundary = $roles[HttpErrorHandlingMiddleware::class];
        foreach (
            [
                [],
                [HttpErrorHandlingMiddleware::class => $boundary],
                [HttpErrorHandlingMiddleware::class => new ManualMiddlewareAlpha()],
                [ManualMiddlewareAlpha::class => $boundary],
            ] as $services
        ) {
            $tags = new TagRegistry();
            foreach ($services as $id => $service) {
                $tags->add(
                    ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                    $id,
                    $id === HttpErrorHandlingMiddleware::class ? 1000 : 10,
                    [],
                );
            }
            $container = $this->recordingContainer(
                \array_keys($services),
                static fn (string $id): mixed => $services[$id],
            );
            $pipeline = self::pipeline($container, self::httpConfig(), tags: $tags);
            self::assertSame(
                \array_values($services),
                new \ReflectionProperty(Pipeline::class, 'middleware')->getValue($pipeline),
            );
            self::assertFalse(new \ReflectionProperty(Pipeline::class, 'guardedContinuation')->getValue($pipeline));
        }
    }

    public function testStackBuilderAndFactoryDelegateResolutionWithoutPrivateAutowiring(): void
    {
        foreach ([HttpMiddlewareStackBuilder::class, HttpServiceFactory::class] as $class) {
            $source = \file_get_contents(new \ReflectionClass($class)->getFileName());
            self::assertIsString($source);
            foreach (
                [
                    'new \\ReflectionClass(',
                    'new \\ReflectionMethod(',
                    'class_exists(',
                    'get_declared_classes(',
                    'canAutowire(',
                    'getDefinitions(',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }

        $source = \file_get_contents(new \ReflectionClass(HttpServiceFactory::class)->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('Pipeline::resolveServices($container, $serviceIds)', $source);
    }

    private static function httpAppTags(bool $errorBoundary = true): TagRegistry
    {
        $tags = new TagRegistry();
        if ($errorBoundary) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000, []);
        }
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP, RouterMiddleware::class, 100, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE, ActionDispatchMiddleware::class, -1000, []);

        return $tags;
    }

    private static function httpAppPlan(): ModulePlan
    {
        // Only effective HttpApp participation is relevant to this unit boundary.
        $id = ModuleId::fromString('platform.http-app');

        return new ModulePlan('web', [$id], [], [$id], [new ModulePlanEntry($id, 'coretsia/platform-http-app')]);
    }

    /**
     * @param list<array{action_id: string, service_id: string}> $registrations
     * @return array<string, MiddlewareInterface>
     */
    private function roleInstances(RouterInterface $router, Container $actions, array $registrations): array
    {
        $config = new ArrayConfigRepository([
            'http_app' => [
                'handlers' => $registrations,
                'arguments' => ['allow_container_types' => []],
                'route_middleware' => [],
            ],
        ], []);
        $handlers = new HandlerResolver($actions, $config);
        $arguments = new ArgumentResolver($handlers, $actions, false, false, $config);
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $streams = $this->createStub(StreamFactoryInterface::class);
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::never())->method('handle');

        return [
            HttpErrorHandlingMiddleware::class => new HttpErrorHandlingMiddleware(
                $errors,
                new ErrorDescriptorToProblemDetailsAdapter(),
                new ProblemDetailsJsonRenderer(),
                $responses,
                $streams,
            ),
            RouterMiddleware::class => new RouterMiddleware($router),
            ActionDispatchMiddleware::class => new ActionDispatchMiddleware(
                $actions,
                new RouteMiddlewareSelector($config),
                new ActionInvoker($handlers, $arguments),
                new ActionResponseMapper($responses, $streams, true, true),
                new NoopTracer(),
                new NoopMeter(),
                new NoopLogger(),
                new Stopwatch(),
                false,
                false,
                false,
            ),
        ];
    }

    /**
     * @param list<string> $expectedIds
     * @param \Closure(string): mixed $resolve
     */
    private function recordingContainer(array $expectedIds, \Closure $resolve): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $index = 0;
        $container->expects(self::exactly(\count($expectedIds)))->method('get')->willReturnCallback(
            static function (string $id) use ($expectedIds, &$index, $resolve): mixed {
                self::assertSame($expectedIds[$index++], $id);

                return $resolve($id);
            },
        );

        return $container;
    }

    private static function assertPipelineRejected(
        ContainerInterface $container,
        TagRegistry $tags,
        ModulePlan $plan,
        string $reason,
    ): void {
        try {
            self::pipeline($container, self::httpConfig(), tags: $tags, plan: $plan);
            self::fail('Expected middleware binding rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($reason, $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $http */
    private static function pipeline(
        ContainerInterface $container,
        array $http,
        ?RequestHandlerInterface $terminal = null,
        ?TagRegistry $tags = null,
        ?ModulePlan $plan = null,
    ): Pipeline {
        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http], []),
            new HttpMiddlewareStackBuilder($tags ?? new TagRegistry()),
            $terminal ?? new class() implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \LogicException('terminal-not-supplied');
                }
            },
            $plan ?? new ModulePlan('web', [], [], [], []),
        );
    }

    /** @return array<string, mixed> */
    private static function httpConfig(): array
    {
        return require __DIR__ . '/../../config/http.php';
    }

    /** @return array<string, mixed> */
    private static function containerConfig(bool $autowire, ?bool $reflection = null): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => $autowire,
                    'allow_reflection_for_concrete' => $reflection ?? $autowire,
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    private static function compiledEmptyContainer(
        ContainerDefinitionSet $set,
        array $config,
    ): Container {
        $graph = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())->compile(
            $set,
        );
        $envelope = new CompiledContainerBuilder(new ArtifactEnvelopeFactory())->build($graph, \str_repeat('b', 64));

        return new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config, []),
                ModulePlan::class => new ModulePlan('web', [], [], [], []),
                RuntimePathContext::class => new RuntimePathContext('/app', '/app/var/cache'),
                RuntimeDiagnosticsInput::class => new RuntimeDiagnosticsInput(
                    appEnv: 'test',
                    artifactGenerationLoaded: false,
                ),
            ]),
        );
    }
}

final class ManualMiddlewareAlpha implements MiddlewareInterface
{
    public int $calls = 0;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->calls;

        return $handler->handle($request);
    }
}

class ManualMiddlewareBeta implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class ManualMiddlewareGamma extends ManualMiddlewareBeta
{
}

final class ManualMiddlewareDelta extends ManualMiddlewareBeta
{
}

final class ManualMiddlewareEpsilon extends ManualMiddlewareBeta
{
}

abstract class ManualAbstractMiddleware implements MiddlewareInterface
{
}

final class ManualMiddlewareNeedsScalar implements MiddlewareInterface
{
    public function __construct(string $required)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
