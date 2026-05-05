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
use Coretsia\Foundation\Tag\TagRegistry;

/**
 * Deterministic Foundation container builder.
 *
 * Provider order is caller-supplied and significant.
 *
 * This builder MUST NOT globally sort providers by FQCN. Upstream module/kernel
 * planning owns deterministic provider-list construction.
 *
 * Collision policy:
 *
 * - later container definitions override earlier definitions deterministically;
 * - this applies to container bindings/definitions only;
 * - tag dedupe remains independent and is owned by `TagRegistry`, where first
 *   occurrence per `(tag, serviceId)` wins.
 */
final class ContainerBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $definitions = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, mixed>
     */
    private array $config;

    private TagRegistry $tagRegistry;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
        ?TagRegistry $tagRegistry = null,
    ) {
        $this->config = $config;
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
    }

    /**
     * Registers providers in the exact caller-supplied order.
     *
     * @param iterable<ServiceProviderInterface> $providers
     */
    public function registerProviders(iterable $providers): self
    {
        foreach ($providers as $provider) {
            if (!$provider instanceof ServiceProviderInterface) {
                throw new ContainerException('container-provider-invalid');
            }

            $provider->register($this);
        }

        return $this;
    }

    /**
     * Registers providers in the exact caller-supplied order.
     */
    public function register(ServiceProviderInterface ...$providers): self
    {
        foreach ($providers as $provider) {
            $provider->register($this);
        }

        return $this;
    }

    /**
     * Registers or replaces a service definition.
     *
     * Later calls for the same id override earlier definitions
     * deterministically.
     */
    public function set(string $id, mixed $definition): self
    {
        self::assertServiceId($id);

        $this->definitions[$id] = $definition;
        unset($this->instances[$id]);

        return $this;
    }

    /**
     * Alias for `set()` for provider readability.
     */
    public function bind(string $id, mixed $definition): self
    {
        return $this->set($id, $definition);
    }

    /**
     * Registers or replaces a shared concrete instance.
     *
     * Later calls for the same id override earlier definitions or instances
     * deterministically.
     */
    public function instance(string $id, mixed $instance): self
    {
        self::assertServiceId($id);

        unset($this->definitions[$id]);
        $this->instances[$id] = $instance;

        return $this;
    }

    /**
     * Registers or replaces a factory definition.
     *
     * The callable is wrapped into a Closure so runtime resolution never treats
     * callable strings as factories by accident.
     *
     * @param callable(Container): mixed $factory
     */
    public function factory(string $id, callable $factory): self
    {
        return $this->set(
            $id,
            static fn (Container $container): mixed => $factory($container),
        );
    }

    /**
     * Registers a tagged service.
     *
     * Tag duplicate handling is intentionally delegated to `TagRegistry`.
     *
     * @param array<string, mixed> $meta
     */
    public function tag(string $tag, string $serviceId, int $priority = 0, array $meta = []): self
    {
        $this->tagRegistry->add($tag, $serviceId, $priority, $meta);

        return $this;
    }

    public function build(): Container
    {
        return new Container(
            definitions: $this->definitions,
            instances: $this->instances,
            config: $this->config,
        );
    }

    public function tagRegistry(): TagRegistry
    {
        return $this->tagRegistry;
    }

    /**
     * Returns known definition and instance ids without exposing definitions,
     * instances, constructor arguments, factories, or reflection data.
     *
     * @return list<string>
     */
    public function serviceIds(): array
    {
        $ids = \array_values(\array_unique([
            ...\array_keys($this->definitions),
            ...\array_keys($this->instances),
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
     * Returns a strict global config root for provider/factory wiring.
     *
     * @return array<string, mixed>
     */
    public function configRoot(string $root): array
    {
        if ($root === '' || \trim($root) !== $root || \preg_match('/\s/u', $root) === 1) {
            throw new ContainerException('container-config-root-invalid');
        }

        $value = $this->config[$root] ?? null;

        if (!\is_array($value)) {
            throw new ContainerException('container-config-root-missing');
        }

        /** @var array<string, mixed> $value */
        return $value;
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
}
