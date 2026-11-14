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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class RequestContextMiddlewareContextKeysTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testEnabledEnrichmentWritesOnlyFiveSafeRequestKeysAndPreservesKernelOwnedIdentity(): void
    {
        $context = new ContextStore();
        self::seedKernelIdentity($context);
        $request = $this->request('192.0.2.5', 'HTTPS', 'Example.COM', '/safe/path', 'TestAgent/1');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)
            ->willReturnCallback(static function () use ($context, $response): ResponseInterface {
                self::assertSame([
                    ContextKeys::CORRELATION_ID => self::CORRELATION,
                    ContextKeys::UOW_ID => self::CORRELATION,
                    ContextKeys::UOW_TYPE => 'http',
                    ContextKeys::CLIENT_IP => '192.0.2.5',
                    ContextKeys::SCHEME => 'https',
                    ContextKeys::HOST => 'example.com',
                    ContextKeys::PATH => '/safe/path',
                    ContextKeys::USER_AGENT => 'TestAgent/1',
                ], $context->all());

                return $response;
            });

        self::assertSame($response, new RequestContextMiddleware($context, true, false)->process($request, $handler));
    }

    public function testDisabledEnrichmentDoesNotWriteEvenAdmissibleRequestMetadata(): void
    {
        $context = new ContextStore();
        self::seedKernelIdentity($context);
        $request = $this->request('192.0.2.5', 'https', 'example.com', '/private', 'agent');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, new RequestContextMiddleware($context, false, true)->process($request, $handler));
        self::assertSame([
            ContextKeys::CORRELATION_ID => self::CORRELATION,
            ContextKeys::UOW_ID => self::CORRELATION,
            ContextKeys::UOW_TYPE => 'http',
        ], $context->all());
    }

    #[DataProvider('unsafeInputs')]
    public function testMalformedOptionalValuesAreOmittedWithoutWeakeningContextStorePolicy(
        mixed $peer,
        string $scheme,
        string $host,
        string $path,
        string $agent,
        string $missingKey,
    ): void {
        $context = new ContextStore();
        $request = $this->request($peer, $scheme, $host, $path, $agent);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        self::assertSame($response, new RequestContextMiddleware($context, true, false)->process($request, $handler));
        self::assertArrayNotHasKey($missingKey, $context->all());
        self::assertNotContains($missingKey, \array_keys($context->all()));
    }

    /** @return iterable<string, array{mixed, string, string, string, string, string}> */
    public static function unsafeInputs(): iterable
    {
        yield 'bad-immediate-ip' => ['192.0.2.999', 'https', 'example.com', '/ok', 'agent', ContextKeys::CLIENT_IP];
        yield 'invalid-scheme' => ['192.0.2.5', 'ftp', 'example.com', '/ok', 'agent', ContextKeys::SCHEME];
        yield 'unsafe-host' => ['192.0.2.5', 'https', 'evil.example/path', '/ok', 'agent', ContextKeys::HOST];
        yield 'oversized-path' => [
            '192.0.2.5',
            'https',
            'example.com',
            '/' . \str_repeat('p', 2049),
            'agent',
            ContextKeys::PATH,
        ];
        yield 'control-in-agent' => [
            '192.0.2.5',
            'https',
            'example.com',
            '/ok',
            "agent\r\nsecret",
            ContextKeys::USER_AGENT,
        ];
        yield 'oversized-agent' => [
            '192.0.2.5',
            'https',
            'example.com',
            '/ok',
            \str_repeat('a', 513),
            ContextKeys::USER_AGENT,
        ];
    }

    public function testAmbiguousUserAgentHeadersAreOmitted(): void
    {
        $context = new ContextStore();
        $request = $this->request(
            '192.0.2.5',
            'https',
            'example.com',
            '/safe',
            'agent-first,agent-second',
            ['agent-first', 'agent-second'],
        );
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, new RequestContextMiddleware($context, true, false)->process($request, $handler));
        self::assertSame('192.0.2.5', $context->get(ContextKeys::CLIENT_IP));
        self::assertArrayNotHasKey(ContextKeys::USER_AGENT, $context->all());
    }

    public function testSequentialUowsResetContextBeforeNextRequestWithoutHttpOwnedResetCycle(): void
    {
        $context = new ContextStore();
        $handleOne = self::handle();
        $handleTwo = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::exactly(2))->method('beginUnitOfWork')
            ->willReturnCallback(static function () use ($context, $handleOne, $handleTwo): UnitOfWorkHandle {
                static $count = 0;
                self::assertSame([], $context->all());
                self::seedKernelIdentity($context);

                return ++$count === 1 ? $handleOne : $handleTwo;
            });
        $snapshots = [];
        $runtime->expects(self::exactly(2))->method('afterUnitOfWork')
            ->willReturnCallback(
                static function (UnitOfWorkHandle $handle, string $outcome) use ($context, &$snapshots): array {
                    $snapshots[] = [$handle, $outcome, $context->all()];
                    $context->reset(); // Simulates Kernel-owned ResetOrchestrator, never HTTP middleware reset.

                    return [];
                },
            );
        $first = $this->request('192.0.2.5', 'https', 'example.com', '/first', 'first-agent');
        $second = $this->request('192.0.2.6', 'http', 'example.org', '/second', 'second-agent');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(2))->method('handle')->willReturn($response);
        $correlation = new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            }
        };
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([new RequestContextMiddleware($context, true, false)], $terminal),
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );
        self::assertSame($response, $kernel->handle($first));
        self::assertSame($response, $kernel->handle($second));
        self::assertCount(2, $snapshots);
        self::assertSame('/first', $snapshots[0][2][ContextKeys::PATH]);
        self::assertSame('/second', $snapshots[1][2][ContextKeys::PATH]);
        self::assertSame('192.0.2.6', $snapshots[1][2][ContextKeys::CLIENT_IP]);
        self::assertSame([], $context->all());
    }

    public function testInProcessRequestMetadataDoesNotEnterMetricsSpansOrAccessLogs(): void
    {
        $context = new ContextStore();
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->method('beginUnitOfWork')->willReturnCallback(
            static function () use ($context, $handle): UnitOfWorkHandle {
                self::seedKernelIdentity($context);

                return $handle;
            },
        );
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'success', null)
            ->willReturnCallback(static function () use ($context): array {
                $context->reset();

                return [];
            });
        $request = $this->request('192.0.2.8', 'https', 'example.com', '/internal/secret-token', 'private-agent-123');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->willReturn($response);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')
            ->willReturnCallback(static function () use ($context, $response): ResponseInterface {
                self::assertSame('/internal/secret-token', $context->get(ContextKeys::PATH));
                self::assertSame('private-agent-123', $context->get(ContextKeys::USER_AGENT));

                return $response;
            });
        $labels = ['method' => 'GET', 'status' => 200, 'outcome' => 'success'];
        $recordedMetrics = [];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('http.request_total', 1, $labels)
            ->willReturnCallback(
                static function (string $name, int $delta, array $labels) use (&$recordedMetrics): void {
                    $recordedMetrics[$name] = $labels;
                },
            );
        $meter->expects(self::once())->method('observe')->with(
            'http.request_duration_ms',
            self::greaterThanOrEqual(0),
            $labels,
        )->willReturnCallback(
            static function (string $name, int $duration, array $labels) use (&$recordedMetrics): void {
                $recordedMetrics[$name] = $labels;
            },
        );
        $loggedRecords = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'http.request.completed',
            self::callback(
                static fn (array $record): bool => $record['method'] === 'GET'
                    && $record['status'] === 200
                    && $record['route_template'] === 'unknown'
                    && $record['correlation_id'] === self::CORRELATION
                    && !\array_key_exists('path', $record)
                    && !\array_key_exists('user_agent', $record)
                    && !\str_contains(\json_encode($record, JSON_THROW_ON_ERROR), 'secret-token'),
            ),
        )->willReturnCallback(static function (string $event, array $record) use (&$loggedRecords): void {
            $loggedRecords[] = $record;
        });
        $span = $this->createMock(SpanInterface::class);
        $attributes = [];
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[$key] = $value;
            });
        $span->expects(self::once())->method('end');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->with('http.request', ['method' => 'GET'])
            ->willReturn($span);
        $correlation = new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            }
        };
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([new RequestContextMiddleware($context, true, false)], $terminal),
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
        );

        self::assertSame($response, $kernel->handle($request));
        self::assertSame([
            'http.request_total' => $labels,
            'http.request_duration_ms' => $labels,
        ], $recordedMetrics);
        self::assertCount(1, $loggedRecords);
        self::assertSame([
            'method',
            'status',
            'outcome',
            'duration_ms',
            'route_template',
            'correlation_id',
        ], \array_keys($loggedRecords[0]));
        self::assertFalse(\str_contains(\json_encode($loggedRecords[0], JSON_THROW_ON_ERROR), 'secret-token'));
        self::assertFalse(\str_contains(\json_encode($loggedRecords[0], JSON_THROW_ON_ERROR), 'private-agent-123'));
        self::assertSame($labels, $attributes);
        self::assertSame([], $context->all());
    }

    /** @param list<string>|null $userAgentValues */
    private function request(
        mixed $peer,
        string $scheme,
        string $host,
        string $path,
        string $agent,
        ?array $userAgentValues = null,
    ): ServerRequestInterface {
        $request = $this->createStub(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => $peer]);
        $uri->method('getScheme')->willReturn($scheme);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getPort')->willReturn(null);
        $uri->method('getPath')->willReturn($path);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => match ($name) {
                'Host' => [],
                'User-Agent' => $userAgentValues ?? [$agent],
                default => [],
            },
        );
        $request->method('getMethod')->willReturn('GET');

        return $request;
    }

    private static function seedKernelIdentity(ContextStore $context): void
    {
        $context->set(ContextKeys::CORRELATION_ID, self::CORRELATION);
        $context->set(ContextKeys::UOW_ID, self::CORRELATION);
        $context->set(ContextKeys::UOW_TYPE, 'http');
    }

    private static function handle(): UnitOfWorkHandle
    {
        return new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => self::CORRELATION,
        ]);
    }
}
