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

use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BodySizeLimitStreamParityTest extends TestCase
{
    #[DataProvider('acceptedStreams')]
    public function testAcceptedStreamsPreserveFullBytesAndInitialSeekableCursor(
        bool $seekable,
        bool $declared,
        int $initialPosition,
        bool $factorySeekable,
    ): void {
        $payload = 'abcde';
        $reads = [];
        $position = $initialPosition;
        $body = $this->stream($payload, $seekable, $position, $reads);
        $current = $body;
        $request = $this->request($current, $declared ? ['5'] : [], $declared);
        $request->expects($seekable ? self::never() : self::once())->method('withBody')
            ->willReturnCallback(
                static function (StreamInterface $replacement) use (&$current, $request): ServerRequestInterface {
                    $current = $replacement;

                    return $request;
                },
            );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects($seekable ? self::never() : self::once())->method('createStream')
            ->willReturnCallback(function (string $buffer) use ($payload, $factorySeekable): StreamInterface {
                self::assertSame($payload, $buffer);
                $cursor = 0;
                $replacementReads = [];

                return $this->stream($buffer, $factorySeekable, $cursor, $replacementReads);
            });
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)
            ->willReturnCallback(static function (ServerRequestInterface $accepted) use (
                $response,
                $body,
                $payload,
                $seekable,
                $initialPosition,
            ): ResponseInterface {
                $verified = $accepted->getBody();
                if ($seekable) {
                    self::assertSame($body, $verified);
                    self::assertSame($initialPosition, $verified->tell());
                    self::assertSame(\substr($payload, $initialPosition), $verified->getContents());
                    $verified->rewind();
                } else {
                    self::assertNotSame($body, $verified);
                    self::assertSame(0, $verified->tell());
                }
                self::assertSame($payload, $verified->getContents());

                return $response;
            });

        self::assertSame($response, new RequestBodySizeLimitMiddleware($streams, 5)->process($request, $handler));
        self::assertLessThanOrEqual(6, \array_sum($reads));
        self::assertNotEmpty($reads);
        foreach ($reads as $length) {
            self::assertLessThanOrEqual(6, $length);
        }
    }

    /** @return iterable<string, array{bool, bool, int, bool}> */
    public static function acceptedStreams(): iterable
    {
        yield 'seekable-known-nonzero-cursor' => [true, true, 3, true];
        yield 'seekable-unknown-zero-cursor' => [true, false, 0, true];
        yield 'nonseekable-known-seekable-factory' => [false, true, 0, true];
        yield 'nonseekable-unknown-nonseekable-factory' => [false, false, 0, false];
    }

    #[DataProvider('overflowStreams')]
    public function testOverflowUsesAtMostLimitPlusOneBytesRegardlessOfLengthOrSeekability(
        bool $seekable,
        ?string $declared,
        int $initialPosition,
    ): void {
        $payload = 'private-payload-not-for-diagnostics';
        $reads = [];
        $position = $initialPosition;
        $body = $this->stream($payload, $seekable, $position, $reads);
        $current = $body;
        $request = $this->request($current, $declared === null ? [] : [$declared], $declared !== null);
        $request->expects(self::never())->method('withBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $middleware = new RequestBodySizeLimitMiddleware($streams, 5);

        try {
            $middleware->process($request, $handler);
            self::fail('An oversized request must be rejected.');
        } catch (BodyTooLargeException $failure) {
            self::assertSame(BodyTooLargeException::ERROR_CODE, $failure->errorCode());
            self::assertSame(BodyTooLargeException::REASON_PAYLOAD_TOO_LARGE, $failure->reason());
            foreach (['private', 'diagnostics', $payload] as $fragment) {
                self::assertStringNotContainsString($fragment, $failure->getMessage());
            }
            self::assertNull($failure->getPrevious());
        }
        self::assertLessThanOrEqual(6, \array_sum($reads));
        if ($declared === null || $declared === '2') {
            self::assertSame(6, \array_sum($reads));
        } else {
            self::assertSame([], $reads);
        }
        if ($seekable) {
            self::assertSame($initialPosition, $position);
        }
    }

    /** @return iterable<string, array{bool, ?string, int}> */
    public static function overflowStreams(): iterable
    {
        yield 'seekable-unknown-nonzero-cursor' => [true, null, 27];
        yield 'seekable-understated-length' => [true, '2', 27];
        yield 'nonseekable-unknown-length' => [false, null, 0];
        yield 'nonseekable-understated-length' => [false, '2', 0];
        yield 'known-declaration-exceeds-limit' => [true, '99', 2];
    }

    public function testOversizedSeekableBodyIsRejectedWhenUnreadRemainderFits(): void
    {
        $payload = 'abcdefg';
        $position = 4;
        $reads = [];
        $body = $this->stream($payload, true, $position, $reads);
        $current = $body;
        $request = $this->request($current, [], false);
        $request->expects(self::never())->method('withBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $this->expectException(BodyTooLargeException::class);
        try {
            new RequestBodySizeLimitMiddleware($streams, 5)->process($request, $handler);
        } finally {
            self::assertSame(4, $position);
            self::assertSame(6, \array_sum($reads));
        }
    }

    public function testDetectablyConsumedNonSeekableBodyIsNotPretendedComplete(): void
    {
        $position = 2;
        $reads = [];
        $body = $this->stream('abcdef', false, $position, $reads);
        $current = $body;
        $request = $this->request($current, [], false);
        $request->expects(self::never())->method('withBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $this->expectException(BodyTooLargeException::class);
        try {
            new RequestBodySizeLimitMiddleware($streams, 5)->process($request, $handler);
        } finally {
            self::assertSame([], $reads);
            self::assertSame(2, $position);
        }
    }

    #[DataProvider('invalidLengthHeaders')]
    public function testMalformedConflictingOrAmbiguousContentLengthIsRejectedBeforeBodyRead(
        array $header,
        bool $present,
        bool $transferEncoding,
    ): void {
        $position = 0;
        $reads = [];
        $body = $this->stream('secret', true, $position, $reads);
        $current = $body;
        $request = $this->request($current, $header, $present, $transferEncoding);
        $request->expects(self::never())->method('withBody');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $this->expectException(BodyTooLargeException::class);
        try {
            new RequestBodySizeLimitMiddleware($streams, 5)->process($request, $handler);
        } finally {
            self::assertSame([], $reads);
        }
    }

    /** @return iterable<string, array{list<string>, bool, bool}> */
    public static function invalidLengthHeaders(): iterable
    {
        yield 'empty-present-header' => [[], true, false];
        yield 'two-values' => [['5', '5'], true, false];
        yield 'comma-combined-values' => [['5, 5'], true, false];
        yield 'negative-length' => [['-1'], true, false];
        yield 'unbounded-length' => [[\str_repeat('9', 65)], true, false];
        yield 'oversized-length' => [['6'], true, false];
        yield 'content-length-and-transfer-encoding' => [['2'], true, true];
    }

    /** @param list<string> $lengths */
    private function request(
        StreamInterface &$body,
        array $lengths,
        bool $hasLength,
        bool $hasTransferEncoding = false,
    ): ServerRequestInterface&MockObject {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getBody')->willReturnCallback(static function () use (&$body): StreamInterface {
            return $body;
        });
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $name === 'Content-Length' ? $lengths : [],
        );
        $request->method('hasHeader')->willReturnCallback(
            static fn (string $name): bool => match ($name) {
                'Content-Length' => $hasLength,
                'Transfer-Encoding' => $hasTransferEncoding,
                default => false,
            },
        );

        return $request;
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
        $body->method('seek')->willReturnCallback(static function (int $offset) use (&$cursor, $seekable): void {
            if (!$seekable) {
                throw new \RuntimeException('not-seekable');
            }
            $cursor = $offset;
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
