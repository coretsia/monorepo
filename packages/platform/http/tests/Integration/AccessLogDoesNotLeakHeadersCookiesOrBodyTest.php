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
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
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

final class AccessLogDoesNotLeakHeadersCookiesOrBodyTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('completionPaths')]
    public function testOneFinalResponseAccessLogContainsOnlyApprovedFields(
        string $mode,
        string $rawMethod,
        string $method,
        int $expectedStatus,
        string $outcome,
    ): void {
        $events = [];
        $handle = self::handle();
        $request = $this->hostileRequest($rawMethod);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), $outcome, null)
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'after';

                return [];
            });
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $response->expects(self::once())->method('withHeader')
            ->with('X-Correlation-Id', self::CORRELATION)
            ->willReturnCallback(static function () use ($response, &$events): ResponseInterface {
                $events[] = 'finalize';

                return $response;
            });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $middleware = [];

        if ($mode === 'short-circuit') {
            $terminal->expects(self::never())->method('handle');
            $shortCircuit = $this->createMock(MiddlewareInterface::class);
            $shortCircuit->expects(self::once())->method('process')
                ->willReturnCallback(static function () use ($response, &$events): ResponseInterface {
                    $events[] = 'pipeline';

                    return $response;
                });
            $middleware[] = $shortCircuit;
        } elseif ($mode === 'success') {
            $terminal->expects(self::once())->method('handle')->with($request)
                ->willReturnCallback(static function () use ($response, &$events): ResponseInterface {
                    $events[] = 'pipeline';

                    return $response;
                });
        } else {
            $failure = $mode === 'bad-json'
                ? BadJsonException::invalid()
                : ($mode === 'body-too-large'
                    ? BodyTooLargeException::tooLarge()
                    : new \RuntimeException('SECRET_FATAL_EXCEPTION_MESSAGE'));
            $terminal->expects(self::once())->method('handle')->with($request)
                ->willReturnCallback(static function () use ($failure, &$events): ResponseInterface {
                    $events[] = 'pipeline';
                    throw $failure;
                });
            $errors = $this->createMock(ErrorHandlerInterface::class);
            $handler = $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure));
            if ($mode === 'emergency') {
                $handler->willThrowException(new \RuntimeException('SECRET_ERROR_HANDLER_FAILURE'));
            } else {
                $handler->willReturnCallback(static function (\Throwable $failure): ErrorDescriptor {
                    $descriptor = new HttpExceptionMapper()->map($failure);
                    self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
                    self::assertSame([], $descriptor->extensions());

                    return $descriptor;
                });
            }
            $responses = $this->createMock(ResponseFactoryInterface::class);
            $errorsResponse = $this->createStub(ResponseInterface::class);
            $errorsResponse->method('getStatusCode')->willReturn($expectedStatus);
            $errorsResponse->method('withHeader')->willReturn($errorsResponse);
            $errorsResponse->method('withBody')->willReturn($response);
            $responses->expects(self::once())->method('createResponse')
                ->with($expectedStatus)->willReturn($errorsResponse);
            /** @var StreamInterface $stream */
            $stream = $this->createStub(StreamInterface::class);
            $streams = $this->createMock(StreamFactoryInterface::class);
            $streams->expects(self::once())->method('createStream')
                ->willReturnCallback(
                    static function (string $safeBody) use ($expectedStatus, $stream): StreamInterface {
                        $public = \json_decode($safeBody, true, 512, JSON_THROW_ON_ERROR);
                        self::assertSame($expectedStatus, $public['status']);
                        self::assertSame(['status', 'title', 'type'], \array_keys($public));
                        self::assertStringNotContainsString('SECRET_', $safeBody);

                        return $stream;
                    },
                );
            $middleware[] = new HttpErrorHandlingMiddleware(
                $errors,
                new ErrorDescriptorToProblemDetailsAdapter(),
                new ProblemDetailsJsonRenderer(),
                $responses,
                $streams,
            );
        }

        $records = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'http.request.completed',
            self::callback(static fn (mixed $context): bool => \is_array($context)),
        )->willReturnCallback(static function (string $event, array $context) use (&$records, &$events): void {
            $events[] = 'access-log';
            $records[] = [$event, $context];
        });
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'debug', 'log'] as $other) {
            $logger->expects(self::never())->method($other);
        }
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn(self::CORRELATION);
        $kernel = $this->kernel($runtime, new Pipeline($middleware, $terminal), $logger, $correlations);

        self::assertSame($response, $kernel->handle($request));
        self::assertCount(1, $records);
        self::assertSame('http.request.completed', $records[0][0]);
        $record = $records[0][1];
        self::assertSame([
            'method',
            'status',
            'outcome',
            'duration_ms',
            'route_template',
            'correlation_id',
        ], \array_keys($record));
        self::assertSame($method, $record['method']);
        self::assertSame($expectedStatus, $record['status']);
        self::assertSame($outcome, $record['outcome']);
        self::assertIsInt($record['duration_ms']);
        self::assertGreaterThanOrEqual(0, $record['duration_ms']);
        self::assertSame('unknown', $record['route_template']);
        self::assertSame(self::CORRELATION, $record['correlation_id']);
        foreach (
            [
                'SECRET_REQUEST_BODY',
                'SECRET_COOKIE',
                'SECRET_AUTHORIZATION',
                'SECRET_QUERY',
                'SECRET_PATH',
                'SECRET_CONTEXT',
                'SECRET_FATAL_EXCEPTION_MESSAGE',
                'SECRET_ERROR_HANDLER_FAILURE',
                $rawMethod,
            ] as $forbidden
        ) {
            if ($forbidden === $method) {
                continue;
            }
            self::assertStringNotContainsString($forbidden, \json_encode($record, JSON_THROW_ON_ERROR));
        }
        self::assertSame(['pipeline', 'finalize', 'access-log', 'after'], $events);
    }

    /** @return iterable<string, array{string, string, string, int, string}> */
    public static function completionPaths(): iterable
    {
        yield 'completed-200' => ['success', 'get', 'GET', 200, 'success'];
        yield 'early-short-circuit-403' => ['short-circuit', 'HEAD', 'HEAD', 403, 'handled_error'];
        yield 'mapped-json-400' => ['bad-json', 'POST', 'POST', 400, 'handled_error'];
        yield 'mapped-body-413' => ['body-too-large', 'PUT', 'PUT', 413, 'handled_error'];
        yield 'emergency-500' => ['emergency', 'CONNECT', 'CONNECT', 500, 'handled_error'];
        yield 'raw-method-normalized' => ['success', 'POST /SECRET_PATH?SECRET_QUERY', 'OTHER', 200, 'success'];
    }

    #[DataProvider('loggingFailurePaths')]
    public function testOptionalLoggerFailureDoesNotChangeResponseOrPrimaryThrowable(bool $fatal): void
    {
        $handle = self::handle();
        $failure = new \RuntimeException('SECRET_PRIMARY_THROWABLE');
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with($handle, $fatal ? 'fatal_error' : 'success', $fatal ? self::identicalTo($failure) : null)
            ->willReturn([]);
        $request = $this->hostileRequest('GET');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->willReturn($response);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturnCallback(
            static function () use ($fatal, $failure, $response): ResponseInterface {
                if ($fatal) {
                    throw $failure;
                }

                return $response;
            },
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                'http.request.completed',
                self::callback(static function (array $context) use ($fatal): bool {
                    self::assertSame($fatal ? 'unknown' : 200, $context['status']);
                    self::assertSame($fatal ? 'fatal_error' : 'success', $context['outcome']);

                    return true;
                }),
            )
            ->willThrowException(new \RuntimeException('SECRET_OPTIONAL_LOGGER_FAILURE'));
        $correlation = $this->createStub(CorrelationIdProviderInterface::class);
        $correlation->method('correlationId')->willReturn(self::CORRELATION);
        $kernel = $this->kernel($runtime, new Pipeline([], $terminal), $logger, $correlation);

        if (!$fatal) {
            self::assertSame($response, $kernel->handle($request));

            return;
        }

        try {
            $kernel->handle($request);
            self::fail('Uncaught primary Throwable must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function loggingFailurePaths(): iterable
    {
        yield 'completed-response' => [false];
        yield 'uncaught-primary-failure' => [true];
    }

    private function hostileRequest(string $method): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Authorization' => ['Bearer SECRET_AUTHORIZATION'],
            'Cookie' => ['session=SECRET_COOKIE'],
            'X-Request-Id' => ['SECRET_CONTEXT'],
            default => [],
        });
        $request->method('getHeaders')->willReturn([
            'Authorization' => ['Bearer SECRET_AUTHORIZATION'],
            'Cookie' => ['session=SECRET_COOKIE'],
        ]);
        $request->method('getCookieParams')->willReturn(['session' => 'SECRET_COOKIE']);
        $request->method('getQueryParams')->willReturn(['token' => 'SECRET_QUERY']);
        $body = $this->createStub(StreamInterface::class);
        $body->method('getContents')->willReturn('SECRET_REQUEST_BODY');
        $request->method('getBody')->willReturn($body);
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/SECRET_PATH');
        $uri->method('getQuery')->willReturn('token=SECRET_QUERY');
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttribute')->willReturn('SECRET_CONTEXT');
        $request->method('getServerParams')->willReturn(['HTTP_AUTHORIZATION' => 'SECRET_AUTHORIZATION']);

        return $request;
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

    private function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        LoggerInterface $logger,
        CorrelationIdProviderInterface $correlation,
    ): HttpKernel {
        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );
    }
}
