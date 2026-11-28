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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class ReservedEndpointsRemainOwnedByHttpTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProvider('endpointConfigurations')]
    public function testReservedEndpointResponsesRemainIdenticalToTheHttpOwnedBoundary(
        string $mode,
        string $environment,
        bool $debugEnabled,
    ): void {
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            fn (string $class): object => $this->createStub($class),
            [
                'appEnv' => $environment,
                'problemDetails' => true,
                'reporter' => true,
                'http' => [
                    'debug' => [
                        'endpoints' => ['enabled' => $debugEnabled],
                        'token_sha256' => \hash('sha256', self::TOKEN),
                        'config' => ['allowlist' => ['kernel']],
                    ],
                ],
            ],
        );
        $root = RoutingIntegrationFixture::root();
        try {
            // Use a non-empty artifact with actual application neighbours of reserved paths.
            $routes = [];
            foreach (['/ping-other', '/healthz', '/_debugger', '/metrics-extra'] as $index => $path) {
                $routes[] = new RouteDefinition('neighbour' . $index, ['GET'], $path, 'app.action');
            }
            self::assertTrue(
                RoutingIntegrationFixture::writer($root)->write(
                    RoutingIntegrationFixture::schema()->createEnvelope($routes, 'web', 'local'),
                ),
            );
            $real = new CompiledRouter(
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
            $cases = self::cases($environment === 'local' && $debugEnabled);
            $router = $this->createMock(RouterInterface::class);
            $matchInputs = new \ArrayObject();
            $router->expects(self::exactly(\count($cases) + 4))->method('match')->willReturnCallback(
                static function (string $method, string $path, ?string $host) use ($real, $matchInputs): ?RouteMatch {
                    $matchInputs->append([$method, $path, $host]);

                    return $real->match($method, $path, $host);
                },
            );
            $router->expects(self::never())->method('allowedMethods');
            $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
            $app['handlers'] = [['action_id' => 'app.action', 'service_id' => 'app.service']];
            $app['route_middleware'] = ['app.middleware'];
            $config = $fixture->container->get(ConfigRepositoryInterface::class)->all();
            $config['http_app'] = $app;
            // The existing HTTP fixture owns the terminal and error services. Compose the real
            // HttpApp contributions around those same services, without replacing their wiring.
            $config['http']['middleware']['app'] = [];
            $config['http']['middleware']['route'] = [];
            $builder = new ContainerBuilder($config);
            $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
            $builder->instance(RouterInterface::class, $router);
            $builder->instance(ResponseFactoryInterface::class, $fixture->factories());
            $builder->instance(StreamFactoryInterface::class, $fixture->factories());
            $tracer = $this->createMock(TracerPortInterface::class);
            $meter = $this->createMock(MeterPortInterface::class);
            $logger = $this->createMock(LoggerInterface::class);
            $tracer->expects(self::exactly(4))->method('startSpan')->with('http.action', ['method' => 'GET'])
                ->willReturnCallback(static fn () => new NoopSpan());
            $meter->expects(self::exactly(4))->method('increment')->with('http.action_total', 1);
            $meter->expects(self::exactly(4))->method('observe')->with('http.action_duration_ms');
            $logger->expects(self::exactly(4))->method('debug')->with('http.action.completed');
            $builder->instance(TracerPortInterface::class, $tracer);
            $builder->instance(MeterPortInterface::class, $meter);
            $builder->instance(LoggerInterface::class, $logger);
            $builder->instance(Stopwatch::class, new Stopwatch());
            $events = new \ArrayObject();
            $builder->factory('app.service', static function () use ($events): object {
                $events->append('resolve-action');

                return new class($events) {
                    public function __construct(private readonly \ArrayObject $events)
                    {
                    }

                    public function __invoke(): string
                    {
                        $this->events->append('action');

                        return 'application-response';
                    }
                };
            }, false);
            $builder->factory('app.middleware', static function () use ($events): never {
                $events->append('unexpected-route-middleware');
                throw new \LogicException('reserved-route-middleware-resolved');
            });
            $handoffs = new \ArrayObject();
            $builder->instance(
                'handoff.probe',
                new class($handoffs) implements MiddlewareInterface {
                    public function __construct(private readonly \ArrayObject $handoffs)
                    {
                    }

                    public function process(
                        ServerRequestInterface $request,
                        RequestHandlerInterface $handler,
                    ): ResponseInterface {
                        $state = RouteDispatchState::fromRequest($request);
                        $state->assertRequest($request);
                        $this->handoffs->append([
                            $request->getMethod(),
                            $request->getUri()->getPath(),
                            $state->match()?->handler(),
                        ]);

                        return $handler->handle($request);
                    }
                },
            )->tag(ReservedTags::HTTP_MIDDLEWARE_ROUTE, 'handoff.probe', -2000);
            $boundary = $fixture->container->get(HttpErrorHandlingMiddleware::class);
            $terminal = $fixture->container->get(RequestHandlerInterface::class);
            self::assertInstanceOf(HttpErrorHandlingMiddleware::class, $boundary);
            self::assertInstanceOf(RequestHandlerInterface::class, $terminal);
            $builder->instance(HttpErrorHandlingMiddleware::class, $boundary)
                ->tag(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000);
            $builder->register(new HttpAppServiceProvider());
            $container = $builder->build();
            $basePlan = $fixture->container->get(ModulePlan::class);
            self::assertInstanceOf(ModulePlan::class, $basePlan);
            $routingId = ModuleId::fromString('platform.routing');
            $appId = ModuleId::fromString('platform.http-app');
            $plan = new ModulePlan(
                'web',
                [...$basePlan->enabled(), $routingId, $appId],
                [],
                [...$basePlan->topologicalOrder(), $routingId, $appId],
                [
                    ...\array_values($basePlan->modules()),
                    new ModulePlanEntry($routingId, 'coretsia/platform-routing', [
                        ModuleId::fromString('core.foundation'),
                        ModuleId::fromString('core.kernel'),
                        ModuleId::fromString('platform.redaction'),
                    ]),
                    new ModulePlanEntry($appId, 'coretsia/platform-http-app', \array_map(ModuleId::fromString(...), [
                        'core.foundation',
                        'platform.errors',
                        'platform.http',
                        'platform.problem-details',
                        'platform.routing',
                    ])),
                ]
            );
            $pipeline = HttpServiceFactory::pipeline(
                $container,
                new ArrayConfigRepository($config, []),
                new HttpMiddlewareStackBuilder($builder->tagRegistry()),
                $terminal,
                $plan,
            );
            $expectedHandoffs = [];
            $expectedMatches = [];
            foreach ($cases as [$method, $path, $query, $authorized, $status, $body, $contentType]) {
                $headers = $authorized ? ['X-Debug-Token' => [self::TOKEN]] : [];
                $reportsBefore = \count($fixture->reporter()->reports);
                $decisionsBefore = \count($fixture->recordingMeter()->increments);
                $baseline = $boundary->process(
                    $fixture->request($method, $path, $query, $headers, serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                    $terminal,
                );
                $baselineDecisions = \array_slice($fixture->recordingMeter()->increments, $decisionsBefore);
                $decisionsAfterBaseline = \count($fixture->recordingMeter()->increments);
                $response = $pipeline->handle(
                    $fixture->request($method, $path, $query, $headers, serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                );
                self::assertSame($status, $response->getStatusCode(), $method . ' ' . $path);
                self::assertSame($baseline->getStatusCode(), $response->getStatusCode());
                self::assertSame($baseline->getHeaders(), $response->getHeaders());
                self::assertSame((string) $baseline->getBody(), (string) $response->getBody());
                if ($body !== null) {
                    self::assertSame($body, (string) $response->getBody());
                }
                if ($contentType !== null) {
                    self::assertSame($contentType, $response->getHeaderLine('Content-Type'));
                }
                self::assertFalse($response->hasHeader('Allow'));
                self::assertSame(
                    $baselineDecisions,
                    \array_slice($fixture->recordingMeter()->increments, $decisionsAfterBaseline),
                );
                $reports = \array_slice($fixture->reporter()->reports, $reportsBefore);
                if ($status === 500 || $status === 403) {
                    self::assertCount(2, $reports);
                    foreach ($reports as [$descriptor]) {
                        self::assertSame(
                            $status === 500 ? 'CORETSIA_HTTP_INTENTIONAL_ERROR' : 'CORETSIA_HTTP_DEBUG_FORBIDDEN',
                            $descriptor->code(),
                        );
                        self::assertSame($status, $descriptor->httpStatus());
                    }
                } else {
                    self::assertSame([], $reports);
                }
                if ($status === 404) {
                    self::assertSame([], $response->getHeaders());
                }
                self::assertSame([], $events->getArrayCopy());
                $expectedHandoffs[] = [$method, $path, null];
                $expectedMatches[] = [$method, $path, 'example.test'];
                self::assertSame($expectedHandoffs, $handoffs->getArrayCopy());
            }
            foreach (['/ping-other', '/healthz', '/_debugger', '/metrics-extra'] as $path) {
                $response = $pipeline->handle($fixture->request('GET', $path));
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application-response', (string) $response->getBody());
                $expectedHandoffs[] = ['GET', $path, 'app.action'];
                $expectedMatches[] = ['GET', $path, 'example.test'];
            }
            self::assertSame($expectedHandoffs, $handoffs->getArrayCopy());
            self::assertSame($expectedMatches, $matchInputs->getArrayCopy());
            self::assertSame(\array_merge(...\array_fill(0, 4, ['resolve-action', 'action'])), $events->getArrayCopy());
        } finally {
            RoutingIntegrationFixture::remove($root);
            $fixture->close();
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function endpointConfigurations(): iterable
    {
        foreach (['source', 'compiled'] as $mode) {
            yield $mode . '-debug-enabled' => [$mode, 'local', true];
            yield $mode . '-debug-disabled' => [$mode, 'local', false];
            yield $mode . '-production' => [$mode, 'prod', true];
        }
    }

    /** @return list<array{string, string, string, bool, int, ?string, ?string}> */
    private static function cases(bool $debug): array
    {
        $cases = [
            ['GET', '/_alive', '', true, 200, 'ALIVE', 'text/plain; charset=utf-8'],
            ['GET', '/ping', '', true, 200, '{"ok":true,"ts":1700000000}' . "\n", 'application/json'],
            [
                'GET',
                '/error',
                '',
                true,
                500,
                '{"status":500,"title":"Internal Server Error","type":"about:blank"}' . "\n",
                'application/problem+json',
            ],
            ['GET', '/health', '', true, 501, '{"error":"owner missing"}' . "\n", 'application/json'],
            ['GET', '/health/', '', true, 501, '{"error":"owner missing"}' . "\n", 'application/json'],
            ['GET', '/health/ready', '', true, 501, '{"error":"owner missing"}' . "\n", 'application/json'],
            ['GET', '/metrics', '', true, 501, "owner missing\n", 'text/plain; charset=utf-8'],
        ];
        foreach (['headers', 'modules', 'config', 'cache'] as $operation) {
            $query = $operation === 'config' ? 'key=kernel.boot.default_env' : '';
            $cases[] = [
                'GET',
                '/_debug/' . $operation,
                $query,
                true,
                $debug ? 200 : 404,
                $debug ? null : '',
                $debug ? 'application/json' : '',
            ];
            $cases[] = [
                'GET',
                '/_debug/' . $operation,
                $query,
                false,
                $debug ? 403 : 404,
                $debug ? '{"status":403,"title":"Forbidden","type":"about:blank"}' . "\n" : '',
                $debug ? 'application/problem+json' : '',
            ];
        }
        foreach (['/_debug', '/_debug/', '/_debug/unknown', '/_debug/cache/'] as $path) {
            $cases[] = ['GET', $path, '', true, 404, '', ''];
        }
        foreach (
            [
                'HEAD',
                'POST',
                'PUT',
                'PATCH',
                'DELETE',
                'OPTIONS',
                'TRACE',
                'CONNECT',
                'get',
                'PROPFIND',
            ] as $method
        ) {
            foreach (['/_alive', '/ping', '/error', '/health', '/health/ready', '/metrics', '/_debug/cache'] as $path) {
                $cases[] = [$method, $path, '', true, 404, '', ''];
            }
        }

        return $cases;
    }

    #[DataProvider('reservedTemplates')]
    public function testApplicationArtifactCannotClaimReservedDomains(string $path): void
    {
        try {
            RoutingIntegrationFixture::schema()->createEnvelope([
                new RouteDefinition('forbidden', ['POST'], $path, 'app.action'),
            ], 'web', 'local');
        } catch (ReservedRouteViolationException $failure) {
            self::assertSame('routing-reserved-route-forbidden', $failure->reason());
            self::assertNull($failure->getPrevious());

            return;
        }
        self::fail('reserved-application-route-admitted');
    }

    /** @return iterable<string, array{string}> */
    public static function reservedTemplates(): iterable
    {
        foreach (
            [
                '/_alive',
                '/ping',
                '/error',
                '/health',
                '/health/{probe}',
                '/metrics',
                '/_debug',
                '/_debug/{operation}',
                '/{endpoint}',
                '/{namespace}/{operation}',
            ] as $path
        ) {
            yield $path => [$path];
        }
    }
}
