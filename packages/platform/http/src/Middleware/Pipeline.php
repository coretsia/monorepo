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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Immutable PSR-15 middleware chain with invocation-local traversal state.
 *
 * The resolved middleware sequence and terminal handler are supplied by the
 * HTTP composition boundary. No request, cursor, or partially executed chain
 * is retained on the reusable Pipeline service.
 *
 * @internal
 */
final readonly class Pipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware;

    /**
     * @param list<MiddlewareInterface> $middleware
     */
    public function __construct(array $middleware, private RequestHandlerInterface $terminal)
    {
        if (!\array_is_list($middleware)) {
            throw new \InvalidArgumentException('http-pipeline-middleware-invalid');
        }

        foreach ($middleware as $entry) {
            if (!$entry instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('http-pipeline-middleware-invalid');
            }
        }

        $this->middleware = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $next = $this->terminal;

        for ($index = \count($this->middleware) - 1; $index >= 0; --$index) {
            $next = new readonly class($this->middleware[$index], $next) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $next->handle($request);
    }
}
