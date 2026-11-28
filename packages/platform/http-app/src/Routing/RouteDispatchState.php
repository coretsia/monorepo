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

namespace Coretsia\Platform\HttpApp\Routing;

use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Immutable request-local handoff from routing to application dispatch.
 *
 * A null match represents explicit reserved-path delegation. Raw routing inputs
 * are retained only for exact invocation-local comparisons, not diagnostics.
 * Continuation protection and original-state identity are owned by the Pipeline.
 */
final readonly class RouteDispatchState
{
    public const string ATTRIBUTE = 'coretsia.http_app.route_dispatch';

    private function __construct(
        private string $method,
        private string $path,
        private ?string $host,
        private ?RouteMatch $match,
    ) {
    }

    public static function matched(ServerRequestInterface $request, RouteMatch $match): self
    {
        return self::capture($request, $match);
    }

    public static function reserved(ServerRequestInterface $request): self
    {
        return self::capture($request, null);
    }

    public function match(): ?RouteMatch
    {
        return $this->match;
    }

    public function assertRequest(ServerRequestInterface $request): void
    {
        $uri = $request->getUri();
        $host = $uri->getHost();

        if (
            $this->method !== $request->getMethod()
            || $this->path !== $uri->getPath()
            || $this->host !== ($host === '' ? null : $host)
        ) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_INPUT_CHANGED);
        }
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $state = $request->getAttributes()[self::ATTRIBUTE] ?? null;
        if (!$state instanceof self) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_STATE_INVALID);
        }

        return $state;
    }

    private static function capture(ServerRequestInterface $request, ?RouteMatch $match): self
    {
        $uri = $request->getUri();
        $host = $uri->getHost();

        return new self(
            $request->getMethod(),
            $uri->getPath(),
            $host === '' ? null : $host,
            $match,
        );
    }
}
