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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class NoopTracingAndMetricsNeverThrowInHttpEnvelopeContractTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string REQUEST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const string REQUEST_HEADER = 'X-Request-Id';

    #[DataProvider('requestIdEnablement')]
    public function testFoundationNoopTracerMeterAndLoggerPreserveSuccessfulHttpCompletion(
        bool $requestIdEnabled,
    ): void {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $response = $this->responseWithIdentityHeaders(200, $requestIdEnabled);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $runtime = $this->runtime('success', null);

        $kernel = $this->kernel(
            $runtime,
            $terminal,
            new NoopMeter(),
            new NoopLogger(),
            new NoopTracer(),
            $this->requestIds($requestIdEnabled),
        );
        self::assertSame($response, $kernel->handle($request));
    }

    #[DataProvider('redirectDecisions')]
    public function testFoundationNoopPortsPreserveCompletedHttpsRedirectDecisions(bool $redirected): void
    {
        $meter = new NoopMeter();
        $logger = new NoopLogger();
        $tracer = new NoopTracer();

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getScheme')->willReturn($redirected ? 'http' : 'https');
        $uri->method('getPath')->willReturn('/resource');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $response = $this->responseWithIdentityHeaders($redirected ? 308 : 200, false);

        $factory = $this->createMock(ResponseFactoryInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);

        if ($redirected) {
            $initial = $this->createMock(ResponseInterface::class);
            $initial->expects(self::once())->method('withHeader')
                ->willReturnCallback(static function (string $name, mixed $value) use (
                    $response,
                ): ResponseInterface {
                    self::assertTrue($name === 'Location', 'redirect-location-header-name-mismatch');
                    self::assertTrue(
                        $value === 'https://example.test/resource',
                        'redirect-location-header-value-mismatch',
                    );

                    return $response;
                });

            $factory->expects(self::once())->method('createResponse')->with(308)->willReturn($initial);
            $terminal->expects(self::never())->method('handle');
        } else {
            $factory->expects(self::never())->method('createResponse');
            $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        }

        $middleware = new HttpsRedirectMiddleware(
            new HttpsRedirectPolicy(true, 308, ['GET', 'HEAD'], 'example.test'),
            $factory,
            $meter,
            $logger,
            false,
            true,
            true,
        );

        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::CORRELATION);
        $requestIds = $this->requestIds(false);

        $kernel = new HttpKernel(
            $this->runtime('success', null),
            new Stopwatch(),
            new Pipeline([$middleware], $terminal),
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
            $requestIds,
        );

        self::assertSame($response, $kernel->handle($request));
    }

    /** @return iterable<string, array{bool}> */
    public static function redirectDecisions(): iterable
    {
        yield 'redirected' => [true];
        yield 'skipped' => [false];
    }

    #[DataProvider('requestIdEnablement')]
    public function testAllOptionalObservabilityFailuresRemainIndependentOfSuccessfulResponse(
        bool $requestIdEnabled,
    ): void {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $response = $this->responseWithIdentityHeaders(503, $requestIdEnabled);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);

        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException(new \RuntimeException('counter-failed'));
        $meter->expects(self::once())->method('observe')->willThrowException(new \RuntimeException('observe-failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('logger-failed'));
        $span = $this->createMock(SpanInterface::class);
        $span->method('setAttribute')->willThrowException(new \RuntimeException('span-attribute-failed'));
        $span->expects(self::once())->method('end')->willThrowException(new \RuntimeException('span-end-failed'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->with('http.request', ['method' => 'POST'])
            ->willReturn($span);

        $kernel = $this->kernel(
            $this->runtime('handled_error', null),
            $terminal,
            $meter,
            $logger,
            $tracer,
            $this->requestIds($requestIdEnabled),
        );
        self::assertSame($response, $kernel->handle($request));
    }

    /** @return iterable<string, array{bool}> */
    public static function requestIdEnablement(): iterable
    {
        yield 'disabled' => [false];
        yield 'enabled' => [true];
    }

    public function testOptionalFailuresCannotReplaceThePrimaryApplicationThrowable(): void
    {
        $failure = new \RuntimeException('private-downstream-failure');
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('PATCH');
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willThrowException($failure);
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException(new \RuntimeException('metric-failed'));
        $meter->expects(self::once())->method('observe')->willThrowException(new \RuntimeException('duration-failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('logging-failed'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->willThrowException(new \RuntimeException('start-failed'));

        $kernel = $this->kernel(
            $this->runtime('fatal_error', $failure),
            $terminal,
            $meter,
            $logger,
            $tracer,
            $this->requestIds(false),
        );
        try {
            $kernel->handle($request);
            self::fail('Original Throwable must be propagated to the caller.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    #[DataProvider('requestIdentityFailureStages')]
    public function testOptionalObservabilityFailuresCannotReplacePrimaryRequestIdentityThrowable(
        bool $preparationFails,
    ): void {
        $failure = new \RuntimeException('request-identity-primary-failure');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generation = $generator->expects(self::once())->method('generate');
        if ($preparationFails) {
            $generation->willThrowException($failure);
        } else {
            $generation->willReturn(self::REQUEST_ID);
        }

        $requestIds = new RequestIdPolicy(
            new ContextStore(),
            $generator,
            true,
            self::REQUEST_HEADER,
            false,
            128,
        );

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('getStatusCode');
        $terminal = $this->createMock(RequestHandlerInterface::class);

        if ($preparationFails) {
            $terminal->expects(self::never())->method('handle');
            $response->expects(self::never())->method('withHeader');
        } else {
            $correlated = $this->createMock(ResponseInterface::class);
            $correlated->expects(self::never())->method('getStatusCode');

            $terminal->expects(self::once())
                ->method('handle')
                ->with($request)
                ->willReturn($response);

            $response->expects(self::once())
                ->method('withHeader')
                ->willReturnCallback(static function (string $name, mixed $value) use (
                    $correlated,
                ): ResponseInterface {
                    self::assertTrue($name === 'X-Correlation-Id', 'unexpected-correlation-header-name');
                    self::assertTrue($value === self::CORRELATION, 'unexpected-correlation-header-value');

                    return $correlated;
                });

            $correlated->expects(self::once())
                ->method('withHeader')
                ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                    self::assertTrue($name === self::REQUEST_HEADER, 'unexpected-request-id-header-name');
                    self::assertTrue($value === self::REQUEST_ID, 'unexpected-request-id-header-value');

                    throw $failure;
                });
        }

        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())
            ->method('increment')
            ->willThrowException(new \RuntimeException('counter-failed'));
        $meter->expects(self::once())
            ->method('observe')
            ->willThrowException(new \RuntimeException('duration-failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->willThrowException(new \RuntimeException('logger-failed'));

        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))
            ->method('setAttribute')
            ->willThrowException(new \RuntimeException('span-attribute-failed'));
        $span->expects(self::once())
            ->method('end')
            ->willThrowException(new \RuntimeException('span-end-failed'));

        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())
            ->method('startSpan')
            ->with('http.request', ['method' => 'POST'])
            ->willReturn($span);

        $kernel = $this->kernel(
            $this->runtime('fatal_error', $failure),
            $terminal,
            $meter,
            $logger,
            $tracer,
            $requestIds,
        );

        try {
            $kernel->handle($request);
            self::fail('primary-request-identity-failure-must-propagate');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function requestIdentityFailureStages(): iterable
    {
        yield 'identity-preparation' => [true];
        yield 'identity-finalization' => [false];
    }

    public function testHttpNoopServicesAreNotMiddlewareOrLifecycleRegistries(): void
    {
        $http = require __DIR__ . '/../../config/http.php';
        $builder = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext(['http' => $http]);
        new RedactionServiceProvider()->define($builder, $context);
        new HttpServiceProvider()->define($builder, $context);
        $observable = [
            HttpRequestMetricsRecorder::class,
            HttpAccessLogWriter::class,
            HttpRequestSpanRecorder::class,
            HttpResponseFinalizer::class,
        ];
        $actual = [];
        $dependencies = [];
        $policyDefinition = null;
        $kernelArguments = null;

        foreach ($builder->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                self::assertNotContains($operation['serviceId'], $observable);
                self::assertNotSame(RequestIdPolicy::class, $operation['serviceId']);
                self::assertNotSame(SecurityHeadersPolicy::class, $operation['serviceId']);
                self::assertNotSame(HttpsRedirectPolicy::class, $operation['serviceId']);
                self::assertNotSame(MaintenanceStateStore::class, $operation['serviceId']);

                if (\in_array(
                    $operation['tag'],
                    [ReservedTags::KERNEL_RESET, ReservedTags::KERNEL_STATEFUL],
                    true,
                )) {
                    self::assertNotSame(MaintenanceMiddleware::class, $operation['serviceId']);
                }
            }

            if (($operation['kind'] ?? null) === 'service.class') {
                $id = $operation['id'];

                if ($id === RequestIdPolicy::class) {
                    self::assertNull($policyDefinition);
                    $policyDefinition = $operation;
                }

                if ($id === HttpKernel::class) {
                    $kernelArguments = $operation['arguments'];
                }

                if (
                    \in_array($id, $observable, true)
                    || \str_starts_with($id, 'Coretsia\\Platform\\Http\\Observability\\')
                ) {
                    $actual[] = $id;
                    $dependencies[$id] = $operation['arguments'];
                }
            }
        }

        self::assertCount(4, $observable);
        self::assertEqualsCanonicalizing($observable, $actual);
        self::assertNotContains(RequestIdPolicy::class, $actual);

        $reference = static fn (string $id): array => ContainerValueReference::service($id)->toArray();

        self::assertNotNull($policyDefinition);
        self::assertSame(RequestIdPolicy::class, $policyDefinition['class']);
        self::assertTrue($policyDefinition['shared']);
        self::assertSame([
            $reference(ContextStore::class),
            $reference(UlidGenerator::class),
            false,
            self::REQUEST_HEADER,
            false,
            128,
        ], $policyDefinition['arguments']);

        self::assertSame([
            $reference(KernelRuntimeInterface::class),
            $reference(Stopwatch::class),
            $reference(Pipeline::class),
            $reference(HttpResponseFinalizer::class),
            $reference(HttpRequestMetricsRecorder::class),
            $reference(HttpAccessLogWriter::class),
            $reference(HttpRequestSpanRecorder::class),
            $reference(RequestIdPolicy::class),
        ], $kernelArguments);

        self::assertSame([
            HttpResponseFinalizer::class => [
                $reference(CorrelationIdProviderInterface::class),
                true,
                'X-Correlation-Id',
                $reference(RequestIdPolicy::class),
                $reference(SecurityHeadersPolicy::class),
            ],
            HttpRequestMetricsRecorder::class => [$reference(MeterPortInterface::class), true],
            HttpAccessLogWriter::class => [$reference(LoggerInterface::class), true],
            HttpRequestSpanRecorder::class => [$reference(TracerPortInterface::class), true],
        ], $dependencies);

        foreach (
            [
                HttpResponseFinalizer::class => [
                    'correlationIds',
                    'enabled',
                    'headerName',
                    'requestIds',
                    'securityHeaders',
                ],
                SecurityHeadersPolicy::class => ['headers'],
                HttpsRedirectPolicy::class => [
                    'allowedMethods',
                    'enabled',
                    'status',
                    'targetAuthority',
                ],
                HttpsRedirectMiddleware::class => [
                    'accessLogEnabled',
                    'logger',
                    'meter',
                    'metricsEnabled',
                    'policy',
                    'proxyEnabled',
                    'responses',
                ],
                MaintenanceStateStore::class => [
                    'locks',
                    'paths',
                ],
                MaintenanceMiddleware::class => [
                    'accessLogEnabled',
                    'enabled',
                    'logger',
                    'meter',
                    'metricsEnabled',
                    'responses',
                    'retryAfterDefaultSeconds',
                    'state',
                    'streams',
                ],
                HttpRequestMetricsRecorder::class => ['enabled', 'meter'],
                HttpAccessLogWriter::class => ['enabled', 'logger'],
                HttpRequestSpanRecorder::class => ['enabled', 'tracer'],
                RequestIdPolicy::class => [
                    'acceptFromUpstream',
                    'context',
                    'enabled',
                    'generator',
                    'headerName',
                    'maxLength',
                ],
                HttpKernel::class => [
                    'accessLog',
                    'finalizer',
                    'metrics',
                    'pipeline',
                    'requestIds',
                    'runtime',
                    'spans',
                    'stopwatch',
                ],
            ] as $class => $expectedProperties
        ) {
            $reflection = new \ReflectionClass($class);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());

            $properties = \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            );

            \sort($properties);
            self::assertSame($expectedProperties, $properties);
        }

        self::assertFalse(
            \is_subclass_of(HttpResponseFinalizer::class, MiddlewareInterface::class),
        );
        self::assertFalse(
            \is_subclass_of(HttpRequestMetricsRecorder::class, MiddlewareInterface::class),
        );
        self::assertFalse(\is_subclass_of(HttpAccessLogWriter::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(HttpRequestSpanRecorder::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(RequestIdPolicy::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(RequestIdPolicy::class, ResetInterface::class));
        self::assertFalse(\is_subclass_of(SecurityHeadersPolicy::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(SecurityHeadersPolicy::class, ResetInterface::class));
        self::assertFalse(\is_subclass_of(HttpsRedirectPolicy::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(HttpsRedirectPolicy::class, ResetInterface::class));
        self::assertFalse(\is_subclass_of(HttpsRedirectMiddleware::class, ResetInterface::class));
        self::assertTrue(\is_subclass_of(MaintenanceMiddleware::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(MaintenanceMiddleware::class, ResetInterface::class));
        self::assertFalse(\is_subclass_of(MaintenanceStateStore::class, MiddlewareInterface::class));
        self::assertFalse(\is_subclass_of(MaintenanceStateStore::class, ResetInterface::class));
    }

    private function runtime(string $outcome, ?\Throwable $failure): KernelRuntimeInterface
    {
        $handle = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => self::CORRELATION,
        ]);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')->with(
            self::identicalTo($handle),
            $outcome,
            $failure === null ? null : self::identicalTo($failure),
        )->willReturn([]);

        return $runtime;
    }

    private function requestIds(bool $enabled): RequestIdPolicy
    {
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($enabled ? self::once() : self::never())
            ->method('generate')
            ->willReturn(self::REQUEST_ID);

        return new RequestIdPolicy(
            new ContextStore(),
            $generator,
            $enabled,
            self::REQUEST_HEADER,
            false,
            128,
        );
    }

    private function responseWithIdentityHeaders(int $status, bool $requestIdEnabled): ResponseInterface
    {
        $expected = [
            ['X-Correlation-Id', self::CORRELATION],
        ];
        if ($requestIdEnabled) {
            $expected[] = [self::REQUEST_HEADER, self::REQUEST_ID];
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->expects(self::never())->method('withAddedHeader');

        $index = 0;
        $response->expects(self::exactly(\count($expected)))
            ->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use (
                $expected,
                $response,
                &$index,
            ): ResponseInterface {
                self::assertTrue(isset($expected[$index]), 'unexpected-identity-header-mutation');
                self::assertTrue($name === $expected[$index][0], 'identity-header-name-mismatch');
                self::assertTrue($value === $expected[$index][1], 'identity-header-value-mismatch');
                ++$index;

                return $response;
            });

        return $response;
    }

    private function kernel(
        KernelRuntimeInterface $runtime,
        RequestHandlerInterface $terminal,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        TracerPortInterface $tracer,
        RequestIdPolicy $requestIds,
    ): HttpKernel {
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::CORRELATION);

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([], $terminal),
            new HttpResponseFinalizer(
                $correlation,
                true,
                'X-Correlation-Id',
                $requestIds,
                new SecurityHeadersPolicy(false, 'nosniff', 'deny', 'no-referrer', '', ''),
            ),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
            $requestIds,
        );
    }
}
