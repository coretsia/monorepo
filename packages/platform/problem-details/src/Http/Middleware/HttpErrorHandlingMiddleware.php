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

namespace Coretsia\Platform\ProblemDetails\Http\Middleware;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Outer PSR-15 Throwable boundary for explicitly selected HTTP targets.
 *
 * Platform/errors remains the sole normalization and reporting owner. Failed
 * normalization or rendering degrades to a fixed safe HTTP 500 response;
 * failure to construct that response preserves the downstream Throwable.
 */
final class HttpErrorHandlingMiddleware implements MiddlewareInterface
{
    private const string EMERGENCY_BODY = "{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n";

    public function __construct(
        private readonly ErrorHandlerInterface $errorHandler,
        private readonly ErrorDescriptorToProblemDetailsAdapter $adapter,
        private readonly ProblemDetailsJsonRenderer $renderer,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $primary) {
            return $this->errorResponse($primary);
        }
    }

    private function errorResponse(Throwable $primary): ResponseInterface
    {
        $status = 500;
        $body = self::EMERGENCY_BODY;

        try {
            $descriptor = $this->errorHandler->handle($primary);
            $problem = $this->adapter->map($descriptor);
            $body = $this->renderer->render($problem);
            $status = $problem->status();
        } catch (Throwable) {
            // Never retry normalization or a failed renderer.
        }

        try {
            $response = $this->responses->createResponse($status);
            $stream = $this->streams->createStream($body);

            return $response
                ->withHeader('Content-Type', ProblemDetailsJsonRenderer::CONTENT_TYPE)
                ->withHeader('Cache-Control', 'no-store')
                ->withBody($stream);
        } catch (Throwable) {
            // Transport construction must not hide the original failure.
            throw $primary;
        }
    }
}
