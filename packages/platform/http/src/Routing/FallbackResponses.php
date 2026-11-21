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

namespace Coretsia\Platform\Http\Routing;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Deterministic response construction for terminal system endpoints and misses.
 *
 * Response finalization remains owned by the HTTP runtime.
 *
 * @internal
 */
final readonly class FallbackResponses
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    public function home(): ResponseInterface
    {
        return $this->respond(200, 'text/html; charset=utf-8', "<h1>Hello Coretsia</h1>\n");
    }

    public function ping(int $timestamp): ResponseInterface
    {
        return $this->respond(200, 'application/json', StableJsonEncoder::encodeStableMap([
            'ok' => true,
            'ts' => $timestamp,
        ]));
    }

    public function alive(): ResponseInterface
    {
        return $this->respond(200, 'text/plain; charset=utf-8', 'ALIVE');
    }

    public function healthUnavailable(): ResponseInterface
    {
        return $this->respond(501, 'application/json', StableJsonEncoder::encodeStableMap([
            'error' => 'owner missing',
        ]));
    }

    public function metricsUnavailable(): ResponseInterface
    {
        return $this->respond(501, 'text/plain; charset=utf-8', "owner missing\n");
    }

    public function notFound(): ResponseInterface
    {
        $response = $this->responses->createResponse(404);

        return self::requireResponse($response->withBody($this->streams->createStream('')));
    }

    private function respond(int $status, string $contentType, string $body): ResponseInterface
    {
        $response = $this->responses->createResponse($status);
        $response = self::requireResponse($response->withHeader('Content-Type', $contentType));
        $response = self::requireResponse($response->withHeader('Cache-Control', 'no-store'));

        return self::requireResponse($response->withBody($this->streams->createStream($body)));
    }

    private static function requireResponse(MessageInterface $response): ResponseInterface
    {
        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException('http-fallback-response-invalid');
        }

        return $response;
    }
}
