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

namespace Coretsia\Platform\Routing\Definition;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Platform\Routing\Compile\RouteTemplate;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;

/**
 * Immutable, declaration-only nested route group for route providers.
 *
 * A group never crosses the RouteProviderInterface boundary. Flattening emits
 * ordinary RouteDefinition descriptors and preserves caller order.
 */
final readonly class RouteGroup
{
    /**
     * @param array<string, string> $requirements
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private string $pathPrefix,
        private string $namePrefix,
        private array $requirements,
        private array $metadata,
    ) {
    }

    public static function root(): self
    {
        return new self('/', '', [], []);
    }

    /**
     * @param array<string, string> $requirements
     * @param array<string, mixed> $metadata
     */
    public function group(string $pathPrefix, string $namePrefix, array $requirements, array $metadata): self
    {
        $child = RouteTemplate::parse($pathPrefix, $requirements);
        self::assertNamePrefix($namePrefix);
        $parent = RouteTemplate::parse($this->pathPrefix, $this->requirements);

        if (\array_intersect($parent->placeholderNames(), $child->placeholderNames()) !== []) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }

        $combinedPath = self::joinPaths($this->pathPrefix, $pathPrefix);
        $combinedRequirements = self::combineUnique(
            $this->requirements,
            $requirements,
            RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
        );
        RouteTemplate::parse($combinedPath, $combinedRequirements);
        $combinedName = self::joinNames($this->namePrefix, $namePrefix);
        $combinedMetadata = self::combineUnique(
            $this->metadata,
            self::normalizeMetadata($metadata),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );

        return new self($combinedPath, $combinedName, $combinedRequirements, $combinedMetadata);
    }

    public function route(RouteDefinition $route): RouteDefinition
    {
        $parent = RouteTemplate::parse($this->pathPrefix, $this->requirements);
        $child = RouteTemplate::parse($route->pathTemplate(), $route->requirements());
        if (\array_intersect($parent->placeholderNames(), $child->placeholderNames()) !== []) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }

        $path = self::joinPaths($this->pathPrefix, $route->pathTemplate());
        $requirements = self::combineUnique(
            $this->requirements,
            $route->requirements(),
            RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
        );
        RouteTemplate::parse($path, $requirements);
        $metadata = self::combineUnique(
            $this->metadata,
            self::normalizeMetadata($route->metadata()),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );

        try {
            return new RouteDefinition(
                name: self::joinNames($this->namePrefix, $route->name()),
                methods: $route->methods(),
                pathTemplate: $path,
                handler: $route->handler(),
                requirements: $requirements,
                defaults: $route->defaults(),
                metadata: $metadata,
            );
        } catch (\Throwable) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return list<RouteDefinition>
     */
    public function routes(array $routes): array
    {
        if (!\array_is_list($routes)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
            );
        }
        $flattened = [];
        foreach ($routes as $route) {
            if (!$route instanceof RouteDefinition) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
                );
            }
            $flattened[] = $this->route($route);
        }
        return $flattened;
    }

    private static function assertNamePrefix(string $prefix): void
    {
        if ($prefix !== '' && \preg_match(
            '/\A[A-Za-z_][A-Za-z0-9_-]*(?:\.[A-Za-z_][A-Za-z0-9_-]*)*\z/D',
            $prefix,
        ) !== 1) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
    }

    private static function joinPaths(string $parent, string $child): string
    {
        if ($parent === '/') {
            return $child;
        }
        if ($child === '/') {
            return $parent;
        }
        return $parent . $child;
    }

    private static function joinNames(string $parent, string $child): string
    {
        if ($parent === '') {
            return $child;
        }
        return $child === '' ? $parent : $parent . '.' . $child;
    }

    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    private static function combineUnique(array $parent, array $child, string $reason): array
    {
        foreach ($child as $key => $_) {
            if (\array_key_exists($key, $parent)) {
                throw RouteDefinitionInvalidException::withReason($reason);
            }
        }
        $merged = [...$parent, ...$child];
        \ksort($merged, \SORT_STRING);
        return $merged;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        if ($metadata !== [] && \array_is_list($metadata)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
        try {
            $normalized = JsonLikeNormalizer::normalize($metadata, 'routingMetadata');
            if (!\is_array($normalized)) {
                throw new \InvalidArgumentException('routing-metadata-invalid');
            }
            return $normalized;
        } catch (\Throwable) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
    }
}
