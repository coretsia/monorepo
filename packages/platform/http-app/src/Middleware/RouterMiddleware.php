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

namespace Coretsia\Platform\HttpApp\Middleware;

use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Exception\HttpMethodNotAllowedException;
use Coretsia\Platform\HttpApp\Exception\HttpNotFoundException;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Canonical non-terminal application matching contribution.
 *
 * Successful matches and reserved paths are handed off to the existing
 * downstream chain. Ordinary misses are classified without action resolution,
 * context mutation, or a second matching operation.
 */
final readonly class RouterMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (\array_key_exists(RouteDispatchState::ATTRIBUTE, $request->getAttributes())) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_STATE_INVALID);
        }

        $method = $request->getMethod();
        $uri = $request->getUri();
        $path = $uri->getPath();
        $host = $uri->getHost();
        $host = $host === '' ? null : $host;

        $match = $this->router->match($method, $path, $host);
        if ($match !== null) {
            $matchedRequest = $request->withAttribute(
                RouteDispatchState::ATTRIBUTE,
                RouteDispatchState::matched($request, $match),
            );

            return $handler->handle($matchedRequest);
        }

        try {
            $reserved = ReservedPathMatcher::match($path);
        } catch (\InvalidArgumentException $exception) {
            if ($exception->getMessage() !== 'http-reserved-path-invalid') {
                throw $exception;
            }

            throw HttpNotFoundException::notFound();
        }

        if ($reserved !== null) {
            return $handler->handle(
                $request->withAttribute(
                    RouteDispatchState::ATTRIBUTE,
                    RouteDispatchState::reserved($request),
                ),
            );
        }

        // HTTP methods are case-sensitive tokens, including extension methods.
        if (\preg_match('/\A[!#$%&\'*+\-.^_`|~0-9A-Za-z]+\z/', $method) !== 1) {
            throw HttpNotFoundException::notFound();
        }

        $allowedMethods = $this->router->allowedMethods($path, $host);
        if ($allowedMethods === []) {
            throw HttpNotFoundException::notFound();
        }

        throw HttpMethodNotAllowedException::methodNotAllowed($allowedMethods);
    }
}
