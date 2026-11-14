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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
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

final class BodySizeLimitReturns413Test extends TestCase
{
    #[DataProvider('oversizedBodies')]
    public function testKnownAndUnknownOversizedBodiesProduceOneCanonicalSafe413(
        bool $seekable,
        ?string $declaredLength,
        string $bodyBytes,
    ): void {
        $limit = 5;
        $offset = 0;
        $readLengths = [];
        $body = $this->createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn($seekable);
        $body->method('tell')->willReturnCallback(static function () use (&$offset): int {
            return $offset;
        });
        $body->method('rewind')->willReturnCallback(static function () use (&$offset): void {
            $offset = 0;
        });
        $body->method('seek')->willReturnCallback(static function (int $position) use (&$offset): void {
            $offset = $position;
        });
        $body->method('eof')->willReturnCallback(static function () use (&$offset, $bodyBytes): bool {
            return $offset >= \strlen($bodyBytes);
        });
        $body->method('read')->willReturnCallback(
            static function (int $length) use (&$offset, &$readLengths, $bodyBytes): string {
                $readLengths[] = $length;
                $chunk = \substr($bodyBytes, $offset, $length);
                $offset += \strlen($chunk);

                return $chunk;
            },
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request->method('hasHeader')->willReturnCallback(
            static fn (string $name): bool => $name === 'Content-Length' && $declaredLength !== null,
        );
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Length' && $declaredLength !== null
                ? [$declaredLength] : [],
        );
        $request->expects(self::never())->method('withBody');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')
            ->with(
                self::callback(static function (\Throwable $failure) use ($bodyBytes): bool {
                    self::assertInstanceOf(BodyTooLargeException::class, $failure);
                    self::assertSame(BodyTooLargeException::ERROR_CODE, $failure->errorCode());
                    self::assertSame(BodyTooLargeException::REASON_PAYLOAD_TOO_LARGE, $failure->reason());
                    self::assertNull($failure->getPrevious());
                    self::assertStringNotContainsString('private', $failure->getMessage());
                    self::assertStringNotContainsString($bodyBytes, $failure->getMessage());

                    return true;
                }),
            )
            ->willReturnCallback(static function (\Throwable $failure): ErrorDescriptor {
                $descriptor = new HttpExceptionMapper()->map($failure);
                self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
                self::assertSame(413, $descriptor->httpStatus());
                self::assertSame([], $descriptor->extensions());

                return $descriptor;
            });
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(413);
        $headers = [];
        $response->expects(self::exactly(2))->method('withHeader')->willReturnCallback(
            static function (string $name, string $value) use ($response, &$headers): ResponseInterface {
                $headers[$name] = $value;

                return $response;
            },
        );
        $response->expects(self::once())->method('withBody')->willReturn($response);
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(413)->willReturn($response);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $problemBody = null;
        $stream = $this->createStub(StreamInterface::class);
        $streams->expects(self::once())->method('createStream')->willReturnCallback(
            static function (string $bytes) use (&$problemBody, $stream): StreamInterface {
                $problemBody = $bytes;

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

        $pipeline = new Pipeline([$boundary, new RequestBodySizeLimitMiddleware($streams, $limit)], $terminal);
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame(413, $response->getStatusCode());
        self::assertSame([
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
        ], $headers);
        self::assertIsString($problemBody);
        self::assertSame([
            'status' => 413,
            'title' => 'Content Too Large',
            'type' => 'about:blank',
        ], \json_decode($problemBody, true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private', $problemBody);
        self::assertStringNotContainsString($bodyBytes, $problemBody);
        self::assertLessThanOrEqual($limit + 1, \array_sum($readLengths));
        foreach ($readLengths as $length) {
            self::assertGreaterThan(0, $length);
            self::assertLessThanOrEqual($limit + 1, $length);
        }
    }

    /** @return iterable<string, array{bool, ?string, string}> */
    public static function oversizedBodies(): iterable
    {
        yield 'known-length-preflight' => [true, '9', 'private-body'];
        yield 'unknown-seekable-body' => [true, null, 'private-body'];
        yield 'unknown-nonseekable-body' => [false, null, 'private-body'];
        yield 'understated-known-length' => [false, '2', 'private-body'];
    }
}
