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

use Coretsia\Contracts\HttpApp\ActionInvokerInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Invokes explicitly registered application actions with named arguments.
 *
 * HTTP dispatch resolves the action once and uses that same instance for
 * argument resolution and invocation. Native invocation failures and action
 * failures propagate unchanged to the canonical outer error boundary.
 */
final readonly class ActionInvoker implements ActionInvokerInterface
{
    public function __construct(
        private HandlerResolver $handlerResolver,
        private ArgumentResolver $argumentResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(string $actionId, array $arguments = []): mixed
    {
        return $this->invokeResolved($this->handlerResolver->resolve($actionId), $arguments);
    }

    /**
     * Resolves and invokes one action using the effective HTTP request.
     *
     * @internal
     */
    public function dispatch(RouteMatch $match, ServerRequestInterface $request): mixed
    {
        $action = $this->handlerResolver->resolve($match->handler());
        $arguments = $this->argumentResolver->resolveForAction($action, $match, $request);

        return $this->invokeResolved($action, $arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function invokeResolved(object $action, array $arguments): mixed
    {
        $method = HandlerResolver::reflectAction($action);
        if (!\is_callable($action)) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_HANDLER_INVALID);
        }

        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $parameters[$name] = true;
            if (!$parameter->isOptional() && !\array_key_exists($name, $arguments)) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_INVALID);
            }
        }

        foreach ($arguments as $name => $_value) {
            if (!\is_string($name) || !isset($parameters[$name])) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ARGUMENT_INVALID);
            }
        }

        return $action(...$arguments);
    }
}
