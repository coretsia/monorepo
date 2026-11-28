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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Immutable PSR-15 middleware chain with invocation-local traversal state.
 *
 * The resolved middleware sequence and terminal handler are supplied by the
 * HTTP composition boundary. No request, cursor, or partially executed chain
 * is retained on the reusable Pipeline service.
 *
 * Guarded composition optionally enforces single-entry continuations, protected
 * request-attribute identity, and exact routing-input preservation.
 *
 * @api
 */
final readonly class Pipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware;

    /** @var list<non-empty-string> */
    private array $protectedRequestAttributes;

    /**
     * @param list<MiddlewareInterface> $middleware
     * @param list<non-empty-string> $protectedRequestAttributes
     */
    public function __construct(
        array $middleware,
        private RequestHandlerInterface $terminal,
        private bool $guardedContinuation,
        array $protectedRequestAttributes,
        private bool $protectRoutingInputs,
    ) {
        if (!\array_is_list($middleware)) {
            throw new \InvalidArgumentException('http-pipeline-middleware-invalid');
        }

        $resolved = [];
        foreach ($middleware as $entry) {
            if (!$entry instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('http-pipeline-middleware-invalid');
            }

            $resolved[] = $entry;
        }

        if (!\array_is_list($protectedRequestAttributes)
            || (!$guardedContinuation && ($protectedRequestAttributes !== [] || $protectRoutingInputs))
            || ($protectRoutingInputs && $protectedRequestAttributes === [])
        ) {
            throw new \InvalidArgumentException('http-pipeline-continuation-policy-invalid');
        }

        $attributes = [];
        foreach ($protectedRequestAttributes as $attribute) {
            if (!\is_string($attribute) || $attribute === '' || \in_array($attribute, $attributes, true)) {
                throw new \InvalidArgumentException('http-pipeline-continuation-policy-invalid');
            }

            $attributes[] = $attribute;
        }

        $this->middleware = $resolved;
        $this->protectedRequestAttributes = $attributes;
    }

    /**
     * Resolves the supplied middleware service ids in their existing order.
     *
     * @param list<non-empty-string> $serviceIds
     * @return list<MiddlewareInterface>
     */
    public static function resolveServices(ContainerInterface $container, array $serviceIds): array
    {
        if (!\array_is_list($serviceIds)) {
            throw new \InvalidArgumentException('http-middleware-service-id-invalid');
        }

        foreach ($serviceIds as $id) {
            if (!\is_string($id) || $id === '') {
                throw new \InvalidArgumentException('http-middleware-service-id-invalid');
            }
        }

        $middleware = [];
        foreach ($serviceIds as $id) {
            // The canonical container owns both explicit bindings and permitted
            // concrete-class autowiring, including its failure semantics.
            $service = $container->get($id);
            if (!$service instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('http-middleware-instance-invalid');
            }

            $middleware[] = $service;
        }

        return $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $next = $this->terminal;

        for ($index = \count($this->middleware) - 1; $index >= 0; --$index) {
            $next = new readonly class($this->middleware[$index], $next, $this->guardedContinuation, $this->protectedRequestAttributes, $this->protectRoutingInputs, ) implements RequestHandlerInterface {
                /** @param list<non-empty-string> $protectedRequestAttributes */
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                    private bool $guardedContinuation,
                    private array $protectedRequestAttributes,
                    private bool $protectRoutingInputs,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    if (!$this->guardedContinuation) {
                        return $this->middleware->process($request, $this->next);
                    }

                    $protectedValues = [];
                    if ($this->protectedRequestAttributes !== []) {
                        $attributes = $request->getAttributes();
                        foreach ($this->protectedRequestAttributes as $attribute) {
                            if (\array_key_exists($attribute, $attributes)) {
                                $protectedValues[$attribute] = $attributes[$attribute];
                            }
                        }
                    }

                    $routingInputs = null;
                    if ($this->protectRoutingInputs && $protectedValues !== []) {
                        $uri = $request->getUri();
                        $routingInputs = [$request->getMethod(), $uri->getPath(), $uri->getHost()];
                    }

                    $continuation = new class($this->next, $protectedValues, $routingInputs) implements RequestHandlerInterface {
                        private bool $entered = false;
                        private ?RequestHandlerInterface $next;

                        /**
                         * @param array<array-key, mixed> $protectedValues
                         * @param array{string, string, string}|null $routingInputs
                         */
                        public function __construct(
                            RequestHandlerInterface $next,
                            private array $protectedValues,
                            private ?array $routingInputs,
                        ) {
                            $this->next = $next;
                        }

                        private function __clone(): void
                        {
                        }

                        public function handle(ServerRequestInterface $request): ResponseInterface
                        {
                            if ($this->next === null) {
                                throw new \LogicException('http-pipeline-continuation-expired');
                            }

                            if ($this->entered) {
                                throw new \LogicException('http-pipeline-continuation-reused');
                            }

                            $this->entered = true;

                            if ($this->protectedValues !== []) {
                                $attributes = $request->getAttributes();
                                foreach ($this->protectedValues as $attribute => $value) {
                                    if (
                                        !\array_key_exists($attribute, $attributes)
                                        || $attributes[$attribute] !== $value
                                    ) {
                                        throw new \LogicException('http-pipeline-protected-attribute-changed');
                                    }
                                }
                            }

                            if ($this->routingInputs !== null) {
                                $uri = $request->getUri();
                                if (
                                    $this->routingInputs !== [
                                        $request->getMethod(),
                                        $uri->getPath(),
                                        $uri->getHost(),
                                    ]
                                ) {
                                    throw new \LogicException('http-pipeline-protected-routing-input-changed');
                                }
                            }

                            return $this->next->handle($request);
                        }

                        public function expire(): void
                        {
                            $this->next = null;
                            $this->protectedValues = [];
                            $this->routingInputs = null;
                        }
                    };

                    try {
                        return $this->middleware->process($request, $continuation);
                    } finally {
                        $continuation->expire();
                    }
                }
            };
        }

        return $next->handle($request);
    }
}
