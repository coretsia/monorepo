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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware;
use Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Middleware\TraceContextMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\Http\Tests\Support\FilesystemPermissionTestSupport;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class MaintenanceEnabledReturns503ForNonBypassedRoutesTest extends TestCase
{
    private const string ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private string $root;
    private ?string $restrictedMessagePath = null;
    private MaintenanceStateStore $store;
    private array $headers = [];
    private array $counters = [];
    private array $observations = [];
    private array $logs = [];
    private array $events = [];
    private array $completion = [];
    private array $contextKeys = [];
    private array $resetChecks = [];
    private int $contextComparisons = 0;
    private bool $allowDownstream = false;
    private ContextStore $context;

    protected function setUp(): void
    {
        parent::setUp();
        $base = \realpath(\sys_get_temp_dir());
        self::assertIsString($base);
        $this->root = $base . '/coretsia-http-maintenance-response-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
        $this->store = new MaintenanceStateStore(
            new RuntimePathContext($this->root, $this->root . '/var/cache/web'),
            new ScopedFileLock(),
        );
        $this->context = new ContextStore();
    }

    protected function tearDown(): void
    {
        if ($this->restrictedMessagePath !== null) {
            FilesystemPermissionTestSupport::restore($this->restrictedMessagePath, 0600);
        }
        if (isset($this->root)) {
            self::remove($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('responseCases')]
    public function testActiveStateReturnsExactFinalizedResponse(
        string $method,
        string $state,
        int $retry,
        string $body,
        ?string $warning,
    ): void {
        $this->prepareState($state);
        $response = $this->response(503);
        $kernel = $this->kernel(
            $this->pipeline(
                $this->middleware($this->factory($response)),
                realRedirect: true,
            ),
        );
        self::assertSame($response, $kernel->handle($this->request($method)));
        self::assertSame(503, $response->getStatusCode());
        self::assertSame([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => (string) $retry,
            'X-Route-Post' => 'yes',
            'X-App-Post' => 'yes',
            'X-System-Post' => 'yes',
            'X-Correlation-Id' => self::ID,
            'X-Request-Id' => self::ID,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'geolocation=()',
            'Content-Security-Policy' => "default-src 'none'",
        ], $this->headers);
        self::assertSame($body, (string) $response->getBody());
        self::assertSame([
            ['http.maintenance_total', 1, ['outcome' => 'served']],
            [
                'http.request_total',
                1,
                ['method' => \strtoupper($method), 'status' => 503, 'outcome' => 'handled_error'],
            ],
        ], $this->counters);
        $expected = [];
        if ($warning !== null) {
            $expected[] = $warning === 'invalid-message'
                ? ['warning', 'http.maintenance.message_invalid', ['reason' => $warning]]
                : ['warning', 'http.maintenance.state_unavailable', ['reason' => $warning]];
        }
        $expected[] = ['info', 'http.maintenance.served', ['outcome' => 'served', 'status' => 503]];
        self::assertSame($expected, $this->maintenanceLogs());
        self::assertSame([
            'system:enter',
            'app:enter',
            'route:enter',
            'response',
            'body',
            'served',
            'route:leave',
            'app:leave',
            'system:leave',
            'after',
            'reset',
        ], $this->events);
        $this->assertCompletion('handled_error', 503);
    }

    /** @return iterable<string, array{string, string, int, string, ?string}> */
    public static function responseCases(): iterable
    {
        yield 'absent' => ['GET', 'absent', 120, "Service Unavailable\n", null];
        yield 'message-default' => ['POST', 'message', 120, "Scheduled café\n", null];
        yield 'override' => ['GET', 'override', 17, "Scheduled café\n", null];
        yield 'head' => ['HEAD', 'override', 17, '', null];
        yield 'head-generic' => ['HEAD', 'absent', 120, '', null];
        yield 'lowercase-head' => ['head', 'override', 17, "Scheduled café\n", null];
        yield 'malformed' => ['GET', 'malformed', 120, "Service Unavailable\n", 'invalid-message'];
        yield 'oversized' => ['GET', 'oversized', 120, "Service Unavailable\n", 'invalid-message'];
        yield 'unreadable' => ['GET', 'unreadable', 120, "Service Unavailable\n", 'invalid-message'];
        yield 'unavailable' => ['GET', 'unavailable', 120, "Service Unavailable\n", 'state-unavailable'];
    }

    public function testEachImmutableResponseMutationResultIsConsumedBeforeServedDecision(): void
    {
        $this->store->enable(new MaintenanceMessage(1, 'Scheduled notice', 17));
        $initial = $this->createMock(ResponseInterface::class);
        $contentType = $this->createMock(ResponseInterface::class);
        $cacheControl = $this->createMock(ResponseInterface::class);
        $retryAfter = $this->createMock(ResponseInterface::class);

        /** @var ResponseInterface&\PHPUnit\Framework\MockObject\Stub $completed */
        $completed = $this->createStub(ResponseInterface::class);
        $completed->method('getStatusCode')->willReturn(503);

        $initial->expects(self::once())->method('withHeader')
            ->with('Content-Type', 'text/plain; charset=utf-8')->willReturn($contentType);
        $contentType->expects(self::once())->method('withHeader')
            ->with('Cache-Control', 'no-store')->willReturn($cacheControl);
        $cacheControl->expects(self::once())->method('withHeader')
            ->with('Retry-After', '17')->willReturn($retryAfter);
        $retryAfter->expects(self::once())->method('withBody')->willReturnCallback(
            function (StreamInterface $stream) use ($completed): ResponseInterface {
                self::assertSame("Scheduled notice\n", (string) $stream);
                self::assertSame([], $this->counters);
                self::assertSame([], $this->maintenanceLogs());

                return $completed;
            },
        );

        $pipeline = $this->pipeline(
            $this->middleware($this->factory($initial)),
            decorate: false,
        );

        self::assertSame($completed, $pipeline->handle($this->request('GET')));
        self::assertSame(503, $completed->getStatusCode());
        self::assertSame([
            ['http.maintenance_total', 1, ['outcome' => 'served']],
        ], $this->counters);
        self::assertSame([
            ['info', 'http.maintenance.served', ['outcome' => 'served', 'status' => 503]],
        ], $this->maintenanceLogs());
    }

    public function testInactiveAndDisabledManualParticipationDelegateExactResponse(): void
    {
        $this->allowDownstream = true;
        $this->store->disable();
        $response = $this->createStub(ResponseInterface::class);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponse');
        $request = $this->request('GET');
        self::assertSame(
            $response,
            $this->pipeline(
                $this->middleware($factory),
                terminalResponse: $response,
                decorate: false,
                expectedRequest: $request,
            )->handle($request),
        );
        // Unavailable state would fail closed if a disabled, manually appended middleware read it.
        self::assertTrue(\mkdir($this->root . '/var/maintenance/enabled', 0700, true));
        self::assertSame(
            $response,
            $this->pipeline(
                $this->middleware($factory, enabled: false),
                enabled: false,
                terminalResponse: $response,
                decorate: false,
                expectedRequest: $request,
            )->handle($request),
        );
        self::assertSame(['terminal', 'terminal'], $this->events);
        self::assertSame([], $this->counters);
        self::assertSame([], $this->logs);
    }

    #[DataProvider('observabilityCases')]
    public function testDecisionObservabilityIsIndependentAndNeverRetried(
        bool $metrics,
        bool $logging,
        bool $failMetric,
        bool $failLog,
    ): void {
        $this->prepareState('malformed');
        $response = $this->response(503);
        $pipeline = $this->pipeline(
            $this->middleware(
                $this->factory($response),
                metrics: $metrics,
                logging: $logging,
                failMetric: $failMetric,
                failLog: $failLog,
            ),
            decorate: false,
        );
        self::assertSame($response, $pipeline->handle($this->request('GET')));
        self::assertSame($metrics ? [['http.maintenance_total', 1, ['outcome' => 'served']]] : [], $this->counters);
        self::assertSame(
            $logging ? [
                ['warning', 'http.maintenance.message_invalid', ['reason' => 'invalid-message']],
                ['info', 'http.maintenance.served', ['outcome' => 'served', 'status' => 503]],
            ] : [],
            $this->logs,
        );
    }

    /** @return iterable<string, array{bool, bool, bool, bool}> */
    public static function observabilityCases(): iterable
    {
        yield 'metrics-only' => [true, false, false, false];
        yield 'logs-only' => [false, true, false, false];
        yield 'neither' => [false, false, false, false];
        yield 'both' => [true, true, false, false];
        yield 'metric-failure' => [true, true, true, false];
        yield 'log-failure' => [true, true, false, true];
        yield 'both-fail' => [true, true, true, true];
    }

    public function testNoopPortsDoNotPreventMaintenanceResponse(): void
    {
        $this->store->enable(null);
        $response = $this->response(503);
        $middleware = new MaintenanceMiddleware(
            $this->store,
            $this->factory($response),
            $this->streams(),
            new NoopMeter(),
            new NoopLogger(),
            true,
            120,
            true,
            true,
        );
        self::assertSame($response, $this->pipeline($middleware, decorate: false)->handle($this->request('GET')));
    }

    #[DataProvider('failureCases')]
    public function testConstructionFailureDoesNotRecordServedAndUsesSelectedErrorBoundary(
        string $state,
        string $stage,
        ?int $boundaryStatus,
    ): void {
        $this->prepareState($state);
        $failure = new \RuntimeException('synthetic-maintenance-construction-failure');
        $response = $this->response(503, $stage === 'header' ? $failure : null, 'Cache-Control');
        $factory = $this->createMock(ResponseFactoryInterface::class);
        if ($stage === 'factory') {
            $factory->expects(self::once())->method('createResponse')->with(503)->willThrowException($failure);
        } else {
            $factory->expects(self::once())->method('createResponse')->with(503)->willReturn($response);
        }
        $streams = $this->streams($stage === 'stream' ? $failure : null);
        $boundary = $boundaryStatus === null ? null : $this->boundary($failure, $boundaryStatus);
        $kernel = $this->kernel($this->pipeline($this->middleware($factory, streams: $streams), boundary: $boundary));
        if ($boundaryStatus !== null) {
            self::assertSame($boundaryStatus, $kernel->handle($this->request('GET'))->getStatusCode());
            $this->assertCompletion('handled_error', $boundaryStatus);
        } else {
            $this->assertOriginalFailure($kernel, $failure);
        }
        self::assertSame([
            $state === 'malformed'
                ? ['warning', 'http.maintenance.message_invalid', ['reason' => 'invalid-message']]
                : ['warning', 'http.maintenance.state_unavailable', ['reason' => 'state-unavailable']],
        ], $this->maintenanceLogs());
        self::assertSame(
            [],
            \array_values(
                \array_filter($this->counters, static fn (array $row): bool => $row[0] === 'http.maintenance_total'),
            )
        );
    }

    /** @return iterable<string, array{string, string, ?int}> */
    public static function failureCases(): iterable
    {
        foreach (['malformed', 'unavailable'] as $state) {
            foreach (['factory', 'header', 'stream'] as $stage) {
                yield $state . '-' . $stage . '-mapped' => [$state, $stage, 502];
                yield $state . '-' . $stage . '-emergency' => [$state, $stage, 500];
                yield $state . '-' . $stage . '-fatal' => [$state, $stage, null];
            }
        }
    }

    #[DataProvider('invalidMutationMethods')]
    public function testInvalidPsr7MutationIsRejectedWithoutServedDecision(string $method): void
    {
        $this->store->enable(null);
        $response = $this->createMock(ResponseInterface::class);
        if ($method === 'withBody') {
            $response->expects(self::exactly(3))->method('withHeader')->willReturnSelf();
        }
        $response->expects(self::once())->method($method)->willReturn($this->createStub(MessageInterface::class));
        $kernel = $this->kernel($this->pipeline($this->middleware($this->factory($response))));
        try {
            $kernel->handle($this->request('GET'));
            self::fail('invalid-maintenance-response-accepted');
        } catch (\RuntimeException $exception) {
            self::assertSame('http-maintenance-response-invalid', $exception->getMessage());
        }
        $this->assertCompletion('fatal_error', 'unknown');
        self::assertSame([], $this->maintenanceLogs());
        self::assertCount(1, $this->counters);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMutationMethods(): iterable
    {
        yield 'header' => ['withHeader'];
        yield 'body' => ['withBody'];
    }

    #[DataProvider('lateFailureStages')]
    public function testCompletedServedDecisionSurvivesLaterFailures(string $stage): void
    {
        $this->store->enable(null);
        $failure = new \RuntimeException('synthetic-late-failure');
        $response = $this->response(503, $stage === 'security' ? $failure : null, 'X-Content-Type-Options');
        $kernel = $this->kernel(
            $this->pipeline(
                $this->middleware($this->factory($response)),
                postFailure: $stage === 'post' ? $failure : null,
            ),
        );
        $this->assertOriginalFailure($kernel, $failure);
        self::assertSame(['http.maintenance_total', 1, ['outcome' => 'served']], $this->counters[0]);
        self::assertCount(2, $this->counters);
        self::assertSame(
            [['info', 'http.maintenance.served', ['outcome' => 'served', 'status' => 503]]],
            $this->maintenanceLogs()
        );
    }

    /** @return iterable<string, array{string}> */
    public static function lateFailureStages(): iterable
    {
        yield 'security' => ['security'];
        yield 'post' => ['post'];
    }

    public function testOneConstructedKernelObservesAllStateTransitionsWithoutAdditionalContextOrReset(): void
    {
        $this->allowDownstream = true;
        $normal = $this->response(200);
        $maintenance = $this->response(503);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::exactly(2))->method('createResponse')->with(503)->willReturn($maintenance);
        $kernel = $this->kernel($this->pipeline($this->middleware($factory), terminalResponse: $normal));
        foreach (
            [
                null,
                new MaintenanceMessage(1, 'First', 1),
                new MaintenanceMessage(1, 'Second', 2),
                null,
            ] as $message
        ) {
            if ($message === null) {
                $this->store->disable();
            } else {
                $this->store->enable($message);
            }
            self::assertSame([], $this->context->all());
            $this->allowDownstream = $message === null;
            $returned = $kernel->handle($this->request('GET'));
            self::assertSame($message === null ? $normal : $maintenance, $returned);
            if ($message !== null) {
                self::assertSame($message->message() . "\n", (string) $returned->getBody());
                self::assertSame((string) $message->retryAfterSeconds(), $this->headers['Retry-After']);
            }
            self::assertSame([], $this->context->all());
        }
        self::assertSame(['success', 'handled_error', 'handled_error', 'success'], $this->completion);
        self::assertSame(\array_fill(0, 4, ['correlation_id', 'request_id', 'uow_id', 'uow_type']), $this->contextKeys);
        self::assertSame([true, true, true, true], $this->resetChecks);
        self::assertSame(4, $this->contextComparisons);
        self::assertSame(4, \count(\array_keys($this->events, 'reset', true)));
        self::assertSame(
            2,
            \count(
                \array_filter($this->counters, static fn (array $row): bool => $row[0] === 'http.maintenance_total'),
            ),
        );
    }

    private function prepareState(string $state): void
    {
        if ($state === 'unavailable') {
            self::assertTrue(\mkdir($this->root . '/var/maintenance/enabled', 0700, true));
            return;
        }
        $this->store->enable(
            match ($state) {
                'message' => new MaintenanceMessage(1, 'Scheduled café', null),
                'override' => new MaintenanceMessage(1, 'Scheduled café', 17),
                default => null,
            },
        );
        $path = $this->root . '/var/maintenance/message.json';
        if (\in_array($state, ['malformed', 'oversized', 'unreadable'], true)) {
            $validMessage = "{\"message\":\"Scheduled notice\",\"retryAfterSeconds\":17,\"schemaVersion\":1}\n";
            $bytes = match ($state) {
                'malformed' => '{"invalid"',
                'oversized' => \str_pad($validMessage, 8193, ' '),
                'unreadable' => $validMessage,
            };
            self::assertSame(\strlen($bytes), \file_put_contents($path, $bytes));
        }
        if ($state === 'unreadable') {
            $this->restrictedMessagePath = $path;
            FilesystemPermissionTestSupport::restrict($path, 0000, 'RD');
            FilesystemPermissionTestSupport::assertUnreadableFile($path);
        }
    }

    private function request(string $method): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/orders');
        $uri->method('getScheme')->willReturn('http');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        return $request;
    }

    private function response(int $status, ?\Throwable $failure = null, string $failureHeader = ''): ResponseInterface
    {
        $body = $this->createStub(StreamInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, mixed $value) use ($response, $failure, $failureHeader): ResponseInterface {
                if ($failure !== null && $name === $failureHeader) {
                    throw $failure;
                }
                $this->headers[$name] = $value;
                return $response;
            },
        );
        $response->method('withBody')->willReturnCallback(
            function (StreamInterface $stream) use ($response, &$body): ResponseInterface {
                $body = $stream;
                $this->events[] = 'body';
                return $response;
            },
        );
        $response->method('getBody')->willReturnCallback(static function () use (&$body): StreamInterface {
            return $body;
        });
        return $response;
    }

    private function factory(ResponseInterface $response): ResponseFactoryInterface
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::once())->method('createResponse')->with(503)->willReturnCallback(
            function () use ($response): ResponseInterface {
                $this->events[] = 'response';
                return $response;
            },
        );
        return $factory;
    }

    private function streams(?\Throwable $failure = null): StreamFactoryInterface
    {
        $streams = $this->createStub(StreamFactoryInterface::class);
        $streams->method('createStream')->willReturnCallback(function (string $bytes) use ($failure): StreamInterface {
            if ($failure !== null) {
                throw $failure;
            }
            /** @var StreamInterface&\PHPUnit\Framework\MockObject\Stub $stream */
            $stream = $this->createStub(StreamInterface::class);
            $stream->method('__toString')->willReturn($bytes);

            return $stream;
        });
        return $streams;
    }

    private function meter(bool $fails = false): MeterPortInterface
    {
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(
            function (string $name, int $delta, array $labels) use ($fails): void {
                $this->counters[] = [$name, $delta, $labels];
                if ($name === 'http.maintenance_total') {
                    $this->events[] = $labels['outcome'];
                }
                if ($fails) {
                    throw new \RuntimeException('synthetic-metric-failure');
                }
            },
        );
        $meter->method('observe')->willReturnCallback(function (string $name, int $value, array $labels): void {
            $this->observations[] = [$name, $value, $labels];
        });
        return $meter;
    }

    private function logger(bool $fails = false): LoggerInterface
    {
        $logger = $this->createStub(LoggerInterface::class);
        foreach (['info', 'warning'] as $level) {
            $logger->method($level)->willReturnCallback(
                function (string $event, array $context) use ($level, $fails): void {
                    $this->logs[] = [$level, $event, $context];
                    if ($fails) {
                        throw new \RuntimeException('synthetic-log-failure');
                    }
                },
            );
        }
        return $logger;
    }

    private function middleware(
        ResponseFactoryInterface $factory,
        bool $enabled = true,
        bool $metrics = true,
        bool $logging = true,
        bool $failMetric = false,
        bool $failLog = false,
        ?StreamFactoryInterface $streams = null,
    ): MaintenanceMiddleware {
        return new MaintenanceMiddleware(
            $this->store,
            $factory,
            $streams ?? $this->streams(),
            $this->meter($failMetric),
            $this->logger($failLog),
            $enabled,
            120,
            $metrics,
            $logging,
        );
    }

    private function pipeline(
        MaintenanceMiddleware $middleware,
        bool $enabled = true,
        ?ResponseInterface $terminalResponse = null,
        bool $decorate = true,
        ?HttpErrorHandlingMiddleware $boundary = null,
        ?\Throwable $postFailure = null,
        ?ServerRequestInterface $expectedRequest = null,
        bool $realRedirect = false,
    ): Pipeline {
        $http = require __DIR__ . '/../../config/http.php';
        $http['maintenance']['enabled'] = $enabled;
        $http['middleware']['system_pre'] = [MaintenanceMiddleware::class];
        $http['https_redirect']['enabled'] = true;
        $http['https_redirect']['target_authority'] = 'example.test';
        $http['proxy']['enabled'] = true;
        $http['hosts']['enabled'] = true;
        $http['context']['enrich']['enabled'] = true;
        $http['request']['json']['enabled'] = true;
        $http['tracing']['enabled'] = true;
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(['http' => $http]));
        $tags = new TagRegistry();
        $services = [MaintenanceMiddleware::class => $middleware];
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'tag' && $operation['tag'] === ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE) {
                $tags->add($operation['tag'], $operation['serviceId'], $operation['priority'], $operation['meta']);
            }
        }
        $downstream = [
            TrustedProxyMiddleware::class,
            RequestContextMiddleware::class,
            TrustedHostMiddleware::class,
            HttpsRedirectMiddleware::class,
            RequestBodySizeLimitMiddleware::class,
            JsonBodyParserMiddleware::class,
        ];
        foreach ([TraceContextMiddleware::class, ...$downstream] as $id) {
            $probe = $this->createStub(MiddlewareInterface::class);
            $probe->method('process')->willReturnCallback(
                function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($id): ResponseInterface {
                    if ($id === TraceContextMiddleware::class) {
                        $before = $this->context->all();

                        try {
                            return $next->handle($request);
                        } finally {
                            ++$this->contextComparisons;
                            self::assertTrue(
                                $before === $this->context->all(),
                                'maintenance-context-changed',
                            );
                        }
                    }

                    self::assertTrue(
                        $this->allowDownstream,
                        'maintenance-entered-downstream-middleware',
                    );

                    return $next->handle($request);
                },
            );
            $services[$id] = $probe;
        }
        if ($realRedirect) {
            $redirectFactory = $this->createMock(ResponseFactoryInterface::class);
            $redirectFactory->expects(self::never())->method('createResponse');
            $services[HttpsRedirectMiddleware::class] = new HttpsRedirectMiddleware(
                new HttpsRedirectPolicy(true, 308, ['GET', 'POST', 'HEAD'], 'example.test'),
                $redirectFactory,
                $this->meter(false),
                $this->logger(false),
                false,
                true,
                true,
            );
        }
        if ($decorate) {
            foreach (
                [
                    'system' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST,
                    'app' => ReservedTags::HTTP_MIDDLEWARE_APP_POST,
                    'route' => ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST,
                ] as $name => $tag
            ) {
                $tags->add($tag, $name . '-post');
                $post = $this->createStub(MiddlewareInterface::class);
                $post->method('process')->willReturnCallback(
                    function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                        $name,
                        $postFailure,
                    ): ResponseInterface {
                        $this->events[] = $name . ':enter';
                        $result = $next->handle($request);
                        $this->events[] = $name . ':leave';
                        if ($postFailure !== null) {
                            throw $postFailure;
                        }
                        return $result->withHeader('X-' . \ucfirst($name) . '-Post', 'yes');
                    },
                );
                $services[$name . '-post'] = $post;
            }
        }
        if ($boundary !== null) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000);
            $services[HttpErrorHandlingMiddleware::class] = $boundary;
        }
        $stack = new HttpMiddlewareStackBuilder($tags);
        $ordered = $stack->build($http);
        self::assertSame(1, \count(\array_keys($ordered, MaintenanceMiddleware::class, true)));
        if ($enabled) {
            self::assertContains(TraceContextMiddleware::class, $ordered);
            self::assertLessThan(
                \array_search(MaintenanceMiddleware::class, $ordered, true),
                \array_search(TraceContextMiddleware::class, $ordered, true),
            );
            foreach ($downstream as $id) {
                self::assertLessThan(
                    \array_search($id, $ordered, true),
                    \array_search(MaintenanceMiddleware::class, $ordered, true),
                );
            }
        }
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static fn (string $id): MiddlewareInterface => $services[$id]);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturnCallback(
            function (ServerRequestInterface $request) use ($terminalResponse, $expectedRequest): ResponseInterface {
                self::assertTrue($this->allowDownstream, 'maintenance-entered-terminal');
                self::assertNotNull($terminalResponse);
                if ($expectedRequest !== null) {
                    self::assertSame($expectedRequest, $request);
                }
                $this->events[] = 'terminal';
                return $terminalResponse;
            },
        );
        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http]),
            $stack,
            $terminal,
        );
    }

    private function kernel(Pipeline $pipeline): HttpKernel
    {
        $context = $this->context;
        $probe = new class(
            function (array $result) use ($context): void {
                $this->completion[] = $result['outcome'];
                $this->events[] = 'after';

                $keys = \array_keys($context->all());
                \sort($keys, \SORT_STRING);
                $this->contextKeys[] = $keys;
            },
            function () use ($context): void {
                $this->events[] = 'reset';
                $this->resetChecks[] = $context->has(ContextKeys::UOW_ID);
            },
        ) implements AfterUowHookInterface, ResetInterface {
            public function __construct(
                private readonly \Closure $onAfterUow,
                private readonly \Closure $onReset,
            ) {
            }

            public function afterUow(array $context, array $result): void
            {
                ($this->onAfterUow)($result);
            }

            public function reset(): void
            {
                ($this->onReset)();
            }
        };
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturnCallback(
            static fn (string $id): mixed => $id === ContextStore::class ? $context : $probe,
        );
        $services->method('has')->willReturn(true);
        $tags = new TagRegistry();
        $reset = new ResetOrchestrator($services, $tags);
        $tags->add($reset->effectiveResetTag(), 'App\\MaintenanceProbe', 100);
        $tags->add($reset->effectiveResetTag(), ContextStore::class);
        $tags->add(ReservedTags::KERNEL_STATEFUL, 'App\\MaintenanceProbe');
        $tags->add(ReservedTags::KERNEL_STATEFUL, ContextStore::class);
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'App\\MaintenanceProbe');
        $ids = $this->createStub(IdGeneratorInterface::class);
        $ids->method('generate')->willReturn(self::ID);
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::ID);
        $runtime = new KernelRuntime(
            $context,
            $reset,
            new Stopwatch(),
            $ids,
            $correlation,
            new CorrelationIdGenerator(new UlidGenerator()),
            new HookInvoker($services, $tags),
            new NoopLogger(),
            new NoopTracer(),
            new NoopMeter(),
            10,
            200,
        );
        $requestIds = new RequestIdPolicy($context, $ids, true, 'X-Request-Id', false, 128);
        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(
                    true,
                    'nosniff',
                    'deny',
                    'no-referrer',
                    'geolocation=()',
                    "default-src 'none'",
                ),
            ),
            new HttpRequestMetricsRecorder($this->meter(), true),
            new HttpAccessLogWriter($this->logger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );
    }

    private function boundary(\Throwable $failure, int $status): HttpErrorHandlingMiddleware
    {
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $normalization = $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure));

        if ($status === 500) {
            $normalization->willThrowException(new \RuntimeException('synthetic-normalization-failure'));
        } else {
            $normalization->willReturn(
                new ErrorDescriptor(
                    'SYNTHETIC_MAINTENANCE_FAILURE',
                    'Synthetic failure.',
                    httpStatus: $status,
                ),
            );
        }

        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with($status)->willReturn(
            $this->response($status),
        );

        return new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $this->streams(),
        );
    }

    private function assertOriginalFailure(HttpKernel $kernel, \Throwable $failure): void
    {
        try {
            $kernel->handle($this->request('GET'));
            self::fail('maintenance-failure-not-propagated');
        } catch (\Throwable $caught) {
            self::assertSame($failure, $caught);
        }
        $this->assertCompletion('fatal_error', 'unknown');
    }

    private function assertCompletion(string $outcome, int|string $status): void
    {
        self::assertSame([$outcome], $this->completion);
        self::assertSame([['correlation_id', 'request_id', 'uow_id', 'uow_type']], $this->contextKeys);
        self::assertSame([true], $this->resetChecks);
        self::assertSame([], $this->context->all());
        self::assertSame(1, \count(\array_keys($this->events, 'reset', true)));
        $requests = \array_values(
            \array_filter($this->counters, static fn (array $row): bool => $row[0] === 'http.request_total'),
        );
        self::assertCount(1, $requests);
        self::assertSame($status, $requests[0][2]['status']);
        self::assertSame($outcome, $requests[0][2]['outcome']);
        self::assertSame(1, $requests[0][1]);
        self::assertSame(['method', 'status', 'outcome'], \array_keys($requests[0][2]));
        self::assertCount(1, $this->observations);
        self::assertSame('http.request_duration_ms', $this->observations[0][0]);
        self::assertGreaterThanOrEqual(0, $this->observations[0][1]);
        self::assertLessThanOrEqual(4_294_967_295, $this->observations[0][1]);
        self::assertSame($requests[0][2], $this->observations[0][2]);
        $logs = \array_values(
            \array_filter($this->logs, static fn (array $row): bool => $row[1] === 'http.request.completed'),
        );
        self::assertCount(1, $logs);
        self::assertSame($status, $logs[0][2]['status']);
        self::assertSame($outcome, $logs[0][2]['outcome']);
    }

    private function maintenanceLogs(): array
    {
        return \array_values(
            \array_filter($this->logs, static fn (array $row): bool => \str_starts_with($row[1], 'http.maintenance.')),
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
            @\chmod($path, 0600);
            \unlink($path);
        }
    }
}
