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
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
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
use Psr\Http\Server\RequestHandlerInterface;

final class BadJsonTriggersCanonicalErrorFlowTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('invalidBodies')]
    public function testSelectedProblemDetailsMapsExactlyOnceToSafe400(
        string $payload,
        string $contentType,
        int $maxDepth,
    ): void {
        $position = 0;
        $body = $this->createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn(true);
        $body->method('tell')->willReturnCallback(static function () use (&$position): int {
            return $position;
        });
        $body->method('rewind')->willReturnCallback(static function () use (&$position): void {
            $position = 0;
        });
        $body->method('seek')->willReturnCallback(static function (int $next) use (&$position): void {
            $position = $next;
        });
        $body->method('eof')->willReturnCallback(static function () use (&$position, $payload): bool {
            return $position >= \strlen($payload);
        });
        $body->method('read')->willReturnCallback(static function (int $length) use (&$position, $payload): string {
            $part = \substr($payload, $position, $length);
            $position += \strlen($part);

            return $part;
        });
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getBody')->willReturn($body);
        $request->method('hasHeader')->willReturn(false);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Type' ? [$contentType] : [],
        );
        $request->expects(self::never())->method('withParsedBody');
        $request->expects(self::never())->method('withBody');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')
            ->with(
                self::callback(static function (\Throwable $failure) use ($payload): bool {
                    self::assertInstanceOf(BadJsonException::class, $failure);
                    self::assertSame(BadJsonException::ERROR_CODE, $failure->errorCode());
                    self::assertSame(BadJsonException::REASON_BAD_JSON, $failure->reason());
                    self::assertNull($failure->getPrevious());
                    self::assertStringNotContainsString($payload, $failure->getMessage());

                    return true;
                }),
            )
            ->willReturnCallback(static function (\Throwable $failure): ErrorDescriptor {
                $descriptor = new HttpExceptionMapper()->map($failure);
                self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
                self::assertSame(400, $descriptor->httpStatus());
                self::assertSame(BadJsonException::ERROR_CODE, $descriptor->code());
                self::assertSame(BadJsonException::REASON_BAD_JSON, $descriptor->message());
                self::assertSame([], $descriptor->extensions());

                return $descriptor;
            });
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $headers = [];
        $response->expects(self::exactly(3))->method('withHeader')->willReturnCallback(
            static function (string $name, string $value) use ($response, &$headers): ResponseInterface {
                $headers[$name] = $value;

                return $response;
            },
        );
        $response->expects(self::once())->method('withBody')->willReturn($response);
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(400)->willReturn($response);
        /** @var StreamInterface $stream */
        $stream = $this->createStub(StreamInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $safeBody = null;
        $streams->expects(self::once())->method('createStream')->willReturnCallback(
            static function (string $bytes) use (&$safeBody, $stream): StreamInterface {
                $safeBody = $bytes;

                return $stream;
            },
        );
        $boundary = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );
        $handle = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => self::CORRELATION,
        ]);
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'handled_error', null)->willReturn([]);
        $correlation = $this->createMock(CorrelationIdProviderInterface::class);
        $correlation->expects(self::once())->method('correlationId')->willReturn(self::CORRELATION);
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([
                $boundary,
                new RequestBodySizeLimitMiddleware($streams, 1024),
                new JsonBodyParserMiddleware($streams, true, $maxDepth, 1024),
            ], $terminal),
            new HttpResponseFinalizer($correlation, true, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder(new NoopMeter(), true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
        );

        self::assertSame($response, $kernel->handle($request));
        self::assertSame([
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Correlation-Id' => self::CORRELATION,
        ], $headers);
        self::assertSame([
            'status' => 400,
            'title' => 'Bad Request',
            'type' => 'about:blank',
        ], \json_decode($safeBody, true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($payload, $safeBody);
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function invalidBodies(): iterable
    {
        yield 'malformed-syntax' => ['{"secret":"never-export",', 'application/json', 32];
        yield 'scalar-root' => ['"private-scalar"', 'application/json', 32];
        yield 'excessive-depth' => ['{"nested":{"deep":true}}', 'application/json', 2];
        yield 'unsupported-encoding' => ['{"private":"value"}', 'application/json; charset=iso-8859-1', 32];
        yield 'duplicate-charset' => ['{"private":"value"}', 'application/json; charset=utf-8; charset=utf-8', 32];
    }

    public function testWithoutSelectedErrorBoundaryPreservesHttpOwnedException(): void
    {
        $bytes = '{"not":"complete"';
        $cursor = 0;
        $body = $this->createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn(true);
        $body->method('tell')->willReturnCallback(static function () use (&$cursor): int {
            return $cursor;
        });
        $body->method('rewind')->willReturnCallback(static function () use (&$cursor): void {
            $cursor = 0;
        });
        $body->method('seek')->willReturnCallback(static function (int $next) use (&$cursor): void {
            $cursor = $next;
        });
        $body->method('eof')->willReturnCallback(static function () use (&$cursor, $bytes): bool {
            return $cursor >= \strlen($bytes);
        });
        $body->method('read')->willReturnCallback(static function (int $length) use (&$cursor, $bytes): string {
            $part = \substr($bytes, $cursor, $length);
            $cursor += \strlen($part);

            return $part;
        });
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Type' ? ['application/json'] : [],
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        try {
            new JsonBodyParserMiddleware($streams, true, 32, 1024)->process($request, $terminal);
            self::fail('Unselected Problem Details must not normalize HTTP exceptions.');
        } catch (BadJsonException $failure) {
            self::assertSame(BadJsonException::ERROR_CODE, $failure->errorCode());
            self::assertSame(BadJsonException::REASON_BAD_JSON, $failure->reason());
            self::assertNull($failure->getPrevious());
        }
    }
}
