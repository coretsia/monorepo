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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
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
use PHPUnit\Framework\Attributes\DataProvider;
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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class ActionDispatchMiddlewareObservabilityTest extends TestCase
{
    #[DataProvider('observedCases')]
    public function testAdmittedTerminalOwnsExactlyOneBoundedObservationLifecycle(
        string $method,
        string $boundedMethod,
        int $responseStatus,
        int|string $status,
        string $mode,
        bool $tracing,
        bool $metrics,
        bool $logging,
        string $fault,
    ): void {
        $ports = new ActionObservationPorts($fault);
        $events = $ports->events;
        $primary = new \RuntimeException('private-action-failure');
        $response = $this->createMock(ResponseInterface::class);
        $observing = $tracing || $metrics || $logging;
        $returns = \in_array($mode, ['returned', 'status-failure', 'html'], true);
        $statusRead = $response->expects(self::exactly($returns && $observing ? 1 : 0))->method('getStatusCode');
        if ($mode === 'status-failure') {
            $statusRead->willThrowException(new \Error('private-status-failure'));
        } else {
            $statusRead->willReturn($responseStatus);
        }
        $match = new RouteMatch(
            'private.route',
            '/items/{slug}',
            $mode === 'unmapped' ? 'private.unmapped' : 'private.action',
            ['slug' => 'private%2Fslug'],
            ['http_app_middleware' => ['private.middleware']],
        );
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with($method, '/items/private%2Fslug', 'private.test')
            ->willReturnCallback(static function () use ($events, $match): RouteMatch {
                $events->append('match');

                return $match;
            });
        $router->expects(self::never())->method('allowedMethods');
        $builder = $this->builder($ports, $router, $tracing, $metrics, $logging);
        $builder->factory('private.middleware', function () use ($events, $fault): MiddlewareInterface {
            $events->append('resolve-middleware');

            return new ActionObservationMiddleware(function (
                ServerRequestInterface $request,
                RequestHandlerInterface $next,
            ) use ($events, $fault): ResponseInterface {
                $events->append('local-before');
                try {
                    if ($fault === 'method-read') {
                        $request = $this->request(
                            $request->getMethod(),
                            $request->getUri()->getPath(),
                            $request->getAttributes(),
                            true,
                        );
                    }

                    return $next->handle($request);
                } finally {
                    $events->append('local-after');
                }
            });
        }, false);
        $builder->factory('private.service', static function () use ($events, $response, $primary, $mode): object {
            $events->append('resolve-action');
            if ($mode === 'construction') {
                throw $primary;
            }
            if ($mode === 'argument') {
                return new class($events) {
                    public function __construct(private readonly \ArrayObject $events)
                    {
                    }

                    public function __invoke(int $slug): never
                    {
                        $this->events->append('unexpected-action');
                        throw new \LogicException('argument-resolution-failed-open');
                    }
                };
            }

            return new ActionObservationAction($events, $response, $primary, $mode);
        }, false);
        $builder->factory(
            ActionObservationArgument::class,
            static function () use ($events): ActionObservationArgument {
                $events->append('resolve-argument');

                return new ActionObservationArgument();
            },
            false,
        );
        if ($mode === 'html') {
            $factory = $this->createMock(ResponseFactoryInterface::class);
            $initial = $this->createMock(ResponseInterface::class);
            $headed = $this->createMock(ResponseInterface::class);
            $stream = $this->createStub(StreamInterface::class);
            $streams = $this->createMock(StreamFactoryInterface::class);
            $factory->expects(self::once())->method('createResponse')->with(200)->willReturnCallback(
                static function () use ($events, $initial): ResponseInterface {
                    $events->append('map');

                    return $initial;
                },
            );
            $initial->expects(self::once())->method('withHeader')->with('Content-Type', 'text/html; charset=utf-8')
                ->willReturn($headed);
            $streams->expects(self::once())->method('createStream')->with('private-html-return')->willReturn($stream);
            $headed->expects(self::once())->method('withBody')->with(self::identicalTo($stream))->willReturn($response);
            $builder->instance(ResponseFactoryInterface::class, $factory);
            $builder->instance(StreamFactoryInterface::class, $streams);
        }
        $global = new ActionObservationMiddleware(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($events): ResponseInterface {
            $events->append('global-before');
            try {
                return $next->handle($request);
            } finally {
                $events->append('global-after');
            }
        });
        $container = $builder->register(new HttpAppServiceProvider())->build();
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline([
            $container->get(RouterMiddleware::class),
            $global,
            $container->get(ActionDispatchMiddleware::class),
        ], $terminal, true, [RouteDispatchState::ATTRIBUTE], true);
        $request = $this->request($method);
        if ($returns) {
            self::assertSame($response, $pipeline->handle($request));
        } else {
            $failure = self::failure(static fn () => $pipeline->handle($request));
            if ($mode === 'throw') {
                self::assertSame($primary, $failure);
            } else {
                self::assertInstanceOf(HttpAppDispatchException::class, $failure);
                self::assertSame(
                    match ($mode) {
                        'unmapped' => 'http-app-handler-unmapped',
                        'construction' => 'http-app-handler-unavailable',
                        'argument' => 'http-app-argument-invalid',
                        'unsupported' => 'http-app-response-unsupported',
                    },
                    $failure->reason(),
                );
                self::assertNull($failure->getPrevious());
            }
        }
        $outcome = $returns ? 'returned' : 'threw';
        self::assertSignals(
            $ports,
            $boundedMethod,
            $returns ? $status : 'unknown',
            $outcome,
            $tracing,
            $metrics,
            $logging,
            $fault,
        );
        $expected = ['match', 'global-before', 'resolve-middleware', 'local-before'];
        if ($tracing) {
            $expected[] = 'start';
        }
        if ($mode !== 'unmapped') {
            $expected[] = 'resolve-action';
        }
        if (!\in_array($mode, ['unmapped', 'construction', 'argument'], true)) {
            $expected[] = 'resolve-argument';
            $expected[] = 'action';
        }
        if ($mode === 'html') {
            $expected[] = 'map';
        }
        if ($tracing && $fault !== 'start') {
            \array_push($expected, 'outcome', 'status');
        }
        if ($metrics) {
            \array_push($expected, 'increment', 'observe');
        }
        if ($logging) {
            $expected[] = 'log';
        }
        if ($tracing && $fault !== 'start') {
            $expected[] = 'end';
        }
        \array_push($expected, 'local-after', 'global-after');
        self::assertSame($expected, $events->getArrayCopy());
    }

    /** @return iterable<string, array{string, string, int, int|string, string, bool, bool, bool, string}> */
    public static function observedCases(): iterable
    {
        foreach (['returned', 'throw'] as $mode) {
            foreach ([false, true] as $tracing) {
                foreach ([false, true] as $metrics) {
                    foreach ([false, true] as $logging) {
                        yield $mode . '-' . (int) $tracing . (int) $metrics . (int) $logging =>
                        ['GET', 'GET', 202, 202, $mode, $tracing, $metrics, $logging, 'none'];
                    }
                }
            }
            foreach (['start', 'outcome', 'status', 'end', 'increment', 'observe', 'log'] as $fault) {
                yield $mode . '-fault-' . $fault => ['GET', 'GET', 202, 202, $mode, true, true, true, $fault];
            }
            yield $mode . '-method-read-failure' => ['GET', 'OTHER', 202, 202, $mode, true, true, true, 'method-read'];
        }
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'] as $method) {
            yield 'method-' . $method => [$method, $method, 202, 202, 'returned', true, true, true, 'none'];
        }
        foreach (['get', 'PROPFIND', ' GET', 'private-method', ''] as $index => $method) {
            yield 'other-' . $index => [$method, 'OTHER', 202, 202, 'returned', true, true, true, 'none'];
        }
        foreach ([99, 100, 599, 600] as $status) {
            yield 'status-' . $status => [
                'GET',
                'GET',
                $status,
                $status < 100 || $status > 599 ? 'unknown' : $status,
                'returned',
                true,
                true,
                true,
                'none',
            ];
        }
        yield 'status-read-failure' => ['GET', 'GET', 202, 'unknown', 'status-failure', true, true, true, 'none'];
        yield 'html-mapping' => ['GET', 'GET', 200, 200, 'html', true, true, true, 'none'];
        foreach (['unmapped', 'construction', 'argument', 'unsupported'] as $mode) {
            yield $mode => ['GET', 'GET', 202, 'unknown', $mode, true, true, true, 'none'];
        }
    }

    #[DataProvider('nonAdmittedCases')]
    public function testBeforeTerminalAndTerminalValidationFailuresNeverEmitActionSignals(string $mode): void
    {
        $ports = new ActionObservationPorts('none');
        $events = $ports->events;
        $primary = new \RuntimeException('private-middleware-failure');
        $reserved = $mode === 'reserved';
        $miss = \in_array($mode, ['404', '405'], true);
        $path = $reserved ? '/ping' : '/items/private%2Fslug';
        $match = $reserved || $miss ? null : new RouteMatch(
            'private.route',
            '/items/{slug}',
            'private.action',
            ['slug' => 'private%2Fslug'],
            ['http_app_middleware' => [$mode === 'unadmitted' ? 'private.unadmitted' : 'private.middleware']],
        );
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with('GET', $path, 'private.test')->willReturn($match);
        $router->expects(self::exactly($miss ? 1 : 0))->method('allowedMethods')
            ->willReturn($mode === '405' ? ['POST'] : []);
        $response = $this->createStub(ResponseInterface::class);
        $builder = $this->builder($ports, $router, true, true, true);
        $builder->factory('private.service', static function () use ($events): never {
            $events->append('unexpected-action-resolution');
            throw new \LogicException('unexpected-action-resolution');
        });
        if ($mode !== 'missing-middleware') {
            $builder->factory(
                'private.middleware',
                function () use ($events, $mode, $response, $primary): MiddlewareInterface {
                    $events->append('resolve-middleware');
                    if ($mode === 'middleware-construction') {
                        throw $primary;
                    }

                    return new ActionObservationMiddleware(function (
                        ServerRequestInterface $request,
                        RequestHandlerInterface $next,
                    ) use ($events, $mode, $response, $primary): ResponseInterface {
                        $events->append('local');
                        if ($mode === 'local-short') {
                            return $response;
                        }
                        if ($mode === 'local-throw') {
                            throw $primary;
                        }
                        if ($mode === 'local-path') {
                            return $next->handle($this->request('GET', '/changed', $request->getAttributes()));
                        }
                        if ($mode === 'local-state') {
                            return $next->handle($request->withAttribute(RouteDispatchState::ATTRIBUTE, null));
                        }
                        // A deliberately inconsistent transport double isolates the final terminal check
                        // from the preceding continuation check. No production guard is bypassed or replaced.
                        $attributes = $request->getAttributes();
                        $equivalent = RouteDispatchState::matched(
                            $request,
                            RouteDispatchState::fromRequest($request)->match() ?? throw new \LogicException(
                                'expected-match',
                            ),
                        );
                        $uri = $request->getUri();
                        /** @var ServerRequestInterface&Stub $adapted */
                        $adapted = $this->createStub(ServerRequestInterface::class);
                        $reads = 0;
                        $adapted->method('getAttributes')->willReturnCallback(static function () use (
                            $attributes,
                            $equivalent,
                            $mode,
                            &$reads,
                        ): array {
                            ++$reads;

                            if ($reads > 1) {
                                if ($mode === 'terminal-state') {
                                    return [];
                                }
                                if ($mode === 'terminal-equivalent') {
                                    return [...$attributes, RouteDispatchState::ATTRIBUTE => $equivalent];
                                }
                            }

                            return $attributes;
                        });
                        $adapted->method('getUri')->willReturn($uri);
                        $methodReads = 0;
                        $adapted->method('getMethod')->willReturnCallback(
                            static function () use ($mode, &$methodReads): string {
                                ++$methodReads;

                                return $mode === 'terminal-input' && $methodReads > 1 ? 'POST' : 'GET';
                            },
                        );

                        return $next->handle($adapted);
                    });
                },
            );
        }
        $global = new ActionObservationMiddleware(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($mode, $response, $primary): ResponseInterface {
            return match ($mode) {
                'global-short' => $response,
                'global-throw' => throw $primary,
                'global-path' => $next->handle($this->request('GET', '/changed', $request->getAttributes())),
                'global-state' => $next->handle($request->withAttribute(RouteDispatchState::ATTRIBUTE, null)),
                default => $next->handle($request),
            };
        });
        $container = $builder->register(new HttpAppServiceProvider())->build();
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly($reserved ? 1 : 0))->method('handle')->willReturn($response);
        $pipeline = new Pipeline([
            $container->get(RouterMiddleware::class),
            $global,
            $container->get(ActionDispatchMiddleware::class),
        ], $terminal, true, [RouteDispatchState::ATTRIBUTE], true);
        $request = $this->request('GET', $path);
        if (\in_array($mode, ['global-short', 'local-short', 'reserved'], true)) {
            self::assertSame($response, $pipeline->handle($request));
        } else {
            $failure = self::failure(static fn () => $pipeline->handle($request));
            if (\in_array($mode, ['global-throw', 'local-throw'], true)) {
                self::assertSame($primary, $failure);
            } elseif ($mode === '404' || $mode === '405') {
                self::assertInstanceOf(
                    $mode === '404' ? HttpNotFoundException::class : HttpMethodNotAllowedException::class,
                    $failure,
                );
            } elseif (\in_array($mode, ['unadmitted', 'missing-middleware', 'middleware-construction'], true)) {
                self::assertInstanceOf(HttpAppDispatchException::class, $failure);
                self::assertSame(
                    $mode === 'unadmitted' ? 'http-app-route-middleware-invalid'
                        : 'http-app-route-middleware-unavailable',
                    $failure->reason(),
                );
                self::assertNull($failure->getPrevious());
            } elseif (\str_starts_with($mode, 'terminal-')) {
                self::assertInstanceOf(HttpAppDispatchException::class, $failure);
                self::assertSame(
                    $mode === 'terminal-input' ? 'http-app-route-input-changed' : 'http-app-route-state-invalid',
                    $failure->reason(),
                );
            } else {
                self::assertSame(\LogicException::class, $failure::class);
                self::assertSame(
                    \str_ends_with($mode, 'state') ? 'http-pipeline-protected-attribute-changed'
                        : 'http-pipeline-protected-routing-input-changed',
                    $failure->getMessage(),
                );
            }
        }
        self::assertSame([], $ports->calls);
        self::assertSame(
            \str_starts_with($mode, 'local-') || \str_starts_with($mode, 'terminal-')
                ? ['resolve-middleware', 'local'] : ($mode === 'middleware-construction' ? ['resolve-middleware'] : []),
            $events->getArrayCopy(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function nonAdmittedCases(): iterable
    {
        foreach (
            [
                'global-short',
                'global-throw',
                'global-path',
                'global-state',
                'local-short',
                'local-throw',
                'local-path',
                'local-state',
                'terminal-state',
                'terminal-equivalent',
                'terminal-input',
                'unadmitted',
                'missing-middleware',
                'middleware-construction',
                'reserved',
                '404',
                '405',
            ] as $mode
        ) {
            yield $mode => [$mode];
        }
    }

    #[DataProvider('repeatCases')]
    public function testRepeatedContinuationDoesNotRewriteTheCompletedObservation(bool $local, bool $throws): void
    {
        $ports = new ActionObservationPorts('none');
        $events = $ports->events;
        $primary = new \RuntimeException('private-action-failure');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $match = new RouteMatch(
            'private.route',
            '/items/{slug}',
            'private.action',
            ['slug' => 'private%2Fslug'],
            ['http_app_middleware' => $local ? ['private.middleware'] : []],
        );
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->willReturn($match);
        $router->expects(self::never())->method('allowedMethods');
        $builder = $this->builder($ports, $router, true, true, true);
        $builder->factory('private.service', static function () use ($events, $response, $primary, $throws): object {
            $events->append('resolve-action');

            return new ActionObservationAction($events, $response, $primary, $throws ? 'throw' : 'returned');
        }, false);
        $builder->instance(ActionObservationArgument::class, new ActionObservationArgument());
        $retry = new ActionObservationMiddleware(static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ) use ($ports, $primary, $throws, $response): ResponseInterface {
            if ($throws) {
                self::assertSame($primary, self::failure(static fn () => $next->handle($request)));
            } else {
                self::assertSame($response, $next->handle($request));
            }
            $completed = $ports->calls;
            try {
                return $next->handle($request);
            } finally {
                self::assertSame($completed, $ports->calls);
            }
        });
        $builder->instance('private.middleware', $retry);
        $container = $builder->register(new HttpAppServiceProvider())->build();
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $middleware = [$container->get(RouterMiddleware::class)];
        if (!$local) {
            $middleware[] = $retry;
        }
        $middleware[] = $container->get(ActionDispatchMiddleware::class);
        $pipeline = new Pipeline($middleware, $terminal, true, [RouteDispatchState::ATTRIBUTE], true);
        $failure = self::failure(fn () => $pipeline->handle($this->request('GET')));
        self::assertSame(\LogicException::class, $failure::class);
        self::assertSame('http-pipeline-continuation-reused', $failure->getMessage());
        self::assertSignals(
            $ports,
            'GET',
            $throws ? 'unknown' : 201,
            $throws ? 'threw' : 'returned',
            true,
            true,
            true,
            'none',
        );
        self::assertSame(1, \count(\array_keys($events->getArrayCopy(), 'resolve-action', true)));
        self::assertSame(1, \count(\array_keys($events->getArrayCopy(), 'action', true)));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function repeatCases(): iterable
    {
        foreach ([false, true] as $local) {
            foreach ([false, true] as $throws) {
                yield ($local ? 'local' : 'global') . ($throws ? '-threw' : '-returned') => [$local, $throws];
            }
        }
    }

    public function testFinalStopwatchAndIndependentCounterAreStructuralContracts(): void
    {
        self::assertTrue(new \ReflectionClass(Stopwatch::class)->isFinal());
        $parameter = new \ReflectionMethod(ActionDispatchMiddleware::class, '__construct')->getParameters()[7];
        self::assertSame(Stopwatch::class, (string) $parameter->getType());
        self::assertFalse($parameter->isOptional());
        self::assertSame(
            4_294_967_295,
            new \ReflectionClass(ActionDispatchMiddleware::class)->getConstant('MAX_DURATION_MS'),
        );
        self::assertMatchesRegularExpression(
            '~if\s*\(\s*!\$this->metricsEnabled\s*\)\s*\{\s*return\s+null\s*;\s*}\s*'
            . 'try\s*\{\s*\$startedAt\s*=\s*\$this->stopwatch->start\(\)\s*;\s*'
            . 'return\s+\$startedAt\s*>\s*0\s*\?\s*\$startedAt\s*:\s*null\s*;\s*}'
            . '\s*catch\s*\(\\\\Throwable\)\s*\{\s*return\s+null\s*;\s*}~',
            self::methodCode('startDuration'),
        );
        self::assertMatchesRegularExpression(
            '~if\s*\(\$startedAt\s*===\s*null\)\s*\{\s*return\s+null\s*;\s*}\s*'
            . 'try\s*\{\s*\$durationMs\s*=\s*\$this->stopwatch->stop\(\$startedAt\)\s*;\s*'
            . 'return\s+\$durationMs\s*>=\s*0\s*&&\s*\$durationMs\s*<=\s*self::MAX_DURATION_MS\s*'
            . '\?\s*\$durationMs\s*:\s*null\s*;\s*}\s*catch\s*\(\\\\Throwable\)\s*\{\s*return\s+null\s*;\s*}~',
            self::methodCode('stopDuration'),
        );
        self::assertMatchesRegularExpression(
            '~if\s*\(\$this->metricsEnabled\)\s*\{\s*try\s*\{\s*'
            . '\$this->meter->increment\(self::METRIC_TOTAL,\s*1,\s*\$labels\)\s*;\s*}'
            . '\s*catch\s*\(\\\\Throwable\)\s*\{\s*}\s*if\s*\(\$durationMs\s*!==\s*null\)~',
            self::methodCode('complete'),
        );
        $dispatch = self::methodCode('dispatchAction');
        self::assertMatchesRegularExpression(
            '~\$startedAt\s*=\s*\$this->startDuration\(\)\s*;\s*\$response\s*=\s*null\s*;\s*'
            . '\$outcome\s*=\s*\'threw\'\s*;\s*try\s*\{\s*'
            . '\$response\s*=\s*\$this->responseMapper->map\(\$this->invoker->dispatch\(\$match,\s*\$request\)\)\s*;\s*'
            . '\$outcome\s*=\s*\'returned\'\s*;\s*return\s+\$response\s*;\s*}\s*finally\s*\{\s*try\s*\{\s*'
            . '\$durationMs\s*=\s*\$this->stopDuration\(\$startedAt\)\s*;\s*if\s*\(\$observing\)\s*\{\s*'
            . '\$status\s*=\s*\$this->observeStatus\(\$response\)\s*;~',
            $dispatch,
        );
        self::assertStringContainsString('$this->complete($span, $method, $status, $outcome, $durationMs);', $dispatch);
        $source = \file_get_contents(new \ReflectionClass(ActionDispatchMiddleware::class)->getFileName());
        self::assertIsString($source);
        self::assertSame(1, \substr_count($source, '->stopwatch->start('));
        self::assertSame(1, \substr_count($source, '->stopwatch->stop('));
        self::assertStringNotContainsString('new Stopwatch(', $source);
    }

    public function testUnavailableDurationDoesNotSuppressTheCounterOrLog(): void
    {
        $ports = new ActionObservationPorts('none');
        $router = $this->createStub(RouterInterface::class);
        $dispatcher = $this->builder($ports, $router, true, true, true)->register(new HttpAppServiceProvider())
            ->build()->get(ActionDispatchMiddleware::class);
        self::assertInstanceOf(ActionDispatchMiddleware::class, $dispatcher);
        // The real final Stopwatch is never substituted. Exercise the existing completion branch
        // with the unavailable-duration result of either isolated Stopwatch failure.
        $stop = new \ReflectionMethod(ActionDispatchMiddleware::class, 'stopDuration');
        self::assertNull($stop->invoke($dispatcher, null));
        self::assertNull($stop->invoke($dispatcher, 0));
        new \ReflectionMethod(ActionDispatchMiddleware::class, 'complete')->invoke(
            $dispatcher,
            $ports,
            'GET',
            'unknown',
            'threw',
            null,
        );
        $labels = ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'threw'];
        self::assertSame([
            ['attribute', 'outcome', 'threw'],
            ['attribute', 'status', 'unknown'],
            ['increment', 'http.action_total', 1, $labels],
            ['log', 'debug', 'http.action.completed', $labels],
        ], $ports->calls);
    }

    private static function assertSignals(
        ActionObservationPorts $ports,
        string $method,
        int|string $status,
        string $outcome,
        bool $tracing,
        bool $metrics,
        bool $logging,
        string $fault,
    ): void {
        $labels = ['method' => $method, 'status' => $status, 'outcome' => $outcome];
        $expected = [];
        if ($tracing) {
            $expected[] = ['start', 'http.action', ['method' => $method]];
            if ($fault !== 'start') {
                $expected[] = ['attribute', 'outcome', $outcome];
                $expected[] = ['attribute', 'status', $status];
            }
        }
        if ($metrics) {
            $expected[] = ['increment', 'http.action_total', 1, $labels];
            $duration = $ports->calls[\count($expected)][2] ?? null;
            self::assertIsInt($duration);
            self::assertGreaterThanOrEqual(0, $duration);
            self::assertLessThanOrEqual(4_294_967_295, $duration);
            $expected[] = ['observe', 'http.action_duration_ms', $duration, $labels];
        }
        if ($logging) {
            $expected[] = ['log', 'debug', 'http.action.completed', $labels];
        }
        if ($tracing && $fault !== 'start') {
            $expected[] = ['end'];
        }
        self::assertSame($expected, $ports->calls);
        foreach (
            [
                'private-action',
                'private.action',
                'private.service',
                'private.route',
                'private%2Fslug',
                'private.test',
                'private-html-return',
                'private-argument',
                'private-header',
                'private-query',
                'private-body',
                'private-cookie',
                'private-method',
            ] as $secret
        ) {
            self::assertStringNotContainsString($secret, \json_encode($ports->calls, \JSON_THROW_ON_ERROR));
        }
    }

    private function builder(
        ActionObservationPorts $ports,
        RouterInterface $router,
        bool $tracing,
        bool $metrics,
        bool $logging,
    ): ContainerBuilder {
        $app = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $app['handlers'] = [['action_id' => 'private.action', 'service_id' => 'private.service']];
        $app['arguments']['allow_container_types'] = [ActionObservationArgument::class];
        $app['route_middleware'] = ['private.middleware'];
        $app['tracing']['enabled'] = $tracing;
        $app['metrics']['enabled'] = $metrics;
        $app['logging']['enabled'] = $logging;
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http_app' => $app,
        ];
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $builder->instance(RouterInterface::class, $router);
        $builder->instance(ResponseFactoryInterface::class, $this->createStub(ResponseFactoryInterface::class));
        $builder->instance(StreamFactoryInterface::class, $this->createStub(StreamFactoryInterface::class));
        $builder->instance(TracerPortInterface::class, $ports);
        $builder->instance(MeterPortInterface::class, $ports);
        $builder->instance(LoggerInterface::class, $ports);
        $builder->instance(Stopwatch::class, new Stopwatch());

        return $builder;
    }

    /** @param array<string, mixed> $attributes */
    private function request(
        string $method,
        string $path = '/items/private%2Fslug',
        array $attributes = [],
        bool $failMethodObservation = false,
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn('private.test');
        $uri->method('getQuery')->willReturn('token=private-query');
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        if ($failMethodObservation) {
            $methodReads = 0;
            $request->method('getMethod')->willReturnCallback(
                static function () use ($method, &$methodReads): string {
                    // The continuation and terminal checks succeed before observation reads the method.
                    if (++$methodReads > 2) {
                        throw new \Error('private-method-read-failure');
                    }

                    return $method;
                },
            );
        } else {
            $request->method('getMethod')->willReturn($method);
        }
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getHeaders')->willReturn(['Authorization' => ['private-header']]);
        $request->method('getQueryParams')->willReturn(['token' => 'private-query']);
        $request->method('getCookieParams')->willReturn(['session' => 'private-cookie']);
        $request->method('getParsedBody')->willReturn(['value' => 'private-body']);
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $method,
                $path,
                [...$attributes, $name => $value],
            ),
        );

        return $request;
    }

    private static function methodCode(string $name): string
    {
        $method = new \ReflectionMethod(ActionDispatchMiddleware::class, $name);
        $file = $method->getFileName();
        self::assertIsString($file);
        $lines = \file($file);
        self::assertIsArray($lines);
        $body = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );
        $code = '';
        foreach (\token_get_all('<?php ' . $body) as $token) {
            if (\is_array($token)) {
                if (!\in_array($token[0], [\T_OPEN_TAG, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    private static function failure(\Closure $operation): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $failure) {
            return $failure;
        }

        self::fail('expected-action-observation-failure');
    }
}

final readonly class ActionObservationMiddleware implements MiddlewareInterface
{
    /** @param \Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $operation */
    public function __construct(private \Closure $operation)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->operation)($request, $handler);
    }
}

final class ActionObservationArgument
{
    public string $value = 'private-argument';
}

final readonly class ActionObservationAction
{
    public function __construct(
        private \ArrayObject $events,
        private ResponseInterface $response,
        private \Throwable $primary,
        private string $mode,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, string $slug, ActionObservationArgument $argument): mixed
    {
        TestCase::assertSame('private%2Fslug', $slug);
        TestCase::assertSame('private-argument', $argument->value);
        TestCase::assertSame(['value' => 'private-body'], $request->getParsedBody());
        $this->events->append('action');

        return match ($this->mode) {
            'throw' => throw $this->primary,
            'unsupported' => 1.5,
            'html' => 'private-html-return',
            default => $this->response,
        };
    }
}

final class ActionObservationPorts extends AbstractLogger implements
    TracerPortInterface,
    SpanInterface,
    MeterPortInterface
{
    /** @var list<array<mixed>> */
    public array $calls = [];

    public readonly \ArrayObject $events;

    public function __construct(private readonly string $fault)
    {
        $this->events = new \ArrayObject();
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $this->record('start', ['start', $name, $attributes]);

        return $this;
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        $this->record('unexpected-in-span', ['in-span', $name, $attributes]);
        throw new \LogicException('unexpected-in-span');
    }

    public function currentSpan(): ?SpanInterface
    {
        $this->record('unexpected-current-span', ['current-span']);

        return null;
    }

    public function name(): string
    {
        $this->record('unexpected-name', ['name']);

        return 'http.action';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->record($key, ['attribute', $key, $value]);
    }

    public function setAttributes(array $attributes): void
    {
        $this->record('unexpected-attributes', ['attributes', $attributes]);
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        $this->record('unexpected-event', ['event', $name, $attributes]);
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        $this->record('unexpected-exception', ['exception', $throwable, $attributes]);
    }

    public function end(): void
    {
        $this->record('end', ['end']);
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->record('increment', ['increment', $name, $delta, $labels]);
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->record('observe', ['observe', $name, $value, $labels]);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->record('log', ['log', $level, (string) $message, $context]);
    }

    /** @param array<mixed> $call */
    private function record(string $stage, array $call): void
    {
        $this->calls[] = $call;
        $this->events->append($stage);
        if ($this->fault === $stage) {
            throw new \Error('private-observation-failure');
        }
    }
}
