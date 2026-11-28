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

namespace Coretsia\Platform\HttpApp\Action;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\HttpApp\ArgumentResolverInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves named action arguments from explicitly admitted sources.
 *
 * Request injection, matched route parameters, allowlisted concrete services,
 * and declared PHP defaults are considered in that order. Selected invalid or
 * unavailable sources fail closed. The format-neutral port never consumes
 * transport objects from its optional context map.
 */
final readonly class ArgumentResolver implements ArgumentResolverInterface
{
    /** @var list<class-string> */
    private array $containerTypes;

    public function __construct(
        private HandlerResolver $handlerResolver,
        private Container $container,
        private bool $allowRequest,
        private bool $allowRouteParams,
        ConfigRepositoryInterface $config,
    ) {
        $this->containerTypes = self::validateContainerTypes($config->get('http_app.arguments.allow_container_types'));
    }

    /**
     * Validates declarative class identifiers without loading or resolving them.
     *
     * @return list<class-string>
     *
     * @internal
     */
    public static function validateContainerTypes(mixed $types): array
    {
        if (!\is_array($types) || !\array_is_list($types)) {
            throw new \InvalidArgumentException('http-app-container-types-invalid');
        }

        $validated = [];
        foreach ($types as $type) {
            if (!\is_string($type) || \in_array($type, $validated, true)) {
                throw new \InvalidArgumentException('http-app-container-types-invalid');
            }

            try {
                ContainerValueReference::class($type);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException('http-app-container-types-invalid');
            }

            /** @var class-string $type */
            $validated[] = $type;
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function resolve(string $actionId, RouteMatch $match, array $context = []): array
    {
        if ($actionId !== $match->handler()) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_INVALID);
        }

        return $this->resolveForAction($this->handlerResolver->resolve($actionId), $match, null);
    }

    /**
     * Resolves arguments for the same admitted action instance used by dispatch.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function resolveForAction(object $action, RouteMatch $match, ?ServerRequestInterface $request): array
    {
        $method = HandlerResolver::reflectAction($action);
        $routeParameters = $match->parameters();
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($this->allowRequest && $request !== null && $typeName === ServerRequestInterface::class) {
                $arguments[$name] = $request;
                continue;
            }

            if ($this->allowRouteParams && \array_key_exists($name, $routeParameters)) {
                if ($typeName !== null && $typeName !== 'mixed' && $typeName !== 'string') {
                    throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_INVALID);
                }

                $arguments[$name] = $routeParameters[$name];
                continue;
            }

            if (
                $type instanceof \ReflectionNamedType
                && !$type->isBuiltin()
                && \in_array($typeName, $this->containerTypes, true)
            ) {
                /** @var class-string $typeName */
                $arguments[$name] = $this->resolveContainerType($typeName);
                continue;
            }

            try {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[$name] = $parameter->getDefaultValue();
                    continue;
                }
            } catch (\Throwable) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
            }

            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
        }

        return $arguments;
    }

    /**
     * @param class-string $type
     */
    private function resolveContainerType(string $type): object
    {
        try {
            $class = new \ReflectionClass($type);
            if ($class->isInterface() || $class->isAbstract() || $class->isEnum() || $class->isTrait()) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
            }

            if (!\in_array($type, $this->container->serviceIds(), true)) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
            }

            $service = $this->container->get($type);
        } catch (\Throwable) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
        }

        if (!\is_object($service) || !$service instanceof $type) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_UNRESOLVABLE);
        }

        /** @var object $service */
        return $service;
    }
}
