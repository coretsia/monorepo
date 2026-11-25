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

namespace Coretsia\Platform\Routing\Runtime;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;

/**
 * Artifact-only application routing through the immutable compiled route table.
 *
 * Routing owns only PATH_TEMPLATE context mutation. A prior template is removed
 * for every invocation; successful matches write the complete canonical template
 * before the observer emits completion signals. Raw path and host are ephemeral.
 *
 * @internal
 */
final readonly class CompiledRouter implements RouterInterface
{
    public function __construct(
        private CompiledRouteTable $routes,
        private ContextWriterInterface $contextWriter,
        private RoutingMatchObserver $observer,
    ) {
    }

    public function match(string $method, string $path, ?string $host = null): ?RouteMatch
    {
        // Host-based routing is deliberately unsupported by routes@1.
        return $this->observer->observe($method, function () use ($method, $path): ?RouteMatch {
            $this->contextWriter->remove(ContextKeys::PATH_TEMPLATE);

            try {
                if (ReservedPathMatcher::normalize($path) !== $path) {
                    return null;
                }
                if ($path !== '/' && \str_ends_with($path, '/')) {
                    return null;
                }
                if (ReservedPathMatcher::match($path) !== null) {
                    return null;
                }
            } catch (\InvalidArgumentException) {
                return null;
            }

            $match = $this->routes->match($method, $path);
            if ($match !== null) {
                $this->contextWriter->set(ContextKeys::PATH_TEMPLATE, $match->pathTemplate());
            }

            return $match;
        });
    }
}
