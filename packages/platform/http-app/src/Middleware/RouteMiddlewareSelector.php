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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;

/**
 * Selects the explicitly admitted route middleware service ids in declared order.
 *
 * The immutable configuration list defines admission only. Selection does not
 * resolve services or execute middleware; resolved-instance eligibility belongs
 * to the application dispatch boundary.
 *
 * @internal
 */
final readonly class RouteMiddlewareSelector
{
    public const string METADATA_KEY = 'http_app_middleware';

    /** @var list<non-empty-string> */
    public const array FORBIDDEN_ROLE_IDS = [
        RouterMiddleware::class,
        ActionDispatchMiddleware::class,
        'Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware',
    ];

    /** @var list<non-empty-string> */
    private array $serviceIds;

    public function __construct(
        ConfigRepositoryInterface $config,
    ) {
        $this->serviceIds = self::validateServiceIds($config->get('http_app.route_middleware'));
    }

    /**
     * Validates the ordered service-id list without resolving services.
     *
     * @return list<non-empty-string>
     */
    public static function validateServiceIds(mixed $value): array
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw new \InvalidArgumentException('http-app-route-middleware-invalid');
        }

        $validated = [];
        foreach ($value as $serviceId) {
            if (
                !\is_string($serviceId)
                || $serviceId === ''
                || \preg_match('/[\x00-\x1F\x7F]/', $serviceId) !== 0
                || \in_array($serviceId, $validated, true)
                || \in_array($serviceId, self::FORBIDDEN_ROLE_IDS, true)
            ) {
                throw new \InvalidArgumentException('http-app-route-middleware-invalid');
            }

            try {
                ContainerValueReference::service($serviceId);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException('http-app-route-middleware-invalid');
            }

            $validated[] = $serviceId;
        }

        return $validated;
    }

    /**
     * @return list<non-empty-string>
     */
    public function select(RouteMatch $match): array
    {
        $metadata = $match->metadata();
        if (!\array_key_exists(self::METADATA_KEY, $metadata)) {
            return [];
        }

        try {
            $selected = self::validateServiceIds($metadata[self::METADATA_KEY]);
        } catch (\InvalidArgumentException) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_MIDDLEWARE_INVALID);
        }

        foreach ($selected as $serviceId) {
            if (!\in_array($serviceId, $this->serviceIds, true)) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_MIDDLEWARE_INVALID);
            }
        }

        return $selected;
    }
}
