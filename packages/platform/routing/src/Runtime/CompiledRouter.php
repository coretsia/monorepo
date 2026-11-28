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
 * for every match invocation; successful matches write the complete canonical
 * template before the observer emits completion signals. Method enumeration
 * uses the same concrete-path admission without context mutation or observation.
 * Raw path and host are ephemeral.
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

            if (!self::admitsPath($path)) {
                return null;
            }

            $match = $this->routes->match($method, $path);
            if ($match !== null) {
                $this->contextWriter->set(ContextKeys::PATH_TEMPLATE, $match->pathTemplate());
            }

            return $match;
        });
    }

    /**
     * @return list<non-empty-string>
     */
    public function allowedMethods(string $path, ?string $host = null): array
    {
        // Host-based routing is deliberately unsupported by routes@1.
        if (!self::admitsPath($path)) {
            return [];
        }

        return $this->routes->allowedMethods($path);
    }

    private static function admitsPath(string $path): bool
    {
        try {
            if (ReservedPathMatcher::normalize($path) !== $path) {
                return false;
            }
            if ($path !== '/' && \str_ends_with($path, '/')) {
                return false;
            }

            return ReservedPathMatcher::match($path) === null;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
