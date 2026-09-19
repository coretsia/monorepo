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

namespace Coretsia\Foundation\Container;

use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use Coretsia\Foundation\Container\Internal\ContainerServiceIdPolicy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * PSR-11 Foundation container runtime.
 *
 * Container definitions are deterministic by construction:
 *
 * - explicit definitions are resolved by service id;
 * - concrete-class autowire is allowed only when `foundation.container.*`
 *   exists in the merged global configuration;
 * - interfaces and abstract classes are never autowired;
 * - runtime reset execution must not depend on autowire/reflection.
 *
 * Explicit definitions are shared by default unless explicitly marked
 * non-shared by container builder metadata.
 *
 * Explicit definitions may be shared or non-shared:
 *
 * - shared definitions are cached after first resolution;
 * - non-shared definitions are resolved fresh on every get();
 * - unregistered concrete-class autowire resolutions are not cached.
 *
 * This default is intentional: Foundation container wiring favors stable
 * runtime service identity only for explicit container ownership. Services
 * that require per-resolution instances must opt out explicitly. Unregistered
 * concrete-class autowire is a fallback resolution path and must not grow the
 * resolved service cache in long-running runtimes.
 *
 * Concrete-class autowire policy and reflection-based instantiation are owned
 * by ConcreteClassAutowireResolver. Container owns PSR-11 orchestration,
 * service-id validation, definition lifecycle, caching, and circular-reference
 * tracking only.
 *
 * This container must not emit stdout/stderr and must not expose constructor
 * arguments, instances, raw config payloads, environment values, tokens, or
 * absolute local paths through diagnostics.
 */
final class Container implements ContainerInterface
{
    private const bool DEFAULT_DEFINITION_SHARED = true;

    /**
     * @var array<string, mixed>
     */
    private array $definitions;

    /**
     * @var array<string, mixed>
     */
    private array $resolved;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, bool>
     */
    private array $definitionShared;

    private ConcreteClassAutowireResolver $autowireResolver;

    /**
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @param array<string, mixed> $definitions
     * @param array<string, mixed> $instances
     * @param array<string, mixed> $config
     * @param array<string, bool> $definitionShared
     */
    public function __construct(
        array $definitions = [],
        array $instances = [],
        array $config = [],
        array $definitionShared = [],
    ) {
        foreach ($definitions as $id => $_) {
            if (!\is_string($id)) {
                throw new ContainerException('container-service-id-invalid');
            }

            ContainerServiceIdPolicy::assertValid($id);
        }

        foreach ($instances as $id => $_) {
            if (!\is_string($id)) {
                throw new ContainerException('container-service-id-invalid');
            }

            ContainerServiceIdPolicy::assertValid($id);
        }

        foreach ($definitionShared as $id => $shared) {
            if (!\is_string($id)) {
                throw new ContainerException('container-service-id-invalid');
            }

            ContainerServiceIdPolicy::assertValid($id);

            if (!\is_bool($shared)) {
                throw new ContainerException('container-definition-shared-flag-invalid');
            }

            if (!\array_key_exists($id, $definitions)) {
                throw new ContainerException('container-definition-shared-flag-orphaned');
            }
        }

        $this->definitions = $definitions;
        $this->resolved = $instances;
        $this->config = $config;
        $this->definitionShared = [];
        $this->autowireResolver = new ConcreteClassAutowireResolver($config);

        foreach ($definitions as $id => $_definition) {
            $this->definitionShared[$id] = $definitionShared[$id] ?? self::DEFAULT_DEFINITION_SHARED;
        }
    }

    public function get(string $id): mixed
    {
        ContainerServiceIdPolicy::assertValid($id);

        if (\array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException('container-circular-reference');
        }

        $this->resolving[$id] = true;

        try {
            if (\array_key_exists($id, $this->definitions)) {
                $resolved = $this->resolveDefinition($id, $this->definitions[$id]);

                if ($this->definitionShared[$id] ?? self::DEFAULT_DEFINITION_SHARED) {
                    $this->resolved[$id] = $resolved;
                }

                return $resolved;
            }

            if (\class_exists($id)) {
                $resolved = $this->autowireResolver->instantiateIfAllowed($id, $this);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        } finally {
            unset($this->resolving[$id]);
        }

        throw new NotFoundException($id);
    }

    /**
     * Returns whether the container can resolve the id under Coretsia's strict
     * resolution policy.
     *
     * Invalid ids, unknown ids, and unbound interfaces/abstract classes return
     * false.
     *
     * Explicit definitions and already-registered instances return true.
     *
     * For unregistered existing concrete class ids, this method evaluates the same
     * strict concrete-class autowire policy as canAutowire(). Missing or invalid
     * foundation.container config therefore fails deterministically with
     * ContainerException instead of silently guessing autowire defaults.
     *
     * @throws ContainerExceptionInterface when strict concrete-class autowire
     *     policy cannot be evaluated because Foundation container config is
     *     missing or invalid.
     */
    public function has(string $id): bool
    {
        if (!ContainerServiceIdPolicy::isValid($id)) {
            return false;
        }

        if (\array_key_exists($id, $this->resolved) || \array_key_exists($id, $this->definitions)) {
            return true;
        }

        if (!\class_exists($id)) {
            return false;
        }

        return $this->canAutowire($id);
    }

    /**
     * Strict concrete-class autowire check.
     *
     * Missing `config['foundation']` or `config['foundation']['container']`
     * is a deterministic hard-fail. This intentionally prevents silently
     * guessing container defaults from inside runtime code.
     */
    public function canAutowire(string $id): bool
    {
        ContainerServiceIdPolicy::assertValid($id);

        return $this->autowireResolver->canAutowire($id);
    }

    /**
     * Returns service ids known to the container without exposing definitions,
     * resolved instances, constructor arguments, factories, or reflection data.
     *
     * @return list<string>
     */
    public function serviceIds(): array
    {
        $ids = \array_values(\array_unique([
            ...\array_keys($this->definitions),
            ...\array_keys($this->resolved),
        ]));

        \usort(
            $ids,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws NotFoundExceptionInterface
     */
    private function resolveDefinition(string $id, mixed $definition): mixed
    {
        if ($definition instanceof \Closure) {
            try {
                return $definition($this);
            } catch (ContainerException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new ContainerException('container-factory-failed', $e);
            }
        }

        if (\is_string($definition) && \class_exists($definition)) {
            return $this->autowire($definition);
        }

        return $definition;
    }

    /**
     * @param class-string $className
     * @return object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    private function autowire(string $className): object
    {
        return $this->autowireResolver->instantiate($className, $this);
    }
}
