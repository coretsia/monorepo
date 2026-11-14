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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class JsonBodyParserPsr7ParsedBodyTest extends TestCase
{
    #[DataProvider('validJsonInputs')]
    public function testParsedBodyAndByteEquivalentRawBodySurviveImmutablePsr7Mutations(
        string $payload,
        array $expected,
        string $contentType,
        bool $sourceSeekable,
        bool $factorySeekable,
        int $originalPosition,
    ): void {
        $cursor = $originalPosition;
        $readLengths = [];
        $body = $this->stream($payload, $sourceSeekable, $cursor, $readLengths);
        $replacement = null;
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request->method('getHeader')->with('Content-Type')->willReturn([$contentType]);
        $rebodied = $this->createMock(ServerRequestInterface::class);
        $rebodied->method('getBody')->willReturnCallback(
            static function () use (&$replacement): StreamInterface {
                self::assertInstanceOf(StreamInterface::class, $replacement);

                return $replacement;
            },
        );
        $parsedRequest = $this->createStub(ServerRequestInterface::class);
        $parsedRequest->method('getParsedBody')->willReturn($expected);
        $request->expects($sourceSeekable ? self::never() : self::once())->method('withBody')
            ->willReturnCallback(
                static function (StreamInterface $stream) use (&$replacement, $rebodied): ServerRequestInterface {
                    $replacement = $stream;

                    return $rebodied;
                },
            );
        $request->expects($sourceSeekable ? self::once() : self::never())->method('withParsedBody')
            ->with($expected)->willReturn($parsedRequest);
        $rebodied->expects($sourceSeekable ? self::never() : self::once())->method('withParsedBody')
            ->with($expected)->willReturn($parsedRequest);
        $parsedRequest->method('getBody')->willReturnCallback(
            static function () use ($sourceSeekable, $body, &$replacement): StreamInterface {
                return $sourceSeekable ? $body : $replacement;
            },
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects($sourceSeekable ? self::never() : self::once())->method('createStream')
            ->willReturnCallback(function (string $buffer) use ($payload, $factorySeekable): StreamInterface {
                self::assertSame($payload, $buffer);
                $position = 0;
                $factoryReads = [];

                return $this->stream($buffer, $factorySeekable, $position, $factoryReads);
            });
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($parsedRequest)
            ->willReturnCallback(static function (ServerRequestInterface $incoming) use (
                $response,
                $payload,
                $expected,
                $sourceSeekable,
                $originalPosition,
                $body,
            ): ResponseInterface {
                self::assertSame($expected, $incoming->getParsedBody());
                $raw = $incoming->getBody();
                if ($sourceSeekable) {
                    self::assertSame($body, $raw);
                    self::assertSame($originalPosition, $raw->tell());
                    self::assertSame(\substr($payload, $originalPosition), $raw->getContents());
                    $raw->rewind();
                } else {
                    self::assertNotSame($body, $raw);
                    self::assertSame(0, $raw->tell());
                }
                self::assertSame($payload, $raw->getContents());

                return $response;
            });

        self::assertSame(
            $response,
            new JsonBodyParserMiddleware($streams, true, 32, 1024)
                ->process($request, $terminal),
        );
        self::assertLessThanOrEqual(1025, \array_sum($readLengths));
        foreach ($readLengths as $length) {
            self::assertGreaterThan(0, $length);
            self::assertLessThanOrEqual(1025, $length);
        }
    }

    /** @return iterable<string, array{string, array, string, bool, bool, int}> */
    public static function validJsonInputs(): iterable
    {
        yield 'object-seekable-with-nonzero-cursor' => [
            '{"name":"safe","items":[1,true]}',
            ['name' => 'safe', 'items' => [1, true]],
            'application/json; charset=UTF-8',
            true,
            true,
            6,
        ];
        yield 'list-nonseekable-factory-seekable' => [
            '[1,{"name":"safe"}]',
            [1, ['name' => 'safe']],
            'application/vnd.api+json',
            false,
            true,
            0,
        ];
        yield 'object-nonseekable-factory-nonseekable' => [
            '{"empty":{}}',
            ['empty' => []],
            'application/json',
            false,
            false,
            0,
        ];
        yield 'empty-object-seekable' => ['{}', [], 'application/json', true, true, 0];
        yield 'empty-list-nonseekable' => ['[]', [], 'application/json', false, false, 0];
    }

    #[DataProvider('factorySeekabilities')]
    public function testBodyLimiterAndJsonParserComposeWithEitherFactorySeekability(bool $factorySeekable): void
    {
        $payload = '{"message":"byte-equivalent"}';
        $cursor = 0;
        $reads = [];
        $original = $this->stream($payload, false, $cursor, $reads);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Type' ? ['application/json'] : [],
        );
        $request->method('hasHeader')->willReturn(false);
        $current = $original;
        $request->method('getBody')->willReturnCallback(static function () use (&$current): StreamInterface {
            return $current;
        });
        $request->expects(self::exactly($factorySeekable ? 1 : 2))->method('withBody')
            ->willReturnCallback(
                static function (StreamInterface $replacement) use (&$current, $request): ServerRequestInterface {
                    $current = $replacement;

                    return $request;
                },
            );
        $request->expects(self::once())->method('withParsedBody')
            ->with(['message' => 'byte-equivalent'])->willReturn($request);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::exactly($factorySeekable ? 1 : 2))->method('createStream')
            ->willReturnCallback(function (string $buffer) use ($payload, $factorySeekable): StreamInterface {
                self::assertSame($payload, $buffer);
                $position = 0;
                $reconstructionReads = [];

                return $this->stream($buffer, $factorySeekable, $position, $reconstructionReads);
            });
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')
            ->willReturnCallback(
                static function (ServerRequestInterface $incoming) use ($payload, $response): ResponseInterface {
                    self::assertSame($payload, $incoming->getBody()->getContents());

                    return $response;
                },
            );

        self::assertSame(
            $response,
            new Pipeline([
                new RequestBodySizeLimitMiddleware($streams, 1024),
                new JsonBodyParserMiddleware($streams, true, 32, 1024),
            ], $terminal)->handle($request),
        );
        self::assertLessThanOrEqual(1025, \array_sum($reads));
    }

    /** @return iterable<string, array{bool}> */
    public static function factorySeekabilities(): iterable
    {
        yield 'seekable-factory-result' => [true];
        yield 'nonseekable-factory-result' => [false];
    }

    public function testValidJsonCanExceedFoundationStrictDiagnosticJsonLimit(): void
    {
        $value = \str_repeat('z', 65_537);
        $payload = '{"value":"' . $value . '"}';
        self::assertGreaterThan(65_536, \strlen($payload));
        $position = 0;
        $reads = [];
        $body = $this->stream($payload, true, $position, $reads);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->with('Content-Type')->willReturn(['application/json']);
        $request->method('getBody')->willReturn($body);
        $request->expects(self::once())->method('withParsedBody')->with(['value' => $value])->willReturn($request);
        $request->expects(self::never())->method('withBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')
            ->willReturnCallback(
                static function (ServerRequestInterface $accepted) use ($body, $payload, $response): ResponseInterface {
                    self::assertSame($body, $accepted->getBody());
                    self::assertSame(0, $body->tell());
                    self::assertSame($payload, $body->getContents());

                    return $response;
                },
            );
        self::assertSame(
            $response,
            new JsonBodyParserMiddleware($streams, true, 32, 100_000)
                ->process($request, $terminal),
        );
        self::assertLessThanOrEqual(100_001, \array_sum($reads));
    }

    #[DataProvider('invalidJsonInputs')]
    public function testUnsafeJsonOrUnsupportedEncodingFailsWithoutPayloadDisclosure(
        string $payload,
        string $contentType,
        int $depth,
    ): void {
        $position = 0;
        $reads = [];
        $body = $this->stream($payload, true, $position, $reads);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->with('Content-Type')->willReturn([$contentType]);
        $request->method('getBody')->willReturn($body);
        $request->expects(self::never())->method('withBody');
        $request->expects(self::never())->method('withParsedBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        try {
            new JsonBodyParserMiddleware($streams, true, $depth, 1024)->process($request, $terminal);
            self::fail('Malformed or unsupported JSON must be rejected.');
        } catch (BadJsonException $failure) {
            self::assertSame(BadJsonException::ERROR_CODE, $failure->errorCode());
            self::assertSame(BadJsonException::REASON_BAD_JSON, $failure->reason());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString($payload, $failure->getMessage());
        }
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function invalidJsonInputs(): iterable
    {
        yield 'malformed-object' => ['{"private":"value"', 'application/json', 32];
        yield 'scalar-string-root' => ['"private-token"', 'application/json', 32];
        yield 'scalar-numeric-root' => ['42', 'application/json', 32];
        yield 'scalar-boolean-root' => ['true', 'application/json', 32];
        yield 'scalar-null-root' => ['null', 'application/json', 32];
        yield 'nesting-overflow' => ['{"a":{"b":true}}', 'application/json', 2];
        yield 'unsupported-charset' => ['{"x":1}', 'application/json; charset=iso-8859-1', 32];
        yield 'duplicate-charset' => ['{"x":1}', 'application/json; charset=utf-8; charset=utf-8', 32];
        yield 'malformed-json-media-parameter' => ['{"x":1}', 'application/json; charset=', 32];
        yield 'unsupported-vendor-json-encoding' => ['{"x":1}', 'application/merge-patch+json; charset=utf-16', 32];
    }

    /** @param list<string> $contentTypes */
    #[DataProvider('ambiguousContentTypes')]
    public function testAmbiguousContentTypeIsRejectedBeforeBodyAccess(array $contentTypes): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->with('Content-Type')->willReturn($contentTypes);
        $request->method('hasHeader')->with('Content-Type')->willReturn(true);
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('withBody');
        $request->expects(self::never())->method('withParsedBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        try {
            new JsonBodyParserMiddleware($streams, true, 32, 1024)->process($request, $terminal);
            self::fail('Ambiguous Content-Type must be rejected.');
        } catch (BadJsonException $failure) {
            self::assertSame(BadJsonException::ERROR_CODE, $failure->errorCode());
            self::assertSame(BadJsonException::REASON_BAD_JSON, $failure->reason());
            self::assertNull($failure->getPrevious());
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function ambiguousContentTypes(): iterable
    {
        yield 'present-empty-header' => [[]];
        yield 'empty-media-value' => [['']];
        yield 'malformed-media-type' => [['application json']];
        yield 'duplicate-json' => [['application/json', 'application/json']];
        yield 'conflicting-media-types' => [['application/json', 'text/plain']];
        yield 'combined-media-types' => [['application/json, text/plain']];
        yield 'oversized-content-type' => [['application/json; charset=utf-8; ' . \str_repeat('x', 513)]];
    }

    public function testOversizedJsonIsRejectedWithFixedReasonAndBoundedReading(): void
    {
        $payload = '{"secret":"' . \str_repeat('x', 200) . '"}';
        $position = 0;
        $reads = [];
        $body = $this->stream($payload, true, $position, $reads);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->with('Content-Type')->willReturn(['application/json']);
        $request->method('getBody')->willReturn($body);
        $request->expects(self::never())->method('withParsedBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        $this->expectException(BadJsonException::class);
        try {
            new JsonBodyParserMiddleware($streams, true, 32, 32)->process($request, $terminal);
        } finally {
            self::assertSame(33, \array_sum($reads));
            self::assertSame(0, $position);
        }
    }

    #[DataProvider('nonJsonInputs')]
    public function testNonJsonOrDisabledParsingLeavesOriginalRequestUnchanged(
        bool $enabled,
        string $contentType,
    ): void {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeader')->with('Content-Type')->willReturn([$contentType]);
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('withBody');
        $request->expects(self::never())->method('withParsedBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame(
            $response,
            new JsonBodyParserMiddleware($streams, $enabled, 32, 1024)
                ->process($request, $terminal),
        );
    }

    /** @return iterable<string, array{bool, string}> */
    public static function nonJsonInputs(): iterable
    {
        yield 'plain-text' => [true, 'text/plain'];
        yield 'url-encoded' => [true, 'application/x-www-form-urlencoded'];
        yield 'disabled-json' => [false, 'application/json'];
    }

    public function testParsedJsonNeverEntersRequestCompletionTelemetry(): void
    {
        $payload = '{"secret":"PRIVATE_PARSED_JSON_VALUE"}';
        $cursor = 0;
        $reads = [];
        $body = $this->stream($payload, true, $cursor, $reads);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getBody')->willReturn($body);
        $request->method('hasHeader')->willReturn(false);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Type' ? ['application/json'] : [],
        );
        $request->method('getParsedBody')->willReturn(['secret' => 'PRIVATE_PARSED_JSON_VALUE']);
        $request->expects(self::once())->method('withParsedBody')
            ->with(['secret' => 'PRIVATE_PARSED_JSON_VALUE'])->willReturn($request);
        $request->expects(self::never())->method('withBody');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)
            ->willReturnCallback(
                static function (ServerRequestInterface $parsed) use ($response, $payload): ResponseInterface {
                    self::assertSame(['secret' => 'PRIVATE_PARSED_JSON_VALUE'], $parsed->getParsedBody());
                    self::assertSame($payload, $parsed->getBody()->getContents());

                    return $response;
                },
            );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $handle = new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'type' => 'http',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
        $runtime->expects(self::once())->method('beginUnitOfWork')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'success', null)->willReturn([]);
        $telemetry = [];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->willReturnCallback(static function (string $name, int $delta, array $labels) use (&$telemetry): void {
                $telemetry[] = [$name, $delta, $labels];
            });
        $meter->expects(self::once())->method('observe')
            ->willReturnCallback(static function (string $name, int $duration, array $labels) use (&$telemetry): void {
                $telemetry[] = [$name, $duration, $labels];
            });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$telemetry): void {
                $telemetry[] = [$event, $context];
            });
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(static function (string $name, mixed $value) use (&$telemetry): void {
                $telemetry[] = [$name, $value];
            });
        $span->expects(self::once())->method('end');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->willReturnCallback(
            static function (string $name, array $attributes) use ($span, &$telemetry): SpanInterface {
                $telemetry[] = [$name, $attributes];

                return $span;
            },
        );
        $correlation = $this->createMock(CorrelationIdProviderInterface::class);
        $correlation->expects(self::never())->method('correlationId');
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            new Pipeline([
                new RequestBodySizeLimitMiddleware($streams, 1024),
                new JsonBodyParserMiddleware($streams, true, 32, 1024),
            ], $terminal),
            new HttpResponseFinalizer($correlation, false, 'X-Correlation-Id'),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter($logger, true),
            new HttpRequestSpanRecorder($tracer, true),
        );

        self::assertSame($response, $kernel->handle($request));
        self::assertCount(7, $telemetry);
        $exported = \json_encode($telemetry, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('PRIVATE_PARSED_JSON_VALUE', $exported);
        self::assertStringNotContainsString($payload, $exported);
        self::assertStringNotContainsString('secret', $exported);
    }

    /** @param list<int> $readLengths */
    private function stream(string $bytes, bool $seekable, int &$cursor, array &$readLengths): StreamInterface
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn($seekable);
        $body->method('tell')->willReturnCallback(static function () use (&$cursor): int {
            return $cursor;
        });
        $body->method('rewind')->willReturnCallback(static function () use (&$cursor, $seekable): void {
            if (!$seekable) {
                throw new \RuntimeException('not-seekable');
            }
            $cursor = 0;
        });
        $body->method('seek')->willReturnCallback(static function (int $next) use (&$cursor, $seekable): void {
            if (!$seekable) {
                throw new \RuntimeException('not-seekable');
            }
            $cursor = $next;
        });
        $body->method('eof')->willReturnCallback(static function () use (&$cursor, $bytes): bool {
            return $cursor >= \strlen($bytes);
        });
        $body->method('read')->willReturnCallback(
            static function (int $length) use ($bytes, &$cursor, &$readLengths): string {
                $readLengths[] = $length;
                $chunk = \substr($bytes, $cursor, $length);
                $cursor += \strlen($chunk);

                return $chunk;
            },
        );
        $body->method('getContents')->willReturnCallback(static function () use ($bytes, &$cursor): string {
            $remaining = \substr($bytes, $cursor);
            $cursor = \strlen($bytes);

            return $remaining;
        });

        return $body;
    }
}
