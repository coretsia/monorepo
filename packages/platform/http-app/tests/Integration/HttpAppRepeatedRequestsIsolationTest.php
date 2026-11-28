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
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouteMiddlewareSelector;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
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

final class HttpAppRepeatedRequestsIsolationTest extends TestCase
{
    public function testOneHttpKernelReusesServicesWithoutRetainingInvocationStateAndResetsAllCanonicalServices(): void
    {
        $record = new RepeatedHttpAppRecorder();
        $context = new ContextStore();
        $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $app['handlers'] = [
            ['action_id' => 'action.a', 'service_id' => 'service.a'],
            ['action_id' => 'action.b', 'service_id' => 'service.b'],
        ];
        $app['arguments']['allow_container_types'] = [RepeatedHttpAppArgument::class];
        $app['route_middleware'] = ['route.nonshared', 'route.shared', 'route.unselected'];
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http_app' => $app,
        ];
        $config['foundation']['container']['autowire_concrete'] = false;
        $config['foundation']['container']['allow_reflection_for_concrete'] = false;
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $cases = [
            ['GET', '/a/first%2Fvalue', 'action.a', 'first%2Fvalue', ['route.nonshared'], 201, false],
            ['POST', '/b/failure', 'action.b', 'failure', ['route.shared'], 202, true],
            ['GET', '/a/second', 'action.a', 'second', ['route.nonshared'], 203, false],
            ['PUT', '/b/third', 'action.b', 'third', ['route.shared', 'route.nonshared'], 204, false],
            ['GET', '/a/empty', 'action.a', 'empty', [], 205, false],
        ];
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::exactly(\count($cases)))->method('match')->willReturnCallback(
            static function (string $method, string $path, ?string $host) use ($cases, $record): RouteMatch {
                [$expectedMethod, $expectedPath, $action, $slug, $ids] = $cases[$record->index];
                self::assertSame([$expectedMethod, $expectedPath, 'example.test'], [$method, $path, $host]);
                $record->events[] = 'match';
                $match = new RouteMatch(
                    'route',
                    '/items/{slug}',
                    $action,
                    ['slug' => $slug],
                    ['http_app_middleware' => $ids],
                );
                $record->match = \WeakReference::create($match);

                return $match;
            },
        );
        $router->expects(self::never())->method('allowedMethods');
        $builder->instance(RouterInterface::class, $router);
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $builder->instance(ResponseFactoryInterface::class, $responses);
        $builder->instance(StreamFactoryInterface::class, $streams);
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::exactly(\count($cases)))->method('startSpan')->willReturnCallback(
            static function (string $name, array $attributes) use ($record, $cases): SpanInterface {
                self::assertSame('http.action', $name);
                self::assertSame(['method' => $cases[$record->index][0]], $attributes);
                $record->events[] = 'span:start';
                $span = new RepeatedHttpAppSpan($record);
                $record->span = \WeakReference::create($span);

                return $span;
            },
        );
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::exactly(\count($cases)))->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use ($record): void {
                $record->signals[] = ['increment', $name, $delta, $labels];
            },
        );
        $meter->expects(self::exactly(\count($cases)))->method('observe')->willReturnCallback(
            static function (string $name, int $value, array $labels) use ($record): void {
                $record->signals[] = ['observe', $name, $value, $labels];
            },
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(\count($cases)))->method('debug')->willReturnCallback(
            static function (string $message, array $labels) use ($record): void {
                $record->signals[] = ['log', $message, $labels];
            },
        );
        $builder->instance(TracerPortInterface::class, $tracer);
        $builder->instance(MeterPortInterface::class, $meter);
        $builder->instance(LoggerInterface::class, $logger);
        $stopwatch = new Stopwatch();
        $builder->instance(Stopwatch::class, $stopwatch);
        foreach (['a', 'b'] as $id) {
            $builder->factory('service.' . $id, static function () use ($record, $id, $cases): RepeatedHttpAppAction {
                $record->events[] = 'resolve-action:' . $id;
                $action = new RepeatedHttpAppAction($record, $id, $cases[$record->index][3], $cases[$record->index][6]);
                $record->action = \WeakReference::create($action);

                return $action;
            }, false);
        }
        $builder->factory(RepeatedHttpAppArgument::class, static function () use ($record): RepeatedHttpAppArgument {
            $record->events[] = 'resolve-argument';
            $argument = new RepeatedHttpAppArgument($record->index);
            $record->argument = \WeakReference::create($argument);

            return $argument;
        }, false);
        $builder->factory('route.nonshared', static function () use ($record): RepeatedHttpAppMiddleware {
            $record->events[] = 'resolve:nonshared';
            $middleware = new RepeatedHttpAppMiddleware($record, 'nonshared');
            $record->localMiddleware[] = \WeakReference::create($middleware);

            return $middleware;
        }, false);
        foreach (['shared', 'unselected'] as $id) {
            $builder->factory(
                'route.' . $id,
                static function () use ($record, $id): RepeatedHttpAppResettableMiddleware {
                    $record->events[] = 'resolve:' . $id;

                    return new RepeatedHttpAppResettableMiddleware($record, $id);
                },
                true,
            );
            $builder->tag(ReservedTags::KERNEL_RESET, 'route.' . $id, $id === 'shared' ? 20 : 10);
        }
        $builder->instance(ContextStore::class, $context)->tag(ReservedTags::KERNEL_RESET, ContextStore::class, -100);
        $hook = new RepeatedHttpAppHook($record);
        $builder->instance(RepeatedHttpAppHook::class, $hook)
            ->tag(ReservedTags::KERNEL_HOOK_BEFORE_UOW, RepeatedHttpAppHook::class)
            ->tag(ReservedTags::KERNEL_HOOK_AFTER_UOW, RepeatedHttpAppHook::class);
        $tags = $builder->tagRegistry();
        $resetTags = $tags->all(ReservedTags::KERNEL_RESET);
        $builder->register(new HttpAppServiceProvider());
        self::assertSame($resetTags, $tags->all(ReservedTags::KERNEL_RESET));
        $container = $builder->build();
        $routerMiddleware = $container->get(RouterMiddleware::class);
        $dispatcher = $container->get(ActionDispatchMiddleware::class);
        $global = new RepeatedHttpAppMiddleware($record, 'global');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline(
            [$routerMiddleware, $global, $dispatcher],
            $terminal,
            true,
            [RouteDispatchState::ATTRIBUTE],
            true,
        );
        $ids = new UlidGenerator();
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $runtime = new KernelRuntime(
            $context,
            new ResetOrchestrator($container, $tags),
            $stopwatch,
            $ids,
            $correlation,
            new CorrelationIdGenerator($ids),
            new HookInvoker($container, $tags),
            new NoopLogger(),
            new NoopTracer(),
            new NoopMeter(),
            10,
            64,
        );
        $requestIds = new RequestIdPolicy($context, $context, $ids, false, 'X-Request-Id', false, 26);
        $kernel = new HttpKernel(
            $runtime,
            $stopwatch,
            $pipeline,
            new HttpResponseFinalizer(
                $correlation,
                false,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder(new NoopMeter(), false),
            new HttpAccessLogWriter(new NoopLogger(), false),
            new HttpRequestSpanRecorder(new NoopTracer(), false),
            $requestIds,
        );
        // Snapshot all reusable HttpApp-owned fields, including policy arrays and dependency identity.
        // Only unregistered PHPUnit stubs are used for transport lifetime checks; no mock expectation owns them.
        $services = [];
        foreach (
            [
                RouterMiddleware::class,
                ActionDispatchMiddleware::class,
                HandlerResolver::class,
                ArgumentResolver::class,
                ActionInvoker::class,
                ActionResponseMapper::class,
                RouteMiddlewareSelector::class,
            ] as $class
        ) {
            $instance = $container->get($class);
            $services[$class] = [$instance, self::state($instance)];
        }
        $expected = [
            RouterMiddleware::class => ['router'],
            ActionDispatchMiddleware::class => [
                'container',
                'selector',
                'invoker',
                'responseMapper',
                'tracer',
                'meter',
                'logger',
                'stopwatch',
                'tracingEnabled',
                'metricsEnabled',
                'loggingEnabled',
            ],
            HandlerResolver::class => ['registrations', 'container'],
            ArgumentResolver::class => [
                'containerTypes',
                'handlerResolver',
                'container',
                'allowRequest',
                'allowRouteParams',
            ],
            ActionInvoker::class => ['handlerResolver', 'argumentResolver'],
            ActionResponseMapper::class => ['responses', 'streams', 'jsonEnabled', 'htmlEnabled'],
            RouteMiddlewareSelector::class => ['serviceIds'],
        ];
        foreach ($cases as $index => [$method, $path, $action, $slug, $selected, $status, $throws]) {
            $record->index = $index;
            $record->events = [];
            $record->signals = [];
            $response = $this->createStub(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($status);
            $record->response = $response;
            $request = $this->request($method, $path, $index);
            $originalRequest = \WeakReference::create($request);
            $responseReference = \WeakReference::create($response);
            // Old continuations cannot enter even while the next HTTP invocation is pending.
            foreach ($record->continuations as $expired) {
                self::assertExpired($expired, $request);
            }
            self::assertSame([], $record->events);
            if ($throws) {
                $failure = self::failure(static fn () => $kernel->handle($request));
                self::assertSame('synthetic-action-failure', $failure->getMessage());
                self::assertSame($failure, $record->primary?->get());
                unset($failure);
            } else {
                self::assertSame($response, $kernel->handle($request));
            }
            $expectedEvents = ['before', 'match', 'global'];
            if (\in_array('route.nonshared', $selected, true)) {
                $expectedEvents[] = 'resolve:nonshared';
            }
            foreach ($selected as $id) {
                $expectedEvents[] = \substr($id, 6);
            }
            \array_push(
                $expectedEvents,
                'span:start',
                'resolve-action:' . \substr($action, -1),
                'resolve-argument',
                'action:' . \substr($action, -1) . ':' . $slug,
                'span:end',
                'after:' . ($throws ? 'fatal_error' : 'success'),
            );
            if ($index === 0) {
                // Neither service was selected. Canonical reset still resolves BOTH shared tagged services.
                \array_push(
                    $expectedEvents,
                    'resolve:shared',
                    'reset:shared:0',
                    'resolve:unselected',
                    'reset:unselected:0',
                );
            } else {
                \array_push(
                    $expectedEvents,
                    'reset:shared:' . (\in_array('route.shared', $selected, true) ? '1' : '0'),
                    'reset:unselected:0',
                );
            }
            self::assertSame($expectedEvents, $record->events);
            $outcome = $throws ? 'threw' : 'returned';
            $observedStatus = $throws ? 'unknown' : $status;
            $labels = ['method' => $method, 'status' => $observedStatus, 'outcome' => $outcome];
            self::assertCount(5, $record->signals);
            $duration = $record->signals[3][2];
            self::assertIsInt($duration);
            self::assertGreaterThanOrEqual(0, $duration);
            self::assertLessThanOrEqual(4_294_967_295, $duration);
            self::assertSame([
                ['attribute', 'outcome', $outcome],
                ['attribute', 'status', $observedStatus],
                ['increment', 'http.action_total', 1, $labels],
                ['observe', 'http.action_duration_ms', $duration, $labels],
                ['log', 'http.action.completed', $labels],
            ], $record->signals);
            self::assertSame([], $context->all());
            $record->response = null;
            foreach ($record->continuations as $expired) {
                self::assertExpired($expired, $request);
                $properties = self::state($expired);
                self::assertSame(true, $properties['entered']);
                self::assertNull($properties['next']);
                self::assertSame([], $properties['protectedValues']);
                self::assertNull($properties['routingInputs']);
            }
            unset($request, $response);
            \gc_collect_cycles();
            foreach (
                [
                    $originalRequest,
                    $responseReference,
                    $record->request,
                    $record->state,
                    $record->match,
                    $record->action,
                    $record->argument,
                    $record->span,
                    ...$record->localMiddleware,
                    ...$record->downstream,
                ] as $reference
            ) {
                self::assertInstanceOf(\WeakReference::class, $reference);
                self::assertNull($reference->get());
            }
            foreach ($services as $class => [$instance, $snapshot]) {
                self::assertSame($instance, $container->get($class));
                self::assertSame($snapshot, self::state($instance));
                $keys = \array_keys($snapshot);
                $wanted = $expected[$class];
                \sort($keys, \SORT_STRING);
                \sort($wanted, \SORT_STRING);
                self::assertSame($wanted, $keys);
            }
            self::assertSame($resetTags, $tags->all(ReservedTags::KERNEL_RESET));
        }
        self::assertSame(5, $hook->begins);
        self::assertSame(5, $hook->completions);
        self::assertCount(5, \array_unique($hook->uowIds));
        self::assertCount(3, $record->localMiddleware);
        // Five global and five selected local middleware continuations; each must remain a distinct object.
        self::assertCount(10, $record->continuations);
        self::assertCount(10, \array_unique(\array_map(\spl_object_id(...), $record->continuations)));
    }

    /** @return array<string, mixed> */
    private static function state(object $instance): array
    {
        $values = [];
        foreach (new \ReflectionClass($instance)->getProperties() as $property) {
            self::assertFalse($property->isStatic());
            $values[$property->getName()] = $property->getValue($instance);
        }

        return $values;
    }

    private static function assertExpired(RequestHandlerInterface $continuation, ServerRequestInterface $request): void
    {
        $failure = self::failure(static fn () => $continuation->handle($request));
        self::assertSame(\LogicException::class, $failure::class);
        self::assertSame('http-pipeline-continuation-expired', $failure->getMessage());
        self::assertNull($failure->getPrevious());
    }

    private static function failure(\Closure $operation): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $failure) {
            return $failure;
        }

        self::fail('expected-repeated-http-failure');
    }

    /** @param array<string, mixed> $attributes */
    private function request(string $method, string $path, int $index, array $attributes = []): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn('example.test');
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getParsedBody')->willReturn(['invocation' => $index]);
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $method,
                $path,
                $index,
                [...$attributes, $name => $value],
            ),
        );

        return $request;
    }
}

final class RepeatedHttpAppRecorder
{
    public int $index = 0;

    /** @var list<string> */
    public array $events = [];

    /** @var list<array<mixed>> */
    public array $signals = [];

    /** @var list<RequestHandlerInterface> */
    public array $continuations = [];

    /** @var list<\WeakReference> */
    public array $downstream = [];

    /** @var list<\WeakReference> */
    public array $localMiddleware = [];

    public ?\WeakReference $action = null;
    public ?\WeakReference $argument = null;
    public ?\WeakReference $span = null;
    public ?\WeakReference $match = null;
    public ?\WeakReference $state = null;
    public ?\WeakReference $request = null;
    public ?\WeakReference $primary = null;
    public ?ResponseInterface $response = null;
}

class RepeatedHttpAppMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected readonly RepeatedHttpAppRecorder $record,
        private readonly string $id,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->record->events[] = $this->id;
        $state = RouteDispatchState::fromRequest($request);
        if ($this->id === 'global') {
            TestCase::assertSame($this->record->match?->get(), $state->match());
            TestCase::assertNotSame($this->record->state?->get(), $state);
            $this->record->state = \WeakReference::create($state);
            $this->record->request = \WeakReference::create($request);
        } else {
            TestCase::assertSame($this->record->state?->get(), $state);
        }
        $this->record->continuations[] = $handler;
        $next = new \ReflectionProperty($handler, 'next')->getValue($handler);
        TestCase::assertInstanceOf(RequestHandlerInterface::class, $next);
        $this->record->downstream[] = \WeakReference::create($next);
        unset($next);

        return $handler->handle($request);
    }
}

final class RepeatedHttpAppResettableMiddleware extends RepeatedHttpAppMiddleware implements ResetInterface
{
    private int $calls = 0;

    public function __construct(RepeatedHttpAppRecorder $record, private readonly string $resetId)
    {
        parent::__construct($record, $resetId);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        TestCase::assertSame(0, $this->calls);
        ++$this->calls;

        return parent::process($request, $handler);
    }

    public function reset(): void
    {
        $this->record->events[] = 'reset:' . $this->resetId . ':' . $this->calls;
        $this->calls = 0;
    }
}

final readonly class RepeatedHttpAppArgument
{
    public function __construct(public int $index)
    {
    }
}

final readonly class RepeatedHttpAppAction
{
    public function __construct(
        private RepeatedHttpAppRecorder $record,
        private string $id,
        private string $slug,
        private bool $throws,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        string $slug,
        RepeatedHttpAppArgument $argument,
    ): ResponseInterface {
        TestCase::assertSame($this, $this->record->action?->get());
        TestCase::assertSame($argument, $this->record->argument?->get());
        TestCase::assertSame($this->record->index, $argument->index);
        TestCase::assertSame($this->slug, $slug);
        TestCase::assertSame(['invocation' => $this->record->index], $request->getParsedBody());
        TestCase::assertSame($this->record->request?->get(), $request);
        TestCase::assertSame($this->record->state?->get(), RouteDispatchState::fromRequest($request));
        TestCase::assertSame($this->record->match?->get(), RouteDispatchState::fromRequest($request)->match());
        $this->record->events[] = 'action:' . $this->id . ':' . $slug;
        if ($this->throws) {
            $failure = new \RuntimeException('synthetic-action-failure');
            $this->record->primary = \WeakReference::create($failure);
            throw $failure;
        }
        $response = $this->record->response;
        TestCase::assertInstanceOf(ResponseInterface::class, $response);

        return $response;
    }
}

final class RepeatedHttpAppHook implements BeforeUowHookInterface, AfterUowHookInterface
{
    public int $begins = 0;
    public int $completions = 0;

    /** @var list<string> */
    public array $uowIds = [];

    public function __construct(private readonly RepeatedHttpAppRecorder $record)
    {
    }

    public function beforeUow(array $context): void
    {
        ++$this->begins;
        TestCase::assertSame('http', $context['type']);
        $this->uowIds[] = $context['uowId'];
        $this->record->events[] = 'before';
    }

    public function afterUow(array $context, array $result): void
    {
        ++$this->completions;
        TestCase::assertSame($this->uowIds[$this->completions - 1], $context['uowId']);
        $this->record->events[] = 'after:' . $result['outcome'];
    }
}

final readonly class RepeatedHttpAppSpan implements SpanInterface
{
    public function __construct(private RepeatedHttpAppRecorder $record)
    {
    }

    public function name(): string
    {
        return 'http.action';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->record->signals[] = ['attribute', $key, $value];
    }

    public function setAttributes(array $attributes): void
    {
        $this->record->signals[] = ['unexpected-attributes', $attributes];
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        $this->record->signals[] = ['unexpected-event', $name, $attributes];
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        $this->record->signals[] = ['unexpected-exception', $throwable, $attributes];
    }

    public function end(): void
    {
        $this->record->events[] = 'span:end';
    }
}
