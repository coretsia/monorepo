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
 * This container must not emit stdout/stderr and must not expose constructor
 * arguments, instances, raw config payloads, environment values, tokens, or
 * absolute local paths through diagnostics.
 */
final class Container implements ContainerInterface
{
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
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @param array<string, mixed> $definitions
     * @param array<string, mixed> $instances
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $definitions = [],
        array $instances = [],
        array $config = [],
    ) {
        foreach ($definitions as $id => $_) {
            self::assertServiceId($id);
        }

        foreach ($instances as $id => $_) {
            self::assertServiceId($id);
        }

        $this->definitions = $definitions;
        $this->resolved = $instances;
        $this->config = $config;
    }

    public function get(string $id): mixed
    {
        self::assertServiceId($id);

        if (\array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException('container-circular-reference');
        }

        $this->resolving[$id] = true;

        try {
            if (\array_key_exists($id, $this->definitions)) {
                $this->resolved[$id] = $this->resolveDefinition($id, $this->definitions[$id]);

                return $this->resolved[$id];
            }

            if (\class_exists($id) && $this->canAutowire($id)) {
                $this->resolved[$id] = $this->autowire($id);

                return $this->resolved[$id];
            }
        } finally {
            unset($this->resolving[$id]);
        }

        throw new NotFoundException($id);
    }

    public function has(string $id): bool
    {
        if (!self::isValidServiceId($id)) {
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
        self::assertServiceId($id);

        $containerConfig = $this->containerConfig();

        $autowireConcrete = $containerConfig['autowire_concrete'] ?? null;
        $allowReflection = $containerConfig['allow_reflection_for_concrete'] ?? null;

        if (!\is_bool($autowireConcrete) || !\is_bool($allowReflection)) {
            throw new ContainerException('container-config-foundation-container-invalid');
        }

        if (!$autowireConcrete || !$allowReflection) {
            return false;
        }

        if (!\class_exists($id)) {
            return false;
        }

        $reflection = new \ReflectionClass($id);

        return $reflection->isInstantiable();
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
        if (!$this->canAutowire($className)) {
            throw new ContainerException('container-autowire-forbidden');
        }

        $reflection = new \ReflectionClass($className);

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            try {
                return $reflection->newInstance();
            } catch (\Throwable $e) {
                throw new ContainerException('container-autowire-instantiation-failed', $e);
            }
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyId = $type->getName();

                if ($this->has($dependencyId)) {
                    $arguments[] = $this->get($dependencyId);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new ContainerException('container-autowire-unresolvable');
        }

        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (\Throwable $e) {
            throw new ContainerException('container-autowire-instantiation-failed', $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function containerConfig(): array
    {
        $foundation = $this->config['foundation'] ?? null;

        if (!\is_array($foundation)) {
            throw new ContainerException('container-config-foundation-missing');
        }

        $container = $foundation['container'] ?? null;

        if (!\is_array($container)) {
            throw new ContainerException('container-config-foundation-container-missing');
        }

        /** @var array<string, mixed> $container */
        return $container;
    }

    private static function assertServiceId(string $id): void
    {
        if ($id === '') {
            throw new ContainerException('container-service-id-empty');
        }

        if (\trim($id) !== $id || \preg_match('/\s/u', $id) === 1) {
            throw new ContainerException('container-service-id-whitespace-forbidden');
        }
    }

    private static function isValidServiceId(string $id): bool
    {
        return $id !== ''
            && \trim($id) === $id
            && \preg_match('/\s/u', $id) !== 1;
    }
}
