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

namespace Coretsia\Kernel\Container\Definition;

use Coretsia\Foundation\Discovery\DeterministicOrder;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;

/**
 * Kernel-owned deterministic compiled-container definition graph.
 *
 * This model represents the complete deterministic compiled container graph
 * used as payload-source data for `container@1` compilation.
 *
 * The graph contains only deterministic schema values:
 *
 * - service definitions;
 * - aliases;
 * - parameters;
 * - tags.
 *
 * It intentionally stores exported array data from ServiceDefinition and
 * ParameterBag, not the model objects themselves. This keeps the graph safe for
 * deterministic artifact serialization and prevents object identity from
 * becoming part of the compiled payload state.
 *
 * Ordering rules:
 *
 * - service ids are sorted by byte-order string comparison (`strcmp`);
 * - alias maps are sorted by byte-order string comparison (`strcmp`);
 * - parameter maps are sorted by byte-order string comparison (`strcmp`);
 * - tag names are sorted by byte-order string comparison (`strcmp`);
 * - tag item discovery order follows canonical Foundation order:
 *   priority DESC, id ASC;
 * - tag duplicate handling follows canonical Foundation first-wins semantics
 *   per `(tag, serviceId)`.
 *
 * Binding collision policy:
 *
 * - later service binding overrides earlier service binding for the same id;
 * - later alias binding overrides earlier alias binding for the same alias;
 * - later parameter binding overrides earlier parameter binding for the same
 *   parameter name;
 * - later duplicate tag registration for the same `(tag, serviceId)` is ignored.
 *
 * This model intentionally does not:
 *
 * - perform runtime service resolution;
 * - instantiate services;
 * - read files;
 * - write files;
 * - calculate fingerprints;
 * - emit stdout/stderr;
 * - store closures, anonymous functions, callable objects, raw PHP callable
 *   arrays, object instances, resources, reflection objects, source snippets,
 *   absolute paths, raw env values, or raw secrets.
 *
 * @internal
 */
final readonly class DefinitionGraph
{
    private const int MAX_SERVICES = 4096;
    private const int MAX_ALIASES = 4096;
    private const int MAX_TAGS = 1024;
    private const int MAX_TAGGED_SERVICES_PER_TAG = 4096;
    private const int MAX_ID_BYTES = 256;

    private const string SERVICE_ID_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.:\\\\-]{0,255}\z/';
    private const string ALIAS_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.:\\\\-]{0,255}\z/';
    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, mixed> $parameters
     * @param array<string, array<string, array{id: string, priority: int}>> $tags
     */
    private function __construct(
        private array $services,
        private array $aliases,
        private array $parameters,
        private array $tags,
    ) {
    }

    public static function empty(): self
    {
        return new self(
            services: [],
            aliases: [],
            parameters: [],
            tags: [],
        );
    }

    /**
     * Creates a graph from deterministic graph parts.
     *
     * Repeated service definitions are applied in caller-supplied order, where
     * later service definitions override earlier ones.
     *
     * Repeated aliases are applied in array iteration order, where later alias
     * values override earlier ones for the same alias key.
     *
     * Tags imported from TagRegistry preserve the registry's first-wins
     * dedupe semantics and canonical discovery order.
     *
     * @param iterable<ServiceDefinition> $services
     * @param array<string, string> $aliases
     *
     * @throws ContainerCompileFailedException
     */
    public static function fromParts(
        iterable $services = [],
        array $aliases = [],
        ?ParameterBag $parameters = null,
        ?TagRegistry $tagRegistry = null,
    ): self {
        $graph = self::empty();

        foreach ($services as $service) {
            if (!$service instanceof ServiceDefinition) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }

            $graph = $graph->withService($service);
        }

        $graph = $graph->withAliases($aliases);

        if ($parameters !== null) {
            $graph = $graph->withParameters($parameters);
        }

        if ($tagRegistry !== null) {
            $graph = $graph->withTagRegistry($tagRegistry);
        }

        return $graph;
    }

    /**
     * Returns a new graph with a service definition added or replaced.
     *
     * Later binding overrides earlier binding for the same service id.
     *
     * @throws ContainerCompileFailedException
     */
    public function withService(ServiceDefinition $definition): self
    {
        $service = self::normalizeServiceDefinition($definition);
        $id = $service['id'];

        if (!\is_string($id)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        $services = $this->services;
        $services[$id] = $service;

        \uksort(
            $services,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        if (\count($services) > self::MAX_SERVICES) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        return new self(
            services: $services,
            aliases: $this->aliases,
            parameters: $this->parameters,
            tags: $this->tags,
        );
    }

    /**
     * @param iterable<ServiceDefinition> $definitions
     *
     * @throws ContainerCompileFailedException
     */
    public function withServices(iterable $definitions): self
    {
        $graph = $this;

        foreach ($definitions as $definition) {
            if (!$definition instanceof ServiceDefinition) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }

            $graph = $graph->withService($definition);
        }

        return $graph;
    }

    /**
     * Returns a new graph with an alias added or replaced.
     *
     * Later alias binding overrides earlier alias binding for the same alias.
     *
     * @throws ContainerCompileFailedException
     */
    public function withAlias(string $alias, string $serviceId): self
    {
        self::assertAlias($alias);
        self::assertServiceId($serviceId);

        if ($alias === $serviceId) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        $aliases = $this->aliases;
        $aliases[$alias] = $serviceId;

        \uksort(
            $aliases,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        if (\count($aliases) > self::MAX_ALIASES) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        return new self(
            services: $this->services,
            aliases: $aliases,
            parameters: $this->parameters,
            tags: $this->tags,
        );
    }

    /**
     * @param array<string, string> $aliases
     *
     * @throws ContainerCompileFailedException
     */
    public function withAliases(array $aliases): self
    {
        $graph = $this;

        foreach ($aliases as $alias => $serviceId) {
            if (!\is_string($alias) || !\is_string($serviceId)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }

            $graph = $graph->withAlias($alias, $serviceId);
        }

        return $graph;
    }

    public function withParameters(ParameterBag $parameters): self
    {
        return new self(
            services: $this->services,
            aliases: $this->aliases,
            parameters: $parameters->toArray(),
            tags: $this->tags,
        );
    }

    /**
     * Returns a new graph with one parameter added or replaced.
     *
     * Later parameter binding overrides earlier parameter binding for the same
     * parameter name.
     *
     * @throws ContainerCompileFailedException
     */
    public function withParameter(string $name, mixed $value): self
    {
        $parameters = ParameterBag::fromArray($this->parameters)
            ->with($name, $value)
            ->toArray();

        return new self(
            services: $this->services,
            aliases: $this->aliases,
            parameters: $parameters,
            tags: $this->tags,
        );
    }

    /**
     * Returns a new graph with a tag registration added.
     *
     * Duplicate `(tag, serviceId)` registrations are ignored. This preserves
     * canonical Foundation first-wins tag dedupe semantics.
     *
     * @throws ContainerCompileFailedException
     */
    public function withTag(string $tag, string $serviceId, int $priority = 0): self
    {
        self::assertTag($tag);
        self::assertServiceId($serviceId);

        $tags = $this->tags;

        if (isset($tags[$tag][$serviceId])) {
            return $this;
        }

        $tags[$tag][$serviceId] = [
            'id' => $serviceId,
            'priority' => $priority,
        ];

        \uksort(
            $tags,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        if (\count($tags) > self::MAX_TAGS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_TAG_INVALID,
            );
        }

        if (\count($tags[$tag]) > self::MAX_TAGGED_SERVICES_PER_TAG) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_TAG_INVALID,
            );
        }

        return new self(
            services: $this->services,
            aliases: $this->aliases,
            parameters: $this->parameters,
            tags: $tags,
        );
    }

    /**
     * Imports tags from the canonical Foundation TagRegistry without storing the
     * registry object or TaggedService objects.
     *
     * Tag metadata is intentionally not emitted by this graph. Discovery payload
     * requires stable service id + priority only; owner-defined meta is not
     * required for container resolution and may contain non-payload values.
     *
     * @throws ContainerCompileFailedException
     */
    public function withTagRegistry(TagRegistry $tagRegistry): self
    {
        $graph = $this;

        foreach ($tagRegistry->tagNames() as $tag) {
            foreach ($tagRegistry->all($tag) as $service) {
                if (!$service instanceof TaggedService) {
                    throw ContainerCompileFailedException::withReason(
                        ContainerCompileFailedException::REASON_TAG_INVALID,
                    );
                }

                $graph = $graph->withTag(
                    tag: $tag,
                    serviceId: $service->id(),
                    priority: $service->priority(),
                );
            }
        }

        return $graph;
    }

    public function hasService(string $id): bool
    {
        if (!self::isValidServiceId($id)) {
            return false;
        }

        return \array_key_exists($id, $this->services);
    }

    /**
     * @return list<string>
     */
    public function serviceIds(): array
    {
        return \array_keys($this->services);
    }

    /**
     * Exports deterministic json-like graph data for `container@1` compiled payload
     * emission.
     *
     * @return array{
     *     aliases: array<string, string>,
     *     parameters: array<string, mixed>,
     *     services: array<string, array<string, mixed>>,
     *     tags: array<string, list<array{id: string, priority: int}>>
     * }
     */
    public function toArray(): array
    {
        return [
            'aliases' => $this->aliases,
            'parameters' => $this->parameters,
            'services' => $this->services,
            'tags' => $this->tagsForExport(),
        ];
    }

    /**
     * @return array<string, list<array{id: string, priority: int}>>
     */
    private function tagsForExport(): array
    {
        $out = [];

        foreach ($this->tags as $tag => $itemsByServiceId) {
            $items = \array_values($itemsByServiceId);

            $sorted = DeterministicOrder::sort(
                $items,
                static fn (array $item): string => (string)$item['id'],
                static fn (array $item): int => (int)$item['priority'],
            );

            $out[$tag] = [];

            foreach ($sorted as $item) {
                $out[$tag][] = [
                    'id' => (string)$item['id'],
                    'priority' => (int)$item['priority'],
                ];
            }
        }

        \uksort(
            $out,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $out;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeServiceDefinition(ServiceDefinition $definition): array
    {
        $service = $definition->toArray();

        foreach (['arguments', 'construction', 'id', 'shared', 'type'] as $key) {
            if (!\array_key_exists($key, $service)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }
        }

        self::assertNoUnknownKeys(
            $service,
            ['arguments', 'construction', 'id', 'shared', 'type'],
        );

        if (!\is_string($service['id']) || !self::isValidServiceId($service['id'])) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if (!\is_string($service['type']) || $service['type'] === '') {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if (!\is_bool($service['shared'])) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if (!\is_array($service['arguments']) || !\array_is_list($service['arguments'])) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if (!\is_array($service['construction']) || \array_is_list($service['construction'])) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        return $service;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $allowedKeys
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertNoUnknownKeys(array $map, array $allowedKeys): void
    {
        $allowed = [];

        foreach ($allowedKeys as $key) {
            $allowed[$key] = true;
        }

        foreach (\array_keys($map) as $key) {
            if (!\is_string($key) || !isset($allowed[$key])) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertAlias(string $alias): void
    {
        if (
            $alias === ''
            || \strlen($alias) > self::MAX_ID_BYTES
            || \trim($alias) !== $alias
            || \preg_match(self::ALIAS_PATTERN, $alias) !== 1
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $alias) === 1
            || \str_contains($alias, '://')
            || \str_contains($alias, '::')
            || self::looksLikeAbsolutePath($alias)
            || self::looksLikeSourceSnippet($alias)
            || self::looksLikeEnvValue($alias)
            || self::looksSensitive($alias)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertServiceId(string $id): void
    {
        if (!self::isValidServiceId($id)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }
    }

    private static function isValidServiceId(string $id): bool
    {
        return $id !== ''
            && \strlen($id) <= self::MAX_ID_BYTES
            && \trim($id) === $id
            && \preg_match(self::SERVICE_ID_PATTERN, $id) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $id) !== 1
            && !\str_contains($id, '://')
            && !\str_contains($id, '::')
            && !self::looksLikeAbsolutePath($id)
            && !self::looksLikeSourceSnippet($id)
            && !self::looksLikeEnvValue($id)
            && !self::looksSensitive($id);
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertTag(string $tag): void
    {
        if (
            $tag === ''
            || \strlen($tag) > self::MAX_ID_BYTES
            || \preg_match(self::TAG_PATTERN, $tag) !== 1
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $tag) === 1
            || self::looksLikeSourceSnippet($tag)
            || self::looksLikeEnvValue($tag)
            || self::looksSensitive($tag)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_TAG_INVALID,
            );
        }
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }

    private static function looksLikeSourceSnippet(string $value): bool
    {
        return \preg_match(self::SOURCE_SNIPPET_PATTERN, $value) === 1;
    }

    private static function looksLikeEnvValue(string $value): bool
    {
        return \preg_match(self::ENV_LIKE_PATTERN, $value) === 1;
    }

    private static function looksSensitive(string $value): bool
    {
        return \preg_match(self::SENSITIVE_VALUE_PATTERN, $value) === 1;
    }
}
