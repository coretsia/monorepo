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

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;

/**
 * Canonical HTTP tag/manual middleware service-id composition.
 *
 * TagRegistry owns the priority-descending and service-id-ascending order.
 * This builder performs no additional sorting, service resolution, middleware
 * instantiation, or Foundation container registration inspection.
 *
 * The returned outer-to-inner order allows post-slot middleware to decorate
 * returned short-circuit responses. The externally selected error boundary is
 * outside those decorators, so its caught exceptions are a separate path.
 *
 * @internal
 */
final readonly class HttpMiddlewareStackBuilder
{
    /** @var array<string, true> */
    private const array BOOLEAN_TOGGLES = [
        'http.correlation.enabled' => true,
        'http.tracing.enabled' => true,
        'http.metrics.enabled' => true,
        'http.access_log.enabled' => true,
        'http.proxy.enabled' => true,
        'http.context.enrich.enabled' => true,
        'http.hosts.enabled' => true,
        'http.request.json.enabled' => true,
    ];

    private const string ERROR_BOUNDARY_SERVICE_ID = 'Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware';

    public function __construct(private TagRegistry $tags)
    {
    }

    /**
     * @param array<string, mixed> $httpConfig Validated effective `http` subtree.
     * @return list<string> Ordered outer-to-inner canonical DI service ids.
     */
    public function build(array $httpConfig): array
    {
        $slotTags = [
            'system_pre' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            'system' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM,
            'app_pre' => ReservedTags::HTTP_MIDDLEWARE_APP_PRE,
            'app' => ReservedTags::HTTP_MIDDLEWARE_APP,
            'route_pre' => ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE,
            'route' => ReservedTags::HTTP_MIDDLEWARE_ROUTE,
            'system_post' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST,
            'app_post' => ReservedTags::HTTP_MIDDLEWARE_APP_POST,
            'route_post' => ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST,
        ];

        $manual = $httpConfig['middleware'] ?? null;
        if (!\is_array($manual) || \count($manual) !== \count($slotTags)) {
            throw new \InvalidArgumentException('http-middleware-config-invalid');
        }

        foreach (\array_keys($manual) as $slot) {
            if (!\is_string($slot) || !isset($slotTags[$slot])) {
                throw new \InvalidArgumentException('http-middleware-config-invalid');
            }
        }

        $slots = [];
        $participation = [];
        $errorBoundary = [];

        foreach ($slotTags as $slot => $tag) {
            $entries = [];
            $seen = [];

            foreach ($this->tags->all($tag) as $service) {
                $id = $service->id();
                if (!self::validServiceId($id)) {
                    throw new \InvalidArgumentException('http-middleware-service-id-invalid');
                }

                $metadata = $service->meta();
                if ($metadata !== [] && (
                    \count($metadata) !== 1
                        || !\array_key_exists('toggle', $metadata)
                        || !\is_string($metadata['toggle'])
                        || !isset(self::BOOLEAN_TOGGLES[$metadata['toggle']])
                )) {
                    throw new \InvalidArgumentException('http-middleware-tag-meta-invalid');
                }

                if ($slot === 'system_pre') {
                    if ($service->priority() >= 1000 && (
                        $service->priority() !== 1000
                            || $id !== self::ERROR_BOUNDARY_SERVICE_ID
                    )) {
                        throw new \InvalidArgumentException('http-middleware-error-boundary-invalid');
                    }
                }

                if ($id === self::ERROR_BOUNDARY_SERVICE_ID && (
                    $slot !== 'system_pre' || $service->priority() !== 1000 || $metadata !== []
                )) {
                    throw new \InvalidArgumentException('http-middleware-error-boundary-invalid');
                }

                if ($metadata !== [] && !self::enabled($metadata['toggle'], $httpConfig)) {
                    continue;
                }

                self::assertParticipation($participation, $id, $slot);

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                if ($id === self::ERROR_BOUNDARY_SERVICE_ID) {
                    $errorBoundary[] = $id;
                    continue;
                }

                $entries[] = $id;
            }

            $configured = $manual[$slot] ?? null;
            if (!\is_array($configured) || !\array_is_list($configured)) {
                throw new \InvalidArgumentException('http-middleware-manual-invalid');
            }

            foreach ($configured as $id) {
                if (!\is_string($id) || !self::validClassString($id)) {
                    throw new \InvalidArgumentException('http-middleware-manual-invalid');
                }

                if ($id === self::ERROR_BOUNDARY_SERVICE_ID) {
                    throw new \InvalidArgumentException('http-middleware-error-boundary-invalid');
                }

                self::assertParticipation($participation, $id, $slot);
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $entries[] = $id;
            }

            $slots[$slot] = $entries;
        }

        return [
            ...$errorBoundary,
            ...$slots['system_post'],
            ...$slots['app_post'],
            ...$slots['route_post'],
            ...$slots['system_pre'],
            ...$slots['system'],
            ...$slots['app_pre'],
            ...$slots['app'],
            ...$slots['route_pre'],
            ...$slots['route'],
        ];
    }

    /**
     * @param array<string, string> $participation
     */
    private static function assertParticipation(array &$participation, string $id, string $slot): void
    {
        if (isset($participation[$id]) && $participation[$id] !== $slot) {
            throw new \InvalidArgumentException('http-middleware-cross-slot-duplicate');
        }

        $participation[$id] = $slot;
    }

    private static function validServiceId(string $id): bool
    {
        return $id !== ''
            && \strlen($id) <= 256
            && \preg_match('/\s/u', $id) === 0
            && \preg_match('/\A[+-]?[0-9]+\z/D', $id) !== 1;
    }

    private static function validClassString(string $id): bool
    {
        return self::validServiceId($id)
            && \preg_match(
                '/\A[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*\z/D',
                $id,
            ) === 1;
    }

    /**
     * @param array<string, mixed> $httpConfig
     */
    private static function enabled(string $toggle, array $httpConfig): bool
    {
        $segments = \explode('.', $toggle);
        \array_shift($segments);
        $value = $httpConfig;

        foreach ($segments as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                throw new \InvalidArgumentException('http-middleware-toggle-invalid');
            }

            $value = $value[$segment];
        }

        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('http-middleware-toggle-invalid');
        }

        return $value;
    }
}
