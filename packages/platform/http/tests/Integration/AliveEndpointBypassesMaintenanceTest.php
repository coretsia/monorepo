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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class AliveEndpointBypassesMaintenanceTest extends TestCase
{
    private string $root;
    private MaintenanceStateStore $store;
    private array $events = [];
    private array $counters = [];
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $base = \realpath(\sys_get_temp_dir());
        self::assertIsString($base);
        $this->root = $base . '/coretsia-http-maintenance-alive-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
        $this->store = new MaintenanceStateStore(
            new RuntimePathContext($this->root, $this->root . '/var/cache/web'),
            new ScopedFileLock(),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            self::remove($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('paths')]
    public function testOnlyExactPathBypassesMaintenance(string $path, string $query, bool $bypass): void
    {
        $this->store->enable(null);
        $request = $this->request($path, $query);
        $downstream = $this->createStub(ResponseInterface::class);
        $downstream->method('getStatusCode')->willReturn(200);
        $served = $this->response(503);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects($bypass ? self::never() : self::once())->method('createResponse')->with(503)->willReturn(
            $served,
        );
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects($bypass ? self::once() : self::never())->method('handle')->with(self::identicalTo($request))
            ->willReturnCallback(function () use ($downstream): ResponseInterface {
                $this->events[] = 'downstream';
                return $downstream;
            });
        $result = $this->pipeline($this->middleware($factory), $terminal)->handle($request);
        self::assertSame($bypass ? $downstream : $served, $result);
        self::assertSame($bypass ? 200 : 503, $result->getStatusCode());
        self::assertSame(
            [['http.maintenance_total', 1, ['outcome' => $bypass ? 'bypassed' : 'served']]],
            $this->counters
        );
        self::assertSame($bypass ? ['bypassed', 'downstream'] : ['served'], $this->events);
        self::assertSame(
            $bypass ? [] : [['info', 'http.maintenance.served', ['outcome' => 'served', 'status' => 503]]],
            $this->logs,
        );
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function paths(): iterable
    {
        yield 'exact' => ['/_alive', '', true];
        yield 'query' => ['/_alive', 'probe=1', true];
        yield 'trailing-slash' => ['/_alive/', '', false];
        yield 'prefix' => ['/prefix/_alive', '', false];
        yield 'suffix' => ['/_alive-extra', '', false];
        yield 'case' => ['/_Alive', '', false];
        yield 'encoded-underscore' => ['/%5Falive', '', false];
        yield 'encoded-letter' => ['/_%61live', '', false];
        yield 'encoded-slash' => ['%2F_alive', '', false];
        yield 'debug' => ['/_debug/toolbar', '', false];
        yield 'debug-root' => ['/_debug', '', false];
    }

    #[DataProvider('bypassCases')]
    public function testBypassReturnsUnchangedResponseDespiteStateAndObservabilityFailures(
        string $state,
        bool $enabled,
        bool $failMetric,
        bool $failLog,
        int $status,
    ): void {
        $this->prepareState($state);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponse');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $request = $this->request('/_alive');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($request))
            ->willReturnCallback(function () use ($response): ResponseInterface {
                $this->events[] = 'downstream';
                return $response;
            });
        self::assertSame(
            $response,
            $this->pipeline(
                $this->middleware($factory, $enabled, $failMetric, $failLog),
                $terminal,
                $enabled,
            )->handle($request),
        );
        $active = $enabled && $state !== 'inactive';
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($active ? ['bypassed', 'downstream'] : ['downstream'], $this->events);
        self::assertSame($active ? [['http.maintenance_total', 1, ['outcome' => 'bypassed']]] : [], $this->counters);
        self::assertSame($active ? self::warning($state) : [], $this->logs);
    }

    /** @return iterable<string, array{string, bool, bool, bool, int}> */
    public static function bypassCases(): iterable
    {
        yield 'active-200' => ['active', true, false, false, 200];
        yield 'active-418' => ['active', true, false, false, 418];
        yield 'active-503' => ['active', true, false, false, 503];
        yield 'inactive' => ['inactive', true, false, false, 200];
        yield 'disabled-unavailable' => ['unavailable', false, false, false, 200];
        yield 'malformed' => ['invalid', true, false, false, 200];
        yield 'unavailable' => ['unavailable', true, false, false, 200];
        yield 'metric-failure' => ['active', true, true, false, 200];
        yield 'warning-failure' => ['invalid', true, false, true, 200];
        yield 'both-fail' => ['unavailable', true, true, true, 503];
    }

    #[DataProvider('throwableCases')]
    public function testBypassedDecisionPrecedesDownstreamThrowableAndCanonicalBoundary(
        ?int $boundaryStatus,
        bool $failMetric,
    ): void {
        $this->store->enable(null);
        $failure = new \RuntimeException('synthetic-liveness-failure');
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponse');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturnCallback(function () use ($failure): never {
            self::assertSame([['http.maintenance_total', 1, ['outcome' => 'bypassed']]], $this->counters);
            $this->events[] = 'downstream';
            throw $failure;
        });
        $boundary = null;
        $converted = $this->response($boundaryStatus ?? 500);
        if ($boundaryStatus !== null) {
            $errors = $this->createMock(ErrorHandlerInterface::class);
            $normalization = $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure));

            if ($boundaryStatus === 500) {
                $normalization->willThrowException(new \RuntimeException('synthetic-normalization-failure'));
            } else {
                $normalization->willReturn(
                    new ErrorDescriptor(
                        'SYNTHETIC_LIVENESS_FAILURE',
                        'Synthetic failure.',
                        httpStatus: $boundaryStatus,
                    ),
                );
            }

            $responses = $this->createMock(ResponseFactoryInterface::class);
            $responses->expects(self::once())->method('createResponse')->with($boundaryStatus)->willReturn($converted);
            $boundary = new HttpErrorHandlingMiddleware(
                $errors,
                new ErrorDescriptorToProblemDetailsAdapter(),
                new ProblemDetailsJsonRenderer(),
                $responses,
                $this->streams(),
            );
        }
        $pipeline = $this->pipeline($this->middleware($factory, true, $failMetric), $terminal, boundary: $boundary);
        if ($boundaryStatus !== null) {
            self::assertSame($converted, $pipeline->handle($this->request('/_alive')));
            self::assertSame($boundaryStatus, $converted->getStatusCode());
        } else {
            try {
                $pipeline->handle($this->request('/_alive'));
                self::fail('liveness-throwable-not-propagated');
            } catch (\Throwable $caught) {
                self::assertSame($failure, $caught);
            }
        }
        self::assertSame(['bypassed', 'downstream'], $this->events);
        self::assertSame([['http.maintenance_total', 1, ['outcome' => 'bypassed']]], $this->counters);
        self::assertSame([], $this->logs);
    }

    /** @return iterable<string, array{?int, bool}> */
    public static function throwableCases(): iterable
    {
        yield 'propagation' => [null, false];
        yield 'selected-boundary' => [422, false];
        yield 'selected-boundary-emergency' => [500, false];
        yield 'metric-failure' => [null, true];
        yield 'selected-boundary-metric-failure' => [422, true];
        yield 'selected-boundary-emergency-metric-failure' => [500, true];
    }

    public function testBypassedResponseStillTraversesPostDecoratorsAndFinalResponseProcessing(): void
    {
        $this->store->enable(null);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponse');
        $response = $this->createMock(ResponseInterface::class);
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $headers = [];
        $response->expects(self::exactly(5))->method('withHeader')->willReturnCallback(
            static function (string $name, mixed $value) use ($response, &$headers): ResponseInterface {
                $headers[$name] = $value;
                return $response;
            },
        );
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturnCallback(
            function () use ($response): ResponseInterface {
                $this->events[] = 'downstream';
                return $response;
            },
        );
        $pipeline = $this->pipeline($this->middleware($factory), $terminal, decorate: true);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $handle = new UnitOfWorkHandle(['attributes' => [], 'correlationId' => $id, 'type' => 'http', 'uowId' => $id]);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with(
            self::identicalTo($handle),
            'success',
            null,
        )->willReturn([]);
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn($id);
        $requestIds = new RequestIdPolicy(new ContextStore(), new UlidGenerator(), false, 'X-Request-Id', false, 128);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(true, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );
        self::assertSame($response, $kernel->handle($this->request('/_alive')));
        self::assertSame(['post:enter', 'bypassed', 'downstream', 'post:leave'], $this->events);
        self::assertSame([
            'X-Post' => 'yes',
            'X-Correlation-Id' => $id,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
        ], $headers);
        self::assertSame([['http.maintenance_total', 1, ['outcome' => 'bypassed']]], $this->counters);
        self::assertSame([], $this->logs);
    }

    private function prepareState(string $state): void
    {
        if ($state === 'inactive') {
            return;
        }
        if ($state === 'unavailable') {
            self::assertTrue(\mkdir($this->root . '/var/maintenance/enabled', 0700, true));
            return;
        }
        $this->store->enable(null);
        if ($state === 'invalid') {
            self::assertSame(1, \file_put_contents($this->root . '/var/maintenance/message.json', '{'));
        }
    }

    private static function warning(string $state): array
    {
        return match ($state) {
            'invalid' => [['warning', 'http.maintenance.message_invalid', ['reason' => 'invalid-message']]],
            'unavailable' => [['warning', 'http.maintenance.state_unavailable', ['reason' => 'state-unavailable']]],
            default => [],
        };
    }

    private function request(string $path, string $query = ''): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($query);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        return $request;
    }

    private function response(int $status): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withBody')->willReturnSelf();
        return $response;
    }

    private function streams(): StreamFactoryInterface
    {
        $streams = $this->createStub(StreamFactoryInterface::class);
        $streams->method('createStream')->willReturn($this->createStub(StreamInterface::class));
        return $streams;
    }

    private function middleware(
        ResponseFactoryInterface $factory,
        bool $enabled = true,
        bool $failMetric = false,
        bool $failLog = false,
    ): MaintenanceMiddleware {
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(
            function (string $name, int $delta, array $labels) use ($failMetric): void {
                $this->counters[] = [$name, $delta, $labels];
                $this->events[] = $labels['outcome'];
                if ($failMetric) {
                    throw new \RuntimeException('synthetic-metric-failure');
                }
            },
        );
        $logger = $this->createStub(LoggerInterface::class);
        foreach (['info', 'warning'] as $level) {
            $logger->method($level)->willReturnCallback(
                function (string $event, array $context) use ($level, $failLog): void {
                    $this->logs[] = [$level, $event, $context];
                    if ($failLog) {
                        throw new \RuntimeException('synthetic-log-failure');
                    }
                },
            );
        }
        return new MaintenanceMiddleware(
            $this->store,
            $factory,
            $this->streams(),
            $meter,
            $logger,
            $enabled,
            60,
            true,
            true,
        );
    }

    private function pipeline(
        MaintenanceMiddleware $middleware,
        RequestHandlerInterface $terminal,
        bool $enabled = true,
        ?HttpErrorHandlingMiddleware $boundary = null,
        bool $decorate = false,
    ): Pipeline {
        $http = require __DIR__ . '/../../config/http.php';
        $http['maintenance']['enabled'] = $enabled;
        $http['middleware']['system_pre'] = [MaintenanceMiddleware::class];
        $tags = new TagRegistry();
        $tags->add(
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            MaintenanceMiddleware::class,
            900,
            ['toggle' => 'http.maintenance.enabled'],
        );
        $services = [MaintenanceMiddleware::class => $middleware];
        if ($boundary !== null) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000);
            $services[HttpErrorHandlingMiddleware::class] = $boundary;
        }
        if ($decorate) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST, 'post');
            $post = $this->createStub(MiddlewareInterface::class);
            $post->method('process')->willReturnCallback(
                function (ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface {
                    $this->events[] = 'post:enter';
                    $result = $next->handle($request);
                    $this->events[] = 'post:leave';
                    return $result->withHeader('X-Post', 'yes');
                },
            );
            $services['post'] = $post;
        }
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static fn (string $id): MiddlewareInterface => $services[$id]);
        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http]),
            new HttpMiddlewareStackBuilder($tags),
            $terminal,
        );
    }

    private static function remove(string $path): void
    {
        if (\is_dir($path) && !\is_link($path)) {
            foreach (new \FilesystemIterator($path) as $entry) {
                self::remove($entry->getPathname());
            }
            \rmdir($path);
        } else {
            \unlink($path);
        }
    }
}
