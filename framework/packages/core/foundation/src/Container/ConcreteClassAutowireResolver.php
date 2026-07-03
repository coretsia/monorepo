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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Internal concrete-class autowire resolver for the Foundation container.
 *
 * The resolver owns strict concrete-class autowire policy evaluation and
 * reflection-based instantiation. Container remains responsible for PSR-11
 * orchestration, service-id validation, definition lifecycle, caching, and
 * circular-reference tracking.
 *
 * @internal Foundation container implementation detail.
 */
final readonly class ConcreteClassAutowireResolver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {
    }

    /**
     * Evaluates whether an existing service id may be resolved through strict
     * concrete-class autowiring.
     *
     * Missing or invalid `foundation.container` config is a deterministic
     * hard-fail. This intentionally prevents silently guessing container
     * defaults from inside runtime code.
     */
    public function canAutowire(string $className): bool
    {
        $containerConfig = $this->containerConfig();

        $autowireConcrete = $containerConfig['autowire_concrete'] ?? null;
        $allowReflection = $containerConfig['allow_reflection_for_concrete'] ?? null;

        if (!\is_bool($autowireConcrete) || !\is_bool($allowReflection)) {
            throw new ContainerException('container-config-foundation-container-invalid');
        }

        if (!$autowireConcrete || !$allowReflection) {
            return false;
        }

        if (!\class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);

        return $reflection->isInstantiable();
    }

    /**
     * Instantiates an unregistered concrete class only when strict autowire policy
     * allows fallback resolution.
     *
     * Returns null when concrete-class autowire is disabled by valid
     * configuration. Missing or invalid `foundation.container` config remains a
     * deterministic hard-fail through canAutowire().
     *
     * @param class-string $className
     * @return object|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function instantiateIfAllowed(string $className, ContainerInterface $container): ?object
    {
        if (!$this->canAutowire($className)) {
            return null;
        }

        return $this->instantiateAllowed($className, $container);
    }

    /**
     * Instantiates a concrete class after strict autowire policy validation.
     *
     * Explicit class-string definitions use this hard-fail path: when strict
     * concrete-class autowire policy does not allow resolution, the definition is
     * invalid and resolution fails deterministically.
     *
     * @param class-string $className
     * @return object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function instantiate(string $className, ContainerInterface $container): object
    {
        if (!$this->canAutowire($className)) {
            throw new ContainerException('container-autowire-forbidden');
        }

        return $this->instantiateAllowed($className, $container);
    }

    /**
     * Instantiates a class after strict policy has allowed concrete autowiring.
     *
     * @param class-string $className
     * @return object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    private function instantiateAllowed(string $className, ContainerInterface $container): object
    {
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

                if ($container->has($dependencyId)) {
                    $arguments[] = $container->get($dependencyId);
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
}
