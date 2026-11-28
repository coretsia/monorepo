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

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Compile\RouteTemplate;

/**
 * Immutable boot-loaded validated and ordered application route table.
 *
 * Construction hydrates only from RoutesArtifactReader. Matching never reads
 * the filesystem, discovers source providers, or mutates request-local state.
 *
 * @internal
 */
final readonly class CompiledRouteTable
{
    /**
     * @var list<array{route: RouteDefinition, template: RouteTemplate}>
     */
    private array $compiled;

    public function __construct(RoutesArtifactReader $reader)
    {
        $compiled = [];
        foreach ($reader->read() as $route) {
            $compiled[] = [
                'route' => $route,
                'template' => RouteTemplate::parse($route->pathTemplate(), $route->requirements()),
            ];
        }
        $this->compiled = $compiled;
    }

    /**
     * @return list<RouteDefinition>
     */
    public function routes(): array
    {
        $routes = [];
        foreach ($this->compiled as $entry) {
            $routes[] = $entry['route'];
        }
        return $routes;
    }

    /**
     * Returns the first match in canonical provider-emitted order.
     */
    public function match(string $method, string $path): ?RouteMatch
    {
        foreach ($this->compiled as $entry) {
            $route = $entry['route'];
            if (!\in_array($method, $route->methods(), true)) {
                continue;
            }

            $parameters = $entry['template']->match($path);
            if ($parameters === null) {
                continue;
            }

            return new RouteMatch(
                name: $route->name(),
                pathTemplate: $route->pathTemplate(),
                handler: $route->handler(),
                parameters: $parameters,
                metadata: $route->metadata(),
            );
        }

        return null;
    }

    /**
     * Enumerates declared methods across every matching hydrated route domain.
     *
     * @return list<non-empty-string>
     */
    public function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->compiled as $entry) {
            if ($entry['template']->match($path) === null) {
                continue;
            }

            foreach ($entry['route']->methods() as $method) {
                $methods[$method] = true;
            }
        }

        $methods = \array_keys($methods);
        \usort(
            $methods,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $methods;
    }
}
