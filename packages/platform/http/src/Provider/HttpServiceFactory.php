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

namespace Coretsia\Platform\Http\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stateless canonical HTTP middleware construction helper.
 *
 * Resolves exactly the service IDs selected by HttpMiddlewareStackBuilder
 * through the final effective container. No secondary autowiring, definition
 * inspection, registry, caching or request-local state is introduced.
 *
 * @internal
 */
final class HttpServiceFactory
{
    public static function pipeline(
        ContainerInterface $container,
        ConfigRepositoryInterface $config,
        HttpMiddlewareStackBuilder $stack,
        RequestHandlerInterface $terminal,
    ): Pipeline {
        $root = $config->get('http');
        if (!\is_array($root)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        $middleware = [];
        foreach ($stack->build($root) as $id) {
            // The canonical container owns both explicit bindings and permitted
            // concrete-class autowiring, including its failure semantics.
            $service = $container->get($id);
            if (!$service instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('http-middleware-instance-invalid');
            }

            $middleware[] = $service;
        }

        return new Pipeline($middleware, $terminal);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function section(array $values, string $key): array
    {
        $value = $values[$key] ?? null;
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function boolean(array $values, string $key): bool
    {
        $value = $values[$key] ?? null;
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!\is_int($value)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<mixed>
     */
    public static function list(array $values, string $key): array
    {
        $value = $values[$key] ?? null;
        if (!\is_array($value) || !\array_is_list($value)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        return $value;
    }
}
