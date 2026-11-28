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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testEffectiveFoundationNoopsPreserveRepeatedDispatchAndPrimaryFailures(): void
    {
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http_app' => require \dirname(__DIR__, 2) . '/config/http_app.php',
        ];
        $config['foundation']['container']['autowire_concrete'] = false;
        $config['foundation']['container']['allow_reflection_for_concrete'] = false;
        $config['http_app']['handlers'] = [
            ['action_id' => 'action.ok', 'service_id' => 'action.ok'],
            ['action_id' => 'action.fail', 'service_id' => 'action.fail'],
            ['action_id' => 'action.type', 'service_id' => 'action.type'],
            ['action_id' => 'action.json', 'service_id' => 'action.json'],
            ['action_id' => 'action.unsupported', 'service_id' => 'action.unsupported'],
        ];
        $config['http_app']['route_middleware'] = ['route.middleware'];
        $foundation = new ContainerBuilder($config)->register(new FoundationServiceProvider())->build();
        $tracer = $foundation->get(TracerPortInterface::class);
        $meter = $foundation->get(MeterPortInterface::class);
        $logger = $foundation->get(LoggerInterface::class);
        self::assertInstanceOf(NoopTracer::class, $tracer);
        self::assertInstanceOf(NoopMeter::class, $meter);
        self::assertInstanceOf(NoopLogger::class, $logger);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $mapped = $this->createStub(ResponseInterface::class);
        $mapped->method('getStatusCode')->willReturn(200);
        $failure = new \RuntimeException('synthetic-primary-failure');
        $middlewareFailure = new \Error('synthetic-middleware-failure');
        $ok = new HttpAppNoopAction($response);
        $broken = new HttpAppNoopAction($failure);
        $middleware = new HttpAppNoopRouteMiddleware($middlewareFailure);
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::exactly(9))->method('match')->willReturnCallback(
            static function (string $method, string $path, ?string $host): RouteMatch {
                self::assertSame('GET', $method);
                self::assertSame('example.test', $host);
                $id = match ($path) {
                    '/ok' => 'action.ok',
                    '/fail' => 'action.fail',
                    '/type' => 'action.type',
                    '/json' => 'action.json',
                    '/unsupported' => 'action.unsupported',
                    '/unmapped' => 'action.unmapped',
                    '/middleware-fail' => 'action.ok',
                    default => throw new \LogicException('unexpected-route'),
                };

                return new RouteMatch('route', $path, $id, metadata: ['http_app_middleware' => ['route.middleware']]);
            },
        );
        $router->expects(self::never())->method('allowedMethods');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $initial = $this->createMock(ResponseInterface::class);
        $headed = $this->createMock(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(200)->willReturn($initial);
        $initial->expects(self::once())->method('withHeader')
            ->with('Content-Type', 'application/json')->willReturn($headed);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')
            ->with(self::identicalTo('{"a":1,"z":2}' . "\n"))->willReturn($stream);
        $headed->expects(self::once())->method('withBody')->with(self::identicalTo($stream))->willReturn($mapped);
        // Only the effective noop ports and clock are imported. No context or reset service exists in this graph.
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $builder->instance(TracerPortInterface::class, $tracer);
        $builder->instance(MeterPortInterface::class, $meter);
        $builder->instance(LoggerInterface::class, $logger);
        $builder->instance(Stopwatch::class, $foundation->get(Stopwatch::class));
        $builder->instance(RouterInterface::class, $router);
        $builder->instance(ResponseFactoryInterface::class, $responses);
        $builder->instance(StreamFactoryInterface::class, $streams);
        $builder->instance('action.ok', $ok);
        $builder->instance('action.fail', $broken);
        $builder->instance(
            'action.type',
            new class() {
                public function __invoke(int $value): never
                {
                    throw new \LogicException('unresolved-action-executed');
                }
            },
        );
        $builder->instance(
            'action.json',
            new class() {
                public function __invoke(): array
                {
                    return ['z' => 2, 'a' => 1];
                }
            },
        );
        $builder->instance(
            'action.unsupported',
            new class() {
                public function __invoke(): float
                {
                    return 1.5;
                }
            },
        );
        $builder->instance('route.middleware', $middleware);
        $container = $builder->register(new HttpAppServiceProvider())->build();
        foreach ([ContextStore::class, ResetOrchestrator::class, PriorityResetOrchestrator::class] as $id) {
            self::assertFalse(\in_array($id, $container->serviceIds(), true));
            self::assertFalse($container->has($id));
        }
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline([
            $container->get(RouterMiddleware::class),
            $container->get(ActionDispatchMiddleware::class),
        ], $terminal, true, [RouteDispatchState::ATTRIBUTE], true);
        self::assertSame($response, $pipeline->handle($this->request('/ok')));
        self::assertSame($failure, self::failure(fn () => $pipeline->handle($this->request('/fail'))));
        $argument = self::failure(fn () => $pipeline->handle($this->request('/type')));
        self::assertInstanceOf(HttpAppDispatchException::class, $argument);
        self::assertSame('http-app-argument-unresolvable', $argument->getMessage());
        $unmapped = self::failure(fn () => $pipeline->handle($this->request('/unmapped')));
        self::assertInstanceOf(HttpAppDispatchException::class, $unmapped);
        self::assertSame('http-app-handler-unmapped', $unmapped->getMessage());
        self::assertSame($response, $pipeline->handle($this->request('/ok')));
        self::assertSame(
            $middlewareFailure,
            self::failure(fn () => $pipeline->handle($this->request('/middleware-fail'))),
        );
        self::assertSame($response, $pipeline->handle($this->request('/ok')));
        self::assertSame($mapped, $pipeline->handle($this->request('/json')));
        $unsupported = self::failure(fn () => $pipeline->handle($this->request('/unsupported')));
        self::assertInstanceOf(HttpAppDispatchException::class, $unsupported);
        self::assertSame('http-app-response-unsupported', $unsupported->getMessage());
        self::assertNull($unsupported->getPrevious());
        self::assertSame(3, $ok->calls);
        self::assertSame(1, $broken->calls);
        self::assertSame(9, $middleware->calls);
    }

    private function request(string $path): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn('example.test');
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn([]);
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use ($uri): ServerRequestInterface {
                self::assertSame(RouteDispatchState::ATTRIBUTE, $name);
                self::assertInstanceOf(RouteDispatchState::class, $value);
                /** @var ServerRequestInterface&Stub $matched */
                $matched = $this->createStub(ServerRequestInterface::class);
                $matched->method('getMethod')->willReturn('GET');
                $matched->method('getUri')->willReturn($uri);
                $matched->method('getAttributes')->willReturn([$name => $value]);

                return $matched;
            },
        );

        return $request;
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
}

final class HttpAppNoopAction
{
    public int $calls = 0;

    public function __construct(private readonly ResponseInterface|\Throwable $outcome)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;
        if ($this->outcome instanceof \Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}

final class HttpAppNoopRouteMiddleware implements MiddlewareInterface
{
    public int $calls = 0;

    public function __construct(private readonly \Throwable $failure)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->calls;
        if ($request->getUri()->getPath() === '/middleware-fail') {
            throw $this->failure;
        }

        return $handler->handle($request);
    }
}
