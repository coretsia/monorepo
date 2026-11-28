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

namespace Coretsia\Platform\HttpApp\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class MatchedRouteMiddlewareAndActionDispatchTest extends TestCase
{
    public function testCanonicalGlobalOrderDispatchesOneResolvedActionAndFinalizesItsResponse(): void
    {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $resolved = new \ArrayObject();
        $service = new MatchedDispatchArgument();
        $matches = new \ArrayObject();
        $root = RoutingIntegrationFixture::root();
        try {
            $route = new RouteDefinition(
                'items',
                ['GET'],
                '/items/{slug}',
                '_Show.item:detail',
                metadata: ['http_app_middleware' => ['local.route']],
            );
            $envelope = RoutingIntegrationFixture::schema()->createEnvelope([$route], 'web', 'local');
            self::assertTrue(RoutingIntegrationFixture::writer($root)->write($envelope));
            $router = new CompiledRouter(
                new CompiledRouteTable(RoutingIntegrationFixture::reader($root)),
                new ContextStore(),
                new RoutingMatchObserver(
                    new NoopTracer(),
                    new NoopMeter(),
                    new NoopLogger(),
                    new Stopwatch(),
                    false,
                    false,
                    false,
                ),
            );
        } finally {
            RoutingIntegrationFixture::remove($root);
        }
        $routingPort = $this->createMock(RouterInterface::class);
        $routingPort->expects(self::once())->method('match')->with('GET', '/items/a%2Fb', 'example.test')
            ->willReturnCallback(static function (
                string $method,
                string $path,
                ?string $host,
            ) use ($router, $matches, $events): ?RouteMatch {
                $events->append('match');
                $match = $router->match($method, $path, $host);
                self::assertInstanceOf(RouteMatch::class, $match);
                $matches->append($match);

                return $match;
            });
        $routingPort->expects(self::never())->method('allowedMethods');
        $response = $this->createMock(ResponseInterface::class);
        $finalized = $this->createStub(ResponseInterface::class);
        $finalized->method('getStatusCode')->willReturn(202);
        $response->expects(self::once())->method('withHeader')->with('X-Correlation-Id', '01ARZ3NDEKTSV4RRFFQ69G5FAV')
            ->willReturn($finalized);
        $response->method('getStatusCode')->willReturn(202);
        $builder = $this->builder(self::app(), $routingPort);
        $builder->instance(MatchedDispatchArgument::class, $service);
        $builder->factory(
            'action.service',
            static function () use ($resolved, $events, $response, $matches, $service): MatchedDispatchAction {
                $events->append('resolve');
                $action = new MatchedDispatchAction($events, $response, $matches[0], $service);
                $resolved->append($action);

                return $action;
            },
            false,
        );
        $builder->factory('local.route', static function () use ($events): MiddlewareInterface {
            $events->append('resolve-local');

            return new MatchedDispatchMiddleware(static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $next,
            ) use ($events): ResponseInterface {
                $events->append('local');

                return $next->handle($request->withParsedBody(['effective' => true]));
            });
        }, false);
        $builder->register(new HttpAppServiceProvider());
        foreach (
            [
                ['high', ReservedTags::HTTP_MIDDLEWARE_APP, 200],
                ['low', ReservedTags::HTTP_MIDDLEWARE_APP, 0],
                ['route-pre', ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE, 0],
                ['route', ReservedTags::HTTP_MIDDLEWARE_ROUTE, -2000],
            ] as [$id, $tag, $priority]
        ) {
            $builder->instance($id, new MatchedDispatchMiddleware(static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $next,
            ) use ($events, $resolved, $id, $matches): ResponseInterface {
                self::assertCount(0, $resolved);
                if ($id !== 'high') {
                    self::assertSame($matches[0], RouteDispatchState::fromRequest($request)->match());
                } else {
                    self::assertCount(0, $matches);
                    self::assertFalse(\array_key_exists(RouteDispatchState::ATTRIBUTE, $request->getAttributes()));
                }
                $events->append($id);

                return $next->handle($request);
            }))->tag($tag, $id, $priority);
        }
        $builder->instance(MatchedDispatchManualApp::class, new MatchedDispatchManualApp(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events, $resolved): ResponseInterface {
            self::assertCount(0, $resolved);
            $events->append('manual-app');

            return $next->handle($request);
        }));
        $builder->instance(MatchedDispatchManualRoute::class, new MatchedDispatchManualRoute(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events, $resolved): ResponseInterface {
            self::assertCount(0, $resolved);
            $events->append('manual-route');

            return $next->handle($request);
        }));
        $pipeline = $this->pipeline($builder, $failures, true);
        $context = new ContextStore();
        $requestIds = new RequestIdPolicy(
            $context,
            $context,
            $this->createStub(IdGeneratorInterface::class),
            false,
            'X-Request-Id',
            false,
            26,
        );
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $finalizer = new HttpResponseFinalizer(
            $correlations,
            true,
            'X-Correlation-Id',
            $requestIds,
            new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
        );
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $handle = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'type' => 'http',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        ]);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with(
            self::identicalTo($handle),
            'success',
            null,
        )->willReturn([]);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            $finalizer,
            new HttpRequestMetricsRecorder(new NoopMeter(), false),
            new HttpAccessLogWriter(new NoopLogger(), false),
            new HttpRequestSpanRecorder(new NoopTracer(), false),
            $requestIds,
        );
        self::assertSame($finalized, $kernel->handle($this->request()));
        self::assertSame(
            [
                'high',
                'match',
                'low',
                'manual-app',
                'route-pre',
                'route',
                'manual-route',
                'resolve-local',
                'local',
                'resolve',
                'action',
            ],
            $events->getArrayCopy(),
        );
        self::assertCount(1, $matches);
        self::assertCount(1, $resolved);
        self::assertSame(1, $resolved[0]->calls);
        self::assertCount(0, $failures);
    }

    #[DataProvider('globalViolations')]
    public function testGlobalHandoffProtectionFailsBeforeAnyLaterMiddlewareOrDispatch(
        string $slot,
        string $mutation,
        string $reason,
        bool $invalidSelection,
    ): void {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $match = new RouteMatch(
            'items',
            '/items/{slug}',
            '_Show.item:detail',
            ['slug' => 'a%2Fb'],
            ['http_app_middleware' => $invalidSelection ? ['not.admitted'] : ['local.route']],
        );
        $builder = $this->builder(self::app(), $this->router($match, $events));
        $builder->factory('local.route', static function () use ($events): never {
            $events->append('unexpected-local-resolution');
            throw new \LogicException('unexpected-local-resolution');
        });
        $builder->factory('action.service', static function () use ($events): never {
            $events->append('unexpected-action-resolution');
            throw new \LogicException('unexpected-action-resolution');
        });
        $builder->register(new HttpAppServiceProvider());
        $caught = null;
        $builder->instance('mutator', new MatchedDispatchMiddleware(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($mutation, &$caught, $match): ResponseInterface {
            self::assertSame($match, RouteDispatchState::fromRequest($request)->match());
            try {
                return $next->handle($this->mutate($request, $mutation));
            } catch (\Throwable $failure) {
                $caught = $failure;
                self::assertGuard(
                    self::failure(static fn () => $next->handle($request)),
                    'http-pipeline-continuation-reused',
                );
                throw $failure;
            }
        }))->tag($slot, 'mutator', 50);
        // This middleware would restore the original routing inputs, but it must never be entered.
        $builder->instance('restore', new MatchedDispatchMiddleware(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events): ResponseInterface {
            $events->append('unexpected-restoration');

            return $next->handle($this->request(attributes: $request->getAttributes()));
        }))->tag($slot, 'restore', 0);
        $pipeline = $this->pipeline($builder, $failures, false);
        $failure = self::failure(fn () => $pipeline->handle($this->request()));
        self::assertGuard($failure, $reason);
        self::assertSame($caught, $failure);
        self::assertSame([$failure], $failures->getArrayCopy());
        self::assertSame(['match'], $events->getArrayCopy());
    }

    /** @return iterable<string, array{string, string, string, bool}> */
    public static function globalViolations(): iterable
    {
        foreach (
            [
                ReservedTags::HTTP_MIDDLEWARE_APP,
                ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE,
                ReservedTags::HTTP_MIDDLEWARE_ROUTE,
            ] as $slot
        ) {
            foreach (['replace', 'equivalent', 'remove', 'null', 'method', 'path', 'host'] as $mutation) {
                $reason = \in_array($mutation, ['replace', 'equivalent', 'remove', 'null'], true)
                    ? 'http-pipeline-protected-attribute-changed' : 'http-pipeline-protected-routing-input-changed';
                yield $slot . '-' . $mutation => [$slot, $mutation, $reason, false];
                yield $slot . '-' . $mutation . '-invalid-selection' => [$slot, $mutation, $reason, true];
            }
        }
    }

    #[DataProvider('continuationCases')]
    public function testGlobalContinuationIsSingleEntryAndExpires(string $mode, bool $beforeMatching): void
    {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $match = new RouteMatch('items', '/items/{slug}', '_Show.item:detail', ['slug' => 'a%2Fb']);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $primary = new \RuntimeException('synthetic-action-failure');
        $builder = $this->builder(
            self::app(),
            $this->router(
                $match,
                $events,
                $beforeMatching && $mode === 'expire' ? 0 : 1,
            ),
        );
        $builder->factory('action.service', static function () use ($events, $response, $primary, $mode): object {
            $events->append('resolve');

            return new class($events, $response, $primary, $mode) {
                public function __construct(
                    private readonly \ArrayObject $events,
                    private readonly ResponseInterface $response,
                    private readonly \Throwable $failure,
                    private readonly string $mode,
                ) {
                }

                public function __invoke(): ResponseInterface
                {
                    $this->events->append('action');
                    if ($this->mode === 'throw') {
                        throw $this->failure;
                    }

                    return $this->response;
                }
            };
        }, false);
        $builder->register(new HttpAppServiceProvider());
        $escaped = null;
        $incoming = null;
        $builder->instance('continuation', new MatchedDispatchMiddleware(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use (&$escaped, &$incoming, $mode, $response): ResponseInterface {
            $escaped = $next;
            $incoming = $request;
            if ($mode === 'expire') {
                return $response;
            }
            $result = $next->handle($request);
            if ($mode === 'repeat') {
                return $next->handle($request);
            }

            return $result;
        }))->tag(ReservedTags::HTTP_MIDDLEWARE_APP, 'continuation', $beforeMatching ? 200 : 0);
        $pipeline = $this->pipeline($builder, $failures, false);
        if (\in_array($mode, ['expire', 'expire-return'], true)) {
            self::assertSame($response, $pipeline->handle($this->request()));
            self::assertInstanceOf(RequestHandlerInterface::class, $escaped);
            self::assertInstanceOf(ServerRequestInterface::class, $incoming);
            self::assertGuard(
                self::failure(static fn () => $escaped->handle($incoming)),
                'http-pipeline-continuation-expired'
            );
            $expected = $mode === 'expire'
                ? ($beforeMatching ? [] : ['match'])
                : ['match', 'resolve', 'action'];
            self::assertSame($expected, $events->getArrayCopy());
            self::assertCount(0, $failures);
        } else {
            $failure = self::failure(fn () => $pipeline->handle($this->request()));
            if ($mode === 'throw') {
                self::assertSame($primary, $failure);
            } else {
                self::assertGuard($failure, 'http-pipeline-continuation-reused');
            }
            self::assertSame([$failure], $failures->getArrayCopy());
            self::assertSame(['match', 'resolve', 'action'], $events->getArrayCopy());
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function continuationCases(): iterable
    {
        foreach ([false, true] as $beforeMatching) {
            $prefix = $beforeMatching ? 'before-match-' : 'after-match-';
            yield $prefix . 'repeat' => ['repeat', $beforeMatching];
            yield $prefix . 'expired-short-circuit' => ['expire', $beforeMatching];
            yield $prefix . 'expired-after-return' => ['expire-return', $beforeMatching];
            yield $prefix . 'primary-throwable' => ['throw', $beforeMatching];
        }
    }

    /** @return array<string, mixed> */
    private static function app(): array
    {
        $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $app['handlers'] = [['action_id' => '_Show.item:detail', 'service_id' => 'action.service']];
        $app['route_middleware'] = ['local.route'];
        $app['arguments']['allow_container_types'] = [MatchedDispatchArgument::class];

        return $app;
    }

    private function router(RouteMatch $match, \ArrayObject $events, int $matches = 1): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::exactly($matches))->method('match')->with(
            'GET',
            '/items/a%2Fb',
            'example.test',
        )->willReturnCallback(
            static function () use ($events, $match): RouteMatch {
                $events->append('match');

                return $match;
            },
        );
        $router->expects(self::never())->method('allowedMethods');

        return $router;
    }

    private function pipeline(ContainerBuilder $builder, \ArrayObject $failures, bool $manual): Pipeline
    {
        $builder->instance(HttpErrorHandlingMiddleware::class, $this->boundary($failures));
        $builder->tag(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000);
        $http = require \dirname(__DIR__, 4) . '/platform/http/config/http.php';
        if ($manual) {
            $http['middleware']['app'] = [MatchedDispatchManualApp::class];
            $http['middleware']['route'] = [MatchedDispatchManualRoute::class];
        }
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $requirements = [
            'core.foundation' => [],
            'core.kernel' => ['core.foundation'],
            'platform.redaction' => ['core.foundation'],
            'platform.errors' => ['core.foundation', 'platform.redaction'],
            'platform.http' => ['core.foundation', 'core.kernel', 'platform.redaction'],
            'platform.problem-details' => ['core.foundation', 'platform.errors'],
            'platform.routing' => ['core.foundation', 'core.kernel', 'platform.redaction'],
            'platform.http-app' => [
                'core.foundation',
                'platform.errors',
                'platform.http',
                'platform.problem-details',
                'platform.routing',
            ],
        ];
        $ids = [];
        $entries = [];
        foreach ($requirements as $module => $requires) {
            $id = ModuleId::fromString($module);
            $ids[] = $id;
            $entries[] = new ModulePlanEntry(
                $id,
                'coretsia/' . \str_replace('.', '-', $module),
                \array_map(ModuleId::fromString(...), $requires),
            );
        }
        $plan = new ModulePlan('web', $ids, [], $ids, $entries);

        return HttpServiceFactory::pipeline(
            $builder->build(),
            new ArrayConfigRepository(['http' => $http], []),
            new HttpMiddlewareStackBuilder($builder->tagRegistry()),
            $terminal,
            $plan,
        );
    }

    /** @param array<string, mixed> $app */
    private function builder(array $app, RouterInterface $router): ContainerBuilder
    {
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http_app' => $app,
        ];
        $config['foundation']['container']['autowire_concrete'] = true;
        $config['foundation']['container']['allow_reflection_for_concrete'] = true;
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $builder->instance(RouterInterface::class, $router);
        $builder->instance(ResponseFactoryInterface::class, $this->createStub(ResponseFactoryInterface::class));
        $builder->instance(StreamFactoryInterface::class, $this->createStub(StreamFactoryInterface::class));
        $builder->instance(TracerPortInterface::class, new NoopTracer());
        $builder->instance(MeterPortInterface::class, new NoopMeter());
        $builder->instance(LoggerInterface::class, new NoopLogger());
        $builder->instance(Stopwatch::class, new Stopwatch());

        return $builder;
    }

    private function boundary(\ArrayObject $failures): HttpErrorHandlingMiddleware
    {
        $handler = $this->createStub(ErrorHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static function (\Throwable $failure) use ($failures): ErrorDescriptor {
                $failures->append($failure);

                return new ErrorDescriptor(
                    'CORETSIA_TEST_FAILURE',
                    'Synthetic failure.',
                    ErrorSeverity::Error,
                    500,
                    [],
                );
            },
        );
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $responses->method('createResponse')->willThrowException(new \RuntimeException('synthetic-transport-failure'));

        return new HttpErrorHandlingMiddleware(
            $handler,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $this->createStub(StreamFactoryInterface::class),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function request(
        string $method = 'GET',
        string $path = '/items/a%2Fb',
        string $host = 'example.test',
        array $attributes = [],
        ?array $body = null,
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn($host);
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => \array_key_exists($name, $attributes)
                ? $attributes[$name] : $default,
        );
        $request->method('getParsedBody')->willReturn($body);
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $method,
                $path,
                $host,
                [...$attributes, $name => $value],
                $body,
            ),
        );
        $request->method('withoutAttribute')->willReturnCallback(
            function (string $name) use ($method, $path, $host, $attributes, $body): ServerRequestInterface {
                unset($attributes[$name]);

                return $this->request($method, $path, $host, $attributes, $body);
            },
        );
        $request->method('withParsedBody')->willReturnCallback(
            fn (array $value): ServerRequestInterface => $this->request($method, $path, $host, $attributes, $value),
        );

        return $request;
    }

    private function mutate(ServerRequestInterface $request, string $mutation): ServerRequestInterface
    {
        return match ($mutation) {
            'replace' => $request->withAttribute(
                RouteDispatchState::ATTRIBUTE,
                RouteDispatchState::matched($request, new RouteMatch('replacement', '/items/{slug}', 'other.action')),
            ),
            'equivalent' => $request->withAttribute(
                RouteDispatchState::ATTRIBUTE,
                RouteDispatchState::matched(
                    $request,
                    RouteDispatchState::fromRequest($request)->match() ?? throw new \LogicException('expected-match'),
                ),
            ),
            'remove' => $request->withoutAttribute(RouteDispatchState::ATTRIBUTE),
            'null' => $request->withAttribute(RouteDispatchState::ATTRIBUTE, null),
            'method' => $this->request(
                'get',
                $request->getUri()->getPath(),
                $request->getUri()->getHost(),
                $request->getAttributes(),
            ),
            'path' => $this->request(
                $request->getMethod(),
                '/items/a%2fb',
                $request->getUri()->getHost(),
                $request->getAttributes(),
            ),
            'host' => $this->request(
                $request->getMethod(),
                $request->getUri()->getPath(),
                'other.test',
                $request->getAttributes(),
            ),
            default => throw new \LogicException('unknown-test-mutation'),
        };
    }

    private static function failure(\Closure $operation): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            return $exception;
        }

        self::fail('expected-dispatch-failure');
    }

    private static function assertGuard(\Throwable $failure, string $reason): void
    {
        self::assertSame(\LogicException::class, $failure::class);
        self::assertSame($reason, $failure->getMessage());
        self::assertNull($failure->getPrevious());
    }
}

class MatchedDispatchMiddleware implements MiddlewareInterface
{
    /** @param \Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $operation */
    public function __construct(private readonly \Closure $operation)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->operation)($request, $handler);
    }
}

final class MatchedDispatchManualApp extends MatchedDispatchMiddleware
{
}

final class MatchedDispatchManualRoute extends MatchedDispatchMiddleware
{
}

final class MatchedDispatchArgument
{
}

final class MatchedDispatchAction
{
    public int $calls = 0;

    public function __construct(
        private readonly \ArrayObject $events,
        private readonly ResponseInterface $response,
        private readonly RouteMatch $match,
        private readonly MatchedDispatchArgument $service,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        string $slug,
        MatchedDispatchArgument $service,
    ): ResponseInterface {
        TestCase::assertSame('a%2Fb', $slug);
        TestCase::assertSame($this->service, $service);
        TestCase::assertSame(['effective' => true], $request->getParsedBody());
        TestCase::assertSame($this->match, RouteDispatchState::fromRequest($request)->match());
        ++$this->calls;
        $this->events->append('action');

        return $this->response;
    }
}
