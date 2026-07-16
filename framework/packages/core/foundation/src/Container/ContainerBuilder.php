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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionApplier;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\Internal\ContainerServiceIdPolicy;
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
 *
 * Definition lifecycle policy:
 *
 * - definitions are shared by default;
 * - shared definitions are cached by service id after first resolution;
 * - non-shared definitions are resolved on every Container::get($id);
 * - lifecycle flags apply only to definitions, not to tag registrations.
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

    /**
     * @var array<string, bool>
     */
    private array $definitionShared = [];

    private TagRegistry $tagRegistry;

    private bool $declarativeDefinitionsApplied = false;

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
    public function set(
        string $id,
        mixed $definition,
        bool $shared = true,
    ): self {
        ContainerServiceIdPolicy::assertValid($id);

        $this->definitions[$id] = $definition;
        $this->definitionShared[$id] = $shared;
        unset($this->instances[$id]);

        return $this;
    }

    /**
     * Alias for `set()` for provider readability.
     */
    public function bind(
        string $id,
        mixed $definition,
        bool $shared = true,
    ): self {
        return $this->set($id, $definition, $shared);
    }

    /**
     * Registers or replaces a shared concrete instance.
     *
     * Later calls for the same id override earlier definitions or instances
     * deterministically.
     */
    public function instance(
        string $id,
        mixed $instance,
    ): self {
        ContainerServiceIdPolicy::assertValid($id);

        unset($this->definitions[$id], $this->definitionShared[$id]);
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
    public function factory(
        string $id,
        callable $factory,
        bool $shared = true,
    ): self {
        return $this->set(
            id: $id,
            definition: static fn (Container $container): mixed => $factory($container),
            shared: $shared,
        );
    }

    /**
     * Registers a tagged service.
     *
     * Tag duplicate handling is intentionally delegated to `TagRegistry`.
     *
     * @param array<string, mixed> $meta
     */
    public function tag(
        string $tag,
        string $serviceId,
        int $priority = 0,
        array $meta = [],
    ): self {
        $this->tagRegistry->add($tag, $serviceId, $priority, $meta);

        return $this;
    }

    /**
     * Applies exactly one complete canonical definition set in semantic
     * operation order.
     *
     * Multiple provider contributions MUST be aggregated through one shared
     * ContainerDefinitionBuilder or ContainerDefinitionSet::merge() before this
     * method is called.
     */
    public function applyDefinitions(
        ContainerDefinitionSet $definitions,
    ): self {
        if ($this->declarativeDefinitionsApplied) {
            throw new ContainerException('container-definition-set-already-applied');
        }

        new ContainerDefinitionApplier()->apply(
            builder: $this,
            definitions: $definitions,
        );

        $this->declarativeDefinitionsApplied = true;

        return $this;
    }

    public function build(): Container
    {
        return new Container(
            definitions: $this->definitions,
            instances: $this->instances,
            config: $this->config,
            definitionShared: $this->definitionShared,
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
        if ($root === '' || \trim($root) !== $root || \preg_match('/\s/u', $root) !== 0) {
            throw new ContainerException('container-config-root-invalid');
        }

        if (!\array_key_exists($root, $this->config)) {
            throw new ContainerException('container-config-root-missing');
        }

        $value = $this->config[$root];

        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw new ContainerException('container-config-root-invalid');
        }

        foreach ($value as $key => $_value) {
            if (!\is_string($key)) {
                throw new ContainerException('container-config-root-invalid');
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
