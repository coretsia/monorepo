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
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;

/**
 * Resolves explicitly registered application actions through Foundation.
 *
 * The immutable lookup map contains logical action ids and service ids only.
 * Handler construction and lifecycle remain container-owned; no resolved action
 * or reflection state is retained by this resolver.
 *
 * @internal
 */
final readonly class HandlerResolver
{
    /** @var array<string, string> */
    private array $registrations;

    public function __construct(
        private Container $container,
        ConfigRepositoryInterface $config,
    ) {
        $this->registrations = self::validateRegistrations($config->get('http_app.handlers'));
    }

    /**
     * Validates the closed registration records without resolving services.
     *
     * @return array<string, string>
     */
    public static function validateRegistrations(mixed $registrations): array
    {
        if (!\is_array($registrations) || !\array_is_list($registrations)) {
            throw new \InvalidArgumentException('http-app-handlers-invalid');
        }

        $validated = [];
        foreach ($registrations as $registration) {
            if (!\is_array($registration) || \count($registration) !== 2) {
                throw new \InvalidArgumentException('http-app-handlers-invalid');
            }

            $actionId = $registration['action_id'] ?? null;
            $serviceId = $registration['service_id'] ?? null;
            if (
                !\is_string($actionId)
                || \strlen($actionId) > 256
                || \preg_match('/\A[A-Za-z_][A-Za-z0-9_.:-]*\z/', $actionId) !== 1
                || isset($validated[$actionId])
                || !\is_string($serviceId)
                || $serviceId === ''
                || \preg_match('/[\x00-\x1F\x7F]/', $serviceId) !== 0
            ) {
                throw new \InvalidArgumentException('http-app-handlers-invalid');
            }

            try {
                ContainerValueReference::service($serviceId);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException('http-app-handlers-invalid');
            }

            $validated[$actionId] = $serviceId;
        }

        return $validated;
    }

    public function resolve(string $actionId): object
    {
        $serviceId = $this->registrations[$actionId] ?? null;
        if ($serviceId === null) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_UNMAPPED);
        }

        try {
            if (!\in_array($serviceId, $this->container->serviceIds(), true)) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_UNAVAILABLE);
            }

            $action = $this->container->get($serviceId);
        } catch (\Throwable) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_UNAVAILABLE);
        }

        if (!\is_object($action)) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
        }

        self::reflectAction($action);

        return $action;
    }

    /**
     * Admits the actual invocation method without executing the action.
     *
     * Shared by the package-internal argument and invocation boundaries; the
     * returned reflection is invocation-local and MUST NOT be cached.
     *
     * @internal
     */
    public static function reflectAction(object $action): \ReflectionMethod
    {
        if ($action instanceof \Closure) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
        }

        try {
            $method = new \ReflectionMethod($action, '__invoke');
            if (!$method->isPublic() || $method->isStatic()) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (
                    $parameter->isPassedByReference()
                    || $parameter->isVariadic()
                    || ($type !== null && !$type instanceof \ReflectionNamedType)
                ) {
                    throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
                }
            }
        } catch (\Throwable) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
        }

        return $method;
    }
}
