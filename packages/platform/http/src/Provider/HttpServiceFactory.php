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
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Debug\DebugGuard;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stateless canonical HTTP service construction helper.
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

    public static function securityHeaders(
        ConfigRepositoryInterface $config,
    ): SecurityHeadersPolicy {
        $root = $config->get('http');
        if (!\is_array($root)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        $values = self::section($root, 'security_headers');

        return new SecurityHeadersPolicy(
            self::boolean($values, 'enabled'),
            self::string($values, 'x_content_type_options'),
            self::string($values, 'x_frame_options'),
            self::string($values, 'referrer_policy'),
            self::string($values, 'permissions_policy'),
            self::string($values, 'csp'),
        );
    }

    public static function httpsRedirect(
        ConfigRepositoryInterface $config,
    ): HttpsRedirectPolicy {
        $root = $config->get('http');
        if (!\is_array($root)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        $values = self::section($root, 'https_redirect');

        return new HttpsRedirectPolicy(
            self::boolean($values, 'enabled'),
            self::integer($values, 'status'),
            self::list($values, 'allow_methods'),
            self::string($values, 'target_authority'),
        );
    }

    public static function trustedProxy(
        ConfigRepositoryInterface $config,
    ): TrustedProxyPolicy {
        $root = $config->get('http');
        if (!\is_array($root)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        $values = self::section($root, 'proxy');

        return new TrustedProxyPolicy(
            self::list($values, 'trusted_proxies'),
        );
    }

    public static function debugGuard(
        ConfigRepositoryInterface $config,
        RuntimeDiagnosticsInput $runtime,
        ModulePlan $modulePlan,
        TrustedProxyPolicy $trustedProxy,
    ): DebugGuard {
        $root = $config->get('http');
        if (!\is_array($root)) {
            throw new \InvalidArgumentException('http-config-invalid');
        }

        $values = self::section($root, 'debug');
        $endpoints = self::section($values, 'endpoints');
        $paths = self::section($values, 'paths');
        $configPolicy = self::section($values, 'config');
        $proxy = self::section($root, 'proxy');

        return new DebugGuard(
            $runtime,
            $modulePlan,
            $trustedProxy,
            self::boolean($proxy, 'enabled'),
            self::boolean($endpoints, 'enabled'),
            self::list($values, 'allowlist'),
            self::string($values, 'token_sha256'),
            self::list($paths, 'enabled'),
            self::list($configPolicy, 'allowlist'),
        );
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
