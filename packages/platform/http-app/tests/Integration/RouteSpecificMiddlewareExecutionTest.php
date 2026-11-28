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
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Exception\HttpMethodNotAllowedException;
use Coretsia\Platform\HttpApp\Exception\HttpNotFoundException;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
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

final class RouteSpecificMiddlewareExecutionTest extends TestCase
{
    public function testRouteOrderResponseUnwindAndContainerOwnedLifecycleAcrossRoutes(): void
    {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $a = new RouteMatch(
            'a',
            '/a/{slug}',
            'action.show',
            ['slug' => 'a%2Fb'],
            metadata: ['http_app_middleware' => ['route.z', 'route.a']],
        );
        $b = new RouteMatch(
            'b',
            '/b/{slug}',
            'action.show',
            ['slug' => 'other'],
            metadata: ['http_app_middleware' => ['route.a']],
        );
        $empty = new RouteMatch('empty', '/empty/{slug}', 'action.show', ['slug' => 'none']);
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::exactly(4))->method('match')->willReturnCallback(
            static fn (string $method, string $path): RouteMatch => match ($path) {
                '/a/a%2Fb' => $a,
                '/b/other' => $b,
                '/empty/none' => $empty,
            },
        );
        $router->expects(self::never())->method('allowedMethods');
        $builder = $this->builder(self::app(['route.a', 'route.z', 'route.unselected']), $router);
        $this->observe($builder, $observations, 4);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $aDecorated = $this->createMock(ResponseInterface::class);
        $zDecorated = $this->createStub(ResponseInterface::class);
        $response->expects(self::exactly(3))->method('withHeader')->with('X-Route-A', 'yes')->willReturn($aDecorated);
        $aDecorated->expects(self::exactly(2))->method('withHeader')->with('X-Route-Z', 'yes')->willReturn($zDecorated);
        $instances = new \ArrayObject();
        $actionRequests = new \ArrayObject();
        $adaptedRequests = new \ArrayObject();
        $builder->factory(
            'action.service',
            static function () use ($events, $response, $actionRequests): RouteSpecificAction {
                $events->append('resolve-action');

                return new RouteSpecificAction($events, $response, $actionRequests);
            },
            false,
        );
        foreach (['route.z' => false, 'route.a' => true, 'route.unselected' => true] as $id => $shared) {
            $builder->factory(
                $id,
                static function () use ($id, $events, $instances, $adaptedRequests): MiddlewareInterface {
                    $events->append('resolve-' . $id);
                    if ($id === 'route.unselected') {
                        return new RouteSpecificResettableMiddleware($events);
                    }
                    $middleware = new RouteSpecificTestMiddleware(static function (
                        ServerRequestInterface $request,
                        RequestHandlerInterface $next,
                    ) use ($id, $events, $adaptedRequests): ResponseInterface {
                        $events->append('before-' . $id);
                        $adapted = $request->withAttribute($id, true);
                        $adaptedRequests->append($adapted);
                        $response = $next->handle($adapted);
                        $events->append('after-' . $id);

                        return $response->withHeader($id === 'route.a' ? 'X-Route-A' : 'X-Route-Z', 'yes');
                    });
                    $instances->append([$id, $middleware]);

                    return $middleware;
                },
                $shared,
            );
        }
        // A reset-tagged but unselected service is not resolved by dispatch. The independent
        // lifecycle/reset consumer is deliberately not invoked in this selection test.
        $builder->tag(ReservedTags::KERNEL_RESET, 'route.unselected');
        $containers = new \ArrayObject();
        $pipeline = $this->pipeline($builder, $failures, containers: $containers);
        self::assertSame($zDecorated, $pipeline->handle($this->request(path: '/a/a%2Fb')));
        self::assertSame($aDecorated, $pipeline->handle($this->request(path: '/b/other')));
        self::assertSame($response, $pipeline->handle($this->request(path: '/empty/none')));
        self::assertSame($zDecorated, $pipeline->handle($this->request(path: '/a/a%2Fb')));
        self::assertSame([
            'resolve-route.z',
            'resolve-route.a',
            'before-route.z',
            'before-route.a',
            'resolve-action',
            'action',
            'after-route.a',
            'after-route.z',
            'before-route.a',
            'resolve-action',
            'action',
            'after-route.a',
            'resolve-action',
            'action',
            'resolve-route.z',
            'before-route.z',
            'before-route.a',
            'resolve-action',
            'action',
            'after-route.a',
            'after-route.z',
        ], $events->getArrayCopy());
        self::assertCount(3, $instances);
        self::assertSame('route.z', $instances[0][0]);
        self::assertSame('route.a', $instances[1][0]);
        self::assertSame('route.z', $instances[2][0]);
        self::assertNotSame($instances[0][1], $instances[2][1]);
        self::assertCount(5, $adaptedRequests);
        self::assertSame($adaptedRequests[1], $actionRequests[0]);
        self::assertSame($adaptedRequests[2], $actionRequests[1]);
        self::assertSame($adaptedRequests[4], $actionRequests[3]);
        foreach ([$a, $b, $empty, $a] as $index => $match) {
            self::assertSame($match, RouteDispatchState::fromRequest($actionRequests[$index])->match());
            self::assertSame($index === 0 || $index === 3, $actionRequests[$index]->getAttribute('route.z', false));
            self::assertSame($index !== 2, $actionRequests[$index]->getAttribute('route.a', false));
        }
        self::assertCount(0, $failures);
        self::assertSame(4, $observations->count());
        // Independently run canonical lifecycle discovery only after dispatch evidence is complete.
        $beforeReset = $events->getArrayCopy();
        self::assertCount(1, $containers);
        self::assertInstanceOf(Container::class, $containers[0]);
        new ResetOrchestrator($containers[0], $builder->tagRegistry(), ReservedTags::KERNEL_RESET, false, null)
            ->resetAll();
        self::assertSame(
            [...$beforeReset, 'resolve-route.unselected', 'reset-route.unselected'],
            $events->getArrayCopy(),
        );
    }

    #[DataProvider('preTerminalCases')]
    public function testPreTerminalFailuresAndShortCircuitsNeverResolveOrObserveAction(
        string $mode,
        string $reason,
    ): void {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $id = $mode === 'autowire' ? RouteSpecificAutowireMiddleware::class : 'private.middleware';
        $match = new RouteMatch(
            'items',
            '/items/{slug}',
            'action.show',
            ['slug' => 'a%2Fb'],
            ['http_app_middleware' => [$id]],
        );
        $builder = $this->builder(self::app($mode === 'unadmitted' ? [] : [$id]), $this->router($match));
        $this->observe($builder, $observations, 0);
        $builder->factory('action.service', static function () use ($events): never {
            $events->append('unexpected-action-resolution');
            throw new \LogicException('unexpected-action-resolution');
        });
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $primary = new \Error('private-middleware-execution-details');
        $escaped = null;
        $incoming = null;
        if (\in_array($mode, ['deny', 'throw', 'expire'], true)) {
            $builder->factory(
                $id,
                static function () use (
                    $events,
                    $mode,
                    $response,
                    $primary,
                    &$escaped,
                    &$incoming,
                ): MiddlewareInterface {
                    $events->append('resolve-middleware');

                    return new RouteSpecificTestMiddleware(static function (
                        ServerRequestInterface $request,
                        RequestHandlerInterface $next,
                    ) use ($events, $mode, $response, $primary, &$escaped, &$incoming): ResponseInterface {
                        $events->append('middleware');
                        $escaped = $next;
                        $incoming = $request;
                        if ($mode === 'throw') {
                            throw $primary;
                        }

                        return $response;
                    });
                },
            );
        } elseif ($mode === 'unadmitted') {
            $builder->factory($id, static function () use ($events): never {
                $events->append('unexpected-unadmitted-resolution');
                throw new \LogicException('unexpected-unadmitted-resolution');
            });
        } elseif ($mode === 'wrong-type') {
            $builder->instance($id, new \stdClass());
        } elseif (\str_starts_with($mode, 'factory-')) {
            $failure = match ($mode) {
                'factory-token' => new \RuntimeException('http-app-route-middleware-invalid'),
                'factory-instance-token' => new \InvalidArgumentException('http-middleware-instance-invalid'),
                'factory-id-token' => new \InvalidArgumentException('http-middleware-service-id-invalid'),
                'factory-dispatch-token' => HttpAppDispatchException::withReason('http-app-route-middleware-invalid'),
                'factory-container-role-token' => new ContainerException('http-app-route-middleware-invalid'),
                'factory-container-instance-token' => new ContainerException('http-middleware-instance-invalid'),
                'factory-container-id-token' => new ContainerException('http-middleware-service-id-invalid'),
                default => new \RuntimeException('private-container-details'),
            };
            $builder->factory($id, static fn () => throw $failure);
        }
        if ($mode === 'autowire') {
            self::assertTrue($builder->build()->has($id));
            self::assertFalse(\in_array($id, $builder->serviceIds(), true));
        }
        $pipeline = $this->pipeline($builder, $failures);
        if ($mode === 'deny' || $mode === 'expire') {
            self::assertSame($response, $pipeline->handle($this->request()));
            self::assertSame(403, $response->getStatusCode());
            if ($mode === 'expire') {
                self::assertInstanceOf(RequestHandlerInterface::class, $escaped);
                self::assertInstanceOf(ServerRequestInterface::class, $incoming);
                self::assertGuard(
                    self::failure(static fn () => $escaped->handle($incoming)),
                    'http-pipeline-continuation-expired'
                );
            }
            self::assertCount(0, $failures);
        } else {
            $failure = self::failure(fn () => $pipeline->handle($this->request()));
            if ($mode === 'throw') {
                self::assertSame($primary, $failure);
            } else {
                self::assertDispatchFailure($failure, $reason);
            }
            self::assertSame([$failure], $failures->getArrayCopy());
        }
        self::assertSame(
            \in_array($mode, ['deny', 'throw', 'expire'], true) ? ['resolve-middleware', 'middleware'] : [],
            $events->getArrayCopy(),
        );
        self::assertCount(0, $observations);
    }

    /** @return iterable<string, array{string, string}> */
    public static function preTerminalCases(): iterable
    {
        yield 'authorization-short-circuit' => ['deny', ''];
        yield 'middleware-throwable' => ['throw', ''];
        yield 'escaped-short-circuit' => ['expire', ''];
        yield 'unadmitted' => ['unadmitted', 'http-app-route-middleware-invalid'];
        foreach (
            [
                'missing',
                'autowire',
                'wrong-type',
                'factory-private',
                'factory-token',
                'factory-instance-token',
                'factory-id-token',
                'factory-dispatch-token',
                'factory-container-role-token',
                'factory-container-instance-token',
                'factory-container-id-token',
            ] as $mode
        ) {
            yield $mode => [$mode, 'http-app-route-middleware-unavailable'];
        }
    }

    #[DataProvider('laterAdmissionFailures')]
    public function testWholeSelectedSequenceIsAdmittedBeforeAnyMiddlewareExecutes(string $mode): void
    {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $ids = ['route.prefix', 'private.later'];
        $match = new RouteMatch('items', '/items/{slug}', 'action.show', metadata: ['http_app_middleware' => $ids]);
        $builder = $this->builder(self::app($mode === 'unadmitted' ? ['route.prefix'] : $ids), $this->router($match));
        $this->observe($builder, $observations, 0);
        $builder->factory('route.prefix', static function () use ($events): MiddlewareInterface {
            $events->append('resolve-prefix');

            return new RouteSpecificTestMiddleware(static function () use ($events): never {
                $events->append('unexpected-prefix-execution');
                throw new \LogicException('unexpected-prefix-execution');
            });
        });
        $builder->factory('action.service', static function () use ($events): never {
            $events->append('unexpected-action-resolution');
            throw new \LogicException('unexpected-action-resolution');
        });
        if ($mode === 'wrong-type') {
            $builder->factory('private.later', static function () use ($events): object {
                $events->append('resolve-later');

                return new \stdClass();
            });
        } elseif ($mode === 'factory-failure' || $mode === 'unadmitted') {
            $builder->factory('private.later', static function () use ($events): never {
                $events->append('resolve-later');
                throw new \RuntimeException('private-later-construction-details');
            });
        }
        $pipeline = $this->pipeline($builder, $failures);
        $failure = self::failure(fn () => $pipeline->handle($this->request()));
        self::assertDispatchFailure(
            $failure,
            $mode === 'unadmitted' ? 'http-app-route-middleware-invalid' : 'http-app-route-middleware-unavailable',
        );
        self::assertSame([$failure], $failures->getArrayCopy());
        self::assertSame(
            \in_array($mode, ['wrong-type', 'factory-failure'], true) ? ['resolve-prefix', 'resolve-later'] : [],
            $events->getArrayCopy(),
        );
        self::assertCount(0, $observations);
    }

    /** @return iterable<string, array{string}> */
    public static function laterAdmissionFailures(): iterable
    {
        foreach (['missing', 'unadmitted', 'wrong-type', 'factory-failure'] as $mode) {
            yield $mode => [$mode];
        }
    }

    #[DataProvider('forbiddenRoles')]
    public function testAliasesOfCanonicalRolesAreRejectedAfterResolution(string $role): void
    {
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $match = new RouteMatch(
            'items',
            '/items/{slug}',
            'action.show',
            metadata: ['http_app_middleware' => ['private.alias']],
        );
        $builder = $this->builder(self::app(['private.alias']), $this->router($match));
        $this->observe($builder, $observations, 0);
        $action = new \stdClass();
        // If admission fails, action resolution is never attempted.
        $resolutions = new \ArrayObject();
        $builder->factory('action.service', static function () use ($resolutions, $action): object {
            $resolutions->append('action');

            return $action;
        });
        $pipeline = $this->pipeline($builder, $failures, $role);
        $failure = self::failure(fn () => $pipeline->handle($this->request()));
        self::assertDispatchFailure($failure, 'http-app-route-middleware-invalid');
        self::assertSame([$failure], $failures->getArrayCopy());
        self::assertCount(0, $resolutions);
        self::assertCount(0, $observations);
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenRoles(): iterable
    {
        yield 'router' => [RouterMiddleware::class];
        yield 'dispatcher' => [ActionDispatchMiddleware::class];
        yield 'outer-boundary' => [HttpErrorHandlingMiddleware::class];
    }

    #[DataProvider('localViolations')]
    public function testEveryLocalContinuationGuardsHandoffAndRoutingBeforeAuthorization(
        string $mutation,
        string $position,
    ): void {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $ids = match ($position) {
            'first' => ['mutator', 'authorization'],
            'later' => ['first', 'mutator', 'authorization'],
            'terminal' => ['first', 'mutator'],
        };
        $match = new RouteMatch(
            'items',
            '/items/{slug}',
            'action.show',
            ['slug' => 'a%2Fb'],
            ['http_app_middleware' => $ids],
        );
        $builder = $this->builder(self::app($ids), $this->router($match));
        $this->observe($builder, $observations, 0);
        $builder->factory('action.service', static function () use ($events): never {
            $events->append('unexpected-action-resolution');
            throw new \LogicException('unexpected-action-resolution');
        });
        $builder->instance('first', new RouteSpecificTestMiddleware(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events): ResponseInterface {
            $events->append('first');

            return $next->handle($request->withParsedBody(['legitimate' => true]));
        }));
        $caught = null;
        $builder->instance('mutator', new RouteSpecificTestMiddleware(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events, $mutation, &$caught): ResponseInterface {
            $events->append('mutator');
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
        }));
        $builder->instance('authorization', new RouteSpecificTestMiddleware(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events): ResponseInterface {
            $events->append('unexpected-authorization:' . $request->getUri()->getPath());
            // A later restoration is not an admissible way to recover the original routing inputs.
            return $next->handle($this->request(attributes: $request->getAttributes()));
        }));
        $failure = self::failure(fn () => $this->pipeline($builder, $failures)->handle($this->request()));
        $reason = \in_array($mutation, ['replace', 'equivalent', 'remove', 'null'], true)
            ? 'http-pipeline-protected-attribute-changed' : 'http-pipeline-protected-routing-input-changed';
        self::assertGuard($failure, $reason);
        self::assertSame($caught, $failure);
        self::assertSame([$failure], $failures->getArrayCopy());
        self::assertSame($position === 'first' ? ['mutator'] : ['first', 'mutator'], $events->getArrayCopy());
        self::assertCount(0, $observations);
    }

    /** @return iterable<string, array{string, string}> */
    public static function localViolations(): iterable
    {
        foreach (['replace', 'equivalent', 'remove', 'null', 'method', 'path', 'host'] as $mutation) {
            foreach (['first', 'later', 'terminal'] as $position) {
                yield $mutation . '-' . $position => [$mutation, $position];
            }
        }
    }

    #[DataProvider('retryCases')]
    public function testLocalContinuationCannotRetryCompletedOrFailedAction(bool $throws): void
    {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $match = new RouteMatch(
            'items',
            '/items/{slug}',
            'action.show',
            metadata: ['http_app_middleware' => ['retry']],
        );
        $builder = $this->builder(self::app(['retry']), $this->router($match));
        $this->observe($builder, $observations, 1);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $primary = new \RuntimeException('synthetic-action-failure');
        $builder->factory('action.service', static function () use ($events, $response, $primary, $throws): object {
            $events->append('resolve-action');

            return new class($events, $response, $primary, $throws) {
                public function __construct(
                    private readonly \ArrayObject $events,
                    private readonly ResponseInterface $response,
                    private readonly \Throwable $primary,
                    private readonly bool $throws,
                ) {
                }

                public function __invoke(): ResponseInterface
                {
                    $this->events->append('action');
                    if ($this->throws) {
                        throw $this->primary;
                    }

                    return $this->response;
                }
            };
        }, false);
        $builder->instance('retry', new RouteSpecificTestMiddleware(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($throws, $primary, $response): ResponseInterface {
            if ($throws) {
                self::assertSame($primary, self::failure(static fn () => $next->handle($request)));
            } else {
                self::assertSame($response, $next->handle($request));
            }

            return $next->handle($request);
        }));
        $failure = self::failure(fn () => $this->pipeline($builder, $failures)->handle($this->request()));
        self::assertGuard($failure, 'http-pipeline-continuation-reused');
        self::assertSame([$failure], $failures->getArrayCopy());
        self::assertSame(['resolve-action', 'action'], $events->getArrayCopy());
        self::assertCount(1, $observations);
    }

    /** @return iterable<string, array{bool}> */
    public static function retryCases(): iterable
    {
        yield 'completed' => [false];
        yield 'failed' => [true];
    }

    #[DataProvider('nonMatchedCases')]
    public function testNoRouteSpecificResolutionForReservedOrUnmatchedRequests(
        string $path,
        array $methods,
        ?string $expected,
    ): void {
        $events = new \ArrayObject();
        $failures = new \ArrayObject();
        $observations = new \ArrayObject();
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with('GET', $path, 'example.test')->willReturn(null);
        $router->expects($expected === null ? self::never() : self::once())->method('allowedMethods')->with(
            $path,
            'example.test',
        )->willReturn($methods);
        $builder = $this->builder(self::app(['private.middleware']), $router);
        $this->observe($builder, $observations, 0);
        foreach (['private.middleware', 'action.service'] as $id) {
            $builder->factory($id, static function () use ($events, $id): never {
                $events->append($id);
                throw new \LogicException('unexpected-service-resolution');
            });
        }
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects($expected === null ? self::once() : self::never())->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $request) use ($response): ResponseInterface {
                self::assertNull(RouteDispatchState::fromRequest($request)->match());

                return $response;
            },
        );
        $pipeline = $this->pipeline($builder, $failures, terminal: $terminal);
        if ($expected === null) {
            self::assertSame($response, $pipeline->handle($this->request(path: $path)));
            self::assertCount(0, $failures);
        } else {
            $failure = self::failure(fn () => $pipeline->handle($this->request(path: $path)));
            self::assertSame($expected, $failure::class);
            self::assertSame([$failure], $failures->getArrayCopy());
        }
        self::assertCount(0, $events);
        self::assertCount(0, $observations);
    }

    /** @return iterable<string, array{string, list<string>, ?class-string}> */
    public static function nonMatchedCases(): iterable
    {
        yield 'reserved' => ['/_alive', [], null];
        yield 'not-found' => ['/missing', [], HttpNotFoundException::class];
        yield 'method-not-allowed' => ['/items', ['POST'], HttpMethodNotAllowedException::class];
    }

    /**
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private static function app(array $ids): array
    {
        $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $app['handlers'] = [['action_id' => 'action.show', 'service_id' => 'action.service']];
        $app['route_middleware'] = $ids;

        return $app;
    }

    private function router(RouteMatch $match): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with('GET', '/items/a%2Fb', 'example.test')->willReturn(
            $match,
        );
        $router->expects(self::never())->method('allowedMethods');

        return $router;
    }

    private function observe(ContainerBuilder $builder, \ArrayObject $observations, int $count): void
    {
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::exactly($count))->method('startSpan')->with('http.action')->willReturnCallback(
            function () use ($observations): SpanInterface {
                $observations->append('action');

                return new NoopSpan();
            },
        );
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::exactly($count))->method('increment')->with('http.action_total', 1);
        $meter->expects(self::exactly($count))->method('observe')->with('http.action_duration_ms');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly($count))->method('debug')->with('http.action.completed');
        $builder->instance(TracerPortInterface::class, $tracer);
        $builder->instance(MeterPortInterface::class, $meter);
        $builder->instance(LoggerInterface::class, $logger);
    }

    private function pipeline(
        ContainerBuilder $builder,
        \ArrayObject $failures,
        ?string $alias = null,
        ?RequestHandlerInterface $terminal = null,
        ?\ArrayObject $containers = null,
    ): Pipeline {
        $boundary = $this->boundary($failures);
        $builder->instance(HttpErrorHandlingMiddleware::class, $boundary);
        $definitions = new ContainerDefinitionBuilder();
        new HttpAppServiceProvider()->define($definitions, new ContainerDefinitionContext($builder->config()));
        if ($alias !== null) {
            $definitions->alias('private.alias', $alias);
        }
        $container = $builder->applyDefinitions($definitions->build())->build();
        $containers?->append($container);
        if ($terminal === null) {
            $terminal = $this->createMock(RequestHandlerInterface::class);
            $terminal->expects(self::never())->method('handle');
        }

        return new Pipeline(
            [$boundary, $container->get(RouterMiddleware::class), $container->get(ActionDispatchMiddleware::class)],
            $terminal,
            true,
            [RouteDispatchState::ATTRIBUTE],
            true,
        );
    }

    private static function assertDispatchFailure(\Throwable $failure, string $reason): void
    {
        self::assertInstanceOf(HttpAppDispatchException::class, $failure);
        self::assertSame($reason, $failure->reason());
        self::assertSame($reason, $failure->getMessage());
        self::assertNull($failure->getPrevious());
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

class RouteSpecificTestMiddleware implements MiddlewareInterface
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

final class RouteSpecificAutowireMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('unregistered-middleware-executed');
    }
}

final class RouteSpecificAction
{
    public function __construct(
        private readonly \ArrayObject $events,
        private readonly ResponseInterface $response,
        private readonly \ArrayObject $requests,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, string $slug): ResponseInterface
    {
        TestCase::assertSame(RouteDispatchState::fromRequest($request)->match()?->parameters()['slug'], $slug);
        $this->events->append('action');
        $this->requests->append($request);

        return $this->response;
    }
}

final class RouteSpecificResettableMiddleware implements MiddlewareInterface, ResetInterface
{
    public function __construct(private readonly \ArrayObject $events)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->events->append('unexpected-unselected-execution');
        throw new \LogicException('unselected-middleware-executed');
    }

    public function reset(): void
    {
        $this->events->append('reset-route.unselected');
    }
}
