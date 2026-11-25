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

namespace Coretsia\Platform\Routing\Compile;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Psr\Container\ContainerInterface;

/**
 * Tag-based discovery of flat source route descriptors, invoked at compile time.
 *
 * Construction is lazy and never resolves provider services or consumes routes.
 * TagRegistry owns deduplication and canonical priority/service-id ordering.
 *
 * @internal
 */
final readonly class RouteProviderRegistry
{
    public function __construct(
        private TagRegistry $tags,
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return list<RouteDefinition>
     */
    public function routes(): array
    {
        $identities = [];
        $flattened = [];

        foreach ($this->tags->all(ReservedTags::ROUTING_ROUTE_PROVIDER) as $tagged) {
            if ($tagged->meta() !== []) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
                );
            }

            try {
                $provider = $this->container->get($tagged->id());
                if (!$provider instanceof RouteProviderInterface) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
                    );
                }
                $identity = $provider->id();
            } catch (\Throwable) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
                );
            }

            if (
                $identity === ''
                || \strlen($identity) > 256
                || \preg_match('/\A[A-Za-z_][A-Za-z0-9_.:-]*\z/D', $identity) !== 1
            ) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
                );
            }
            if (isset($identities[$identity])) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_IDENTITY_DUPLICATE,
                );
            }
            $identities[$identity] = true;

            try {
                $routes = $provider->routes();
            } catch (\Throwable) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
                );
            }

            if (!\array_is_list($routes)) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
                );
            }
            $localNames = [];
            foreach ($routes as $route) {
                if (!$route instanceof RouteDefinition) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
                    );
                }
                if (isset($localNames[$route->name()])) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
                    );
                }
                if (\count($flattened) >= RoutesArtifactSchema::MAX_ROUTES) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
                    );
                }
                $localNames[$route->name()] = true;
                $flattened[] = $route;
            }
        }
        return $flattened;
    }
}
