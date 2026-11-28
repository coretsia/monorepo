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

namespace Coretsia\Platform\HttpApp\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\HttpApp\ActionInvokerInterface;
use Coretsia\Contracts\HttpApp\ArgumentResolverInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Error\HttpAppProblemMapper;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouteMiddlewareSelector;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical declarative source and compiled HttpApp service definitions.
 *
 * Semantic admission uses the effective config snapshot. Configured services
 * are required declaratively and resolved only at their runtime boundaries.
 * HTTP composition, graph completeness, and error handling remain owned by
 * the existing framework boundaries.
 *
 * @internal
 */
final class HttpAppServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $httpApp = $context->configRoot('http_app');
        $arguments = self::section($httpApp, 'arguments');
        $response = self::section($httpApp, 'response');

        $handlers = HandlerResolver::validateRegistrations($httpApp['handlers'] ?? null);
        $containerTypes = ArgumentResolver::validateContainerTypes($arguments['allow_container_types'] ?? null);
        $routeMiddleware = RouteMiddlewareSelector::validateServiceIds($httpApp['route_middleware'] ?? null);

        $allowRequest = self::boolean($arguments, 'allow_request');
        $allowRouteParams = self::boolean($arguments, 'allow_route_params');
        $jsonEnabled = self::boolean(self::section($response, 'json'), 'enabled');
        $htmlEnabled = self::boolean(self::section($response, 'html'), 'enabled');
        $tracingEnabled = self::boolean(self::section($httpApp, 'tracing'), 'enabled');
        $metricsEnabled = self::boolean(self::section($httpApp, 'metrics'), 'enabled');
        $loggingEnabled = self::boolean(self::section($httpApp, 'logging'), 'enabled');

        foreach ($handlers as $serviceId) {
            $definitions->requireService($serviceId);
        }

        foreach ($containerTypes as $type) {
            $definitions->requireService($type);
        }

        foreach ($routeMiddleware as $serviceId) {
            $definitions->requireService($serviceId);
        }

        $definitions
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(Container::class)
            ->requireService(RouterInterface::class)
            ->requireService(ResponseFactoryInterface::class)
            ->requireService(StreamFactoryInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
            ->requireService(Stopwatch::class)
            ->classService(
                HandlerResolver::class,
                HandlerResolver::class,
                [self::ref(Container::class), self::ref(ConfigRepositoryInterface::class)],
            )
            ->classService(
                ArgumentResolver::class,
                ArgumentResolver::class,
                [
                    self::ref(HandlerResolver::class),
                    self::ref(Container::class),
                    $allowRequest,
                    $allowRouteParams,
                    self::ref(ConfigRepositoryInterface::class),
                ],
            )
            ->classService(
                ActionInvoker::class,
                ActionInvoker::class,
                [self::ref(HandlerResolver::class), self::ref(ArgumentResolver::class)],
            )
            ->classService(
                ActionResponseMapper::class,
                ActionResponseMapper::class,
                [
                    self::ref(ResponseFactoryInterface::class),
                    self::ref(StreamFactoryInterface::class),
                    $jsonEnabled,
                    $htmlEnabled,
                ],
            )
            ->classService(
                RouterMiddleware::class,
                RouterMiddleware::class,
                [self::ref(RouterInterface::class)],
            )
            ->classService(
                HttpAppProblemMapper::class,
                HttpAppProblemMapper::class,
            )
            ->classService(
                RouteMiddlewareSelector::class,
                RouteMiddlewareSelector::class,
                [self::ref(ConfigRepositoryInterface::class)],
            )
            ->classService(
                ActionDispatchMiddleware::class,
                ActionDispatchMiddleware::class,
                [
                    self::ref(Container::class),
                    self::ref(RouteMiddlewareSelector::class),
                    self::ref(ActionInvoker::class),
                    self::ref(ActionResponseMapper::class),
                    self::ref(TracerPortInterface::class),
                    self::ref(MeterPortInterface::class),
                    self::ref(LoggerInterface::class),
                    self::ref(Stopwatch::class),
                    $tracingEnabled,
                    $metricsEnabled,
                    $loggingEnabled,
                ],
            )
            ->alias(ArgumentResolverInterface::class, ArgumentResolver::class)
            ->alias(ActionInvokerInterface::class, ActionInvoker::class)
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_APP,
                RouterMiddleware::class,
                priority: 100,
                meta: [],
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_ROUTE,
                ActionDispatchMiddleware::class,
                priority: -1000,
                meta: [],
            )
            ->tag(
                ReservedTags::ERROR_MAPPER,
                HttpAppProblemMapper::class,
                priority: 800,
                meta: [],
            );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function section(array $values, string $key): array
    {
        $value = $values[$key] ?? null;
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw new \InvalidArgumentException('http-app-config-invalid');
        }

        foreach ($value as $name => $_item) {
            if (!\is_string($name)) {
                throw new \InvalidArgumentException('http-app-config-invalid');
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function boolean(array $values, string $key): bool
    {
        $value = $values[$key] ?? null;
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('http-app-config-invalid');
        }

        return $value;
    }

    private static function ref(string $serviceId): ContainerValueReference
    {
        return ContainerValueReference::service($serviceId);
    }
}
