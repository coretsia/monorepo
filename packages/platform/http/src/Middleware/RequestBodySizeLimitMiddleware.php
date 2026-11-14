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

namespace Coretsia\Platform\Http\Middleware;

use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Strict bounded request-body verification before any request JSON parsing.
 *
 * Complete seekable streams are verified from offset zero and the original
 * position is restored. Complete unread non-seekable streams are reconstructed
 * through the configured PSR-17 factory, never through a local stream class.
 *
 * @internal
 */
final readonly class RequestBodySizeLimitMiddleware implements MiddlewareInterface
{
    private const int MAX_CONTENT_LENGTH_BYTES = 64;

    public function __construct(
        private StreamFactoryInterface $streams,
        private int $maxBodyBytes,
    ) {
        if ($this->maxBodyBytes < 1 || $this->maxBodyBytes > 16_777_216) {
            throw new \InvalidArgumentException('http-request-body-limit-invalid');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentLength = $request->getHeader('Content-Length');
        if ($request->hasHeader('Content-Length') && (
            $contentLength === [] || $request->hasHeader('Transfer-Encoding')
        )) {
            throw BodyTooLargeException::tooLarge();
        }

        self::assertContentLength($contentLength, $this->maxBodyBytes);
        $body = $request->getBody();

        if ($body->isSeekable()) {
            try {
                $position = $body->tell();
                $body->rewind();
            } catch (\Throwable) {
                throw new \RuntimeException('http-request-body-stream-invalid');
            }

            $primary = null;
            try {
                self::readBounded($body, $this->maxBodyBytes);
            } catch (\Throwable $throwable) {
                $primary = $throwable;
                throw $throwable;
            } finally {
                try {
                    $body->seek($position);
                } catch (\Throwable) {
                    if ($primary === null) {
                        throw new \RuntimeException('http-request-body-stream-invalid');
                    }
                }
            }
        } else {
            try {
                $position = $body->tell();
                if ($position !== 0) {
                    throw BodyTooLargeException::tooLarge();
                }
            } catch (BodyTooLargeException $exception) {
                throw $exception;
            } catch (\Throwable) {
                // Some PSR-7 non-seekable streams cannot report a position.
            }

            $buffer = self::readBounded($body, $this->maxBodyBytes);
            try {
                $request = $request->withBody($this->streams->createStream($buffer));
            } catch (\Throwable) {
                throw new \RuntimeException('http-request-body-stream-invalid');
            }
        }

        return $handler->handle($request);
    }

    /**
     * @param list<string> $values
     */
    private static function assertContentLength(array $values, int $limit): void
    {
        if ($values === []) {
            return;
        }

        if (\count($values) !== 1 || $values[0] === ''
            || \strlen($values[0]) > self::MAX_CONTENT_LENGTH_BYTES
            || \preg_match('/\A[0-9]+\z/D', $values[0]) !== 1
        ) {
            throw BodyTooLargeException::tooLarge();
        }

        $length = \ltrim($values[0], '0');
        if ($length === '') {
            return;
        }

        $limitText = (string) $limit;
        if (\strlen($length) > \strlen($limitText)
            || (\strlen($length) === \strlen($limitText) && \strcmp($length, $limitText) > 0)
        ) {
            throw BodyTooLargeException::tooLarge();
        }
    }

    private static function readBounded(StreamInterface $body, int $limit): string
    {
        $buffer = '';
        while (\strlen($buffer) <= $limit) {
            try {
                if ($body->eof()) {
                    return $buffer;
                }

                $remaining = $limit + 1 - \strlen($buffer);
                $chunk = $body->read(\min(8192, $remaining));
            } catch (\Throwable) {
                throw new \RuntimeException('http-request-body-stream-invalid');
            }

            if ($chunk === '' || \strlen($chunk) > $remaining) {
                throw new \RuntimeException('http-request-body-stream-invalid');
            }

            $buffer .= $chunk;
            if (\strlen($buffer) > $limit) {
                throw BodyTooLargeException::tooLarge();
            }
        }

        throw BodyTooLargeException::tooLarge();
    }
}
