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

use Coretsia\Platform\Http\Exception\BadJsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bounded UTF-8 JSON request parser preserving byte-equivalent raw bodies.
 *
 * The upstream body-limit middleware verifies every inbound byte. Parsing
 * independently retains that budget, including for standalone usage. Parsed
 * values remain on the PSR-7 request, never in shared context or diagnostics.
 *
 * @internal
 */
final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StreamFactoryInterface $streams,
        private bool $enabled,
        private int $maxDepth,
        private int $maxBodyBytes,
    ) {
        if ($this->maxDepth < 1 || $this->maxDepth > 512
            || $this->maxBodyBytes < 1 || $this->maxBodyBytes > 16_777_216
        ) {
            throw new \InvalidArgumentException('http-request-json-config-invalid');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $contentType = $request->getHeader('Content-Type');
        if ($contentType === [] && $request->hasHeader('Content-Type')) {
            throw BadJsonException::invalid();
        }

        if (!self::isJsonContentType($contentType)) {
            return $handler->handle($request);
        }

        $body = $request->getBody();
        if ($body->isSeekable()) {
            try {
                $position = $body->tell();
                $body->rewind();
            } catch (\Throwable) {
                throw BadJsonException::invalid();
            }

            try {
                $buffer = self::readBounded($body, $this->maxBodyBytes);
            } finally {
                try {
                    $body->seek($position);
                } catch (\Throwable) {
                    throw BadJsonException::invalid();
                }
            }
        } else {
            try {
                if ($body->tell() !== 0) {
                    throw BadJsonException::invalid();
                }
            } catch (BadJsonException $exception) {
                throw $exception;
            } catch (\Throwable) {
                // An unread non-seekable stream may not expose its cursor.
            }

            $buffer = self::readBounded($body, $this->maxBodyBytes);
            try {
                $request = $request->withBody($this->streams->createStream($buffer));
            } catch (\Throwable) {
                throw BadJsonException::invalid();
            }
        }

        try {
            $parsed = \json_decode($buffer, true, $this->maxDepth, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw BadJsonException::invalid();
        }

        $start = \ltrim($buffer, " \t\r\n");
        if (!\is_array($parsed) || $start === '' || ($start[0] !== '{' && $start[0] !== '[')) {
            throw BadJsonException::invalid();
        }

        return $handler->handle($request->withParsedBody($parsed));
    }

    /**
     * @param list<string> $values
     */
    private static function isJsonContentType(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        if (\count($values) !== 1 || \strlen($values[0]) > 512) {
            throw BadJsonException::invalid();
        }

        $parts = \explode(';', $values[0]);
        $media = \strtolower(\trim(\array_shift($parts), " \t"));
        if (\preg_match('/\A[!#$%&\'*+.^_`|~a-z0-9-]+\/[!#$%&\'*+.^_`|~a-z0-9-]+\z/D', $media) !== 1) {
            throw BadJsonException::invalid();
        }

        if ($media !== 'application/json'
            && \preg_match('/\Aapplication\/[a-z0-9!#$&^_.+-]+\+json\z/D', $media) !== 1
        ) {
            return false;
        }

        $charsetSeen = false;
        foreach ($parts as $part) {
            $part = \trim($part, " \t");
            if (\preg_match(
                '/\A([a-z0-9_-]+)[ \t]*=[ \t]*(?:"([a-z0-9._-]+)"|([a-z0-9._-]+))\z/iD',
                $part,
                $matches,
            ) !== 1) {
                throw BadJsonException::invalid();
            }

            if (\strcasecmp($matches[1], 'charset') !== 0) {
                continue;
            }

            if ($charsetSeen || \strcasecmp($matches[2] !== '' ? $matches[2] : $matches[3], 'utf-8') !== 0) {
                throw BadJsonException::invalid();
            }

            $charsetSeen = true;
        }

        return true;
    }

    private static function readBounded(StreamInterface $body, int $limit): string
    {
        $buffer = '';
        while (true) {
            try {
                if ($body->eof()) {
                    return $buffer;
                }

                $remaining = $limit + 1 - \strlen($buffer);
                if ($remaining <= 0) {
                    throw BadJsonException::invalid();
                }

                $chunk = $body->read(\min(8192, $remaining));
            } catch (BadJsonException $exception) {
                throw $exception;
            } catch (\Throwable) {
                throw BadJsonException::invalid();
            }

            if ($chunk === '' || \strlen($chunk) > $remaining) {
                throw BadJsonException::invalid();
            }

            $buffer .= $chunk;
            if (\strlen($buffer) > $limit) {
                throw BadJsonException::invalid();
            }
        }
    }
}
